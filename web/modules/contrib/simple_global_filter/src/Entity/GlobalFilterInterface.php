<?php

namespace Drupal\simple_global_filter\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Global filter entities.
 */
interface GlobalFilterInterface extends ConfigEntityInterface {

  /**
   * Get the vocabulary name.
   *
   * @return string
   *   The vocabulary name.
   */
  public function getVocabulary();

  /**
   * Sets internal vocabulary name.
   *
   * @param string $vocabulary_name
   *   The vocabulary name.
   */
  public function setVocabulary($vocabulary_name);

  /**
   * Returns the default value, used when not any value has been set.
   */
  public function getDefaultValue();

  /**
   * Returns the field storing the alias information of the global filter value.
   */
  public function getAliasField();

  /**
   * Returns TRUE if the global filter will show a 'display all items' option.
   */
  public function displayAllOption();

  /**
   * Returns the label for the 'display all items' option.
   */
  public function displayAllLabel();

  /**
   * Returns an array containing the options available for the global filter.
   */
  public function getOptions();

  /**
   * Returns TRUE if the global filter should be displayed in the URL.
   */
  public function displayInUrl();

  /**
   * Returns the storing mode configured.
   *
   * Session or cookie are currently the supported values.
   */
  public function getStoringMode();

}
