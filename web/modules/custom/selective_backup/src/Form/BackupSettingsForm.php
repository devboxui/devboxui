<?php

namespace Drupal\selective_backup\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Selective Backup settings.
 */
class BackupSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BackupSettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['selective_backup.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'selective_backup_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('selective_backup.settings');
    $entity_types = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach ($entity_types as $id => $entity_type) {
      // Only show entity types that are backed by a database table.
      if ($entity_type->getStorageClass()) {
        $options[$id] = $entity_type->getLabel();
      }
    }

    ksort($options, SORT_NATURAL);
    $form['backup_entities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity Types to Backup'),
      '#description' => $this->t('Select the entity types you want to back up on save.'),
      '#options' => $options,
      '#default_value' => $config->get('backup_entities') ?? [],
    ];

    $form['actions']['backup_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Backup all entities now'),
      '#submit' => ['::startBatchBackup'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('selective_backup.settings');
    $config->set('backup_entities', array_filter($form_state->getValue('backup_entities')));
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Starts a batch backup operation.
   */
  public function startBatchBackup(array &$form, FormStateInterface $form_state) {
    $selected_entities = array_filter($form_state->getValue('backup_entities'));
    $operations = [];

    foreach ($selected_entities as $entity_type_id) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_ids = $entity_storage->getQuery()->execute();
      foreach ($entity_ids as $id) {
        // Use the full namespace of the new class.
        $operations[] = ['\Drupal\selective_backup\BackupBatch::backupEntity', [$entity_type_id, $id]];
      }
    }

    $batch = [
      'title' => $this->t('Backing up entities...'),
      'operations' => $operations,
      // Use the full namespace of the new class for the finished callback.
      'finished' => '\Drupal\selective_backup\BackupBatch::backupFinished',
      'init_message' => $this->t('Initializing batch backup.'),
      'progress_message' => $this->t('Processed @current out of @total entities.'),
      'error_message' => $this->t('An error occurred during backup.'),
    ];

    batch_set($batch);
  }

}