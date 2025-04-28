<?php

namespace Drupal\Tests\translation_views\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\translators_content\Functional\TranslatorsContentTestsTrait;

/**
 * Class TranslatorsContentIntegrationTest.
 *
 * @package Drupal\Tests\translation_views\Functional
 *
 * @group translation_views
 * @requires module translators
 */
class TranslatorsContentIntegrationTest extends BrowserTestBase {
  use TranslatorsContentTestsTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['translation_views_translators_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected $adminTheme = 'stark';

  /**
   * Translators skills service.
   *
   * @var \Drupal\translators\Services\TranslatorSkills
   */
  protected $translatorSkills;
  /**
   * User registered skills.
   *
   * @var array
   */
  protected static $registeredSkills = ['en', 'fr'];
  /**
   * User unregistered skills.
   *
   * @var array
   */
  protected static $unregisteredSkills = ['de', 'sq'];
  /**
   * Default language ID.
   *
   * @var string
   */
  protected $defaultLanguage = 'en';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // By default Drupal 9.4 uses Claro theme in "standard" install profile.
    // The submit buttons in this theme located outside of "form" element.
    // The above is true for "/admin/structure/views/nojs/handler" like paths.
    // So to avoid failure on submitting form,
    // within testTranslatorsLanguageFilterInView,
    // we need to change default admin theme.
    \Drupal::service('theme_installer')->install([$this->adminTheme]);
    \Drupal::configFactory()
      ->getEditable('system.theme')
      ->set('admin', $this->adminTheme)
      ->save();

    $this->drupalLogin($this->rootUser);
    $this->translatorSkills = $this->container->get('translators.skills');
    $this->createLanguages(['fr', 'de', 'sq']);
    $this->enableTranslation('node', 'article');
    $this->drupalLogout();
  }

  /**
   * Simply check that all required modules have been installed.
   */
  public function testDependencyInstallation() {
    $this->assertTrue($this->container->get('module_handler')
      ->moduleExists('translators'));
    $this->assertTrue($this->container->get('module_handler')
      ->moduleExists('translators_content'));
    $this->assertTrue($this->container->has('translators.skills'));
  }

  /**
   * Test Content Translators integration for target language filter.
   */
  public function testTranslatorsLanguageFilterInView() {
    $this->drupalLogin($this->rootUser);
    $this->addSkill(['en', 'fr']);
    $node = $this->createTestNode();
    $this->addAllNodeTranslations($node);

    // Check that all languages are available as target language.
    $this->drupalGet('/test-translators-content-filter');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertOptionCount('translation_target_language', 4);
    $this->assertOptionAvailable('translation_target_language', 'en');
    $this->assertOptionAvailable('translation_target_language', 'fr');
    $this->assertOptionAvailable('translation_target_language', 'de');
    $this->assertOptionAvailable('translation_target_language', 'sq');

    // Limit target languages to translation skills.
    $this->drupalGet('/admin/structure/views/nojs/handler/test_translators_content_integration/page_1/filter/translation_target_language');
    // Check for the default state of the options.
    $this->assertSession()->checkboxNotChecked('options[limit]');
    $this->assertSession()->checkboxNotChecked('options[column][source]');
    $this->assertSession()->checkboxChecked('options[column][target]');
    // Update options.
    $this->submitForm([
      'options[limit]'          => TRUE,
      'options[column][source]' => TRUE,
      'options[column][target]' => TRUE,
    ], 'Apply');

    $this->drupalGet('/admin/structure/views/view/test_translators_content_integration/edit/page_1');
    $this->submitForm([], 'Save');

    // Check that all languages are available as target language.
    $this->drupalGet('/test-translators-content-filter');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertOptionCount('translation_target_language', 2);
    $this->assertOptionAvailable('translation_target_language', 'en');
    $this->assertOptionAvailable('translation_target_language', 'fr');
    $this->assertOptionNotAvailable('translation_target_language', 'de');
    $this->assertOptionNotAvailable('translation_target_language', 'sq');

    // Check results without any registered translation skills.
    $this->removeSkills();
    $this->drupalGet('/test-translators-content-filter');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->elementNotExists('css', 'table > tbody > tr:nth-child(1)');
    $this->assertOptionCount('translation_target_language', 1);
    $this->assertOptionAvailable('translation_target_language', 'All');

  }

  /**
   * Test Content Translators integration for target language filter.
   */
  public function testTranslatorsOperationLinks() {
    $userTranslatorsLimited = $this->createUser([
      'translators_content create content translations',
      'translators_content update content translations',
      'translators_content delete content translations',
      'translate any entity',
    ]);
    Node::create([
      'type' => 'article',
      'title' => "English node",
      'langcode' => 'en',
    ])->save();

    $this->drupalLogin($userTranslatorsLimited);
    $this->addSkill(['en', 'fr']);
    // Check Add translation.
    $this->drupalGet('/test-translators-content-filter', [
      'query' => [
        'langcode' => 'en',
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->elementTextContains(
        'css',
        "table > tbody > tr:nth-child(1) .views-field-translation-operations ul li a",
        'Add'
      );
    $this->drupalGet('/test-translators-content-filter', [
      'query' => [
        'langcode' => 'en',
        'translation_target_language' => 'de',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->elementTextNotContains(
        'css',
        "table > tbody > tr:nth-child(1) .views-field-translation-operations",
        'Add'
      );
    // Check Edit translation for registered language.
    Node::load(1)->addTranslation('fr', ['title' => 'French translation '])
      ->save();
    $this->drupalGet('/test-translators-content-filter', [
      'query' => [
        'langcode' => 'en',
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $edit_op_selector = 'table > tbody > tr:nth-child(1) .views-field-translation-operations ul li a[href$="/edit"]';
    $this->assertSession()
      ->elementTextContains(
        'css',
        $edit_op_selector,
        'Edit'
      );
    $this->click($edit_op_selector);
    // @todo: Fix when translators permission handeling is fixed.
    $this->assertSession()->addressEquals('fr/node/1/edit');

    // Check Delete translation for registered language.
    $this->drupalGet('/test-translators-content-filter', [
      'query' => [
        'langcode' => 'en',
        'translation_target_language' => 'fr',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $delete_op_selector = 'table > tbody > tr:nth-child(1) .views-field-translation-operations ul li a[href$="/delete"]';
    $this->assertSession()
      ->elementTextContains(
        'css',
        $delete_op_selector,
        'Delete'
      );
    $this->click($delete_op_selector);
    // @todo: Fix when translators permission handeling is fixed.
    $this->assertSession()->addressEquals('fr/node/1/delete');

    // Check translation operations for not registered languages.
    $this->drupalGet('/test-translators-content-filter', [
      'query' => [
        'langcode' => 'en',
        'translation_target_language' => 'de',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()
      ->elementTextNotContains(
        'css',
        "table > tbody > tr:nth-child(1) .views-field-translation-operations",
        'Edit'
      );
    $this->assertSession()
      ->elementTextNotContains(
        'css',
        "table > tbody > tr:nth-child(1) .views-field-translation-operations",
        'Delete'
      );
  }

}
