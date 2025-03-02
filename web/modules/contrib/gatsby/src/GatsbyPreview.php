<?php

namespace Drupal\gatsby;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Defines a class for generating Gatsby based previews.
 */
class GatsbyPreview {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Config Interface for accessing site configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Gatsby entity logger definition.
   *
   * @var \Drupal\gatsby\GatsbyEntityLogger
   */
  protected $gatsbyLogger;

  /**
   * Tracks data changes that should be sent to Gatsby.
   *
   * @var array
   */
  public static array $updateData = [];

  /**
   * Constructs a new GatsbyPreview object.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
    GatsbyEntityLogger $gatsby_logger
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('gatsby');
    $this->gatsbyLogger = $gatsby_logger;
  }

  /**
   * Updates Gatsby Data array.
   *
   * @param string $key
   * @param string $url
   * @param string $preview_path
   */
  public function updateData(string $key, string $url, string $preview_path = ""): void {
    self::$updateData[$key][$url] = [
      'url' => $url,
      'path' => $preview_path,
    ];
  }

  /**
   * Prepares Gatsby Data to send to the preview servers.
   *
   * By preparing the data in a separate step we prevent multiple requests from
   * being sent to the preview servers if multiple Drupal entities are
   * update/created/deleted in a single request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   * @param string $action
   */
  public function gatsbyPreparePreviewData(ContentEntityInterface $entity = NULL, string $action = ''): void {
    $settings = $this->configFactory->get('gatsby.settings');
    $preview_url = $settings->get('preview_callback_url');

    if (empty($action) || !$preview_url) {
      return;
    }

    $this->gatsbyLogger->logEntity($entity, $action, 'preview');
    $this->updateData('preview', $preview_url);
  }

  /**
   * Prepares Gatsby Data to send to the build servers.
   *
   * By preparing the data in a separate step we prevent multiple requests from
   * being sent to the incremental builds servers if multiple Drupal entities
   * are update/created/deleted in a single request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   * @param string $action
   */
  public function gatsbyPrepareBuildData(ContentEntityInterface $entity = NULL, string $action = ''): void {
    $settings = $this->configFactory->get('gatsby.settings');
    $incrementalbuild_url = $settings->get('incrementalbuild_url');

    if (empty($action) || !$incrementalbuild_url) {
      return;
    }

    $this->gatsbyLogger->logEntity($entity, $action);
    $this->updateData('incrementalbuild', $incrementalbuild_url);
  }

  /**
   * Verify the entity is supported for syncing to the Gatsby site.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   If the entity type should be sent to Gatsby Preview.
   */
  public function isSupportedEntity(EntityInterface $entity): bool {
    // Only content entities are supported.
    if (!($entity instanceof ContentEntityInterface)) {
      return FALSE;
    }

    $entity_type = $entity->getEntityTypeId();

    // A list of entity types that are not supported.
    $not_supported = [
      'gatsby_log_entity',
    ];
    if (in_array($entity_type, $not_supported, TRUE)) {
      return FALSE;
    }

    // Get the list of supported entity types.
    $supported_types = $this->configFactory->get('gatsby.settings')->get('supported_entity_types');
    if (empty($supported_types)) {
      return FALSE;
    }

    // Check to see if the entity type is supported.
    return in_array($entity_type, array_values($supported_types), TRUE);
  }

  /**
   * Triggers the refreshing of Gatsby preview and incremental builds.
   */
  public function gatsbyUpdate(): void {
    foreach (self::$updateData as $endpoint_type => $urls) {
      foreach ($urls as $data) {
        $this->triggerRefresh($data['url'], $data['path']);
      }
    }

    // Reset update data to ensure it's only processed once.
    self::$updateData = [];
  }

  /**
   * Triggers Gatsby refresh endpoint.
   *
   * @param string $preview_callback_url
   *   The Gatsby URL to refresh.
   * @param string $path
   *   The path used to trigger the refresh endpoint.
   */
  public function triggerRefresh(string $preview_callback_url, string $path = ''): void {
    // If the URL has a comma it means multiple end points need to be called.
    if (stripos($preview_callback_url, ',')) {
      $urls = array_map('trim', explode(',', $preview_callback_url));

      foreach ($urls as $url) {
        $this->triggerRefresh($url, $path);
      }

      return;
    }

    // All values transmitted to the endpoint.
    $arguments = [
      // The default timeout is 30 seconds, which is a really long time for an
      // API, so time out really quickly.
      'timeout' => 1,
    ];

    // Add optional source plugin header for use on Gatsby Cloud with custom
    // source plugins.
    if (!empty($this->configFactory->get('gatsby.settings')->get('custom_source_plugin'))) {
      $arguments['headers']['x-gatsby-cloud-data-source'] = $this->configFactory->get('gatsby.settings')->get('custom_source_plugin');
    }

    // Trigger the HTTP request.
    try {
      $this->httpClient->post($preview_callback_url . $path, $arguments);
    }
    catch (ConnectException $e) {
      // This is maintained for the legacy callback URL only.
      // Do nothing as no response is returned from the preview server.
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

}
