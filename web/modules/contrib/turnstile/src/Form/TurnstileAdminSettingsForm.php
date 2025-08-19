<?php

namespace Drupal\turnstile\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Turnstile settings for this site.
 */
class TurnstileAdminSettingsForm extends ConfigFormBase {
  use StringTranslationTrait;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    ModuleHandlerInterface $module_handler,
    MessengerInterface $messenger,
    KeyRepositoryInterface $key_repository,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('cache_tags.invalidator'),
      $container->get('module_handler'),
      $container->get('messenger'),
      $container->get('key.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'turnstile_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['turnstile.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('turnstile.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];

    $form['general']['turnstile_keys'] = [
      '#default_value' => $config->get('keys'),
      '#description' => $this->t('<p><strong>Choose "Authentication (Multivalue)"</strong>, and ensure your JSON includes "site_key" and "secret_key", for example:</p><code>{
        "site_key": "0x000...",
        "secret_key": "0x000...",
      }</code><p>The site and secret keys given to you when you <a href=":url" target="_blank">register for Turnstile</a>.</p>', [
        ':url' => 'https://cloudflare.com',
      ]),
      '#required' => TRUE,
      '#title' => $this->t('Keys'),
      '#type' => 'key_select',
      '#key_filters' => [
        'type' => 'authentication_multivalue',
      ],
    ];

    if (!$this->moduleHandler->moduleExists('key')) {
      $form['general']['turnstile_keys']['#type'] = 'item';

      $form['general']['turnstile_keys']['#description'] = $this->t('<p><strong><em>The Key module must be enabled for Turnstile to work.</em></strong></p>');

      $this->messenger->addError($this->t('The Key module must be enabled for Turnstile to work.'));
    }

    $form['general']['turnstile_src'] = [
      '#default_value' => $config->get('turnstile_src'),
      '#description' => $this->t('Default URL is ":url".', [
        ':url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
      ]),
      '#maxlength' => 200,
      '#required' => TRUE,
      '#title' => $this->t('Turnstile JavaScript resource URL'),
      '#type' => 'textfield',
    ];

    $testing_details_open = FALSE;
    $testing_site_key = $config->get('testing_site_key');
    $testing_secret_key = $config->get('testing_secret_key');

    if ($testing_site_key || $testing_secret_key) {
      $testing_details_open = TRUE;
    }

    $form['general']['testing'] = [
      '#type' => 'details',
      '#title' => $this->t('Testing Settings'),
      '#open' => $testing_details_open,
      '#description' => $this->t('For detailed information about testing, please visit <a href=":url" target="_blank">Cloudflare\'s Testing page</a>.', [
        ':url' => 'https://developers.cloudflare.com/turnstile/troubleshooting/testing/',
      ]),
    ];

    $form['general']['testing']['testing_turnstile_site_key'] = [
      '#default_value' => $testing_site_key,
      '#title' => $this->t('Site key'),
      '#type' => 'select',
      '#options' => [
        '1x00000000000000000000AA' => $this->t('Always passes - visible (1x00000000000000000000AA)'),
        '2x00000000000000000000AB' => $this->t('Always blocks - visible (2x00000000000000000000AB)'),
        '1x00000000000000000000BB' => $this->t('Always passes - invisible (1x00000000000000000000BB)'),
        '2x00000000000000000000BB' => $this->t('Always blocks - invisible (2x00000000000000000000BB)'),
        '3x00000000000000000000FF' => $this->t('Forces an interactive challenge - visible (3x00000000000000000000FF)'),
      ],
      '#empty_option' => $this->t('Disabled (use live site key)'),
    ];

    $form['general']['testing']['testing_turnstile_secret_key'] = [
      '#default_value' => $testing_secret_key,
      '#title' => $this->t('Secret key'),
      '#type' => 'select',
      '#options' => [
        '1x0000000000000000000000000000000AA' => $this->t('Always passes (1x0000000000000000000000000000000AA)'),
        '2x0000000000000000000000000000000AA' => $this->t('Always fails (2x0000000000000000000000000000000AA)'),
        '3x0000000000000000000000000000000AA' => $this->t('Yields a "token already spent" error (3x0000000000000000000000000000000AA)'),
      ],
      '#empty_option' => $this->t('Disabled (use live secret key)'),
    ];

    // Widget configurations.
    $form['widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Widget settings'),
      '#open' => TRUE,
    ];

    $form['widget']['turnstile_theme'] = [
      '#default_value' => $config->get('widget.theme'),
      '#description' => $this->t('Defines which theme to use for Turnstile.'),
      '#options' => [
        'light' => $this->t('Light (default)'),
        'dark' => $this->t('Dark'),
        'auto' => $this->t('Auto'),
      ],
      '#title' => $this->t('Theme'),
      '#type' => 'select',
    ];

    $form['widget']['turnstile_language'] = [
      '#default_value' => $config->get('widget.language'),
      '#description' => $this->t('Language to display, must be either: auto (default) to use the language that the visitor has chosen.'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'ar-eg' => $this->t('Arabic (Egypt)'),
        'de' => $this->t('German'),
        'en' => $this->t('English'),
        'es' => $this->t('Spanish'),
        'fa' => $this->t('Farsi'),
        'fr' => $this->t('French'),
        'id' => $this->t('Indonesian'),
        'it' => $this->t('Italian'),
        'ja' => $this->t('Japanese'),
        'ko' => $this->t('Korean'),
        'nl' => $this->t('Dutch'),
        'pl' => $this->t('Polish'),
        'pt-br' => $this->t('Portuguese (Brazil)'),
        'ru' => $this->t('Russian'),
        'tr' => $this->t('Turkish'),
        'zh-cn' => $this->t('Chinese (Simplified)'),
        'zh-tw' => $this->t('Chinese (Traditional)'),
      ],
      '#title' => $this->t('Language'),
      '#type' => 'select',
    ];

    $form['widget']['turnstile_size'] = [
      '#default_value' => $config->get('widget.size'),
      '#description' => $this->t('The widget size.'),
      '#options' => [
        'normal' => $this->t('Normal'),
        'compact' => $this->t('Compact'),
        'flexible' => $this->t('Flexible'),
      ],
      '#title' => $this->t('Size'),
      '#type' => 'select',
    ];

    $form['widget']['turnstile_retry'] = [
      '#default_value' => $config->get('widget.retry'),
      '#description' => $this->t('Controls whether the widget should automatically retry to obtain a token if it did not succeed. The default is auto, which will retry automatically. This can be set to never to disable retry upon failure.'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'never' => $this->t('Never'),
      ],
      '#title' => $this->t('Retry'),
      '#type' => 'select',
    ];

    $form['widget']['turnstile_retry_interval'] = [
      '#default_value' => $config->get('widget.retry_interval'),
      '#description' => $this->t('When retry is set to auto, retry-interval controls the time between retry attempts in milliseconds. Value must be a positive integer less than 900000, defaults to 8000.'),
      '#maxlength' => 6,
      '#title' => $this->t('Retry Interval'),
      '#type' => 'number',
      '#min' => 1,
      '#max' => 900000,
      '#step' => '1',
      '#states' => [
        'visible' => [
          ':input[name="turnstile_retry"]' => [
            'value' => 'auto',
          ],
        ],
        'required' => [
          ':input[name="turnstile_retry"]' => [
            'value' => 'auto',
          ],
        ],
      ],
    ];

    $form['widget']['turnstile_appearance'] = [
      '#default_value' => $config->get('widget.appearance'),
      '#description' => $this->t('Appearance controls when the widget is visible. It can be always (default), execute, or interaction-only. Refer to <a href="https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/#appearance-modes" target="_blank">Appearance Modes</a> for more information.'),
      '#options' => [
        'always' => $this->t('Always'),
        'execute' => $this->t('Execute'),
        'interaction-only' => $this->t('Interaction Only'),
      ],
      '#title' => $this->t('Appearance'),
      '#type' => 'select',
    ];

    $form['widget']['turnstile_tabindex'] = [
      '#default_value' => $config->get('widget.tabindex'),
      '#description' => $this->t('Set the <a href=":tabindex" target="_blank">tabindex</a> of the widget and challenge (Default = 0). If other elements in your page use tabindex, it should be set to make user navigation easier.', [':tabindex' => Url::fromUri('https://www.w3.org/TR/html4/interact/forms.html', ['fragment' => 'adef-tabindex'])->toString()]),
      '#maxlength' => 4,
      '#title' => $this->t('Tabindex'),
      '#type' => 'number',
      '#min' => -1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->cacheTagsInvalidator->invalidateTags(['library_info']);

    $keys = [];
    if ($this->moduleHandler->moduleExists('key')) {
      $keys = $this->keyRepository->getKey($form_state->getValue('turnstile_keys'));

      if ($keys) {
        $keys = $keys->getKeyValues();
      }
    }
    else {
      $form_state->setErrorByName('turnstile_keys', 'Please ensure the Key module is installed and enabled.');
    }

    if (empty($keys['site_key'])) {
      $form_state->setErrorByName('turnstile_keys', 'Please ensure your key has a "site_key" value.');
    }
    if (empty($keys['secret_key'])) {
      $form_state->setErrorByName('turnstile_keys', 'Please ensure your key has a "secret_key" value.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->cacheTagsInvalidator->invalidateTags(['library_info']);

    $config = $this->config('turnstile.settings');
    $config
      ->set('keys', $form_state->getValue('turnstile_keys'))
      ->set('turnstile_src', $form_state->getValue('turnstile_src'))
      ->set('testing_site_key', $form_state->getValue('testing_turnstile_site_key'))
      ->set('testing_secret_key', $form_state->getValue('testing_turnstile_secret_key'))
      ->set('widget.theme', $form_state->getValue('turnstile_theme'))
      ->set('widget.tabindex', $form_state->getValue('turnstile_tabindex'))
      ->set('widget.language', $form_state->getValue('turnstile_language'))
      ->set('widget.size', $form_state->getValue('turnstile_size'))
      ->set('widget.retry', $form_state->getValue('turnstile_retry'))
      ->set('widget.retry_interval', $form_state->getValue('turnstile_retry_interval'))
      ->set('widget.appearance', $form_state->getValue('turnstile_appearance'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
