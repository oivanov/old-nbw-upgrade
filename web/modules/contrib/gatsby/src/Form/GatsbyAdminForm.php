<?php

namespace Drupal\gatsby\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gatsby\PathMapping;

/**
 * Defines a config form to store Gatsby configuration.
 */
class GatsbyAdminForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Drupal\gatsby\PathMapping definition.
   *
   * @var \Drupal\gatsby\PathMapping
   */
  protected $pathMapping;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\gatsby\PathMapping $pathMapping
   *   The path mapping.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler, PathMapping $pathMapping) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->pathMapping = $pathMapping;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('gatsby.path_mapping')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gatsby.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gatsby_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gatsby.settings');

    $form['site_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site settings'),
      '#desription' => $this->t('In a Gatsby Cloud account these details may be found on the "Site Settings" section of the dashboard.'),
    ];
    $form['site_settings']['server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gatsby Server URL'),
      '#description' => $this->t('The URL to the Gatsby server (with port number if needed). Separate multiple values with a comma.<br />For Gatsby Cloud accounts this will be a URL in the format https://example12345.gatsbyjs.io.'),
      '#default_value' => $config->get('server_url'),
      '#maxlength' => 250,
      '#weight' => 0,
    ];
    $form['site_settings']['preview_callback_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preview Webhook URL(s)'),
      '#description' => $this->t('The URL to the Gatsby preview build webhook (if running locally it is likely "localhost:8000/__refresh"). Separate multiple values with a comma. The full node data packet will not be transmitted.'),
      '#default_value' => $config->get('preview_callback_url'),
      '#maxlength' => 250,
      '#weight' => 0,
    ];
    $form['site_settings']['incrementalbuild_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Build Webhook URL(s)'),
      '#description' => $this->t('The Callback URL to trigger the Gatsby Build. Multiple build server URLS can be separated by commas. Note: Incremental builds are currently only supported with JSON:API and gatsby-source-drupal.'),
      '#default_value' => $config->get('incrementalbuild_url'),
      '#maxlength' => 250,
      '#weight' => 1,
    ];
    // @todo Add validation to remove a trailing slash if one is found.
    $form['site_settings']['contentsync_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Sync URL (Gatsby 4)'),
      '#description' => $this->t('With Gatsby v4 the Content Sync URL provides an alternative solution for previewing content changes. Do not include a trailing slash.'),
      '#default_value' => $config->get('contentsync_url'),
      '#maxlength' => 250,
      '#weight' => 1,
    ];
    $form['content_advanced'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content / path mapping'),
    ];
    $form['content_advanced']['path_mapping'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Preview Server Path Mapping'),
      '#description' => $this->t('If you do any path manipulation in your Gatsby site you may need to map the preview iframe and preview button to this correct path. For instance, you may have a /home path in Drupal that maps to / on your Gatsby site. Enter the Drupal path on the left (starting with a "/") and the Gatsby path on the right (starting with a "/") separated by a "|" character. For example: "/home|/". Enter one path mapping per line.'),
      '#default_value' => $config->get('path_mapping'),
    ];
    $form['data_selection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Data selection'),
    ];
    $form['data_selection']['build_published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only trigger builds for published content'),
      '#description' => $this->t('Depending on your content workflow, you may only want builds to be triggered for published content. By checking this box only published content will trigger a build. This means additional entities such as Media or Files will not trigger a rebuild until the content it is attached to is published. The downside is that this will only allow content entities to trigger a rebuild.'),
      '#default_value' => $config->get('build_published') !== NULL ? $config->get('build_published') : TRUE,
      '#weight' => 2,
    ];
    $form['preview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preview'),
    ];
    $form['preview']['supported_entity_types'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getContentEntityTypes(),
      '#default_value' => $config->get('supported_entity_types') ?: [],
      '#title' => $this->t('Entity types to send to Gatsby Preview and Build Server'),
      '#description' => $this->t('What entities should be sent to the Gatsby Preview and Build Server?'),
      '#weight' => 2,
    ];
    $form['advanced'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced settings'),
    ];
    $form['advanced']['log_json'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create log messages of JSON objects sent to the Gatsby preview server'),
      '#description' => $this->t('By checking this box, log messages will be created every time a POST request is sent to the Gatsby preview server. Useful for debugging the JSON object data that gets sent to Gatsby.<br /><strong>Note: this may have security implications, esure that only trusted users can access the logged messages. Should not be used on a production environment.</strong>. Log messages for preview requests will not contain the full data packet and will instead only contain the URL that was contacted.'),
      '#default_value' => $config->get('log_json'),
      '#weight' => 3,
    ];
    $form['advanced']['prevent_selfreferenced_entities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not store data for entity types that are self referenced'),
      '#description' => $this->t('By checking this box, data will not be stored in the fastbuilds log for entity types that are referenced from the same entity type. This can cause very large amounts of data to be stored in some situations. As an example, if you have an Article content type that references other Articles, when you save the page all of the data for every referenced Article is stored by default. Most of the time this is not necessary as those referenced articles did not actually change when the main article page was saved.'),
      '#default_value' => $config->get('prevent_selfreferenced_entities'),
      '#weight' => 4,
    ];
    $form['advanced']['custom_source_plugin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gatsby Cloud: Custom source plugin'),
      '#description' => $this->t('On Gatsby Cloud this module only works with gatsby-source-drupal by default. If you are building a custom plugin or using gatsby-source-graphql (not recommended), you can enter the full source plugin name here (such as "gatsby-source-graphql"). This is experimental and not officially supported.'),
      '#default_value' => $config->get('custom_source_plugin'),
      '#maxlength' => 250,
      '#weight' => 5,
    ];

    $form['fastbuilds'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fastbuilds'),
    ];
    $form['fastbuilds']['delete_log_entities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Old Gatsby Fastbuilds Log Entities'),
      '#description' => $this->t('Enable this to automatically clean up old
        Fastbuilds log entities on cron runs.'),
      '#default_value' => $config->get('delete_log_entities'),
      '#weight' => 2,
    ];
    $form['fastbuilds']['log_expiration'] = [
      '#type' => 'select',
      '#title' => $this->t('Fastbuilds Log Expiration'),
      '#description' => $this->t('How long do you want to store the Fastbuild
        log entities (after this time they will be automatically deleted and a
        full Gatsby rebuild will be required)?'),
      // Expiration values are stored in seconds.
      '#options' => [
        '86400' => $this->t('1 day'),
        '259200' => $this->t('3 days'),
        '604800' => $this->t('7 days'),
        '1209600' => $this->t('14 days'),
        '2592000' => $this->t('30 days'),
        '5184000' => $this->t('60 days'),
        '7776000' => $this->t('90 days'),
      ],
      '#default_value' => $config->get('log_expiration'),
      '#weight' => 3,
      '#states' => [
        'visible' => [
          ':input[name="delete_log_entities"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['fastbuilds']['number_items_delete'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Log Entities to Delete'),
      '#description' => $this->t('How many Fastbuild log entities should be
        deleted on each cron run?'),
      '#default_value' => $config->get('number_items_delete') ?? 500,
      '#min' => 1,
      '#weight' => 4,
      '#states' => [
        'visible' => [
          ':input[name="delete_log_entities"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $this->validateCsvUrl('server_url', $this->t('Invalid Gatsby preview server URL.'), $form_state);
    $this->validateCsvUrl('preview_callback_url', $this->t('Invalid Gatsby preview callback URL.'), $form_state);

    $path_mapping = $form_state->getValue('path_mapping');
    try {
      $map = PathMapping::parsePathMapping($path_mapping);
      if (strlen(trim($path_mapping)) > 0 && count($map) === 0) {
        // Unable to parse anything meaningful from the path mapping.
        $form_state->setErrorByName('path_mapping', $this->t('Invalid preview server path mapping.'));
      }
    }
    catch (\Error $e) {
      // Parsing the path mapping caused a PHP Error.
      $form_state->setErrorByName('path_mapping', $this->t('Invalid preview server path mapping.'));
    }

    $incremental_build_url = $form_state->getValue('incrementalbuild_url');
    if (strlen($incremental_build_url) > 0 and !filter_var($incremental_build_url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('incrementalbuild_url', $this->t('Invalid incremental build URL.'));
    }

    $contentsync_url = $form_state->getValue('contentsync_url');
    if (strlen($contentsync_url) > 0 and !filter_var($contentsync_url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('contentsync_url', $this->t('Invalid ContentSync URL.'));
    }

    if (!is_numeric($form_state->getValue('number_items_delete'))) {
      $form_state->setErrorByName('number_items_delete', $this->t('Number of log entities to delete must be a number.'));
    }
  }

  /**
   * Validates a URL that may be multi-value via commas.
   *
   * @param string $field_name
   *   Field name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $error
   *   Error message to show if invalid.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  protected function validateCsvUrl(string $field_name, TranslatableMarkup $error, FormStateInterface $form_state) : void {
    $urls = array_map('trim', explode(',', $form_state->getValue($field_name)));
    foreach ($urls as $url) {
      if (strlen($url) > 0 and !filter_var($url, FILTER_VALIDATE_URL)) {
        $form_state->setErrorByName($field_name, $error);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('gatsby.settings')
      ->set('server_url', $form_state->getValue('server_url'))
      ->set('preview_callback_url', $form_state->getValue('preview_callback_url'))
      ->set('incrementalbuild_url', $form_state->getValue('incrementalbuild_url'))
      ->set('contentsync_url', $form_state->getValue('contentsync_url'))
      ->set('build_published', $form_state->getValue('build_published'))
      ->set('supported_entity_types', array_values(array_filter($form_state->getValue('supported_entity_types'))))
      ->set('path_mapping', $form_state->getValue('path_mapping'))
      ->set('log_json', $form_state->getValue('log_json'))
      ->set('prevent_selfreferenced_entities', $form_state->getValue('prevent_selfreferenced_entities'))
      ->set('custom_source_plugin', $form_state->getValue('custom_source_plugin'))
      ->set('publish_private_files', $form_state->getValue('publish_private_files'))
      ->set('delete_log_entities', $form_state->getValue('delete_log_entities'))
      ->set('log_expiration', $form_state->getValue('log_expiration'))
      ->set('number_items_delete', $form_state->getValue('number_items_delete'))
      ->save();
  }

  /**
   * Gets a list of all the defined content entities in the system.
   *
   * @return array
   *   An array of content entities definitions.
   */
  private function getContentEntityTypes() {
    $content_entity_types = [];
    $allEntityTypes = $this->entityTypeManager->getDefinitions();

    foreach ($allEntityTypes as $entity_type_id => $entity_type) {
      // Add all content entity types but not the gatsby log entity.
      if ($entity_type instanceof ContentEntityTypeInterface &&
          $entity_type_id !== 'gatsby_log_entity') {

        $content_entity_types[$entity_type_id] = $entity_type->getLabel();
      }
    }
    return $content_entity_types;
  }

}
