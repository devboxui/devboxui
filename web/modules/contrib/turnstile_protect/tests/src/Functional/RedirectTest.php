<?php

namespace Drupal\Tests\turnstile_protect\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests redirection from /node/1 to /challenge.
 *
 * @group turnstile_protect
 */
class RedirectTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'captcha',
    'turnstile',
    'turnstile_protect',
    'key',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    // Always pass the turnstile
    // https://developers.cloudflare.com/turnstile/troubleshooting/testing/
    $this->config('key.key.turnstile')
      ->set('id', 'turnstile')
      ->set('label', 'turnstile')
      ->set('key_type', 'authentication_multivalue')
      ->set('key_provider', 'config')
      ->set('key_provider_settings', [
        'key_value' => '{"site_key": "1x00000000000000000000AA", "secret_key": "1x0000000000000000000000000000000AA"}',
      ])
      ->set('key_input', 'textarea_field')
      ->save();

    $this->config('turnstile.settings')
      ->set('keys', 'turnstile')
      ->set('turnstile_src', 'https://challenges.cloudflare.com/turnstile/v0/api.js')
      ->save();

    $config = $this->config('turnstile_protect.settings')
      ->set('routes', ["entity.node.canonical"])
      ->set('bots', [])
      ->set('rate_limit', TRUE)
      ->set('window', 86400)
      ->set('threshold', 1)
      ->set('history_enabled', FALSE)
      ->save();

    $this->assertEquals("entity.node.canonical", $config->get('routes')[0], 'Routes configuration is set to node.entity.canonical.');
  }

  /**
   * Tests redirection from node to /challenge.
   */
  public function testNodeRedirect() {
    // Create a node we'll try accessing.
    $node = $this->drupalCreateNode(['type' => 'page']);
    $nodeUrl = $node->toUrl()->toString();

    // Since turnstile_protect.settings["threshold"] = 1
    // we should be able to view the node once.
    $this->drupalGet($nodeUrl);
    $url = $this->getSession()->getCurrentUrl();
    $components = parse_url($url);
    $this->assertEquals($nodeUrl, $components['path'], 'User is not redirected to /challenge.');

    // We should be challenged now on the second look.
    $this->drupalGet($nodeUrl);
    $url = $this->getSession()->getCurrentUrl();
    $components = parse_url($url);
    $this->assertEquals('/challenge', $components['path'], 'User is not redirected to /challenge.');

    sleep(15);

    // We should be redirected to the node after the turnstile passed
    // which it always will in this test with the turnstile site/secret keys
    // set to their always pass test.
    $url = $this->getSession()->getCurrentUrl();
    $components = parse_url($url);
    $this->assertEquals($nodeUrl, $components['path'], 'User is redirected back to node.');
  }

}
