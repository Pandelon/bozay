<?php

declare(strict_types=1);

namespace Drupal\Tests\yearonly\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Year Only default widget behavior and configuration.
 *
 * @group yearonly
 */
class YearOnlyDefaultWidgetTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'yearonly',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create and log in an administrative user.
    $adminUser = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($adminUser);
  }

  /**
   * Tests the Year Only widget functionality.
   */
  public function testYearOnlyWidget(): void {
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
      'settings' => [
        'yearonly_from' => 2020,
        'yearonly_to' => 2024,
      ],
    ])->save();

    // Assign widget settings for the 'default' form mode.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_year_only', [
        'type' => 'yearonly_default',
        'settings' => [
          'sort_order' => 'desc',
        ],
      ])
      ->save();

    $this->drupalGet('/node/add/article');

    // Ensure the years are listed in descending order 2024..2020.
    $select = $this->assertSession()->elementExists('css', 'select[name="field_year_only[0][value]"]');

    $option_nodes = $select->findAll('css', 'option');
    $years = [];
    foreach ($option_nodes as $option) {
      $value = $option->getAttribute('value') ?? '';
      if ($value !== '') {
        $years[] = $value;
      }
    }

    $this->assertSame(['2024', '2023', '2022', '2021', '2020'], $years, 'Years are listed in descending order.');
  }

  /**
   * Tests the Year Only widget with relative year configured.
   */
  public function testYearOnlyWidgetWithRelativeYear(): void {
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
      'settings' => [
        'yearonly_from' => 2000,
        'yearonly_to' => '-15 years',
      ],
    ])->save();

    // Assign widget settings for the 'default' form mode.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_year_only', [
        'type' => 'yearonly_default',
      ])
      ->save();

    $this->drupalGet('/node/add/article');

    $select = $this->assertSession()->elementExists('css', 'select[name="field_year_only[0][value]"]');
    $option_nodes = $select->findAll('css', 'option');

    $years = [];
    foreach ($option_nodes as $option) {
      $value = $option->getAttribute('value') ?? '';
      if ($value !== '') {
        $years[] = $value;
      }
    }

    $to_year = date('Y', strtotime('-15 years'));
    $expected_years = array_map('strval', range('2000', $to_year));

    $this->assertSession()->pagetextContains("Select a year from 2000 to {$to_year}.");
    $this->assertSame($expected_years, $years, 'Years are listed in descending order.');
  }

}
