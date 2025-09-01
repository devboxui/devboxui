<?php

namespace Drupal\selective_backup;

use Drupal\Core\Messenger\MessengerInterface;

/**
 * Helper class for selective backup batch process.
 */
class BackupBatch {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new BackupBatch object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Batch callback function to retrieve all entities of the selected type.
   */
  public static function backupEntities(array &$context) {
    $container = \Drupal::getContainer();
    $entity_type_manager = $container->get('entity_type.manager');
    $state = $container->get('state');

    // Get the list of entity types to back up from state.
    $entity_types_to_backup = $state->get('selective_backup.backup_entities');

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_entity_type'] = '';
      $context['sandbox']['entity_types'] = array_values($entity_types_to_backup);
      $context['sandbox']['total_entities'] = 0;

      // Calculate the total number of entities to process.
      foreach ($context['sandbox']['entity_types'] as $entity_type_id) {
        $storage = $entity_type_manager->getStorage($entity_type_id);
        $query = $storage->getQuery()->accessCheck(FALSE);
        $context['sandbox']['total_entities'] += $query->count()->execute();
      }

      $context['results']['entities_processed'] = 0;
    }

    $backup_service = $container->get('selective_backup.service');

    if (!empty($context['sandbox']['entity_types'])) {
      $entity_type_id = reset($context['sandbox']['entity_types']);
      $context['sandbox']['current_entity_type'] = $entity_type_id;
      
      $storage = $entity_type_manager->getStorage($entity_type_id);
      $query = $storage->getQuery()
        ->range($context['sandbox']['progress'], 10)
        ->accessCheck(FALSE);
      
      $ids = $query->execute();
      
      if ($ids) {
        $entities = $storage->loadMultiple($ids);
        foreach ($entities as $entity) {
          $backup_service->backupEntity($entity);
          $context['results']['entities_processed']++;
          $context['sandbox']['progress']++;
        }
      } else {
        // No more entities of this type, move to the next.
        array_shift($context['sandbox']['entity_types']);
        $context['sandbox']['progress'] = 0;
      }
    }
    
    // Set a human-readable message.
    $message = t('Backing up entities of type @type. Processed @processed of @total.', [
      '@type' => $context['sandbox']['current_entity_type'],
      '@processed' => $context['results']['entities_processed'],
      '@total' => $context['sandbox']['total_entities'],
    ]);
    
    // Update the progress bar.
    if ($context['sandbox']['total_entities'] > 0) {
      $context['finished'] = $context['results']['entities_processed'] / $context['sandbox']['total_entities'];
    } else {
      $context['finished'] = 1;
    }
  }

  /**
   * Callback function for the batch process completion.
   */
  public static function backupFinished($success, $results, $operations) {
    if ($success) {
      $messenger = \Drupal::messenger();
      $messenger->addStatus(t('Finished backing up entities. Processed @count entities.', [
        '@count' => $results['entities_processed'],
      ]));
    } else {
      $error_operation = reset($operations);
      $messenger = \Drupal::messenger();
      $messenger->addError(t('An error occurred while processing @operation with arguments: @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]));
    }
  }

}
