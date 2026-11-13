<?php

namespace Drupal\simplei\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\simplei\IndicatorParser;

/**
 * Hook implementations for simplei.
 */
class SimpleiHooks {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected AccountInterface $currentUser,
    protected IndicatorParser $parser,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name == 'help.page.simplei') {
      $path = __DIR__ . '/README.txt';
      if (file_exists($path)) {
        return '<pre>' . file_get_contents($path) . '</pre>';
      }
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $indicator = trim(Settings::get('simple_environment_indicator', ''));
    if (!$indicator) {
      return;
    }
    [
      $color,
      $background,
      $environment,
    ] = $this->parser->parse($indicator);

    // When core's navigation module is active for this user, the indicator is
    // rendered by the EnvironmentIndicator TopBarItem plugin — skip the legacy
    // toolbar attachment so the indicator is not shown twice.
    $navigation = $this->moduleHandler->moduleExists('navigation') && $this->currentUser->hasPermission('access navigation');

    if (!$navigation && $this->moduleHandler->moduleExists('toolbar') && $this->currentUser->hasPermission('access toolbar')) {
      $attachments['#attached']['drupalSettings']['simplei'] = [
        'color' => $color,
        'background' => $background,
        'environment' => $environment,
      ];
      $attachments['#attached']['library'][] = 'simplei/simplei';
    }
    elseif (($anon = Settings::get('simple_environment_anonymous', FALSE)) && $this->currentUser->isAnonymous()) {
      if (is_string($anon)) {
        $css = $anon;
      }
      elseif (is_bool($anon)) {
        $css = "body:after {\n          content: \"[$environment]\";\n          position: fixed;\n          top: 0;\n          left: 0;\n          padding: 0.1em 0.5em;\n          font-family: monospace;\n          font-weight: bold;\n          color: $color;\n          background: $background;\n          border: 1px solid #fff;\n          z-index: 1001; }";
      }
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'style',
          '#value' => $css,
          '#attributes' => [
            'media' => 'all',
            'type' => 'text/css',
          ],
          '#weight' => 100,
        ],
        'simpleicss',
      ];
    }
  }

}
