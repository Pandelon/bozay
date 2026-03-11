<?php

namespace Drupal\Tests\yearonly\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\yearonly\Plugin\Field\FieldType\YearOnlyItem;

/**
 * Tests YearOnlyItem class.
 *
 * @coversDefaultClass \Drupal\yearonly\Plugin\Field\FieldType\YearOnlyItem
 *
 * @group yearonly
 */
class YearOnlyItemTest extends UnitTestCase {

  /**
   * Tests that a default minimum of 1970 is used when 'yearonly_from' is empty.
   *
   * @covers ::generateSampleValue
   */
  public function testGenerateSampleValueUsesDefaultMin(): void {
    $field_definition = $this->createFieldDefinition([
      'yearonly_from' => NULL,
      'yearonly_to' => 2000,
    ]);

    $values = YearOnlyItem::generateSampleValue($field_definition);
    $this->assertGreaterThanOrEqual(1970, $values['value'], 'Uses default lower bound 1970 when "yearonly_from" is empty.');
    $this->assertLessThanOrEqual(2000, $values['value']);
  }

  /**
   * Tests that 'yearonly_to' = 'now' is treated as the current year.
   *
   * @covers ::generateSampleValue
   */
  public function testGenerateSampleValueHandlesNowMax(): void {
    $current_year = (int) date('Y');
    $field_definition = $this->createFieldDefinition([
      'yearonly_from' => 2000,
      'yearonly_to' => 'now',
    ]);

    $values = YearOnlyItem::generateSampleValue($field_definition);
    $this->assertGreaterThanOrEqual(2000, $values['value']);
    $this->assertLessThanOrEqual($current_year, $values['value']);
  }

  /**
   * Helper to create a field definition mock with given settings.
   *
   * @param array $settings
   *   Settings keyed by setting name.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The mocked field definition.
   */
  private function createFieldDefinition(array $settings): FieldDefinitionInterface {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition
      ->method('getSetting')
      ->willReturnCallback(static function ($key) use ($settings) {
        return $settings[$key] ?? NULL;
      });

    return $field_definition;
  }

}
