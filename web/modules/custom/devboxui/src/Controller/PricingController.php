<?php

namespace Drupal\devboxui\Controller;

use Drupal\Core\Controller\ControllerBase;

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
      $header[] = ucwords(str_replace('_', ' ', $p));
      $row[] = [
        'data' => [
          '#markup' => '<strong>$' . $p . '</strong><br>- ' .
            $p . ' (' . $p . ', ' . $p . ', ' . $p . ')',
          ],
      ];
    }
    $rows = [$row];

    /*
    $providers = [
      ['provider' => 'Hetzner'],
    ];

    $rows = [];
    foreach ($providers as $provider) {
      $rows[] = [
        $provider['provider'],
      ];
    }
    */

    // Render table.
    $build['pricing_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['vps-pricing-table']],
      '#empty' => $this->t('No pricing data available.'),
      '#attached' => [
        'library' => [
          'devboxui/datatables',
        ],
      ],
    ];

    return $build;
  }
}
