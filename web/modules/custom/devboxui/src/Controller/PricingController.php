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
    $rows = [];
    $providerCount = 0;
    foreach ($providers as $p) {
      if ($data = $this->processProvider($p)) {
        $provider_name = ucwords(str_replace('_', ' ', $p));
        if ($provider_name == 'Digitalocean') {
          $provider_name = 'DigitalOcean';
        }
        $header[] = $provider_name;

        # Fill columns instead of rows.
        $rowCount = 0;
        foreach ($data as $d) {
          # Account for different provider counts.
          for ($i = 0; $i < $providerCount; $i++) {
            if (!isset($rows[$rowCount][$i])) {
              $rows[$rowCount][$i] = ['data' => ['#markup' => '']];
            }
          }
          # Add the current data.
          $rows[$rowCount][$providerCount] = ['data' => ['#markup' => $d]];
          # Increase counter.
          $rowCount++;
        }
        $providerCount++;
      }
    }

    // Render table.
    $build['pricing_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['vps-pricing-table']],
      '#empty' => $this->t('No pricing data available.'),
      '#attached' => ['library' => ['devboxui/datatables']],
    ];

    return $build;
  }

  public function processProvider($p) {
    $user = entityManage('user', \Drupal::currentUser()->id());
    if (!empty($user->get('field_vps_'.$p)->getString())) {
      $plugin_manager = \Drupal::service('plugin.manager.vps_provider');
      if ($plugin_manager->hasDefinition($p)) {
        $servers = $plugin_manager->createInstance($p)->server_type();
        return $this->arrayOfStrings($servers);
      }
      return [];
    }
    return [];
  }

  public function arrayOfStrings($servers) {
    $output = [];
    foreach ($servers as $sk => $sv) {
      if (is_array($sv)) {
        foreach ($sv as $vk => $vv) {
          $row = '<b>'.$sk.'</b><br>'.$vv;
          $output[] = $row;
        }
      }
    }
    return $output;
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
