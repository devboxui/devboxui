<?php

namespace Drupal\devboxui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles forward-auth requests from Caddy.
 */
class ForwardAuthController extends ControllerBase {

  /**
   * Endpoint for Caddy forward_auth.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   200 with headers if logged in, 401 if not.
   */
  public function authCheck(): Response {
    $user = $this->currentUser();

    if ($user->isAuthenticated()) {
      $response = new Response('', 200);
      $response->headers->set('Remote-User', $user->getAccountName());
      $response->headers->set('Remote-Email', $user->getEmail());
      $response->headers->set('Remote-Name', $user->getDisplayName());
      return $response;
    }

    return new Response('', 401);
  }

}
