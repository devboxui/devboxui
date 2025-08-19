<?php

namespace Drupal\eca_tamper\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_tamper\Plugin\TamperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide all tamper plugins as ECA actions.
 */
#[Action(
  id: 'eca_tamper',
  deriver: 'Drupal\eca_tamper\Plugin\Action\TamperDeriver',
)]
#[EcaAction(
  version_introduced: '1.0.0',
)]
class Tamper extends ConfigurableActionBase {

  use TamperTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tamperManager = $container->get('plugin.manager.tamper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function execute(): void {
    $value = $this->doTamper('eca_data', 'eca_token_name');
    $this->tokenService->addTokenData($this->configuration['eca_token_name'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'eca_data' => '',
      'eca_token_name' => '',
    ] + $this->tamperDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['eca_data'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data to be tampered'),
      '#default_value' => $this->configuration['eca_data'],
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    $form['eca_token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Result token name'),
      '#description' => $this->t('Provide a token name under which the tampered result will be made available for subsequent actions.'),
      '#default_value' => $this->configuration['eca_token_name'],
      '#required' => TRUE,
      '#weight' => 99,
    ];
    return $this->buildTamperConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateTamperConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['eca_data'] = $form_state->getValue('eca_data');
    $this->configuration['eca_token_name'] = $form_state->getValue('eca_token_name');
    $this->submitTamperConfigurationForm($form, $form_state);
    $this->configuration = $this->tamperPlugin()->getConfiguration() + $this->configuration;
  }

}
