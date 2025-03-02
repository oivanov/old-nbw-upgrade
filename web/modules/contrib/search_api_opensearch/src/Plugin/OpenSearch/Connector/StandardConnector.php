<?php

namespace Drupal\search_api_opensearch\Plugin\OpenSearch\Connector;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a standard OpenSearch connector.
 *
 * @OpenSearchConnector(
 *   id = "standard",
 *   label = @Translation("Standard"),
 *   description = @Translation("A standard connector without authentication")
 * )
 */
class StandardConnector extends PluginBase implements OpenSearchConnectorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new StandardConnector.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.search_api_opensearch_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return (string) $this->configuration['url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): Client {
    // We only support one host.
    return ClientBuilder::create()
      ->setHosts([$this->configuration['url']])
      ->setSSLVerification((bool) $this->configuration['ssl_verification'])
      ->setLogger($this->logger)
      ->build();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => '',
      'ssl_verification' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('OpenSearch URL'),
      '#description' => $this->t('The URL of your OpenSearch server, e.g. <code>http://127.0.0.1:9200</code> or <code>https://www.example.com:443</code>. The port defaults to <code>9200</code> if not specified. <strong>Do not include a trailing slash.</strong>'),
      '#default_value' => $this->configuration['url'] ?? '',
      '#required' => TRUE,
    ];

    $form['ssl_verification'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('SSL Verification'),
      '#description' => $this->t('Whether to verify the SSL certificate of the OpenSearch server. This should be enabled in production environments.'),
      '#default_value' => $this->configuration['ssl_verification'] ?? '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    if (!UrlHelper::isValid($url)) {
      $form_state->setErrorByName('url', $this->t("Invalid URL"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['url'] = trim($form_state->getValue('url'), '/ ');
    $this->configuration['ssl_verification'] = (bool) $form_state->getValue('ssl_verification');
  }

}
