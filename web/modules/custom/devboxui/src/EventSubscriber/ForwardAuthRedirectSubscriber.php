<?php

namespace Drupal\devboxui\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;

class ForwardAuthRedirectSubscriber implements EventSubscriberInterface {

  protected $currentUser;

  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Only act on /user/login
    if ($request->getPathInfo() === '/user/login') {
      if ($this->currentUser->isAuthenticated()) {
        $destination = $request->query->get('destination') ?: $request->headers->get('Destination');

        if (!empty($destination)) {
          // Normalize: if no scheme, prepend https://
          if (!preg_match('#^https?://#i', $destination)) {
            $destination = 'https://' . ltrim($destination, '/');
          }

          // Issue the redirect via Drupal's response
          $event->setResponse(new TrustedRedirectResponse($destination, 302, [], TRUE));
        }
      }
    }
  }
}
