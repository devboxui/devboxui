<?php

namespace Drupal\forward_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ForwardAuthSettingsForm extends ConfigFormBase {
  public function getFormId() {
    return 'forward_auth_settings_form';
  }

  protected function getEditableConfigNames() {
    return ['forward_auth.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('forward_auth.settings');

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => [
        'cookie' => $this->t('Cookie / Drupal session (default)'),
        'token' => $this->t('Shared token header'),
      ],
      '#default_value' => $config->get('mode') ?: 'cookie',
      '#description' => $this->t('If cookie/session is chosen, proxied requests with Drupal session cookies will authenticate. If token is chosen, the proxy must supply a header with the shared secret.'),
    ];

    $form['token_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token header name'),
      '#default_value' => $config->get('token_header') ?: 'X-Forward-Auth-Token',
      '#description' => $this->t('Header name to check when in token mode.'),
    ];

    // BuildForm: load token secret from state.
    $secret = \Drupal::state()->get('forward_auth.token_secret', '');

    $form['token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token secret'),
      '#default_value' => $secret,
      '#description' => $this->t('Shared secret used to validate the token header. Stored in state, not config.'),
    ];

    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    $role_options = [];
    foreach ($roles as $id => $role) {
      $role_options[$id] = $role->label();
    }

    $form['allowed_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed roles'),
      '#options' => $role_options,
      '#default_value' => $config->get('allowed_roles') ?: [],
      '#description' => $this->t('If set, only users with at least one of these roles will be allowed.'),
    ];

    $form['login_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login/redirect path'),
      '#default_value' => $config->get('login_path') ?: '',
      '#description' => $this->t('Optional path to redirect the user to when authentication is required (e.g., /user/login). The module will return a 401 with a Location header set to this path.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Clean up allowed_roles checkboxes to only selected keys.
    $allowed_roles = [];
    if (!empty($values['allowed_roles']) && is_array($values['allowed_roles'])) {
      foreach ($values['allowed_roles'] as $r => $v) {
        if ($v) {
          $allowed_roles[] = $r;
        }
      }
    }

    $this->config('forward_auth.settings')
      ->set('mode', $values['mode'])
      ->set('token_header', $values['token_header'])
      ->set('allowed_roles', $allowed_roles)
      ->set('login_path', $values['login_path'])
      ->save();

    \Drupal::state()->set('forward_auth.token_secret', $values['token_secret']);

    parent::submitForm($form, $form_state);
  }
}