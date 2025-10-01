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
        $destination = $request->query->get('destination');
        if (empty($destination)) {
          $destination = $request->headers->get('Destination');
        }
        if ($destination) {
          if (str_starts_with($destination, 'http')) {
            $event->setResponse(new TrustedRedirectResponse($destination, 302, [], TRUE));
          }
          else {
            $destination = 'https://'.$destination;
            # Why does this not redirect?
            $event->setResponse(new TrustedRedirectResponse($destination, 302, [], TRUE));
          }
        }
      }
    }
  }
}
