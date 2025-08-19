<?php

namespace Drupal\turnstile_protect\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Present Turnstile captcha.
 */
class Challenge extends FormBase {

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new TurnstileForm.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'turnstile_protect_challenge_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('captcha.settings');

    // If captcha's globally adding turnstile to all forms
    // no need to add it here.
    if (!$config->get('enable_globally')) {
      $form['turnstile'] = [
        '#type' => 'captcha',
        '#captcha_type' => 'turnstile/Turnstile',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => ['d-none'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();
    $session = $request->getSession();
    $session->set('turnstile_protect_pass', TRUE);

    $destination = $request->query->get('destination');
    if (!$destination) {
      $destination = '/';
    }
    $form_state->setRedirectUrl(Url::fromUserInput($destination));
  }

}
