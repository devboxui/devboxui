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
 *   id = "akamai_cloud_linode",
 *   label = @Translation("Akamai Cloud (Linode)")
 * )
 */
class ProviderAkamaiCloudLinode extends VpsProviderPluginBase implements ContainerFactoryPluginInterface {

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
  protected $server_types_ret_key;
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
    $this->api_url = 'https://api.linode.com/v4';
    $this->currency = 'currency';
    $this->images = 'images';
    $this->locations = 'regions';
    $this->locationsRetKey = 'data';
    $this->pricing = 'pricing';
    $this->provider = 'akamai_cloud_linode';
    $this->providerName = 'Akamai Cloud (Linode)';
    $this->server_types = 'linode/types';
    $this->server_types_ret_key = 'data';
    $this->ssh_keys = 'profile/sshkeys';
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
  public function ssh_key($keyid = '') {
    if ($sshResp = $this->userData->get('devboxui', $this->user->id(), $this->sshRespField)) {
      $key_resp = json_decode($sshResp, TRUE);
      // Don't upload if the current and previously stored keys are the same.
      if (isset($key_resp[$this->ssh_keys_ret_key]) && $this->pbkey == $key_resp[$this->ssh_keys_ret_key]) {
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
      'label' => $this->sshKeyName,
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

    $locationsArray = [];
    foreach ($locations[$this->locationsRetKey] as $location) {
      $locationsArray[$location['id']] = $location['label'];
    }

    $processed_server_types = [];
    foreach ($servers[$this->server_types_ret_key] as $server) {
      $price_key = implode(' (', [
        $server['price']['monthly'] . ' '. $currency .'/mo',
        $server['price']['hourly'] . ' '. $currency .'/hr)',
      ]);

      $specs = implode(', ', [
        $server['vcpus'] . ' core(s)',
        $server['memory'] . ' MB RAM',
        $server['disk'] . ' MB SSD',
        $server['transfer']/1000 . ' TB traffic',
      ]);

      foreach ($locations[$this->locationsRetKey] as $sloc) {
        if ($sloc['status'] != 'ok') continue;

        $processed_value = implode(' - ', [
          $sloc['label'],
          $server['id'],
          $specs,
        ]);

        # Get location exceptions.
        $locationExcp = array_column($server['region_prices'], 'id');
        if (in_array($sloc['id'], $locationExcp)) {
          $locationExcpFlipped = array_flip($locationExcp);

          # Get the exception price.
          $exceptionPrice = $server['region_prices'][$locationExcpFlipped[$sloc['id']]];
          $price_key = implode(' (', [
            $exceptionPrice['monthly'] . ' '. $currency .'/mo',
            $exceptionPrice['hourly'] . ' '. $currency .'/hr)',
          ]);
        }

        # Key format: 'server type ID'.
        $processed_key = implode('_', [$server['id'], $sloc['id']]);
        # Build the record.
        $processed_server_types[$price_key][$processed_key] = Markup::create($processed_value);
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
    foreach ($results['data'] as $os) {
      if (strtolower($os['vendor']) == 'debian' && preg_match('/^linode\/debian[0-9]+$/', $os['id'])) {
        if ($osid < $os['id']) {
          $osid = $os['id'];
        }
      }
    }

    return $osid;
  }

  private function generatePassword(int $length = 128): string {
    // Character set that includes uppercase, lowercase, numbers, and special characters.
    $characterSet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
    $characterSetLength = strlen($characterSet);
    $password = '';
    for ($i = 0; $i < $length; $i++) {
      $password .= $characterSet[random_int(0, $characterSetLength - 1)];
    }
    return $password;
  }

  public function create_vps($paragraph) {
    $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);
    // Create server only if it does not exist.
    if (empty($server_info)) {
      $vpsName = $paragraph->uuid();
      [$server_type, $location] = explode('_', $paragraph->get('field_server_type')->getValue()[0]['value'], 2);

      $os = $this->os_image();
      # Create the server.
      $ret = vpsCall($this->provider, 'linode/instances', [
        'label' => $vpsName,
        'region' => $location,
        'type' => $server_type,
        'image' => $os,
        'authorized_keys' => [$this->pbkey],
        'root_pass' => $this->generatePassword(),
        'booted' => true,
        'backups_enabled' => false,
        'disk_encryption' => 'disabled',
      ], 'POST');

      # Save the server ID to the paragraph field.
      if (isset($ret['id'])) {
        $server_status = $ret['status'];
        // Loop until the server is ready to use.
        while ($server_status != 'running') {
          sleep(3); // Wait for 3 seconds before checking again.
          $ret = vpsCall($this->provider, 'linode/instances/'.$ret['id'], [], 'GET', '', FALSE);
          $server_status = $ret['status'];
        }

        $paragraph->set('field_response', json_encode($ret));
        $paragraph->save();
      }
    }
  }

  public function delete_vps($paragraph) {
    $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);

    # Delete the server.
    vpsCall($this->provider, 'linode/instances/'.$server_info['id'], [], 'DELETE');
  }

}
