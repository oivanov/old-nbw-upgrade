<?php

namespace Drupal\download_statistics;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\download_statistics\DownloadStatisticsStorageInterface;

/**
 * Configure file downloads statistics settings for this site.
 */
class DownloadStatisticsSettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The storage for download statistics.
   *
   * @var \Drupal\download_statistics\DownloadStatisticsStorageInterface
   */
  protected $statisticsStorage;

  /**
   * Constructs \Drupal\download_statistics\DownloadStatisticsSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\download_statistics\DownloadStatisticsStorageInterface $statistics_storage
   *   The storage for statistics.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, DownloadStatisticsStorageInterface $statistics_storage) {
    parent::__construct($config_factory);

    $this->moduleHandler = $module_handler;
    $this->statisticsStorage = $statistics_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('download_statistics.storage.file')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'download_statistics_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['download_statistics.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('download_statistics.settings');

    // File downloads counter settings.
    $form['downloads'] = [
      '#type' => 'details',
      '#title' => t('File downloads counter settings'),
      '#open' => TRUE,
    ];
    $form['downloads']['statistics_count_file_downloads'] = [
      '#type' => 'checkbox',
      '#title' => t('Count file downloads'),
      '#default_value' => $config->get('count_file_downloads'),
      '#description' => t('Increment a counter each time a private file with proper FieldFormatter downloaded. NOTE: changing this value will result in total cache flush.'),
    ];

    $form['downloads']['delete_downloads'] = [
      '#type' => 'checkbox',
      '#title' => t('Clear Downloads Statistics'),
      '#default_value' => 1,
      '#description' => t('Delete all file downloads data upon saving this form.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $old_value = \Drupal::config('download_statistics.settings')->get('count_file_downloads');
    $new_value = $form_state->getValue('statistics_count_file_downloads');
    $this->config('download_statistics.settings')
      ->set('count_file_downloads', $new_value)
      ->save();

    // The popular file downloads statistics block is dependent on these
    // settings, so clear the block plugin definitions cache.
    if ($this->moduleHandler->moduleExists('block')) {
      \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
    }
    if ($form_state->getValue('delete_downloads')) {
      $this->statisticsStorage->deleteAllDownloads();
    }

    parent::submitForm($form, $form_state);
    if ($old_value != $new_value) {
      // We need to clear the caches so all the hooks and alters properly register;
      drupal_flush_all_caches();
    }
  }

}
