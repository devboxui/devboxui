<?php

namespace Drupal\devboxui\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Rewrites /__forward__/ redirects to external URLs.
 */
class ForwardAuthRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'kernel.response' => ['onResponse', -100], // Run late
    ];
  }

  /**
   * Rewrite RedirectResponse if it targets /__forward__/.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    if ($response instanceof RedirectResponse) {
      $target = $response->getTargetUrl();
      if (str_starts_with($target, '/__forward__/')) {
        $trimmed = substr($target, strlen('/__forward__/'));
        $event->setResponse(new RedirectResponse('https://' . $trimmed));
      }
    }
  }
}
