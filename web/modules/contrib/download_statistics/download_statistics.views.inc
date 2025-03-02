<?php

/**
 * @file
 * Provide views data for statistics.module.
 */

/**
 * Implements hook_views_data().
 */
function download_statistics_views_data() {
  if (!\Drupal::config('download_statistics.settings')->get('count_file_downloads')) {
    return [];
  }
  $data['download_statistics']['table']['group'] = t('File Download Statistics');

  $data['download_statistics']['table']['join'] = [
    'file_managed' => [
      'left_field' => 'fid',
      'field' => 'fid',
    ],
  ];

  $data['download_statistics']['table']['base'] = array(
    'field' => 'fid',
    'title' => t('Download Statistics'),
    'help' => t('Download Statistics of managed file downloads.'),
  );

  $data['download_statistics']['fid'] = array(
    'title' => t('File'),
    'help' => t('The downloaded file information.'),
    'relationship' => [
      'base' => 'file_managed',
      'base field' => 'fid',
      'label' => t('File'),
      'id' => 'standard',
    ],
    'field' => [
      'id' => 'download_statistics_numeric',
      'click sortable' => TRUE,
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  );

  $data['download_statistics']['uid'] = array(
    'title' => t('User'),
    'help' => t('The user who downloaded the file.'),
    'relationship' => [
      'base' => 'users_field_data',
      'base field' => 'uid',
      'label' => t('User'),
      'id' => 'standard',
    ],
    'field' => [
      'id' => 'download_statistics_numeric',
      'click sortable' => TRUE,
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  );

  $data['download_statistics']['totalcount'] = [
    'title' => t('Total file downloads'),
    'help' => t('The total number of times the file has been downloaded.'),
    'field' => [
      'id' => 'download_statistics_numeric',
      'click sortable' => TRUE,
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['download_statistics']['daycount'] = [
    'title' => t('File downloads today'),
    'help' => t('The total number of times the file has been downloaded today.'),
    'field' => [
      'id' => 'download_statistics_numeric',
      'click sortable' => TRUE,
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['download_statistics']['timestamp'] = [
    'title' => t('Most recent file download'),
    'help' => t('The most recent time the file has been downloaded.'),
    'field' => [
      'id' => 'download_statistics_timestamp',
      'click sortable' => TRUE,
    ],
    'filter' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  return $data;
}
