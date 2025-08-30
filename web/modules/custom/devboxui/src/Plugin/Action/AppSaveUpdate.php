<?php

namespace Drupal\devboxui\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\devboxui\Service\DevBoxBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom action.
 *
 * @Action(
 *   id = "devboxui_save_update_app",
 *   label = @Translation("App Save/Update"),
 *   type = "node",
 *   category = @Translation("DevBoxUI"),
 *   context = {
 *     "entity" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
final class AppSaveUpdate extends ActionBase implements ContainerFactoryPluginInterface {

  protected DevBoxBatchService $batchService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, DevBoxBatchService $batchService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->batchService = $batchService;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('devboxui.batch')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($entity, AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $access = $entity->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $node = NULL): void {
    if ($node) {
      $status = $node->get('status')->getString() == '1';
      // Created.
      if ($node->isNew() && $status) {
        $this->processAppNodes($node);
      }
      else { // Updated.
        if ($status) {
          $this->processAppNodes(
            // Current values, after update.
            $node,
            // Original values, before update.
            $node->getOriginal()
          );
        }
      }
    }
  }

  private function processAppNodes($current, $original = NULL) {
    $title = "Processing app installation request";

    $commands = [];
    // Updated
    if (!empty($original)) {
      $currentAppValues = $current->get('field_app')->getValue();
      $originalAppValues = $original->get('field_app')->getValue();
      $commands = array_merge(
        $commands,
        $this->processCurrOrigApps($currentAppValues, $originalAppValues)
      );
    }
    else { // Created
      $currentAppValues = $current->get('field_app')->getValue();
      $commands = array_merge(
        $commands,
        $this->processCurrOrigApps($currentAppValues)
      );
    }
    // Run the batch operation.
    if (!empty($commands)) {
      $this->batchService->startBatch($commands, $title);
    }
  }

  private function processCurrOrigApps($currentAppValues, $originalAppValues = []) {
    $commands = [];
    // Use $appValues to create App nodes that were added.
    foreach ($currentAppValues as $app_node) {
      if (isset($app_node['subform'])) {
        $app_paragraph = entityManage('paragraph', $app_node['target_id']);
        $app_paragraph->set('field_saved_config', $this->processAppConfig($app_node));
        $app_paragraph->save();

        $pid = $app_node['target_id'];
        $commands["App created (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_provision_app']];
      }
      else {
        $app_paragraph = entityManage('paragraph', $app_node['target_id']);
        $saved_config = json_decode($app_paragraph->get('field_saved_config')->getString(), TRUE);
        # In case the configs have not been saved.
        if (empty($saved_config)) {
          $app_paragraph->set('field_saved_config', $this->processAppConfig($app_node));
          $app_paragraph->save();

          $pid = $app_node['target_id'];
          $commands["App created (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_provision_app']];
        }
      }
    }

    // Use $originalAppValues to delete App nodes that were removed.
    if ($originalAppValues) {
      $currentIds = array_column($currentAppValues, 'target_id');
      foreach ($originalAppValues as $app_node) {
        $pid = $app_node['target_id'];
        // Delete app if it does not exist.
        if (!in_array($pid, $currentIds)) {
          $commands["App deleted (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_delete_app']];
          $commands["App cleanup (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_app_cleanup']];
        }
      }
    }

    return $commands;
  }

  private function processAppConfig($appNode) {
    $app_config = [];
    # Submitted values, process them.
    if (isset($appNode['subform'])) {

    }
    else {

    }
    return json_encode($app_config);
  }

}
