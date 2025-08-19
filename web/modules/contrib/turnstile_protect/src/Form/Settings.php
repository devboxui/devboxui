<?php

namespace Drupal\turnstile_protect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Turnstile settings for this site.
 */
class Settings extends ConfigFormBase {

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs the RouteSelectForm.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   */
  public function __construct(RouteProviderInterface $route_provider) {
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'turnstile_protect_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['turnstile_protect.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('turnstile_protect.settings');
    $routes = $this->routeProvider->getAllRoutes();
    $route_options = [];
    foreach ($routes as $route_name => $route) {
      $route_options[$route_name] = $route_name . ' (' . $route->getPath() . ')';
    }
    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("Select which route(s) will have a Cloudflare turnstile challenge presented to visitors before proceeding.
        <br><br>
        Authenticated users and trusted networks set in <a href=\":url\">the CAPTCHA module's IP settings</a> will not be challenged.
        <br><br>
        This may negatively effort your SEO score, so you can configure which bots will not be challenged below.",
        [':url' => '/admin/config/people/captcha']),
    ];

    $form['routes'] = [
      '#type' => 'select',
      '#title' => $this->t('Route(s) to protect'),
      '#description' => $this->t('Any route here will present a client with a turnstile challenge once per session.'),
      '#default_value' => $config->get('routes'),
      '#required' => TRUE,
      '#options' => $route_options,
      '#multiple' => TRUE,
      '#attributes' => [
        'class' => ['chosen-select'],
      ],
    ];
    $form['#attached']['library'][] = 'turnstile_protect/chosen';

    $form['bots'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Parent domain(s) of bots to not challenge*'),
      '#description' => $this->t('If a client IP reaches your site that resolves to a given domain, you can let them through the captcha. One bot per line, only the parent domain.
        <br>* The bot will be denied if routes with URL parameters are also protected (setting below)'),
      '#default_value' => implode("\n", $config->get('bots')),
      '#rows' => 20,
    ];

    $form['protect_parameters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always protect routes with URL parameters key'),
      '#description' => $this->t('Return a 403/Forbidden when good bots hit protected route(s) with one or more URL parameters (e.g. example.com?foo=bar). This is to avoid having good bots do things like crawl facets on your search index.'),
      '#default_value' => $config->get('protect_parameters'),
    ];

    $form['rate_limit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rate limit: Only challenge for ranges seeing increased traffic'),
      '#description' => $this->t('Clients IPs will be tracked by range (e.g. 8.8.0.0-8.8.255.255) and only challenged if that range is seeing increased traffic.'),
      '#default_value' => $config->get('rate_limit'),
    ];

    $form['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate limit: How many requests within a time window before blocking traffic within an IP range'),
      '#default_value' => $config->get('threshold'),
    ];

    $form['window'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate limit: Number of seconds in the time window to allow "threshold" number of events'),
      '#default_value' => $config->get('window'),
    ];

    $form['max_challenges'] = [
      '#type' => 'number',
      '#title' => 'Max challenges',
      '#description' => $this->t('The maximum times the client can be challenged in a session before sending 429 responses'),
      '#default_value' => $config->get('max_challenges'),
      '#min' => 1,
    ];

    $form['history_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable History'),
      '#description' => $this->t('Enable or disable history tracking in Turnstile Protect.'),
      '#default_value' => $config->get('history_enabled'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $routes = $form_state->getValue('routes');
    $goodBots = explode("\n", $form_state->getValue('bots'));
    array_walk($goodBots, function (&$line) {
      $line = trim($line);
    });

    $config = $this->config('turnstile_protect.settings');
    $config
      ->set('routes', array_keys($routes))
      ->set('protect_parameters', (bool) $form_state->getValue('protect_parameters'))
      ->set('bots', $goodBots)
      ->set('rate_limit', (bool) $form_state->getValue('rate_limit'))
      ->set('threshold', (int) $form_state->getValue('threshold'))
      ->set('window', (int) $form_state->getValue('window'))
      ->set('max_challenges', (int) $form_state->getValue('max_challenges'))
      ->set('history_enabled', (bool) $form_state->getValue('history_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
