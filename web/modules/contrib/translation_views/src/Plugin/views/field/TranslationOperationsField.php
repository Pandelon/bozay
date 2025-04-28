<?php

namespace Drupal\translation_views\Plugin\views\field;

use Drupal\content_translation\ContentTranslationManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\translation_views\TranslationViewsTargetLanguage as TargetLanguage;
use Drupal\views\Plugin\views\field\EntityOperations;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders translation operations links.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("translation_views_operations")
 */
class TranslationOperationsField extends EntityOperations {
  use TargetLanguage;

  /**
   * Current user account object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * Flag to indicate if translators_content module exists.
   *
   * @var bool
   */
  protected $translatorsModuleExists;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    EntityRepositoryInterface $entity_repository,
    AccountProxyInterface $account
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $entity_repository);
    $this->currentUser             = $account;
    $this->entityTypeManager       = $entity_type_manager;
    $this->translatorsModuleExists = \Drupal::moduleHandler()->moduleExists('translators_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity.repository'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Build operation links.
   */
  public function render(ResultRow $values) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity          = $this->getEntity($values);
    $langcode_key    = $this->buildSourceEntityLangcodeKey($entity);
    $source_langcode = $values->{$langcode_key};
    $operations      = $this->getTranslationOperations($entity, $source_langcode);

    if ($this->options['destination']) {
      foreach ($operations as &$operation) {
        if (!isset($operation['query'])) {
          $operation['query'] = [];
        }
        $operation['query'] += $this->getDestinationArray();
      }
    }
    $build = [
      '#type'  => 'operations',
      '#links' => $operations,
    ];
    $build['#cache']['contexts'][] = 'url.query_args:target_language';

    return $build;
  }

  /**
   * Build value key.
   *
   * Value key based on base table,
   * and system name of langcode key (it might be differ then just 'langcode'),
   * usually table alias is [entity_type]_field_data_[langcode_key].
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Used to extract entity type info from entity.
   *
   * @return string
   *   The value key.
   */
  protected function buildSourceEntityLangcodeKey(ContentEntityInterface $entity) {
    return implode('_', [
      $this->view->storage->get('base_table'),
      $entity->getEntityType()->getKey('langcode'),
    ]);
  }

  /**
   * Operation links manager.
   *
   * Decide which links we should generate:
   * based on user permissions,
   * and entity state (has translation, is default, etc.).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity to get context for decision.
   * @param string $source_langcode
   *   The langcode of the row.
   *
   * @return array
   *   Operation links' render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getTranslationOperations(ContentEntityInterface $entity, $source_langcode) {
    $target_langcode = $this->getTargetLangcode()
      ? $this->getTargetLangcode()
      : $source_langcode;

    // Load correct translation and revision.
    if ($entity->hasTranslation($target_langcode)) {
      $entity = $entity->getTranslation($target_langcode);
    }
    $entity_type_id = $entity->getEntityTypeId();
    $use_latest_revisions = $entity->getEntityType()->isRevisionable() && ContentTranslationManager::isPendingRevisionSupportEnabled($entity_type_id, $entity->bundle());
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($use_latest_revisions) {
      $entity = $storage->load($entity->id());
      $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $target_langcode);
      if ($latest_revision_id) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_revision */
        $latest_revision = $storage->loadRevision($latest_revision_id);
        // Make sure we do not list removed translations, i.e. translations
        // that have been part of a default revision but no longer are.
        if (!$latest_revision->wasDefaultRevision() || $entity->hasTranslation($target_langcode)) {
          $entity = $latest_revision;
        }
      }
    }

    // Build translation operation links.
    /* @var \Drupal\content_translation\ContentTranslationHandlerInterface $handler */
    $handler = $this->getEntityTypeManager()
      ->getHandler($entity->getEntityTypeId(), 'translation');
    $target_language = $this->languageManager->getLanguage($target_langcode);
    $options = ['language' => $target_language];
    $is_default_language = $entity->getUntranslated()->language()->getId() === $target_langcode ? TRUE : FALSE;
    $links = [];
    if ($entity->hasTranslation($target_langcode)) {
      // Build edit translation links.
      if ($entity->access('update')
        && $entity->getEntityType()->hasLinkTemplate('edit-form')) {
        $links += [
          'edit' => [
            'title'    => $this->t('Edit'),
            'url'      => $entity->toUrl('edit-form'),
            'language' => $target_language,
          ],
        ];
      }
      elseif (!$is_default_language) {
        if ($this->translatorsModuleExists && $handler->getTranslationAccess($entity, 'update', $target_langcode)->isAllowed()) {
          $links += [
            'edit' => [
              'title'    => $this->t('Edit'),
              'url'      => $entity->toUrl('drupal:content-translation-edit', $options)
                ->setRouteParameter('language', $target_langcode),
            ],
          ];
        }
        else {
          if (!$this->translatorsModuleExists && $handler->getTranslationAccess($entity, 'update')->isAllowed()) {
            $links += [
              'edit' => [
                'title'    => $this->t('Edit'),
                'url'      => $entity->toUrl('drupal:content-translation-edit', $options)
                  ->setRouteParameter('language', $target_langcode),
              ],
            ];
          }
        }
      }
      // Build delete translation links.
      if ($entity->access('delete')
      && $entity->getEntityType()->hasLinkTemplate('delete-form')) {
        $links += [
          'delete' => [
            'title'    => $this->t('Delete'),
            'url'      => $entity->toUrl('delete-form'),
            'language' => $target_language,
          ],
        ];
      }
      elseif (!$is_default_language && \Drupal::service('content_translation.delete_access')->checkAccess($entity)) {
        if ($this->translatorsModuleExists && $handler->getTranslationAccess($entity, 'delete', $target_langcode)->isAllowed()) {
          $links += [
            'delete' => [
              'title'    => $this->t('Delete'),
              'url'      => $entity->toUrl('drupal:content-translation-delete', $options)
                ->setRouteParameter('language', $target_langcode),
            ],
          ];
        }
        else {
          if (!$this->translatorsModuleExists && $handler->getTranslationAccess($entity, 'delete')->isAllowed()) {
            $links += [
              'delete' => [
                'title'    => $this->t('Delete'),
                'url'      => $entity->toUrl('drupal:content-translation-delete', $options)
                  ->setRouteParameter('language', $target_langcode),
              ],
            ];
          }
        }
      }
    }
    // Build add link.
    elseif (!$entity->hasTranslation($target_langcode) && $entity->isTranslatable()) {
      $route_name = "entity.$entity_type_id.content_translation_add";
      $add_url = Url::fromRoute($route_name, [
        'source'        => $source_langcode,
        'target'        => $target_langcode,
        $entity_type_id => $entity->id(),
      ]);
      if ($this->translatorsModuleExists
        && $handler->getTranslationAccess($entity, 'create', $source_langcode, $target_langcode)->isAllowed()
      ) {
        $links += [
          'add' => [
            'url'      => $add_url,
            'language' => $target_language,
            'title'    => $this->t('Add'),
          ],
        ];
      }
      elseif (!$this->translatorsModuleExists
        && $handler->getTranslationAccess($entity, 'create')->isAllowed()
      ) {
        $links += [
          'add' => [
            'url'      => $add_url,
            'language' => $target_language,
            'title'    => $this->t('Add'),
          ],
        ];
      }
    }
    return $links;
  }

}
