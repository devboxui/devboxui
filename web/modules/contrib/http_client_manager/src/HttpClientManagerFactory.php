<?php

namespace Drupal\http_client_manager;

use Drupal\http_client_manager\RequestLocation\RequestLocationPluginManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class HttpClientManagerFactory.
 *
 * @package Drupal\http_client_manager
 */
class HttpClientManagerFactory implements HttpClientManagerFactoryInterface {

  /**
   * An array of HTTP Clients.
   *
   * @var array
   */
  protected $clients = [];

  /**
   * The http_client_manager API handler.
   *
   * @var \Drupal\http_client_manager\HttpServiceApiHandlerInterface
   */
  protected HttpServiceApiHandlerInterface $apiHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $dispatcher;

  /**
   * The http_client_manager request location manager.
   *
   * @var \Drupal\http_client_manager\RequestLocation\RequestLocationPluginManager
   */
  protected RequestLocationPluginManager $requestLocation;

  /**
   * Initialization method.
   *
   * @param \Drupal\http_client_manager\HttpServiceApiHandlerInterface $apiHandler
   *   The http_client_manager.http_services_api service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event_dispatcher service.
   * @param \Drupal\http_client_manager\RequestLocation\RequestLocationPluginManager $requestLocation
   *   The plugin.manager.http_client_manager.request_location service.
   */
  public function __construct(
    HttpServiceApiHandlerInterface $apiHandler,
    EventDispatcherInterface $dispatcher,
    RequestLocationPluginManager $requestLocation,
  ) {
    $this->apiHandler = $apiHandler;
    $this->dispatcher = $dispatcher;
    $this->requestLocation = $requestLocation;
  }

  /**
   * {@inheritdoc}
   */
  public function get($service_api) {
    if (!isset($this->clients[$service_api])) {
      $this->clients[$service_api] = new HttpClient(
        $service_api,
        $this->apiHandler,
        $this->dispatcher,
        $this->requestLocation,
      );
    }
    return $this->clients[$service_api];
  }

}
