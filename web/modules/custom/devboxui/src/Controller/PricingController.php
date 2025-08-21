<?php

namespace Drupal\devboxui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;

/**
 * Returns a VPS pricing table.
 */
class PricingController extends ControllerBase {

  /**
   * Builds the VPS pricing table.
   */
  public function pricingTable() {
    $providers = devboxui_get_providers_list();
    natsort($providers);

    $header = [];
    $row = [];
    foreach ($providers as $p) {
      if ($data = $this->processProvider($p)) {
        $header[] = ucwords(str_replace('_', ' ', $p));
        $row[] = ['data' => ['#markup' => $data]];
      }
    }
    $rows = [$row];

    // Render table.
    $build['pricing_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['vps-pricing-table']],
      '#empty' => $this->t('No pricing data available.'),
      '#attached' => ['library' => ['devboxui/datatables'],
      ],
    ];

    return $build;
  }

  public function processProvider($p) {
    $user = entityManage('user', \Drupal::currentUser()->id());
    if ($token = $user->get('field_vps_'.$p)->getString()) {
      $plugin_manager = \Drupal::service('plugin.manager.vps_provider');
      if ($plugin_manager->hasDefinition($p)) {
        $servers = $plugin_manager->createInstance($p)->server_type();
        return $this->pretify($servers);
      }
      return NULL;
    }
    return NULL;
  }

  public function pretify($servers) {
    $list = ['<ul>'];
    foreach ($servers as $sk => $sv) {
      $list[] = '<li>';
      $list[] = $sk;
      if (is_array($sv)) {
        $list[] = '<ul>';
        foreach ($sv as $vk => $vv) {
          $list[] = '<li>';
          $list[] = $vv;
          $list[] = '</li>';
        }
        $list[] = '</ul>';
      }
      $list[] = '</li>';
    }
    $list[] = '</ul>';
    return implode('', $list);
  }

}
