<?php

namespace Drupal\enhanced_button_link;

use Drupal\Component\Utility\Html;

/**
 * The helper class to handle configuration for the field widget and formatter.
 */
class EnhancedButtonLinkHelper {

  /**
   * Parse style options array from string value.
   *
   * @param string $value
   *   String containing key|value per line of available button link styles
   *   options.
   *
   * @return array
   *   Array which contains configs.
   *
   * @throws EnhancedButtonLinkParseException
   */
  public static function parseConfigsFromValue($value) {
    $lines = explode("\n", $value);
    $configs = [];
    foreach ($lines as $line) {
      // If line is empty skip it.
      if (empty(trim($line))) {
        continue;
      }

      // Parse and check style options pairs.
      [$raw_css_class, $raw_name] = explode('|', trim($line), 2);
      $raw_css_class = trim($raw_css_class);
      $raw_name = trim($raw_name);

      if (empty($raw_css_class) || empty($raw_name)) {
        throw new EnhancedButtonLinkParseException('Error parsing pair', EnhancedButtonLinkInterface::EXC_CODE_PARSE_PAIR);
      }

      // Check for style option CSS class name.
      $css_class = Html::cleanCssIdentifier($raw_css_class);
      if ($css_class !== $raw_css_class) {
        throw new EnhancedButtonLinkParseException('Error parsing style option css class name', EnhancedButtonLinkInterface::EXC_CODE_PARSE_CSS_CLASS);
      }

      // Check for style option name.
      $name = Html::escape($raw_name);
      if ($name !== $raw_name) {
        throw new EnhancedButtonLinkParseException('Error parsing style option name', EnhancedButtonLinkInterface::EXC_CODE_PARSE_NAME);
      }

      $configs[$css_class] = $name;
    }

    return $configs;
  }

  /**
   * Make value from configs array to load it to config form.
   *
   * @param array $configs
   *   Array which contains configs.
   *
   * @return string
   *   String containing key|value per line of available button link styles.
   */
  public static function makeValueFromConfigs(array $configs) {
    $value = '';
    foreach ($configs as $css_class => $name) {
      $value .= "{$css_class}|{$name}\n";
    }

    return $value;
  }

}
