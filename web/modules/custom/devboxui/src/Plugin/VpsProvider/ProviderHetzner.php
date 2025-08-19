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

  protected $pbkey;
  protected $provider;
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
    $this->provider = 'hetzner';
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
      'name' => 'Hetzner',
      'api_url' => 'https://api.hetzner.cloud/v1',
      'currency' => 'EUR',
    ];
  }

  /**
   * $sshKeyName is always the user's uuid.
   */
  public function ssh_key() {
    if ($sshResp = $this->userData->get('devboxui', $this->user->id(), $this->sshRespField)) {
      $key_resp = json_decode($sshResp, TRUE);
      // Don't upload if the current and previously stored keys are the same.
      if (isset($key_resp['ssh_key']) && $this->pbkey == $key_resp['ssh_key']['public_key']) {
        \Drupal::logger('dexboxui')->notice('SSH key already exists for user @uid', [
          '@uid' => $this->user->id(),
        ]);
        return;
      }

      # First, delete the old key if it exists.
      if (!empty($key_resp)) {
        vpsCall($this->provider, 'ssh_keys/'.$key_resp['ssh_key']['id'], [], 'DELETE');
      }
    }

    # Then, upload it.
    $ret = vpsCall($this->provider, 'ssh_keys', [
      'name' => $this->sshKeyName,
      'public_key' => $this->pbkey,
    ], 'POST');
    $this->saveKeys($ret);
  }

  public function saveKeys($ret) {
    if (isset($ret['ssh_key'])) {
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
    $results = vpsCall($this->provider, 'locations');
    foreach($results['locations'] as $l) {
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
  public function server_type() {
    $locations = vpsCall($this->provider, 'locations');
    $response = vpsCall($this->provider, 'server_types');
    $server_types = array_column($response['server_types'], 'description', 'id');

    $processed_server_types = [];
    foreach ($locations['locations'] as $lk => $lv) {
      foreach ($server_types as $key => $server_name) {
        $prices = array_column($response['server_types'], 'prices', 'id');
        if (!isset($prices[$key][$lk])) {
          continue; // Skip if no price is available for current location.
        }
        $monthly_price = $prices[$key][$lk]['price_monthly']['gross'];
        if (empty($monthly_price)) {
          continue; // Skip if no monthly price is available.
        }
        $hourly_price = $prices[$key][$lk]['price_hourly']['gross'];

        $arch = array_column($response['server_types'], 'architecture', 'id');
        $cores = array_column($response['server_types'], 'cores', 'id');
        $memory = array_column($response['server_types'], 'memory', 'id');
        $disk = array_column($response['server_types'], 'disk', 'id');
        $cpu_type = array_column($response['server_types'], 'cpu_type', 'id');

        $price_key = implode(' (', [
          number_format($monthly_price, 4) . ' EUR/mo',
          number_format($hourly_price, 5) . ' EUR/hr)',
        ]);

        $location_key = Markup::create('<b>' . $lv['city'] . ', ' . $lv['country'] . ' (' . $lv['network_zone'] . ')</b>');
        $processed_value = implode(', ', [
          implode(' - ', [$location_key, $server_name, $arch[$key]]),
          $cores[$key] . ' cores',
          $memory[$key] . ' GB RAM',
          $disk[$key] . ' GB SSD',
          $cpu_type[$key] . ' CPU',
        ]);

        # Key format: 'server type ID'_'location ID'
        $processed_key = implode('_', [$key, $lv['id']]);

        $processed_server_types[$price_key][$processed_key] = $processed_value;
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
    $results = vpsCall($this->provider, 'images', [
      'type' => 'system',
      'status' => 'available',
      'os_flavor' => 'ubuntu',
      'sort' => 'name:desc',
      'architecture' => $arch,
      'per_page' => '1',
    ]);
    return $results['images'][0]['id'];
  }

  public function create_vps($paragraph) {
    $server_info = json_decode($paragraph->get('field_response')->getString(), TRUE);
    // Create server only if it does not exist.
    if (empty($server_info)) {
      $vpsName = $paragraph->uuid();
      [$server_type, $location] = explode('_', $paragraph->get('field_server_type')->getValue()[0]['value'], 2);
      $chosen_server_type = vpsCall($this->provider, 'server_types/'.$server_type, [], 'GET', FALSE);
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
          $ret = vpsCall($this->provider, 'servers/'.$ret['server']['id'], [], 'GET', FALSE);
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
