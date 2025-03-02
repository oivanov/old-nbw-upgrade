<?php

namespace Drupal\view_mode_page\PathProcessor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\view_mode_page\Repository\ViewmodepagePatternRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for processing path.
 *
 * @package Drupal\view_mode_page\PathProcessor
 */
class DynamicPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The viewmodepage pattern repository.
   *
   * @var \Drupal\view_mode_page\Repository\ViewmodepagePatternRepository
   */
  protected $patternRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * DynamicPathProcessor constructor.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager interface.
   * @param \Drupal\view_mode_page\Repository\ViewmodepagePatternRepository $pattern_repository
   *   The viewmodepage pattern repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager, ViewmodepagePatternRepository $pattern_repository, LanguageManagerInterface $language_manager) {
    $this->aliasManager      = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->patternRepository = $pattern_repository;
    $this->languageManager   = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path_alias.manager'),
      $container->get('entity_type.manager'),
      $container->get('view_mode_page.path_processor'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // DR - REMINDER: Empty cache after altering this code!
    $patterns = $this->patternRepository->findAll();

    /** @var \Drupal\view_mode_page\ViewmodepagePatternInterface $pattern */
    foreach ($patterns as $pattern) {
      if (preg_match($pattern->getPatternRegex(), $path, $matchesArray)) {
        $entityAlias = $matchesArray[1];
        $entityUri = $this->aliasManager->getPathByAlias('/' . $entityAlias);

        $url = Url::fromUri('internal:' . $entityUri);
        if ($url->isRouted()) {
          $routeParams = $url->getRouteParameters();
          if ($entityType = key($routeParams)) {
            $entityId = $routeParams[$entityType];
          }
        }

        if (!empty($entityType) && !empty($entityId) && $entityType == $pattern->getAliasType()->getDerivativeId()) {
          $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);

          if ($entity instanceof EntityInterface) {

            // Check for a translation of the entity and load that instead if
            // one is found.
            $language_interface = $this->languageManager->getCurrentLanguage();
            if ($entity instanceof TranslatableInterface && $entity->hasTranslation($language_interface->getId())) {
              $entity = $entity->getTranslation($language_interface->getId());
            }
            if ($pattern->applies($entity)) {
              $newPath = '/view_mode_page/' . $pattern->getViewMode() . '/' . $entityType . '/' . $entityId;
              return $newPath;
            }
          }
        }
      }
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $prefix = '/view_mode_page/';
    if (substr($path, 0, strlen($prefix)) === $prefix) {
      $path_minus_prefix = substr($path, strlen($prefix));
      $path_minus_prefix_parts = explode('/', $path_minus_prefix);
      if (count($path_minus_prefix_parts) === 3) {
        [$view_mode, $entityType, $entityId] = $path_minus_prefix_parts;
        $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
        if ($entity) {
          // Check if the target language is set.
          if (isset($options['language'])) {
            $target_language = $options['language'];
          }
          else {
            $target_language = $this->languageManager->getCurrentLanguage();
          }
          // Check for a translation of the entity and load that instead if
          // one is found.
          if ($target_language instanceof LanguageInterface && $entity instanceof TranslatableInterface && $entity->hasTranslation($target_language->getId())) {
            $entity = $entity->getTranslation($target_language->getId());
          }
          /** @var \Drupal\view_mode_page\ViewmodepagePatternInterface[] $patterns */
          $patterns = $this->entityTypeManager->getStorage('view_mode_page_pattern')->loadByProperties(['view_mode' => $view_mode]);
          foreach ($patterns as $pattern) {
            if ($pattern->applies($entity)) {
              $url = $entity->toUrl();
              $url_alias = $this->aliasManager->getAliasByPath("/" . $url->getInternalPath(), $target_language->getId());
              $path = str_replace('/%', $url_alias, $pattern->getPattern());
              break;
            }
          }
        }
      }
    }

    return $path;
  }

}
