<?php

namespace Drupal\simple_global_filter;

use Drupal\Core\Entity\EntityTypeManager;

/**
 * Description of GlobalFilter.
 *
 * @author alberto@exove.fi
 */
class GlobalFilter {

  /**
   * The cache backend.
   *
   * @var \Drupal\simple_global_filter\Cache
   */
  protected $cache;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entity_type_manager;

  /**
   * Constructs a new GlobalFilter.
   *
   * @param \Drupal\simple_global_filter\Cache $cache
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   */
  public function __construct(Cache $cache, EntityTypeManager $entity_type_manager) {
    $this->cache = $cache;
    $this->entity_type_manager = $entity_type_manager;
  }

  /**
   * Sets the global filter value.
   *
   * @param string $global_filter_id
   *   The global filter identifier.
   * @param mixed $value
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function set($global_filter_id, $value) {
    $data = $this->cache
      ->setStoringMode($this->entity_type_manager->getStorage('global_filter')->load($global_filter_id)->getStoringMode())
      ->get();
    if (!isset($data[$global_filter_id]) || $data[$global_filter_id] != $value) {
      $data[$global_filter_id] = $value;
      $this->cache->set($data);
      $cache = &drupal_static('global_filter_get');
      $cache[$global_filter_id] = $value;
    }
  }

  /**
   * Gets the value of the global filter.
   *
   * @param string $global_filter_id
   *   The global filter identifier.
   *
   * @return mixed
   *   The value of the global filter.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get($global_filter_id) {
    $cache = &drupal_static('global_filter_get');
    if (isset($cache[$global_filter_id])) {
      return $cache[$global_filter_id];
    }

    $value = NULL;
    $data = $this->cache
      ->setStoringMode($this->entity_type_manager->getStorage('global_filter')->load($global_filter_id)->getStoringMode())
      ->get();
    if (empty($data[$global_filter_id])) {
      // If the user did not select any value, return the default value.
      $value = $this->entity_type_manager
        ->getStorage('global_filter')
        ->load($global_filter_id)
        ->getDefaultValue();
    }
    else {
      $value = $data[$global_filter_id];
    }

    $cache[$global_filter_id] = $value;
    return $value;
  }

}
