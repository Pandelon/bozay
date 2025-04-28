<?php

namespace Drupal\Tests\translation_views\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\workflows\Entity\Workflow;

/**
 * Class ContentModerationIntegration.
 *
 * @package Drupal\Tests\translation_views\Functional
 *
 * @group translation_views
 */
class ContentModerationIntegration extends ViewTestBase {

  /**
   * List of the additional language IDs to be created for the tests.
   *
   * @var array
   */
  private static $langcodes = ['fr', 'de'];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['translation_views_test_views'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['content_moderation_integration_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp($import_test_views = TRUE, $modules = ['translation_views_test_views']): void {
    // Inherit set up from the parent class.
    parent::setUp($import_test_views, $modules);
    // Login as a root user.
    $this->drupalLogin($this->rootUser);
    // Create additional languages.
    foreach (self::$langcodes as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    // Enable translation for article nodes.
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm([
      "entity_types[node]"                                              => 1,
      "settings[node][article][translatable]"                           => 1,
      "settings[node][article][settings][language][language_alterable]" => 1,
    ], 'Save configuration');
    // Flush definitions caches.
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    // Enable moderation state for article nodes.
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $workflow->save();
    // Logout.
    $this->drupalLogout();
  }

  /**
   * Test view's field "Translation Moderation State".
   */
  public function testTranslationModerationFieldViews() {
    // Login as a root user.
    $this->drupalLogin($this->rootUser);
    // Create node for test.
    $node = $this->createNode(['type' => 'article']);
    // Create translations
    foreach (self::$langcodes as $langcode) {
      $node->addTranslation($langcode, [
        'title' => $this->randomMachineName(),
        'status' => 0,
        'moderation_state[0][state]' => 'draft'
      ])
        ->save();
    }

    // Ensure we have moderation state "Draft" by default
    // in the newly created node.
    $this->assertTrue($node->hasField('moderation_state'));
    $this->assertFalse($node->get('moderation_state')->isEmpty());
    $this->assertEquals('draft', $node->get('moderation_state')->first()->getString());

    // Check that created node has "Draft" moderation state in English.
    $this->drupalGet('/content-moderation-integration-test', [
      'query' => [
        'translation_target_language' => 'en',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains(
      'css',
      'table > tbody > tr:nth-child(1) .views-field-translation-moderation-state',
      'Draft'
    );

    // Check that created node has "Draft" moderation state in German.
    $this->drupalGet('/content-moderation-integration-test', [
      'query' => [
        'translation_target_language' => 'de',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains(
      'css',
      'table > tbody > tr:nth-child(1) .views-field-translation-moderation-state',
      'Draft'
    );

    // Change moderation state to "Published".
    $node->set('moderation_state', 'published')->save();

    // Check that created node has "Published" moderation state in English.
    $this->drupalGet('/content-moderation-integration-test', [
      'query' => [
        'translation_target_language' => 'en',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains(
      'css',
      'table > tbody > tr:nth-child(1) .views-field-translation-moderation-state',
      'Published'
    );
  }

  /**
   * Test view's field "Translation Operation".
   */
  public function testTranslationOperations() {
    // Create node for test.
    $node = $this->createNode(['type' => 'article']);
    // Create translations
    foreach (self::$langcodes as $langcode) {
      $node->addTranslation($langcode, [
        'title' => $this->randomMachineName(),
        'status' => 0,
        'moderation_state[0][state]' => 'draft'
      ])
        ->save();
    }

    // Ensure we have moderation state "Draft" by default
    // in the newly created node.
    $this->assertTrue($node->hasField('moderation_state'));
    $this->assertFalse($node->get('moderation_state')->isEmpty());
    $this->assertEquals('draft', $node->get('moderation_state')->first()->getString());

    // Login as a translator.
    $translator = $this->createUser(['translate article node', 'update content translations']);
    $this->drupalLogin($translator);

    // Check that operations are provided for translation "Draft".
    $this->drupalGet('/content-moderation-integration-test', [
      'query' => [
        'translation_target_language' => 'de',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains(
      'css',
      'table > tbody > tr:nth-child(1) .views-field-translation-operations ul li a',
      'Edit'
    );
  }

}
