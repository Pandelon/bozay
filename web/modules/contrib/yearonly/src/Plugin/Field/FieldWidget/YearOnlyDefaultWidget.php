<?php

namespace Drupal\yearonly\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;

/**
 * Plugin implementation of the 'yearonly_default' widget.
 *
 * @FieldWidget(
 *   id = "yearonly_default",
 *   label = @Translation("Select Year"),
 *   field_types = {
 *     "yearonly"
 *   }
 * )
 */
class YearOnlyDefaultWidget extends WidgetBase implements WidgetInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'sort_order' => 'asc',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['sort_order'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sort order'),
      '#default_value' => $this->getSetting('sort_order'),
      '#required' => TRUE,
      '#options' => $this->getSortOptions(),
      '#description' => $this->t('Choose a sort order for years in the select list.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Sort order: @sort_order', [
      '@sort_order' => $this->getSortOptions()[$this->getSetting('sort_order')],
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $yearonly_from = $this->getFieldSetting('yearonly_from') ?: 0;
    $yearonly_to = $this->getFieldSetting('yearonly_to') ?: 0;

    if ($yearonly_to == 'now') {
      $yearonly_to = date('Y');
    }

    $yearonly_from = (int) $yearonly_from;
    $yearonly_to = (int) $yearonly_to;

    if($yearonly_from > $yearonly_to)
    {
      $yearonly_from = $yearonly_to;
    }

    $options = array_combine(
      range($yearonly_from, $yearonly_to),
      range($yearonly_from, $yearonly_to)
    );

    if ($this->getSetting('sort_order') == 'desc') {
      $options = array_reverse($options, TRUE);
    }

    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $options,
      '#empty_value' => '',
      '#default_value' => $items[$delta]->value ?? '',
      '#description' => $this->t('Select year'),
    ];
    return $element;
  }

  /**
   * Get the widget sort options with translated labels.
   *
   * @return array
   *   The options array.
   */
  protected function getSortOptions() {
    return [
      'asc' => $this->t('Ascending'),
      'desc' => $this->t('Descending'),
    ];
  }

}
