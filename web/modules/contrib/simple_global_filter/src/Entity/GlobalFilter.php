<?php

namespace Drupal\simple_global_filter\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Global filter entity.
 *
 * @ConfigEntityType(
 *   id = "global_filter",
 *   label = @Translation("Global filter"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simple_global_filter\GlobalFilterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\simple_global_filter\Form\GlobalFilterForm",
 *       "edit" = "Drupal\simple_global_filter\Form\GlobalFilterForm",
 *       "delete" = "Drupal\simple_global_filter\Form\GlobalFilterDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\simple_global_filter\GlobalFilterHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "global_filter",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "vocabulary_name",
 *     "default_value",
 *     "alias_field",
 *     "display_in_url",
 *     "display_all_option",
 *     "display_all_label",
 *     "storing_mode"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/global_filter/{global_filter}",
 *     "add-form" = "/admin/structure/global_filter/add",
 *     "edit-form" = "/admin/structure/global_filter/{global_filter}/edit",
 *     "delete-form" = "/admin/structure/global_filter/{global_filter}/delete",
 *     "collection" = "/admin/structure/global_filter"
 *   }
 * )
 */
class GlobalFilter extends ConfigEntityBase implements GlobalFilterInterface {

  const ALL_ITEMS = '__all__';

  /**
   * The Global filter ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Global filter label.
   *
   * @var string
   */
  protected $label;

  /**
   * The alias field.
   *
   * It is the field that stores the information about the alias.
   *
   * @var string
   */
  protected $alias_field;

  /**
   * Flag indicating if display the filter in the URL, or not.
   *
   * @var bool
   */
  protected $display_in_url;

  /**
   * The global filter related taxonomy vocabulary.
   *
   * @var string
   */
  protected $vocabulary_name;

  /**
   * If there has not been selected any value yet, return this value.
   *
   * @var mixed
   */
  protected $default_value;

  /**
   * If the global filter is configured to display a 'display all items' option.
   *
   * @var bool
   */
  protected $display_all_option;

  /**
   * Label for the 'display all items' option.
   *
   * @var string
   */
  protected $display_all_label;

  /**
   * The storing mode being used (session or cookie)
   *
   * @var string
   */
  protected $storing_mode;

  /**
   * {@inheritdoc}
   */
  public function getVocabulary() {
    return $this->vocabulary_name;
  }

  /**
   * {@inheritdoc}
   */
  public function setVocabulary($vocabulary_name) {
    $this->vocabulary_name = $vocabulary_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue() {
    return $this->default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAliasField() {
    return $this->alias_field;
  }

  /**
   * {@inheritdoc}
   */
  public function displayAllOption() {
    return $this->display_all_option;
  }

  /**
   * {@inheritdoc}
   */
  public function displayAllLabel() {
    return $this->display_all_label;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions($vocabulary_name = NULL) {
    if (!$vocabulary_name) {
      $vocabulary_name = $this->getVocabulary();
    }

    if ($this->displayAllOption()) {
      $options[self::ALL_ITEMS] = $this->displayAllLabel();
    }
    else {
      $options = [];
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary_name, 0, NULL, TRUE) as $term) {
      if ($term->hasTranslation($langcode)) {
        $term = $term->getTranslation($langcode);
      }
      $options[$term->id()] = $term->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function displayInUrl() {
    return $this->display_in_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoringMode() {
    return $this->storing_mode;
  }

}
