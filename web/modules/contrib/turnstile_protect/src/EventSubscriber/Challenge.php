<?php

namespace Drupal\turnstile_protect\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirect protected routes to challenge page.
 */
class Challenge implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs the event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The watchdog service.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, FloodInterface $flood, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user) {
    $this->logger = $logger_factory->get('turnstile_protect');
    $this->flood = $flood;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST] = ['protect'];

    return $events;
  }

  /**
   * Helper function to see if the given response needs handled by this logic.
   */
  protected function applies(RequestEvent $event, ImmutableConfig $config): bool {
    $request = $event->getRequest();
    $session = $request->getSession();
    if ($session->get('turnstile_protect_pass')) {
      return FALSE;
    }

    $route_name = $request->attributes->get('_route');
    if (!in_array($route_name, $config->get('routes'))) {
      return FALSE;
    }

    // Do not challenge logged in users.
    if ($this->currentUser->isAuthenticated()) {
      return FALSE;
    }

    // Do not challenge IPs whitelisted by captcha module.
    $clientIp = $request->getClientIp();
    if (captcha_whitelist_ip_whitelisted($clientIp)) {
      return FALSE;
    }
    // See if the client IP resolves to a good bot.
    $hostname = gethostbyaddr($clientIp);
    // Being sure to lookup the domain to avoid spoofing.
    $resolved_ip = gethostbyname($hostname);
    if ($clientIp !== $resolved_ip) {
      if ($clientIp !== '127.0.0.1') {
        return TRUE;
      }
    }
    $parts = explode(".", $hostname);
    if (count($parts) < 2) {
      if ($clientIp !== '127.0.0.1') {
        return TRUE;
      }
    }
    $tld = array_pop($parts);
    $hostname = array_pop($parts) . '.' . $tld;
    if (in_array($hostname, $config->get('bots'))) {
      // Do not allow good bots to crawl URLs with parameters
      // if the config is set accordingly.
      if ($config->get('protect_parameters') && count($request->query->all()) > 0) {
        $response = new Response('Forbidden', 403);
        $event->setResponse($response);
      }

      return FALSE;
    }

    // don't check the rate limit if it's not set.
    if (!$config->get("rate_limit")) {
      return TRUE;
    }

    // Check if we're rate limited.
    $threshold = $config->get("threshold");
    $window = $config->get("window");

    // Base the rate limit identifier on /16 for ipv4
    // and /64 for ipv6.
    $delimiter = strpos($clientIp, ":") ? ":" : ".";
    $components = explode($delimiter, $clientIp);
    // ipv6.
    if ($delimiter == ':') {
      $components = self::expandIpv6($clientIp);
      $components = array_slice($components, 0, 4);
    }
    else {
      $components = array_slice($components, 0, 2);
    }
    $identifier = implode($delimiter, $components);
    $event_name = 'turnstile_protect_rate_limit';
    $allowed = $this->flood->isAllowed(
      $event_name,
      $threshold,
      $window,
      $identifier
    );
    $this->flood->register($event_name, $window, $identifier);

    // If we haven't been flooded by this ip range
    // do not present a challenge.
    return !$allowed;
  }

  /**
   * Redirect to challenge page for protected routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function protect(RequestEvent $event) {
    $config = $this->configFactory->get('turnstile_protect.settings');
    if (!$this->applies($event, $config)) {
      return;
    }

    $request = $event->getRequest();

    // Only allow "max_challenges" attempts at passing a challenge.
    $session = $request->getSession();
    $submission_count = $session->get('turnstile_protect_submission_count', 0);
    $submission_count++;
    $session->set('turnstile_protect_submission_count', $submission_count);
    $max = $config->get('max_challenges') ?? 5;
    if ($submission_count > $max) {
      $response = new Response('Too many requests', 429);
      $event->setResponse($response);
      // Log every ten failures.
      if (($submission_count % 10) == 0) {
        $this->logger->notice('@failures attempts by @ip', [
          '@failures' => $submission_count,
          '@ip' => $request->getClientIp(),
        ]);
      }
      return;
    }

    $challenge_url = Url::fromRoute('turnstile_protect.challenge', [], [
      'query' => [
        'destination' => $request->getRequestUri(),
      ],
    ])->toString();
    $response = new RedirectResponse($challenge_url);
    $event->setResponse($response);
  }

  /**
   * Helper function to normalize ipv6 addresses.
   *
   * @param string $ip
   *   The ipv6 address to expand.
   */
  public static function expandIpv6($ip) {
    $hextets = explode(':', $ip);
    $expanded = [];

    // Find the index of an empty hextet (indicating :: compression)
    $emptyIndex = array_search('', $hextets, TRUE);
    if ($emptyIndex !== FALSE) {
      // Calculate how many hextets are missing.
      $missingCount = 8 - count($hextets) + 1;
      // Fill in zeros for the missing hextets.
      array_splice($hextets, $emptyIndex, 1, array_fill(0, $missingCount, '0'));
    }

    // Pad each hextet to 4 digits.
    foreach ($hextets as $hextet) {
      $expanded[] = str_pad($hextet, 4, '0', STR_PAD_LEFT);
    }

    return $expanded;
  }

}
