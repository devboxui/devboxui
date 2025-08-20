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
      if ($p == 'hetzner') {
        $header[] = ucwords(str_replace('_', ' ', $p));
        $row[] = [
          'data' => ['#markup' => $this->processProvider($p)],
        ];
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
    if ($p == 'hetzner') {
      $servers = \Drupal::service('plugin.manager.vps_provider')->createInstance($p)->server_type();
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
    else {
      return '<p>'.$p.'</p>';
    }
  }
}
