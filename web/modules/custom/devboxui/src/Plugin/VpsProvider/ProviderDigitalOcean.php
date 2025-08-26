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
 *   id = "digitalocean",
 *   label = @Translation("DigitalOcean")
 * )
 */
class ProviderDigitalOcean extends VpsProviderPluginBase implements ContainerFactoryPluginInterface {

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
    $this->api_url = 'https://api.digitalocean.com/v2';
    $this->currency = 'currency';
    $this->images = 'images';
    $this->locations = 'regions';
    $this->locationsRetKey = 'data';
    $this->pricing = 'pricing';
    $this->provider = 'digitalocean';
    $this->providerName = 'DigitalOcean';
    $this->server_types = 'linode/types';
    $this->server_types_ret_key = 'data';
    $this->ssh_keys = 'account/keys';
    $this->ssh_keys_public_key = 'public_key';
    $this->ssh_keys_ret_key = 'ssh_keys';
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
  public function server_type() {
    $currency = 'USD';
    $locations = vpsCall($this->provider, $this->locations);
    $response = vpsCall($this->provider, $this->server_types);

    $locationsArray = [];
    foreach ($locations['data'] as $location) {
      $locationsArray[$location['id']] = $location['label'];
    }

    $locationIds = array_flip(array_column($locations[$this->locationsRetKey], 'id'));
    $processed_server_types = [];
    foreach ($response[$this->server_types_ret_key] as $server) {
      $key = $server_name = $server['id'];
      $price_key = implode(' (', [
        $server['price']['monthly'] . ' '. $currency .'/mo',
        $server['price']['hourly'] . ' '. $currency .'/hr)',
      ]);

      $processed_value = implode('<br>', [
        '<b>ID:</b> '.implode(' - ', [$server_name]),
        '<b>Specs:</b> '.implode(', ', [
          '<b>'.$server['vcpus'].'</b>' . ' core(s)',
          '<b>'.$server['memory'].'</b>' . ' MB RAM',
          '<b>'.$server['disk'].'</b>' . ' MB SSD',
          '<b>'.$server['network_out'].'</b>' . ' MB traffic',
        ]),
      ]);

      # Add locations.
      $processed_value = implode('<br>', [
        $processed_value,
        '<b>Locations:</b> All',
      ]);

      # Process location exceptions.
      $exceptions = [];
      foreach ($server['region_prices'] as $ex) {
        $exceptions[] = '' . implode(', ', [
          $ex['monthly'] . ' '. $currency .'/mo',
          $ex['hourly'] . ' '. $currency .'/hr',
        ]);
      }

      # Add location exceptions.
      if (!empty($exceptions)) {
        $processed_value = implode('<br>', [
          $processed_value,
          '<b>Exceptions:</b> ' . implode('; ', $exceptions),
        ]);
      }

      # Key format: 'server type ID'.
      $processed_key = implode('_', [$key]);
      # Build the record.
      $processed_server_types[$price_key][$processed_key] = Markup::create($processed_value);
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
      'sort' => 'name:desc',
      'architecture' => $arch,
      'per_page' => '1',
    ]);
    return $results[$this->images][0]['id'];
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
