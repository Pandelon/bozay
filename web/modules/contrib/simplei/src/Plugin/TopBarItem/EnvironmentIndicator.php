<?php

declare(strict_types=1);

namespace Drupal\simplei\Plugin\TopBarItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Drupal\simplei\IndicatorParser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Simplei environment indicator top bar item.
 */
#[TopBarItem(
  id: 'simplei_environment_indicator',
  region: TopBarRegion::Context,
  label: new TranslatableMarkup('Environment indicator'),
)]
class EnvironmentIndicator extends TopBarItemBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected IndicatorParser $parser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(IndicatorParser::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $indicator = trim(Settings::get('simple_environment_indicator', ''));
    if (!$indicator) {
      return [];
    }
    [$color, $background, $environment] = $this->parser->parse($indicator);

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $environment,
      '#attributes' => [
        'class' => ['simplei-indicator', 'top-bar__title'],
        'style' => "--simplei-fg: $color; --simplei-bg: $background;",
      ],
      '#attached' => [
        'library' => ['simplei/navigation'],
      ],
    ];
  }

}
