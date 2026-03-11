<?php

declare(strict_types=1);

namespace Drupal\Tests\yearonly\Kernel;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\yearonly\Feeds\Target\YearOnly;

/**
 * Kernel tests for the Feeds target definition of the yearonly field.
 *
 * @group yearonly
 */
class YearOnlyTargetDefinitionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'options',
    'feeds',
    'yearonly',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['node']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    // Add a 'yearonly' field to validate positive behavior.
    FieldStorageConfig::create([
      'field_name' => 'field_year_only',
      'entity_type' => 'node',
      'type' => 'yearonly',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_year_only',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Year only',
    ])->save();

    // Add a non-yearonly field to validate negative behavior.
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Text',
    ])->save();
  }

  /**
   * Ensures the yearonly field type exposes the 'value' property.
   */
  public function testPrepareTarget(): void {
    $field_definition = FieldConfig::loadByName('node', 'article', 'field_year_only');

    $definition = $this->invokePrepareTarget($field_definition);
    $this->assertTrue($definition->hasProperty('value'));
  }

  /**
   * Ensures non-yearonly fields do not expose the value property.
   */
  public function testNotYearOnlyFieldTypePrepareTarget() {
    $field_definition = FieldConfig::loadByName('node', 'article', 'field_text');

    $definition = $this->invokePrepareTarget($field_definition);
    $this->assertFalse($definition->hasProperty('value'));
  }

  /**
   * Invokes the protected static YearOnly::prepareTarget() method.
   */
  private function invokePrepareTarget(FieldDefinitionInterface $field_definition): object {
    $ref = new \ReflectionMethod(YearOnly::class, 'prepareTarget');
    $ref->setAccessible(TRUE);

    $definition = $ref->invoke(NULL, $field_definition);
    return $definition;
  }

}
