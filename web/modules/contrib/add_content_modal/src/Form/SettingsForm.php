<?php

namespace Drupal\add_content_modal\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form.
 */
class SettingsForm extends ConfigFormBase {

  const SETTINGSNAME = 'add_content_modal.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_content_modal';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SETTINGSNAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $contentTypes = $this->entityTypeManager->getStorage('node_type')
      ->loadMultiple();
    $config = $this->config(self::SETTINGSNAME);

    $form['type_of_dialog'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of dialog'),
      '#options' => [
        'dialog' => $this->t('Dialog (off-canvas)'),
        'modal' => $this->t('Modal'),
      ],
      '#default_value' => $config->get('type_of_dialog'),
    ];

    $form['width_modal_node_add'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width modal node add'),
      '#description' => $this->t('Specify the width for the modal.'),
      '#default_value' => $config->get('width_modal_node_add') ?? '80%',
    ];

    $optionsContentTypes = [];
    foreach ($contentTypes as $key => $nodeType) {
      $optionsContentTypes[$key] = $key;
    }

    $form['node_add_content_types_modal'] = [
      '#type' => 'select',
      '#options' => $optionsContentTypes,
      '#title' => $this->t('Content types modal'),
      '#description' => $this->t('Specify the content types that you want to be opened in a popup'),
      '#default_value' => $config->get('node_add_content_types_modal') ?? NULL,
      '#multiple' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory->getEditable(self::SETTINGSNAME);

    $config->set('type_of_dialog', $form_state->getValue('type_of_dialog', 'dialog'));
    $config->set('width_modal_node_add', $form_state->getValue('width_modal_node_add', '80%'));
    $config->set('node_add_content_types_modal', $form_state->getValue('node_add_content_types_modal', NULL));

    $config->save();

    // Flushing caches so our alters are taken into account.
    drupal_flush_all_caches();
  }

}
