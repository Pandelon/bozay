<?php

namespace Drupal\simple_timeline\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;

/**
 * Hook implementations for simple_timeline.
 */
class SimpleTimelineHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the simple_timeline module.
      case 'help.page.simple_timeline':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Provide a views style for displaying content in a vertical timeline') . '</p>';
        return $output;

      default:
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_views_view_simple_timeline_alter')]
  public static function themeSuggestionsViewsViewSimpleTimelineAlter(array &$suggestions, array $variables): void {
    $view = $variables['view'];
    $id = $view->storage->id();
    $display = $view->current_display;
    $suggestions[] = 'views_view_simple_timeline__' . $id;
    $suggestions[] = 'views_view_simple_timeline__' . $display;
    $suggestions[] = 'views_view_simple_timeline__' . $id . '__' . $display;
  }

  /**
   * Implements hook_preprocess_HOOK() for views_view_simple_timeline.
   *
   * Default template: views-view-simple-timeline.html.twig.
   */
  #[Hook('preprocess_views_view_simple_timeline')]
  public function preprocessViewsViewSimpleTimeline(array &$variables): void {
    $view = $variables['view'];
    $rows = $variables['rows'];
    $style = $view->style_plugin;
    $options = $style->options;

    // Add our specific class.
    $variables['list']['attributes'] = new Attribute(['class' => 'timeline-list']);

    // Fetch classes from options.
    if ($options['class']) {
      $class = explode(' ', $options['class']);
      $class = array_map([Html::class, 'cleanCssIdentifier'], $class);
      $variables['list']['attributes']->addClass($class);
    }

    if ($options['position_items']) {
      $variables['list']['attributes']->addClass($options['position_items']);
    }

    if ($options['position_marker']) {
      $variables['list']['attributes']->addClass($options['position_marker']);
    }

    // Fetch wrapper classes from handler options.
    if ($options['wrapper_class']) {
      $wrapper_class = explode(' ', $options['wrapper_class']);
      $variables['attributes']['class'] = array_map([Html::class, 'cleanCssIdentifier'], $wrapper_class);
    }

    $count = 0;
    $variables['default_row_class'] = !empty($options['default_row_class']);
    foreach ($rows as $id => $row) {
      $count++;
      $variables['rows'][$id] = [];
      $variables['rows'][$id]['content'] = $row;
      $variables['rows'][$id]['attributes'] = new Attribute(['class' => 'timeline-item clearfix']);
      // Add odd/even class.
      $odd = $count % 2 ? 'odd' : 'even';
      $variables['rows'][$id]['attributes']->addClass($odd);

      if ($row_class = $view->style_plugin->getRowClass($id)) {
        $variables['rows'][$id]['attributes']->addClass($row_class);
      }
    }
  }

}
