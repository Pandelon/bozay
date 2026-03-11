<?php

namespace Drupal\yearonly\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Represents a configurable entity datetime field.
 */
class YearOnlyFieldItemList extends FieldItemList {

  /**
   * Defines the default value as now.
   */
  const DEFAULT_VALUE_NOW = 'now';

  /**
   * Defines the default value as relative.
   */
  const DEFAULT_VALUE_RELATIVE = 'relative';

  /**
   * Defines the default value as a specific year.
   */
  const DEFAULT_VALUE_SPECIFIC = 'specific';

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {
    if (empty($this->getFieldDefinition()->getDefaultValueCallback())) {
      $default_value = $this->getFieldDefinition()->getDefaultValueLiteral();

      $element = [
        '#parents' => ['default_value_input'],
        'default_type' => [
          '#type' => 'select',
          '#title' => $this->t('Default year'),
          '#description' => $this->t('Choose a default value for the year.'),
          '#default_value' => $default_value[0]['default_type'] ?? '',
          '#options' => [
            static::DEFAULT_VALUE_NOW => $this->t('Current year'),
            static::DEFAULT_VALUE_RELATIVE => $this->t('Relative year'),
            static::DEFAULT_VALUE_SPECIFIC => $this->t('Specific year'),
          ],
          '#empty_value' => '',
        ],
        'default_relative' => [
          '#type' => 'textfield',
          '#title' => $this->t('Relative default value'),
          '#description' => $this->t("Describe a year relative to the current date; e.g., '+2 years'."),
          '#default_value' => (isset($default_value[0]['default_type']) && $default_value[0]['default_type'] == static::DEFAULT_VALUE_RELATIVE) ? $default_value[0]['default_relative'] : '',
          '#states' => [
            'visible' => [
              ':input[name="default_value_input[default_type]"]' => ['value' => static::DEFAULT_VALUE_RELATIVE],
            ],
            'required' => [
              ':input[name="default_value_input[default_type]"]' => ['value' => static::DEFAULT_VALUE_RELATIVE],
            ],
          ],
        ],
        'default_specific' => [
          '#type' => 'number',
          '#min' => 1,
          '#step' => 1,
          '#title' => $this->t('Specific default year'),
          '#description' => $this->t("Enter a specific year for the default value (ex. 2020)."),
          '#default_value' => (isset($default_value[0]['default_type']) && $default_value[0]['default_type'] == static::DEFAULT_VALUE_SPECIFIC) ? $default_value[0]['default_specific'] : '',
          '#states' => [
            'visible' => [
              ':input[name="default_value_input[default_type]"]' => ['value' => static::DEFAULT_VALUE_SPECIFIC],
            ],
            'required' => [
              ':input[name="default_value_input[default_type]"]' => ['value' => static::DEFAULT_VALUE_SPECIFIC],
            ],
          ],
        ],
      ];

      return $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {
    $default_type = $form_state->getValue(['default_value_input', 'default_type']);
    $default_year = '';
    $element_name = '';

    if ($default_type == static::DEFAULT_VALUE_NOW) {
      $default_year = 'now';
      $element_name = 'default_value_input';
    }

    // Validate relative year format.
    if ($default_type == static::DEFAULT_VALUE_RELATIVE) {
      $default_year = $form_state->getValue(['default_value_input', 'default_relative']);
      $element_name = 'default_value_input][default_relative';
      $is_strtotime = @strtotime($default_year);

      if (!$is_strtotime) {
        $form_state->setErrorByName($element_name, $this->t('The default relative year value is not valid.'));
      }
    }

    // Validate specific year.
    if ($default_type == static::DEFAULT_VALUE_SPECIFIC) {
      $default_year = $form_state->getValue(['default_value_input', 'default_specific']);
      $element_name = 'default_value_input][default_specific';
      // Ensure default year is a positive integer.
      if (!((int) $default_year == $default_year && (int) $default_year > 0)) {
        $form_state->setErrorByName($element_name, $this->t('Please provide a valid default year greater than 0.'));
      }
    }

    // Ensure default year is within min/max range.
    $default_year = YearOnlyItem::calculateYear($default_year);
    $min_year = $form_state->getValue(['settings', 'yearonly_from']);
    $max_year = YearOnlyItem::calculateYear($form_state->getValue(['settings', 'yearonly_to']));

    if (!($min_year <= $default_year && $default_year <= $max_year)) {
      $form_state->setErrorByName($element_name,
        $this->t('The default year (@year) must be between @min and @max.', [
          '@year' => $default_year,
          '@min' => $min_year,
          '@max' => $max_year,
        ]
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue(['default_value_input', 'default_type'])) {
      if ($form_state->getValue(['default_value_input', 'default_type']) == static::DEFAULT_VALUE_NOW) {
        $form_state->setValueForElement($element['default_relative'], static::DEFAULT_VALUE_NOW);
      }
      return [$form_state->getValue('default_value_input')];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    if ($default_value && isset($default_value[0]['default_type'])) {
      switch ($default_value[0]['default_type']) {
        case static::DEFAULT_VALUE_NOW:
          $default_value = [(int) date('Y')];
          break;

        case static::DEFAULT_VALUE_RELATIVE:
          $default_value = [(int) date('Y', strtotime($default_value[0]['default_relative']))];
          break;

        case static::DEFAULT_VALUE_SPECIFIC:
          $default_value = [(int) $default_value[0]['default_specific']];
          break;
      }
    }

    return $default_value;
  }

}
