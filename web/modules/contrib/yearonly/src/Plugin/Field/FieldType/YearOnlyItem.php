<?php

namespace Drupal\yearonly\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'yearonly' field type.
 *
 * @FieldType(
 *   id = "yearonly",
 *   label = @Translation("Year only"),
 *   category = "date_time",
 *   description = {
 *     @Translation("Collect and store only the year portion of a date"),
 *     @Translation("Define a range of valid year values"),
 *   },
 *   list_class = "\Drupal\yearonly\Plugin\Field\FieldType\YearOnlyFieldItemList",
 *   default_widget = "yearonly_default",
 *   default_formatter = "yearonly_default",
 * )
 */
class YearOnlyItem extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'length' => 50,
        ],
      ],
      'indexes' => [
        'value' => [
          'value',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(t('Year'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'yearonly_from' => '',
      'yearonly_to' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $element['#markup'] = '<strong>' . $this->t('Valid year value range') . '</strong>';

    $element['yearonly_from'] = [
      '#type' => 'number',
      '#title' => $this->t('From (minimum year)'),
      '#default_value' => $this->getSetting('yearonly_from'),
      '#required' => TRUE,
      '#element_validate' => [[static::class, 'validateMinAndMaxConfig']],
      '#description' => $this->t('Provide a minimum valid year, e.g., <strong>530</strong>, <strong>1900</strong>, etc.'),
    ];

    $element['yearonly_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To (maximum year)'),
      '#size' => 20,
      '#default_value' => $this->getSetting('yearonly_to'),
      '#required' => TRUE,
    ];

    $element['yearonly_to']['#description'] = $this->t(
      'Provide a specific year <b>OR</b> a value relative to the current year.<br>
      Examples: <b>1978</b> (specific year), <b>now</b> (current year), <b>+5 years</b> (five years from now)<br>
      See <a href=":url" target="_blank">relative date/time formats</a>.',
      [':url' => 'https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative']);

    return $element;
  }

  /**
   * Validates that the minimum value is less than the maximum.
   *
   * @param array[] $element
   *   The numeric element to be validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array[] $complete_form
   *   The complete form structure.
   */
  public static function validateMinAndMaxConfig(array &$element, FormStateInterface &$form_state, array &$complete_form): void {
    $settings = $form_state->getValue('settings');

    // Ensure that the minimum and maximum are numeric.
    $min = is_numeric($settings['yearonly_from']) ? (int) $settings['yearonly_from'] : NULL;
    $max = static::calculateYear($settings['yearonly_to']);

    // Only proceed with validation if both values are numeric.
    if ($min === NULL || $max === FALSE) {
      return;
    }

    if ((int) $min >= (int) $max) {
      $form_state->setError($element, t('The minimum value must be less than the %max.', ['%max' => $max]));
      return;
    }
  }

  /**
   * Calculate a year value based on provide numeric or relative string.
   *
   * @param string $year
   *   String representation of a specific year or relative strtotime format.
   *
   * @return mixed
   *   The calculated year value as a number, or false if strtotime was invoked
   *   and failed.
   */
  public static function calculateYear(string $year) {
    if (!is_numeric($year)) {
      $year = date('Y', strtotime($year));
    }
    return $year;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $min = $field_definition->getSetting('yearonly_from') ?: 1970;
    $max = static::calculateYear($field_definition->getSetting('yearonly_to'));

    $values['value'] = mt_rand($min, $max);

    return $values;
  }

}
