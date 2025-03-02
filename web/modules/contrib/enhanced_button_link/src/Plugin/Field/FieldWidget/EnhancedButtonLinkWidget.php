<?php

namespace Drupal\enhanced_button_link\Plugin\Field\FieldWidget;

use Drupal\Core\Config\Config;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\enhanced_button_link\EnhancedButtonLinkInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Enhanced Button Link' widget.
 *
 * @FieldWidget(
 *   id = "enhanced_button_link_widget",
 *   label = @Translation("Enhanced Button Link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class EnhancedButtonLinkWidget extends LinkWidget {

  /**
   * Contains the enhanced_button_link.settings configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $enhancedButtonLinkConfigs;

  /**
   * Constructs a EnhancedButtonLinkWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\Config $enhanced_button_link_configs
   *   The enhanced_button_link.settings configuration factory object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, Config $enhanced_button_link_configs) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->enhancedButtonLinkConfigs = $enhanced_button_link_configs;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory')->get('enhanced_button_link.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $button_link_styles = $this->enhancedButtonLinkConfigs->get('button_link_styles');

    $override_type = (bool) $this->enhancedButtonLinkConfigs->get('override_type');
    $override_size = (bool) $this->enhancedButtonLinkConfigs->get('override_size');
    $override_status = (bool) $this->enhancedButtonLinkConfigs->get('override_status');
    $override_target = (bool) $this->enhancedButtonLinkConfigs->get('override_target');

    $element['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Button Link Options'),
      // Hide whole options area if all of options are not checked to be
      // overridden.
      '#access' => ($override_type || $override_size || $override_status || $override_target),
    ];

    $element['options']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#default_value' => $items[$delta]->options['type'] ?? EnhancedButtonLinkInterface::TYPE_DEFAULT,
      '#description' => $this->t('Select the type of the button.'),
      '#options' => [
        EnhancedButtonLinkInterface::TYPE_DEFAULT => $this->t('Default'),
      ] + $button_link_styles,
      '#access' => $override_type,
    ];

    $element['options']['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Size'),
      '#default_value' => $items[$delta]->options['size'] ?? EnhancedButtonLinkInterface::SIZE_DEFAULT,
      '#description' => $this->t('Select the size of the button.'),
      '#options' => [
        EnhancedButtonLinkInterface::SIZE_DEFAULT => $this->t('Default'),
        EnhancedButtonLinkInterface::SIZE_NORMAL => $this->t('Normal'),
        EnhancedButtonLinkInterface::SIZE_BIG => $this->t('Big'),
        EnhancedButtonLinkInterface::SIZE_SMALL => $this->t('Small'),
      ],
      '#access' => $override_size,
    ];

    $element['options']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#default_value' => $items[$delta]->options['status'] ?? EnhancedButtonLinkInterface::STATUS_DEFAULT,
      '#description' => $this->t('Select the status of the button.'),
      '#options' => [
        EnhancedButtonLinkInterface::STATUS_DEFAULT => $this->t('Default'),
        EnhancedButtonLinkInterface::STATUS_ENABLED => $this->t('Enabled'),
        EnhancedButtonLinkInterface::STATUS_DISABLED => $this->t('Disabled'),
      ],
      '#access' => $override_status,
    ];

    $element['options']['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Target'),
      '#default_value' => $items[$delta]->options['target'] ?? EnhancedButtonLinkInterface::TARGET_DEFAULT,
      '#description' => $this->t('Select the link target.'),
      '#options' => [
        EnhancedButtonLinkInterface::TARGET_DEFAULT => $this->t('Default'),
        EnhancedButtonLinkInterface::TARGET_SAME_WINDOW => $this->t('Same Window'),
        EnhancedButtonLinkInterface::TARGET_NEW_TAB => $this->t('New Tab'),
      ],
      '#access' => $override_target,
    ];

    return $element;
  }

}
