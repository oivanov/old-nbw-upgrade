<?php

namespace Drupal\download_statistics\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\download_statistics\Controller\DownloadStatisticsFileController;

/**
 * Listens to the dynamic route events.
 */
class FileDownloadAlterRouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FileDownloadAlterRouteSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if (!\Drupal::config('download_statistics.settings')->get('count_file_downloads')) {
      return;
    }
    // Change controller for /system/files/{filepath}
    // and /system/files/{scheme} routes.
    if ($route = $collection->get('system.private_file_download')) {
      $route->setDefault('_controller', DownloadStatisticsFileController::class . '::download');
    }
    if ($route = $collection->get('system.files')) {
      $route->setDefault('_controller', DownloadStatisticsFileController::class . '::download');
    }
  }

}
