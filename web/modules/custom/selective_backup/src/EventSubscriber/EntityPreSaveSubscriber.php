<?php

namespace Drupal\selective_backup\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\selective_backup\Service\CloudflareBackupService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity events to trigger backups.
 */
class EntityPreSaveSubscriber implements EventSubscriberInterface {

  /**
   * The Cloudflare backup service.
   *
   * @var \Drupal\selective_backup\Service\CloudflareBackupService
   */
  protected $backupService;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an EntityPreSaveSubscriber object.
   *
   * @param \Drupal\selective_backup\Service\CloudflareBackupService $backup_service
   * The Cloudflare backup service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The config factory.
   */
  public function __construct(CloudflareBackupService $backup_service, ConfigFactoryInterface $config_factory) {
    $this->backupService = $backup_service;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // The event to listen for is 'entity.presave', and the method to call is 'onEntityPreSave'.
    $events['entity.presave'] = ['onEntityPreSave'];
    return $events;
  }

  /**
   * This method is called when an entity is being saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * The entity being saved.
   */
  public function onEntityPreSave(EntityInterface $entity) {
    // We only care about content entities.
    if ($entity instanceof ContentEntityInterface) {
      $config = $this->configFactory->get('selective_backup.settings');
      $enabled_entity_types = $config->get('backup_entities');

      // Check if the entity type is enabled for backup.
      if (in_array($entity->getEntityTypeId(), $enabled_entity_types, TRUE)) {
        $this->backupService->backupEntity($entity);
      }
    }
  }

}
