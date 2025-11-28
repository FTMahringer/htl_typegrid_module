<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\htl_typegrid\Model\GridNode;
use Drupal\htl_typegrid\Model\GridCard;
use Drupal\htl_typegrid\Model\GridConfig;

final class GridCardBuilder {

  public function __construct(
    private readonly GridImageRenderer $imageRenderer,
    private readonly GridMetaBuilder $metaBuilder,
    private readonly FieldRenderer $fieldRenderer,
  ) {}

  /**
   * @param GridNode[] $nodes
   * @return GridCard[]
   */
  public function buildCards(array $nodes, GridConfig $config): array {
    $cards = [];
    foreach ($nodes as $node) {
      $imageHtml = $this->imageRenderer->render($node->image, $node->imageStyleSettings);
      $metaItems = $this->metaBuilder->buildMeta($node->fields);
      $cards[] = new GridCard(
        title: $node->title,
        url: $node->url,
        imageHtml: $imageHtml,
        metaItems: $metaItems,
        cssClasses: $config->cssClasses[$node->bundle] ?? '',
        pinned: $node->pinned
      );
    }
    return $cards;
  }
}
