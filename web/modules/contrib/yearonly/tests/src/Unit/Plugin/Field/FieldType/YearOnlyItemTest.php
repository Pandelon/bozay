<?php

namespace Drupal\Tests\yearonly\Unit\Plugin\Field\FieldType;

use Drupal\Tests\UnitTestCase;
use Drupal\yearonly\Plugin\Field\FieldType\YearOnlyItem;

/**
 * Unit tests for the YearOnlyItem field type.
 *
 * @group yearonly
 *
 * @coversDefaultClass \Drupal\yearonly\Plugin\Field\FieldType\YearOnlyItem
 */
class YearOnlyItemTest extends UnitTestCase {

  /**
   * Tests year calculation from numeric and relative inputs.
   *
   * @dataProvider calculateYearProvider
   *
   * @covers ::calculateYear
   */
  public function testCalculateYear($year, $expected_result): void {
    $calculated_year = YearOnlyItem::calculateYear($year);
    $this->assertEquals($expected_result, $calculated_year);
  }

  /**
   * Provides test cases for testCalculateYear().
   */
  public static function calculateYearProvider(): array {
    return [
      'numeric year' => ['2023', '2023'],
      'current year' => ['now', date('Y')],
      'relative year' => ['+5 years', date('Y', strtotime('+5 years'))],
    ];
  }

}
