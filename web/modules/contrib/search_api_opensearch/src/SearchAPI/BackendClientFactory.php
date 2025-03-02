<?php

namespace Drupal\search_api_opensearch\SearchAPI;

use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api_opensearch\Analyser\AnalyserManager;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder;
use Drupal\search_api_opensearch\SearchAPI\Query\QueryResultParser;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a factory for creating a backend client.
 *
 * This is needed because the client is dynamically created based on the
 * connector plugin selected.
 */
class BackendClientFactory {

  /**
   * Creates a backend client factory.
   *
   * @param \Drupal\search_api_opensearch\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   *   The query param builder.
   * @param \Drupal\search_api_opensearch\SearchAPI\Query\QueryResultParser $resultParser
   *   The query result parser.
   * @param \Drupal\search_api_opensearch\SearchAPI\DeleteParamBuilder $deleteParamBuilder
   *   The delete param builder.
   * @param \Drupal\search_api_opensearch\SearchAPI\IndexParamBuilder $itemParamBuilder
   *   The index param builder.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\search_api_opensearch\SearchAPI\FieldMapper $fieldParamsBuilder
   *   The field mapper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\search_api_opensearch\Analyser\AnalyserManager $analyserManager
   *   Analyser manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected QueryParamBuilder $queryParamBuilder,
    protected QueryResultParser $resultParser,
    protected DeleteParamBuilder $deleteParamBuilder,
    protected IndexParamBuilder $itemParamBuilder,
    protected FieldsHelperInterface $fieldsHelper,
    protected FieldMapper $fieldParamsBuilder,
    protected LoggerInterface $logger,
    protected AnalyserManager $analyserManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Creates a new OpenSearch Search API client.
   *
   * @param \OpenSearch\Client $client
   *   The OpenSearch client.
   * @param array $settings
   *   THe backend settings.
   *
   * @return \Drupal\search_api_opensearch\SearchAPI\BackendClientInterface
   *   The backend client.
   */
  public function create(Client $client, array $settings): BackendClientInterface {
    return new BackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->deleteParamBuilder,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client,
      $this->analyserManager,
      $this->eventDispatcher,
      $settings,
    );
  }

}
