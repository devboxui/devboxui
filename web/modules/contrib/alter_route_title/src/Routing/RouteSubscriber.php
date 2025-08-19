<?php

namespace Drupal\alter_route_title\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $config = $this->configFactory->get('alter_route_title.configuration');
    $routetable = $config->get('routetable');
    if (!empty($routetable)) {
      foreach ($routetable as $item) {
        if ($route = $collection->get($item['route_alter_title']['route_hidden'])) {
          if ($item['route_alter_title']['alter_title'] != '') {
            $route->setDefault('_title', $item['route_alter_title']['alter_title']);
          }
        }
      }
    }
  }

}
