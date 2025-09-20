<?php

namespace Drupal\devboxui\Plugin\VpsProvider;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\devboxui\Plugin\VpsProvider\VpsProviderPluginBase;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @VpsProvider(
 *   id = "hetzner",
 *   label = @Translation("Hetzner")
 * )
 */
class ProviderHetzner extends VpsProviderPluginBase implements ContainerFactoryPluginInterface {

  protected $api_url;
  protected $currency;
  protected $images;
  protected $locations;
  protected $locationsRetKey;
  protected $pbkey;
  protected $pricing;
  protected $provider;
  protected $providerName;
  protected $server_types;
  protected $ssh_keys;
  protected $ssh_keys_public_key;
  protected $ssh_keys_ret_key;
  protected $sshKeyName;
  protected $sshRespField;
  protected $user;
  protected $userData;

  /**
   * ProviderHetzner constructor.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user.data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    /* Default values. */
    $this->api_url = 'https://api.hetzner.cloud/v1';
    $this->currency = 'currency';
    $this->images = 'images';
    $this->locations = 'locations';
    $this->locationsRetKey = 'locations';
    $this->pricing = 'pricing';
    $this->provider = 'hetzner';
    $this->providerName = 'Hetzner';
    $this->server_types = 'server_types';
    $this->ssh_keys = 'ssh_keys';
    $this->ssh_keys_public_key = 'public_key';
    $this->ssh_keys_ret_key = 'ssh_key';
    $this->user = User::load(\Drupal::currentUser()->id());
    $this->userData = $user_data;
    $this->sshKeyName = $this->user->uuid();
    /* END OF Default values. */

    /* Computed values. */
    $this->sshRespField = 'ssh_servers_'.$this->provider;
    $this->pbkey = $this->user->get('field_ssh_public_key')->getString();
    /* END OF Computed values. */
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.data')
    );
  }

  public function info() {
    return [
      'name' => $this->providerName,
      'api_url' => $this->api_url,
    ];
  }

  /**
   * $sshKeyName is always the user's uuid.
   */
  public function ssh_key() {
    if ($sshResp = $this->userData->get('devboxui', $this->user->id(), $this->sshRespField)) {
      $key_resp = json_decode($sshResp, TRUE);
      // Don't upload if the current and previously stored keys are the same.
      if (isset($key_resp[$this->ssh_keys_ret_key]) && $this->pbkey == $key_resp[$this->ssh_keys_ret_key]['public_key']) {
        \Drupal::logger('dexboxui')->notice('SSH key already exists for user @uid', [
          '@uid' => $this->user->id(),
        ]);
        return;
      }

      # First, delete the old key if it exists.
      if (!empty($key_resp)) {
        vpsCall($this->provider, $this->ssh_keys.'/'.$key_resp[$this->ssh_keys_ret_key]['id'], [], 'DELETE');
      }
    }

    # Then, upload it.
    $ret = vpsCall($this->provider, $this->ssh_keys, [
      'name' => $this->sshKeyName,
      $this->ssh_keys_public_key => $this->pbkey,
    ], 'POST');
    $this->saveKeys($ret);
  }

  public function saveKeys($ret) {
    if (isset($ret[$this->ssh_keys_ret_key])) {
      $this->userData->set('devboxui', $this->user->id(), $this->sshRespField, json_encode($ret));
    }
  }

  /**
   * Get vps locations, cache results.
   *
   * @return void
   */
  public function location() {
    $options = [];
    $results = vpsCall($this->provider, $this->locations);
    foreach($results[$this->locations] as $l) {
      $options[$l['id']] = implode(', ', [
        $l['city'],
        $l['country'],
      ]);
    }
    return $options;
  }

  /**
   * Get vps server types, cache results.
   *
   * @return void
   */
  public function server_type($uid = '') {
    $currency = vpsCall($this->provider, $this->pricing, [], 'GET', $uid)[$this->pricing][$this->currency];
    $locations = vpsCall($this->provider, $this->locations, [], 'GET', $uid);
    $servers = vpsCall($this->provider, $this->server_types, [], 'GET', $uid);

    $locationIds = array_flip(array_column($locations[$this->locationsRetKey], 'name'));
    $processed_server_types = [];
    $condition = count($servers[$this->server_types]) <= $servers['meta']['pagination']['total_entries'];
    while ($condition) {
      foreach ($servers[$this->server_types] as $server) {
        $specs = implode(', ', [
          $server['architecture'],
          $server['cores'] . ' core(s)',
          $server['memory'] . ' GB RAM',
          $server['disk'] . ' GB SSD',
          $server['category'],
        ]);

        foreach ($server['prices'] as $pv) {
          // Skip if no price is available for current location.
          if (!isset($pv)) continue;
          $monthly_price = $pv['price_monthly']['gross'];
          // Skip if no monthly price is available.
          if (empty($monthly_price)) continue;
          $hourly_price = $pv['price_hourly']['gross'];

          $price_key = implode(' (', [
            number_format($monthly_price, 4) . ' '. $currency .'/mo',
            number_format($hourly_price, 5) . ' '. $currency .'/hr)',
          ]);

          $lv = $locations[$this->locations][$locationIds[$pv['location']]];
          $loc = $lv['city'] . ', ' . $lv['country'] . ' (' . $lv['network_zone'] . ')';

          $oneTerabyte = bcpow(10, 12);
          $traffic = bcdiv($pv['included_traffic'], $oneTerabyte, 0) . ' TB traffic';

          $processed_value = implode(' - ', [
            $loc,
            $server['name'],
            $specs . ', ' . $traffic,
          ]);

          # Key format: 'server type ID'_'location ID'.
          $processed_key = implode('_', [$server['id'], $lv['id']]);
          # <select> option.
          $processed_server_types[$price_key][$processed_key] = $processed_value;
        }
      }

      $condition = !empty($servers['meta']['pagination']['next_page']);
      if ($condition) {
        $servers = vpsCall($this->provider, $this->server_types, ['page' => $servers['meta']['pagination']['next_page']], 'GET', $uid);
      }
    }

    ksort($processed_server_types, SORT_NATURAL);
    return $processed_server_types;
  }

  /**
   * Get vps os images, cache results.
   *
   * @return void
   */
  public function os_image($arch = 'x86') {
    $results = vpsCall($this->provider, $this->images, [
      'type' => 'system',
      'status' => 'available',
      'os_flavor' => 'ubuntu',
      'architecture' => $arch,
    ]);
    $oslist = array_column($results[$this->images], 'name', 'id');
    $osid = 0; $osname = '';
    foreach ($oslist as $osk => $osv) {
      if (str_starts_with($osv, 'ubuntu')) {
        if ($osname < $osv) {
          $osid = $osk;
          $osname = $osv;
        }
      }
    }
    return $osid;
  }

  public function create_vps($paragraph) {
    $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);
    // Create server only if it does not exist.
    if (empty($server_info)) {
      $vpsName = $paragraph->uuid();
      [$server_type, $location] = explode('_', $paragraph->get('field_server_type')->getValue()[0]['value'], 2);
      $chosen_server_type = vpsCall($this->provider, $this->server_types.'/'.$server_type, [], 'GET', FALSE);
      $arch = $chosen_server_type['server_type']['architecture'];

      # Create the server.
      $ret = vpsCall($this->provider, 'servers', [
        'name' => $vpsName,
        'location' => $location,
        'server_type' => $server_type,
        'image' => $this->os_image($arch),
        'start_after_create' => TRUE,
        'public_net' => [
          'enable_ipv4' => TRUE,
          'enable_ipv6' => TRUE,
        ],
        'ssh_keys' => [$this->sshKeyName],
      ], 'POST');

      # Save the server ID to the paragraph field.
      if (isset($ret['server'])) {
        $server_status = $ret['server']['status'];
        // Loop until the server is ready to use.
        while ($server_status != 'running') {
          sleep(3); // Wait for 3 seconds before checking again.
          $ret = vpsCall($this->provider, 'servers/'.$ret['server']['id'], [], 'GET', '', FALSE);
          $server_status = $ret['server']['status'];
        }

        $paragraph->set('field_response', json_encode($ret['server']));
        $paragraph->save();
      }
    }
  }

  public function delete_vps($paragraph) {
    $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);

    # Delete the server.
    vpsCall($this->provider, 'servers/'.$server_info['id'], [], 'DELETE');
  }

}
