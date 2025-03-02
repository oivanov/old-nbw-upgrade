<?php

namespace Drupal\message_banner\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The message banner settings form.
 */
class MessageBannerSettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'message_banner.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'message_banner.settings';
  }

  /**
   * Constructs a message banner settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state storage service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, StateInterface $state) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('message_banner.settings');

    $form['banner_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable banner'),
      '#default_value' => $config->get('banner_enabled') ?: FALSE,
    ];

    $form['message_banner'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Message banner settings'),
      '#states' => [
        'visible' => [
          ':input[name="banner_enabled"]' => ['checked' => TRUE],
        ]
      ],
    ];

    $form['message_banner']['banner_enabled_on_admin_routes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable banner on admin routes'),
      '#description' => $this->t('If not checked, the banner will only appear on non-admin routes.'),
      '#default_value' => $config->get('banner_enabled_on_admin_routes') ?: FALSE,
      '#states' => [
        'visible' => [
          ':input[name="banner_enabled"]' => ['checked' => TRUE],
        ]
      ],
    ];

    $form['message_banner']['banner_show_again_minutes'] = [
      '#type' => 'number',
      '#step' => 1,
      '#title' => $this->t('Minutes before showing banner after dismissing it'),
      '#default_value' => $config->get('banner_show_again_minutes'),
      '#description' => $this->t('The number of minutes to elapse after dimissing the banner before showing it again (0 to disable).'),
    ];

    $form['message_banner']['banner_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Banner color'),
      '#description' => $this->t('Choose the background color for the banner.'),
      '#options' => $this->getBannerColors(),
      '#default_value' => $config->get('banner_color') ?: NULL,
      '#states' => [
        'required' => [
          ':input[name="banner_enabled"]' => ['checked' => TRUE],
        ]
      ],
    ];

    $form['message_banner']['banner_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#description' => $this->t('This message will be shown to every site visitor, so make sure it does not contain any sensitive information!'),
      '#default_value' => $config->get('banner_text.value') ?: '',
      '#format' => $config->get('banner_text.format') ?: 'basic_html',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('message_banner.settings')
      ->set('banner_enabled', $form_state->getValue('banner_enabled'))
      ->set('banner_enabled_on_admin_routes', $form_state->getValue('banner_enabled_on_admin_routes'))
      ->set('banner_color', $form_state->getValue('banner_color'))
      ->set('banner_text', $form_state->getValue('banner_text'))
      ->set('banner_show_again_minutes', $form_state->getValue('banner_show_again_minutes'))
      ->save();

    // Save the save time as a state value, so that config is not affected.
    $this->state->set('banner_saved', time());

    return parent::submitForm($form, $form_state);
  }

  /**
   * Gets the available colors for the message banner.
   *
   * @see hook_message_banner_colors_alter()
   *
   * @return array
   *   An array of background colors.
   */
  protected function getBannerColors(): array {
    $colors = [
      'default--red' => $this->t('Red'),
      'default--amber' => $this->t('Amber'),
      'default--green' => $this->t('Green'),
      'default--black' => $this->t('Black'),
      'default--gray' => $this->t('Gray'),
      'default--white' => $this->t('White'),
    ];

    // Allow other developers to add extra colors, such as brand colors.
    $this->moduleHandler->alter('message_banner_colors', $colors);
    return $colors;
  }

}
