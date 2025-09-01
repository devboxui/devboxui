<?php

namespace Drupal\selective_backup\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Service to backup entities to Cloudflare KV.
 */
class CloudflareBackupService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new CloudflareBackupService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * The Guzzle HTTP client.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   * The JSON serializer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The config factory.
   */
  public function __construct(ClientInterface $http_client, SerializerInterface $serializer, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->serializer = $serializer;
    $this->configFactory = $config_factory;
  }

  /**
   * Backs up a single entity to Cloudflare KV.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   * The entity to backup.
   * @return bool
   * TRUE if the backup was successful, FALSE otherwise.
   */
  public function backupEntity(ContentEntityInterface $entity) {
    $config = $this->configFactory->get('selective_backup.settings');
    $cloudflare_namespace_id = $config->get('kv_namespace_id');
    $cloudflare_api_token = $config->get('kv_api_token');
    
    // Check for required configuration values.
    if (empty($cloudflare_namespace_id) || empty($cloudflare_api_token)) {
      \Drupal::logger('selective_backup')->error('Cloudflare KV configuration is missing. Backup skipped.');
      return FALSE;
    }

    $entity_type_id = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $key = "{$entity_type_id}:{$entity_id}";
    $url = "https://api.cloudflare.com/client/v4/accounts/{$cf_account_id}/storage/kv/namespaces/{$cloudflare_namespace_id}/values/{$key}";

    // Serialize the entity to JSON.
    $data = $this->serializer->serialize($entity, 'json');

    try {
      $response = $this->httpClient->put($url, [
        'headers' => [
          'Authorization' => "Bearer {$cloudflare_api_token}",
          'Content-Type' => 'application/json',
        ],
        'body' => $data,
      ]);

      if ($response->getStatusCode() === 200) {
        return TRUE;
      }
    } catch (RequestException $e) {
      \Drupal::logger('selective_backup')->error('Failed to backup entity @id. Error: @error', ['@id' => $entity_id, '@error' => $e->getMessage()]);
    }

    return FALSE;
  }
}