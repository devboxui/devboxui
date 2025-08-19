<?php

namespace Drupal\Tests\turnstile_protect\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests basic functionality of the turnstile_protect module.
 *
 * @group turnstile_protect
 */
class TurnstileProtectTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'turnstile_protect',
    'system',
  ];

  /**
   * Tests if the module is enabled.
   */
  public function testModuleEnabled() {
    $this->assertTrue(
      $this->container->get('module_handler')->moduleExists('turnstile_protect'),
      'The turnstile_protect module is enabled.'
    );
  }

  /**
   * Tests basic configuration or service.
   */
  public function testBasicFunctionality() {
    $config = $this->config('turnstile_protect.settings');
    $this->assertNotEmpty(
      $config,
      'turnstile_protect settings configuration is available.'
    );
  }

}
