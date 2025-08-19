<?php

namespace Drupal\alter_route_title\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;

/**
 * Class ConfigurationForm.
 *
 * Configuration form for Alter Route Title.
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * Drupal\Core\Routing\RouteProviderInterface definition.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routerRouteProvider;

  /**
   * Drupal\Core\Extension\ModuleExtensionList definition.
   *
   * @var Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\Database\Connection definition.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->routerRouteProvider = $container->get('router.route_provider');
    $instance->moduleHandler = $container->get('extension.list.module');
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'alter_route_title.configuration',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('alter_route_title.configuration');
    $routetable = $config->get('routetable');
    // Prepare saved configuration.
    $saved = [];
    if (!empty($routetable)) {
      foreach ($routetable as $item) {
        $saved[$item['route_alter_title']['route_hidden']] = $item['route_alter_title']['alter_title'];
      }
    }
    $list = $this->moduleHandler->getList();
    $excludeCoreModules = [
      'action',
      'aggregator',
      'automated_cron',
      'ban',
      'basic_auth',
      'big_pipe',
      'block',
      'block_content',
      'book',
      'breakpoint',
      'ckeditor',
      'ckeditor5',
      'color',
      'comment',
      'config',
      'config_translation',
      'contact',
      'content_moderation',
      'content_translation',
      'contextual',
      'datetime',
      'datetime_range',
      'dblog',
      'dynamic_page_cache',
      'editor',
      'entity_reference',
      'field',
      'field_layout',
      'field_ui',
      'file',
      'filter',
      'forum',
      'hal',
      'help',
      'help_topics',
      'history',
      'image',
      'inline_form_errors',
      'jsonapi',
      'language',
      'layout_builder',
      'layout_discovery',
      'link',
      'locale',
      'media',
      'media_library',
      'menu_link_content',
      'menu_ui',
      'migrate',
      'migrate_drupal',
      'migrate_drupal_multilingual',
      'migrate_drupal_ui',
      'node',
      'options',
      'page_cache',
      'path',
      'path_alias',
      'quickedit',
      'rdf',
      'responsive_image',
      'rest',
      'search',
      'serialization',
      'settings_tray',
      'shortcut',
      'simpletest',
      'statistics',
      'syslog',
      'system',
      'taxonomy',
      'telephone',
      'text',
      'toolbar',
      'tour',
      'tracker',
      'update',
      'user',
      'views',
      'views_ui',
      'workflows',
      'workspaces',
    ];

    foreach ($list as $ext) {
      if ($ext->getType() == 'module' && $ext->status == 1) {
        if (in_array($ext->getName(), $excludeCoreModules)) {
          continue;
        }
        $query = $this->database->query("SELECT name FROM {router} WHERE name LIKE :name", [":name" => $ext->getName() . '%']);
        $output = $query->fetchCol(0);
        if (!empty($output)) {
          $results[$ext->getName()] = $output;
        }
      }
    }

    // Loop through routes and add to sitemap.
    $table = [];
    foreach ($results as $module => $result) {
      foreach ($result as $routeName) {
        $route = $this->routerRouteProvider->getRouteByName($routeName);
        $table[$routeName]['title'] = $route->getDefault('_title');
        $table[$routeName]['path'] = $route->getPath();
        $table[$routeName]['module'] = $module;
      }
    }

    $form['routetable'] = [
      '#type' => 'table',
      '#caption' => $this->t('Alter Route Title: Configuraiton'),
      '#header' => [
        '#',
        $this->t('Module'),
        $this->t('Route Name'),
        $this->t('Defined Title'),
        $this->t('Path'),
        $this->t('Alter Title'),
      ],
      '#sticky' => TRUE,
    ];
    $i = 0;
    foreach ($table as $route => $item) {
      $form['routetable'][$i]['sl'] = [
        '#type' => 'markup',
        '#title' => $this->t('#'),
        '#title_display' => 'invisible',
        '#markup' => $i + 1,
      ];
      $form['routetable'][$i]['module'] = [
        '#type' => 'markup',
        '#title' => $this->t('Module'),
        '#title_display' => 'invisible',
        '#markup' => $item['module'],
      ];
      $form['routetable'][$i]['route'] = [
        '#type' => 'markup',
        '#title' => $this->t('Route Name'),
        '#title_display' => 'invisible',
        '#markup' => $route,
      ];
      $form['routetable'][$i]['title'] = [
        '#type' => 'markup',
        '#title' => $this->t('Defined Title'),
        '#title_display' => 'invisible',
        '#markup' => $item['title'],
      ];
      $form['routetable'][$i]['path'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="alter-route-title-path"><span customtitle="{{ path }}" class="tooltip">Hover me!</span></div>',
        '#context' => [
          'path' => Xss::filter($item['path']),
        ],
      ];
      $form['routetable'][$i]['route_alter_title']['alter_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alter Title'),
        '#title_display' => 'invisible',
        '#size' => 100,
        '#maxlength' => 128,
        '#default_value' => isset($saved[$route]) ? Xss::filter($saved[$route]) : '',
        '#element_validate' => [
          [static::class, 'validation'],
        ],
      ];
      $form['routetable'][$i]['route_alter_title']['route_hidden'] = [
        '#type' => 'hidden',
        '#value' => $route,
      ];
      $i++;
    }
    $form['routetable'][]['footer'] = [
      '#plain_text' => $this->t('Note: This module will replace route _title property, if you want dynamic title you can use default _title_callback attributed, this module will not override the _title_callback property.'),
      '#wrapper_attributes' => [
        'colspan' => 6,
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'alter_route_title/alter-route-title.global';
    return parent::buildForm($form, $form_state);
  }

  /**
   * Validate.
   */
  public static function validation($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if (preg_match('/[^a-z0-9 _]+/i', $value)) {
      $form_state->setError($element, t("Invalid character found."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('alter_route_title.configuration')
      ->set('routetable', $form_state->getValue('routetable'))
      ->save();

    // Rebuilding the route cache.
    \Drupal::service("router.builder")->rebuild();
  }

}
