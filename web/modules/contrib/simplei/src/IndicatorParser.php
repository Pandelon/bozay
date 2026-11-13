<?php

declare(strict_types=1);

namespace Drupal\simplei;

use Drupal\Component\Utility\Html;

/**
 * Parses Simplei environment indicator strings.
 */
class IndicatorParser {

  /**
   * Parse color and environment from $indicator.
   *
   * @param string $indicator
   *   Environment indicator.
   *
   * @return array
   *   Indexed: [foreground color, background color, environment label].
   */
  public function parse(string $indicator): array {
    $color = '#ffffff';
    $background = '#999999';

    if (str_starts_with($indicator, '@')) {
      $environment = substr($indicator, 1);

      if (str_contains($environment, '#access')) {
        [$environment] = explode('#access', $environment, 2);

        $background = match (strtolower(substr($environment, 0, 2))) {
          'pr', 'li' => '#8b0000',
          'st', 'te' => '#59590d',
          'de' => '#005b94',
          default => '#4a0080',
        };
      }
      else {
        $background = match (strtolower(substr($environment, 0, 2))) {
          'pr', 'li' => 'FireBrick',
          'st', 'te' => 'GoldenRod',
          'de' => '#0057ad',
          default => 'DodgerBlue',
        };
      }
    }
    elseif (preg_match('/(\S+)\s+(.*)/', $indicator, $match)) {
      if (strpos($match[1], '/')) {
        [$color, $background] = explode('/', $match[1]);
      }
      else {
        $background = $match[1];
      }

      $environment = $match[2];
    }

    return [
      Html::escape($color),
      Html::escape($background),
      Html::escape($environment ?? 'SITE'),
    ];
  }

}
