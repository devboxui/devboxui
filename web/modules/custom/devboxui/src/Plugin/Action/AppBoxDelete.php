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
 *   id = "devboxui_delete_app",
 *   label = @Translation("App Delete"),
 *   type = "node",
 *   category = @Translation("DevBoxUI"),
 *   context = {
 *     "entity" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
final class AppBoxDelete extends ActionBase implements ContainerFactoryPluginInterface {

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
    if ($node && $node->get('status')->getString() == '1') {
      $app_nodes = $node->get('field_app')->getValue();
      $title = "Deleting App";

      $commands = [];
      foreach ($app_nodes as $app_node) {
        $pid = $app_node['target_id'];
        $commands["App deleted (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_delete_app']];
        $commands["App cleanup (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_app_cleanup']];
      }

      $this->batchService->startBatch($commands, $title);
    }
  }

}
