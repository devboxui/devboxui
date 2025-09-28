<?php

namespace Drupal\devboxui\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

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
      return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

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
