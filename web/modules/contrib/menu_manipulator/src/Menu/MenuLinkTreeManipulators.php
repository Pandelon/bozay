<?php

namespace Drupal\menu_manipulator\Menu;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Menu\InaccessibleMenuLink;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\Router;
use Drupal\views\Plugin\Menu\ViewsMenuLink;

/**
 * Provides a menu link tree manipulators.
 *
 * This class provides a menu link tree manipulators to:
 * - filter by current language.
 *
 * @see menu_manipulator_get_multilingual_menu() to see example of use.
 */
class MenuLinkTreeManipulators {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected $entityRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A router instance.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected $router;

  /**
   * Constructs a \Drupal\Core\Menu\DefaultMenuLinkTreeManipulators object.
   *
   * @param \Drupal\Core\Entity\EntityRepository $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\Router $router
   *   The router instance.
   */
  public function __construct(
    EntityRepository $entity_repository,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManager $language_manager,
    ConfigFactoryInterface $config_factory,
    Router $router
  ) {
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
    $this->router = $router;
  }

  /**
   * Filter a menu tree by current language.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function filterTreeByCurrentLanguage(array $tree) {
    foreach ($tree as $key => $element) {
      if (!$element->link instanceof MenuLinkBase) {
        continue;
      }

      $access = $this->checkLinkAccess($element->link);

      if (!$access) {
        // Deny access and hide children items.
        $tree[$key]->link = new InaccessibleMenuLink($tree[$key]->link);
        $tree[$key]->access = AccessResult::forbidden();
        $tree[$key]->subtree = [];
      }

      // Filter children items recursively.
      if ($element->hasChildren && !empty($tree[$key]->subtree)) {
        $element->subtree = $this->filterTreeByCurrentLanguage($element->subtree);
      }
    }

    return $tree;
  }

  /**
   * Filter a list of menu items by current language.
   *
   * @param array $items
   *   Generally, the $variables['items'] in menu preprocess.
   *   Passed by reference.
   */
  public function filterItemsByCurrentLanguage(array &$items) {
    foreach (Element::children($items) as $i) {
      if (($link = $items[$i]['original_link'] ?? NULL) instanceof MenuLinkBase) {
        if (!$this->checkLinkAccess($link)) {
          // Deny access and hide children items.
          unset($items[$i]);
        }
      }

      // Filter children items recursively.
      $children = $items[$i]['below'] ?? [];
      if (!empty($children)) {
        $this->filterItemsByCurrentLanguage($items[$i]['below']);
      }
    }
  }

  /**
   * Checking the link access.
   *
   * @param \Drupal\Core\Menu\MenuLinkBase $link
   *   `The Menu Link Content entity.
   */
  public function checkLinkAccess(MenuLinkBase $link) {
    $langcode = $this->getLinkLanguage($link);

    $not_applicable_langcodes = [
      LanguageInterface::LANGCODE_NOT_APPLICABLE,
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ];

    // Allow unspecified languages.
    if (in_array($langcode, $not_applicable_langcodes)) {
      return TRUE;
    }

    $current_langcode = $this->getCurrentLangcode();

    // Check if referenced entity can be used. Yes by default.
    $options = $link->getOptions() ?: [];
    $settings = $this->configFactory->get('menu_manipulator.settings');
    $language_use_entity_default = $settings->get('preprocess_menus_language_use_entity') ?? 1;
    $language_use_entity = $options['language_use_entity'] ?? $language_use_entity_default;
    if ($language_use_entity) {
      $entity = $this->getLinkEntity($link);
      // Allow if targeted entity is translated, no matter menu item's language.
      if ($entity instanceof ContentEntityInterface && method_exists($entity, 'hasTranslation')) {
        return $entity->hasTranslation($current_langcode);
      }
    }

    // Allow by the menu item's language itself.
    return $current_langcode == $langcode;
  }

  /**
   * Force the MenuLinkBase to tell us its language code.
   *
   * @param \Drupal\Core\Menu\MenuLinkBase $link
   *   The Menu Link item - usually an menu_link_content entity but it can be a
   *   config from Views or something else we don't even know about yet.
   *
   * @return string
   *   The menu Link language ID or a default value.
   *
   * @todo Handle config links such as those added by Views (e.g. get language).
   */
  protected function getLinkLanguage(MenuLinkBase $link) {
    $metadata = $link->getMetaData();
    $entity_id = $metadata['entity_id'] ?? NULL;

    if ($entity_id && $this->entityTypeManager->hasHandler('menu_link_content', 'storage')) {
      if ($loaded_link = $this->entityTypeManager->getStorage('menu_link_content')->load($entity_id)) {
        if ($loaded_lang_link = $this->entityRepository->getTranslationFromContext($loaded_link)) {
          return $loaded_lang_link->language()->getId();
        }
      }
    }

    if ($link instanceof ViewsMenuLink) {
      $langcode = $this->getCurrentLangcode();
      if ($this->viewHasTranslation($langcode, $metadata['view_id'], $metadata['display_id'])) {
        return $langcode;
      }
    }

    return LanguageInterface::LANGCODE_NOT_APPLICABLE;
  }

  /**
   * Get targeted entity for a given MenuLinkBase.
   *
   * @param \Drupal\Core\Menu\MenuLinkBase $link
   *   The Menu Link Content entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null|bool
   *   FALSE if Url is unrouted. Otherwise, an entity object variant or NULL.
   */
  protected function getLinkEntity(MenuLinkBase $link) {
    // Skip if <nolink> or empty.
    $uri = $link->getUrlObject()->toString();
    if (!UrlHelper::isValid($uri)) {
      return FALSE;
    }

    try {
      // Get entity info from route.
      // @see https://www.drupal.org/project/menu_manipulator/issues/3251675
      // @see https://www.computerminds.co.uk/drupal-code/get-entity-route
      $route_match = $this->router->match($uri);
      if ($route = $route_match['_route_object'] ?? NULL) {
        foreach ($route->getOption('parameters') ?? [] as $name => $options) {
          if (isset($options['type']) && strpos($options['type'], 'entity:') === 0) {
            $entity = $route_match[$name] ?? NULL;
            if ($entity instanceof EntityInterface) {
              return $this->entityRepository->getActive($entity->getEntityTypeId(), $entity->id());
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      /* Fail silently */
    }

    return FALSE;
  }

  /**
   * Check if a View has translation.
   *
   * @param string $langcode
   *   A given language ID.
   * @param string $view_id
   *   A given View ID.
   * @param string $display_id
   *   (optional) A given display ID (default: `default`).
   *
   * @return bool
   *   Wether or not the View has translation for the given langcode.
   */
  protected function viewHasTranslation(string $langcode, string $view_id, string $display_id = 'default') {
    // Get translated configuration.
    $view_config_id = 'views.view.' . $view_id;
    $view_config_translated = $this->configFactory->get($view_config_id)->get();
    $view_langcode = $view_config_translated['langcode'] ?? NULL;

    // No more logic if View's language is the current language.
    if ($view_langcode == $langcode) {
      return $view_langcode;
    }

    // Load original configuration to compare differences between languages.
    // If differences found between configs, it means View is translated.
    // Returns the current_language to mark this View link as translated.
    $language = $this->languageManager->getLanguage($view_langcode);
    $this->languageManager->setConfigOverrideLanguage($language);
    $view_config_original = $this->configFactory->get($view_config_id)->get();

    // Reset current language to avoid impacts on other parts of the code.
    $language = $this->languageManager->getLanguage($langcode);
    $this->languageManager->setConfigOverrideLanguage($language);

    $view_diff = json_encode($view_config_translated) === json_encode($view_config_original);
    if ($view_diff) {
      return $langcode;
    }

    // No diff at the main config level. Check the current display.
    $display_config_translated = $view_config_translated['display'][$display_id] ?? [];
    $display_config_original = $view_config_original['display'][$display_id] ?? [];
    $display_diff = json_encode($display_config_translated) === json_encode($display_config_original);
    if ($display_diff) {
      return $langcode;
    }

    return FALSE;
  }

  /**
   * Gets the current language ID.
   * Get
   * @return string
   *   The langcode.
   */
  protected function getCurrentLangcode(): string {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
  }
}
