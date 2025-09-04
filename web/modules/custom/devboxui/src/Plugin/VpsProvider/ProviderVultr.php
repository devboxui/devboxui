<?php

namespace Drupal\devboxui\Plugin\VpsProvider;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\devboxui\Plugin\VpsProvider\VpsProviderPluginBase;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @VpsProvider(
 *   id = "vultr",
 *   label = @Translation("Vultr")
 * )
 */
class ProviderVultr extends VpsProviderPluginBase implements ContainerFactoryPluginInterface {

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
    $this->api_url = 'https://api.vultr.com/v2';
    $this->currency = 'currency';
    $this->images = 'os';
    $this->locations = 'regions';
    $this->locationsRetKey = 'regions';
    $this->pricing = 'pricing';
    $this->provider = 'vultr';
    $this->providerName = 'Vultr';
    $this->server_types = 'plans';
    $this->ssh_keys = 'ssh-keys';
    $this->ssh_keys_public_key = 'ssh_key';
    $this->ssh_keys_ret_key = 'ssh_key';
    $this->user = User::load(\Drupal::currentUser()->id());
    $this->userData = $user_data;
    $this->sshKeyName = $this->user->uuid();
    /* END OF Default values. */

    /* Computed values. */
    $this->sshRespField = 'ssh_response_'.$this->provider;
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
    $currency = 'USD';
    $locations = vpsCall($this->provider, $this->locations, [], 'GET', $uid);
    $servers = vpsCall($this->provider, $this->server_types, [], 'GET', $uid);

    $locationIds = array_flip(array_column($locations[$this->locationsRetKey], 'id'));
    $processed_server_types = [];
    while (!empty($servers['meta']['links']['next'])) {
      foreach ($servers[$this->server_types] as $server) {
        $price_key = implode(' (', [
          $server['monthly_cost'] . ' '. $currency .'/mo',
          $server['hourly_cost'] . ' '. $currency .'/hr)',
        ]);

        foreach ($server['locations'] as $sloc) {
          $lv = $locations[$this->locations][$locationIds[$sloc]];
          $loc = $lv['city'] . ', '. $lv['country'];
          $processed_value = implode(' - ', [
            $loc,
            $server['id'],
            implode(', ', [
              $server['cpu_vendor'],
              $server['vcpu_count'] . ' core(s)',
              $server['ram'] . ' MB RAM',
              $server['disk'] . ' GB ' . $server['disk_type'],
              number_format($server['bandwidth']/1000, 1) . ' TB traffic',
            ]),
          ]);

          # Key format: 'server type ID'_'location ID'.
          $processed_key = implode('_', [$server['id'], $lv['id']]);
          # <select> option.
          $processed_server_types[$price_key][$processed_key] = $processed_value;
        }
      }

      if (!empty($servers['meta']['links']['next'])) {
        $servers = vpsCall($this->provider, $this->server_types, ['cursor' => $servers['meta']['links']['next']], 'GET', $uid);
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
  public function os_image() {
    $results = vpsCall($this->provider, $this->images);

    $osid = 0;
    foreach ($results[$this->images] as $os) {
      if ($os['family'] == 'debian') {
        if ($osid < $os['id']) {
          $osid = $os['id'];
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

      $osid = $this->os_image();
      # Create the server.
      $ret = vpsCall($this->provider, 'instances', [
        'name' => $vpsName,
        'region' => $location,
        'plan' => $server_type,
        'os_id' => $osid,
        'sshkey_id' => [$this->getSshKeyId()],
        'backups' => 'disabled',
      ], 'POST');

      # Save the server ID to the paragraph field.
      if (isset($ret['server'])) {
        $server_status = $ret['server']['status'];
        // Loop until the server is ready to use.
        while ($server_status != 'running') {
          sleep(3); // Wait for 3 seconds before checking again.
          $ret = vpsCall($this->provider, 'servers/'.$ret['server']['id'], [], 'GET', FALSE);
          $server_status = $ret['server']['status'];
        }

        $paragraph->set('field_response', json_encode($ret['server']));
        $paragraph->save();
      }
    }
  }

  private function getSshKeyId() {
    if ($sshResp = $this->userData->get('devboxui', $this->user->id(), $this->sshRespField)) {
      $key_resp = json_decode($sshResp, TRUE);
      return $key_resp[$this->ssh_keys_ret_key]['id'];
    }
    return '';
  }

  public function delete_vps($paragraph) {
    $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);

    # Delete the server.
    vpsCall($this->provider, 'instances/'.$server_info['id'], [], 'DELETE');
  }

}
