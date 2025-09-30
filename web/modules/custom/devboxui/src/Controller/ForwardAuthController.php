<?php

namespace Drupal\devboxui\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ForwardAuthController extends ControllerBase {
  protected $currentUser;

  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  public function check() {
    if ($this->currentUser->isAnonymous()) {
      // Pull original URL from headers Caddy provides.
      $request = \Drupal::request();
      $origHost = $request->headers->get('X-Original-Host');
      $origUri  = $request->headers->get('X-Original-Uri');

      if ($origHost && $origUri) {
        $target = 'https://' . $origHost . $origUri;
        return new RedirectResponse(
          '/user/login?destination=' . urlencode($target)
        );
      }

      return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

    // Authenticated: return headers for Caddy to forward.
    return new JsonResponse([
      'message' => 'OK',
      'user' => [
        'id' => $this->currentUser->id(),
        'name' => $this->currentUser->getDisplayName(),
        'email' => $this->currentUser->getEmail(),
      ],
    ]);
  }
}
