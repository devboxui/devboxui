<?php

namespace Drupal\devboxui\Controller;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ForwardAuthController extends ControllerBase {

  protected AccountProxyInterface $account;

  public function __construct(AccountProxyInterface $account) {
    $this->account = $account;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user')
    );
  }

  public function check() {
    $user = $this->account;
    if (!$user || $user->isAnonymous()) {
      return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

    $email = method_exists($user, 'getEmail') ? $user->getEmail() : '';

    $response = new JsonResponse([
      'message' => 'OK',
      'user' => [
        'id' => $user->id(),
        'name' => $user->getDisplayName(),
        'email' => $email,
      ],
    ]);

    // Headers for Caddy forward_auth
    $response->headers->set('Remote-User', $user->getAccountName());
    $response->headers->set('Remote-Email', $email);
    $response->headers->set('Remote-Name', $user->getDisplayName());

    return $response;
  }

}
