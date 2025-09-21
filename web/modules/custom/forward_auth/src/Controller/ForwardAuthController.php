<?php

namespace Drupal\forward_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the forward-auth validation endpoint.
 */
class ForwardAuthController extends ControllerBase {
  /** @var \Drupal\Core\Config\ConfigFactoryInterface */
  protected $configFactory;

  /** @var \Drupal\Core\Session\AccountProxyInterface */
  protected $currentUser;

  public function __construct(ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Validate request from reverse proxy.
   *
   * Behaviour is configured in admin settings:
   * - mode: 'token' or 'cookie'
   * - token header name + shared secret
   * - allowed roles (optional)
   * - login path to redirect when not authenticated (optional)
   */
  public function validate(Request $request) {
    $config = $this->configFactory->get('forward_auth.settings');
    $mode = $config->get('mode') ?: 'cookie';

    // If token mode: check header equality using hash_equals.
    if ($mode === 'token') {
      $header_name = $config->get('token_header') ?: 'X-Forward-Auth-Token';
      $provided = $request->headers->get($header_name);
      $secret = $config->get('token_secret');

      if (empty($secret) || !is_string($secret)) {
        // Misconfigured - deny.
        return $this->deny($config, 'Token secret not configured.');
      }

      if (empty($provided) || !hash_equals($secret, $provided)) {
        return $this->deny($config);
      }

      // Token matched. Build a synthetic "user" header if desired.
      return $this->allow(['uid' => 0, 'name' => 'token-user'], $config);
    }

    // Cookie/session mode: rely on Drupal session to set current_user.
    if ($this->currentUser->isAuthenticated()) {
      // Optionally check roles.
      $allowed_roles = $config->get('allowed_roles') ?: [];
      if (!empty($allowed_roles)) {
        $account = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser->id());
        if ($account) {
          $roles = $account->getRoles();
          $intersect = array_intersect($roles, $allowed_roles);
          if (empty($intersect)) {
            return $this->deny($config);
          }
        }
      }

      $account = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser->id());
      $user = [
        'uid' => $this->currentUser->id(),
        'name' => $this->currentUser->getAccountName(),
      ];

      // Optionally provide email if available.
      if ($account && $account->getEmail()) {
        $user['email'] = $account->getEmail();
      }

      return $this->allow($user, $config);
    }

    // Not authenticated.
    return $this->deny($config);
  }

  protected function allow(array $user, $config) {
    $response = new Response('', 200);
    // Add convenient headers that proxies can forward to upstream services.
    if (!empty($user['uid'])) {
      $response->headers->set('X-Forward-Auth-Uid', (string) $user['uid']);
    }
    if (!empty($user['name'])) {
      $response->headers->set('X-Forward-Auth-User', $user['name']);
    }
    if (!empty($user['email'])) {
      $response->headers->set('X-Forward-Auth-Email', $user['email']);
    }

    // Optionally preserve a permissive header for proxies wanting to check.
    $response->headers->set('X-Forward-Auth-Status', 'OK');

    return $response;
  }

  protected function deny($config, $message = NULL) {
    // If a login path is configured, return 401 with Location header to encourage the proxy to redirect.
    $login = $config->get('login_path');
    $headers = [];
    if (!empty($login)) {
      $headers['Location'] = $login;
    }
    $response = new Response($message ?: 'Unauthorized', 401, $headers);
    $response->headers->set('X-Forward-Auth-Status', 'DENIED');
    return $response;
  }
}