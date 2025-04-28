<?php

namespace Drupal\yearonly\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a year only field mapper.
 *
 * @FeedsTarget(
 *   id = "year_only_feeds_target",
 *   field_types = {"yearonly"}
 * )
 */
class YearOnly extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
   $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition);
   if ($field_definition->getType() === 'yearonly') {
     $definition
       ->addProperty('value');
    }
    return $definition;
  }
}
