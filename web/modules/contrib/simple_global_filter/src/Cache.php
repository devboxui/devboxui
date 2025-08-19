<?php

namespace Drupal\simple_global_filter;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Cache
 *
 * Stores the cache in the user's session.
 */
class Cache {

  /**
   * The storing mode, currently supported session or cookie.
   *
   * @var string
   */
  protected $storing_mode;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The Cache constructor.
   */
  public function __construct(RequestStack $request_stack) {
    // Set default value.
    $this->storing_mode = 'session';
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * Write the supplied data to the user session.
   *
   * @param string $bin
   *   Unique id, eg a string prefixed by the module name.
   * @param $data
   *   Use NULL to delete the bin; it may be refilled at any time
   */
  public function set($data = NULL, $bin = 'default', $storing_mode = 'session') {
    if (!isset($bin) || !is_string($bin)) {
      return;
    }

    if ($this->storing_mode == 'session') {
      $_SESSION['simple_global_filter.cache'][$bin] = $data;
    }
    elseif ($this->storing_mode == 'cookie') {
      $serialized_data = ($data == NULL) ? NULL : json_encode($data);
      $cookie_domain = ini_get('session.cookie_domain');
      setcookie("Drupal_simple_global_filter_" . $bin, $serialized_data, 0, '/',
        $cookie_domain, NULL, FALSE);
    }

  }

  /**
   * Read data from the user session, given its bin id.
   *
   * @param string $bin,
   *   unique id eg a string prefixed by the module name.
   */
  public function get($bin = 'default', $storing_mode = 'session') {
    $data = NULL;

    if (!isset($bin)) {
      return $data;
    }

    if ($this->storing_mode == 'session') {
      $data = !empty($_SESSION['simple_global_filter.cache'][$bin]) ?
       $_SESSION['simple_global_filter.cache'][$bin] : NULL;
    }
    elseif ($this->storing_mode == 'cookie') {
      $cookie = $this->request->cookies->has("Drupal_simple_global_filter_" . $bin);
      if ($cookie) {
        $data = json_decode($this->request->cookies->get("Drupal_simple_global_filter_" . $bin), TRUE);
      }
    }

    return $data;
  }

  /**
   * Sets the storing mode for this cache.
   *
   * @param string $mode
   *
   * @return \Drupal\simple_global_filter\Cache
   */
  public function setStoringMode($mode) {
    switch ($mode) {
      case 'session':
      case 'cookie':
        $this->storing_mode = $mode;
        break;

      default:
        // @todo Add here a warning message in the log (?)
    }
    return $this;
  }

}
