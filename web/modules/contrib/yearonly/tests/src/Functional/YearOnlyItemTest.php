<?php

declare(strict_types=1);

namespace Drupal\Tests\yearonly\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests creating the Year Only field and saving its settings via UI.
 *
 * @group yearonly
 */
class YearOnlyItemTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_ui',
    'node',
    'yearonly',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    FieldStorageConfig::create([
      'field_name' => 'field_year_only',
      'type' => 'yearonly',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_year_only',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Year only',
    ])->save();

    // Create and log in an administrative user.
    $adminUser = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($adminUser);
  }

  /**
   * Tests the Year Only field creation and settings persistence.
   */
  public function testYearOnlyFieldType(): void {
    $this->drupalGet('/admin/structure/types/manage/article/fields/node.article.field_year_only');

    $this->assertSession()->elementExists('css', '[name="settings[yearonly_from]"]');
    $this->assertSession()->elementExists('css', '[name="settings[yearonly_to]"]');

    $this->submitForm([
      'settings[yearonly_from]' => 2000,
      'settings[yearonly_to]' => 'now',
    ], 'Save');

    // Verify the settings were persisted on the FieldConfig.
    $field = FieldConfig::loadByName('node', 'article', 'field_year_only');
    $this->assertNotNull($field, 'FieldConfig was created.');
    $this->assertSame(2000, (int) $field->getSetting('yearonly_from'));
    $this->assertSame('now', (string) $field->getSetting('yearonly_to'));
  }

  /**
   * Test min max validation on the field item configuration form.
   *
   * @dataProvider minMaxYearConfigProvider
   */
  public function testValidateMinMaxYearConfig($from, $to, string $expectedMessage): void {
    $this->drupalGet('/admin/structure/types/manage/article/fields/node.article.field_year_only');

    $this->submitForm([
      'settings[yearonly_from]' => $from,
      'settings[yearonly_to]' => $to,
    ], 'Save');

    $this->assertSession()->statusMessageContains($expectedMessage);
  }

  /**
   * Data provider for testValidateMinMaxYearConfig.
   */
  public static function minMaxYearConfigProvider(): array {
    return [
      'numeric years' => [
        2000,
        1990,
        'The minimum value must be less than the 1990.',
      ],
      'relative year' => [
        date('Y'),
        '-100 years',
        'The minimum value must be less than the ' . date('Y', strtotime('-100 years')),
      ],
    ];
  }

}
