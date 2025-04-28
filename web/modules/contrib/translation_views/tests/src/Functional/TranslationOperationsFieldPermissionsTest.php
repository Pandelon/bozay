<?php

namespace Drupal\Tests\translation_views\Functional;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\user\Entity\Role;
use Drupal\views\Tests\ViewTestData;

/**
 * Class TranslationOperationsFieldPermissionsTest.
 *
 * @group translation_views
 *
 * @package Drupal\Tests\translation_views\Functional
 */
class TranslationOperationsFieldPermissionsTest extends ViewTestBase {

  /**
   * List of the additional language IDs to be created for the tests.
   *
   * @var array
   */
  private static $langcodes = ['fr', 'de', 'it', 'af', 'sq'];
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'node',
    'translation_views',
    'translation_views_test_views',
  ];
  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';
  /**
   * Testing views ID array.
   *
   * @var array
   */
  public static $testViews = ['test_operations_links'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views, self::$modules);

    $this->drupalLogin($this->rootUser);

    // Set up testing views.
    //ViewTestData::createTestViews(get_class($this), ['translation_views_test_views']);
    try {
      $this->setUpLanguages();
    }
    catch (EntityStorageException $e) {
      dump($e->getMessage());
    }
    // Enable translation for Article nodes.
    $this->enableTranslation('node', 'article');

    // Create testing node.
    $this->drupalGet('node/add/article');
    $this->submitForm([
      'title[0][value]' => $this->randomString(),
    ], 'Save');

    $this->drupalLogout();
  }

  /**
   * Set up languages.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function setUpLanguages() {
    foreach (self::$langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
  }

  /**
   * Change language settings for entity types.
   *
   * @param string $category
   *   Entity category (e.g. node).
   * @param string $subcategory
   *   Entity subcategory (e.g. article).
   */
  private function enableTranslation($category, $subcategory) {
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm([
      "entity_types[$category]"                                                   => 1,
      "settings[$category][$subcategory][translatable]"                           => 1,
      "settings[$category][$subcategory][settings][language][language_alterable]" => 1,
    ], 'Save configuration');
    \Drupal::entityTypeManager()->clearCachedDefinitions();
  }

  /**
   * Translate node all specified languages.
   */
  private function translateNode() {
    $node = Node::load(1);
    foreach (self::$langcodes as $langcode) {
      if (!$node->hasTranslation($langcode)) {
        $node->addTranslation($langcode, ['title' => $this->randomMachineName()])
          ->save();
      }
    }
  }

  /**
   * Add specific set of permissions to the "authenticated" role.
   *
   * @param array $permissions
   *   Permissions array.
   */
  private function addPermissionsForAuthUser(array $permissions = []) {
    if (!empty($permissions)) {
      $role = Role::load(Role::AUTHENTICATED_ID);
      if ($role instanceof Role) {
        $this->grantPermissions($role, $permissions);
      }
    }
  }

  /**
   * Test translation operation create permissions.
   *
   * @throws \Behat\Mink\Exception\ElementTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testTranslationOperationsCreatePermissions() {
    $default_language = \Drupal::languageManager()->getDefaultLanguage();
    $target_language = static::$langcodes[mt_rand(0, 4)];
    $this->assertNotNull($target_language);
    $this->assertNotNull($default_language);

    $userCreate = $this->createUser(['create content translations']);
    $this->drupalLogin($userCreate);

    $this->drupalGet('/translate/content', [
      'query' => [
        'langcode'                    => $default_language->getId(),
        'translation_target_language' => $target_language,
        'translation_outdated'        => 'All',
        'translation_status'          => 'All',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists(
      'css',
      'table > tbody > tr:nth-child(1) .views-field-translation-operations ul li a'
    );

    $this->addPermissionsForAuthUser(['translate any entity']);
    $this->assertTrue($userCreate->hasPermission('translate any entity'));

    $this->drupalGet('/translate/content', [
      'query' => [
        'langcode'                    => $default_language->getId(),
        'translation_target_language' => $target_language,
        'translation_outdated'        => 'All',
        'translation_status'          => 'All',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->elementTextContains(
        'css',
        "table > tbody > tr:nth-child(1) .views-field-translation-operations ul li a",
        'Add'
      );
  }

  /**
   * Test translation operation update permissions.
   *
   * @throws \Behat\Mink\Exception\ElementTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testTranslationOperationsUpdatePermissions() {
    $this->translateNode();
    $userUpdate = $this->createUser(['update content translations']);
    $this->drupalLogin($userUpdate);
    // Check without translation permission.
    $this->drupalGet('/test_operations_links', [
      'query' => [
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()
      ->elementTextNotContains(
        'css',
        ".view-content > div:nth-child(1) .views-field-translation-operations",
        'Edit'
      );
    // Check with translation permission.
    $this->addPermissionsForAuthUser(['translate article node']);
    $this->assertTrue($userUpdate->hasPermission('translate article node'));
    $this->drupalGet('/test_operations_links', [
      'query' => [
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $base_edit_op_selector = '.view-content > div:nth-child(1) .views-field-translation-operations ul li';
    $this->assertSession()
      ->elementTextContains(
        'css',
        "$base_edit_op_selector a[href$='/edit/fr']",
        'Edit'
      );
    $this->click("$base_edit_op_selector a[href$='/edit/fr']");
    $this->assertSession()->addressEquals('/fr/node/1/translations/edit/fr');
    // Check with edit permission.
    $this->addPermissionsForAuthUser(['edit any article content']);
    $this->assertTrue($userUpdate->hasPermission('edit any article content'));
    $this->drupalGet('/test_operations_links', [
      'query' => [
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()
      ->elementTextContains(
        'css',
        "$base_edit_op_selector a[href$='/edit']",
        'Edit'
      );
    $this->click("$base_edit_op_selector a[href$='/edit']");
    $this->assertSession()->addressEquals('/fr/node/1/edit');
  }

  /**
   * Test translation operation delete permissions.
   *
   * @throws \Behat\Mink\Exception\ElementTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testTranslationOperationsDeletePermissions() {
    $this->translateNode();
    $userDelete = $this->createUser(['delete content translations']);
    $this->drupalLogin($userDelete);
    // Check without translation permission.
    $this->drupalGet('/test_operations_links', [
      'query' => [
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()
      ->elementTextNotContains(
        'css',
        ".view-content > div:nth-child(1) .views-field-translation-operations",
        'Delete'
      );
    // Check with translation permission.
    $this->addPermissionsForAuthUser(['translate article node']);
    $this->assertTrue($userDelete->hasPermission('translate article node'));
    $this->drupalGet('/test_operations_links', [
      'query' => [
        'translation_target_language' => 'fr',
      ],
    ]);
    $base_delete_op_selector = '.view-content > div:nth-child(1) .views-field-translation-operations ul li';
    $this->assertSession()
      ->elementTextContains(
        'css',
        "$base_delete_op_selector a[href$='/delete/fr']",
        'Delete'
      );
    $this->click("$base_delete_op_selector a[href$='/delete/fr']");
    $this->assertSession()->addressEquals('/fr/node/1/translations/delete/fr');
    // Check with edit permission.
    $this->addPermissionsForAuthUser(['delete any article content']);
    $this->assertTrue($userDelete->hasPermission('delete any article content'));
    $this->drupalGet('/test_operations_links', [
      'query' => [
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()
      ->elementTextContains(
        'css',
        "$base_delete_op_selector a[href$='/delete']",
        'Delete'
      );
    $this->click("$base_delete_op_selector a[href$='/delete']");
    $this->assertSession()->addressEquals('/fr/node/1/delete');
  }

}
