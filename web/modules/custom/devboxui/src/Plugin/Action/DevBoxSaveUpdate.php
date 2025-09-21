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
 *   id = "devboxui_save_update",
 *   label = @Translation("DevBox Save/Update"),
 *   type = "node",
 *   category = @Translation("DevBoxUI"),
 *   context = {
 *     "entity" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
final class DevBoxSaveUpdate extends ActionBase implements ContainerFactoryPluginInterface {

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
        $this->processVpsNodes($node->get('field_vps_provider')->getValue());
      }
      else { // Updated.
        if ($status) {
          $this->processVpsNodes(
            // Current values, after update.
            $node->get('field_vps_provider')->getValue(),
            // Original values, before update.
            $node->getOriginal()->get('field_vps_provider')->getValue()
          );
        }
      }
    }
  }

  public function processVpsNodes($currentValues, $originalValues = []) {
    $title = "Processing VPS request";

    // Updated
    if (!empty($originalValues)) {
      $commands = [];
      // Use $currentValues to create VPS nodes that were added.
      foreach ($currentValues as $vps_node) {
        $pid = $vps_node['target_id'];
        $paragraph = entityManage('paragraph', $pid);
        if ($paragraph->getType() == 'manual') {
          $server_info = $paragraph->get('field_server_ip')->getString();
        }
        else {
          $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);
        }
        // Create server only if it does not exist.
        if (empty($server_info)) {
          $this->vpsBuildCmds($paragraph, $commands, $pid);
        }
      }

      $currentIds = array_column($currentValues, 'target_id');
      // Use $originalValues to delete VPS nodes that were removed.
      foreach ($originalValues as $vps_node) {
        $pid = $vps_node['target_id'];
        // Delete server.
        if (!in_array($pid, $currentIds)) {
          $commands["VPS deleted (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'delete_vps']];
        }
      }
    }
    else { // Created
      $commands = [];
      foreach ($currentValues as $vps_node) {
        $pid = $vps_node['target_id'];
        $paragraph = entityManage('paragraph', $pid);
        $this->vpsBuildCmds($paragraph, $commands, $pid);
      }
      // Reboot
      #$commands["Reboot (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_reboot']];
    }
    // Run the batch operation.
    if (!empty($commands)) {
      $this->batchService->startBatch($commands, $title);
    }
  }

  private function vpsBuildCmds($paragraph, &$commands, $pid) {
    $type = $paragraph->get('type')->getString();
    $tools = array_column($paragraph->get('field_tools')->getValue(), 'value');

    if ($type != 'manual') {
      $commands["VPS created (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'provision_vps']];
    }
    if (array_search('ubuntu_package_updates', $tools) !== FALSE) {
      $commands["OS package info updated (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_system_update']];
    }
    if (array_search('ubuntu_package_upgrades', $tools) !== FALSE) {
      $commands["OS system upgraded (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_system_upgrade']];
    }
    if (array_search('ssh_config_updates', $tools) !== FALSE) {
      $commands["SSH configs updated (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_ssh_configs']];
    }
    if (array_search('oh_my_bash', $tools) !== FALSE) {
      $commands["OhMyBASH! installed (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_ohmybash']];
    }
    if (array_search('docker_engine', $tools) !== FALSE) {
      $commands["Docker installed (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_docker_install']];
    }
    $commands["User created (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_create_user']];
    if (array_search('ddev', $tools) !== FALSE) {
      $commands["DDEV installed (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_ddev_install']];
    }
    if (array_search('composer', $tools) !== FALSE) {
      #$commands["Composer installed (id: $pid)"] = [$pid => [DevBoxBatchService::class, 'ssh_composer_install']];
    }
  }

  private function phpVersion($paragraph) {
    return $paragraph->get('field_php_version')->getString();
  }

}
