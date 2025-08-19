<?php

namespace Drupal\devboxui\Plugin\Action;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

/**
 * Provides a custom action.
 *
 * @Action(
 *   id = "devboxui_upload_keys_to_provider",
 *   label = @Translation("Upload keys to provider"),
 *   type = "user",
 *   category = @Translation("DevBoxUI"),
 *   context = {
 *     "entity" = @ContextDefinition("entity:user", label = @Translation("User")),
 *   }
 * )
 */
final class UploadKeysToProvider extends ActionBase {

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
  public function execute(ContentEntityInterface $user = NULL): void {
    # User is always provided by the action context.
    if ($user) {
      foreach($user->getFields() as $fieldk => $field) {
        if (str_starts_with($fieldk, 'field_vps_')) {
          if (!empty($user->get($fieldk)->getString())) {
            # Get provider name.
            $provider = explode('field_vps_', $fieldk)[1];
            # Initialize the provider plugin.
            $vps_plugin = \Drupal::service('plugin.manager.vps_provider')->createInstance($provider);
            # Upload SSH key if it does not exist.
            $vps_plugin->ssh_key();
          }
        }
      }
    }
  }

}
