<?php

namespace Drupal\download_statistics\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\GenericFileFormatter;

/**
 * Plugin implementation of the 'counted_downloads_file' formatter.
 *
 * @FieldFormatter(
 *  id = "counted_downloads_file",
 *  label = @Translation("File with Download Statistics recorded"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class DownloadStatisticsFileFormatter extends GenericFileFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    if (\Drupal::config('download_statistics.settings')->get('count_file_downloads')) {
      foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
        $elements[$delta]['#file']->countDownloads = TRUE;
      }
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if (\Drupal::config('download_statistics.settings')->get('count_file_downloads')) {
      return $field_definition->getFieldStorageDefinition()
          ->getSetting('target_type') === 'file';
    }
    return FALSE;
  }

}
