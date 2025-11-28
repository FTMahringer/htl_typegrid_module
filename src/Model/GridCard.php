<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

final class GridCard {

  /**
   * @param string[] $metaItems
   * @param bool $pinned
   *   Whether the underlying node is marked as pinned for TypeGrid.
   */
  public function __construct(
    public readonly string $title,
    public readonly string $url,
    public readonly ?string $imageHtml,
    public readonly array $metaItems,
    public readonly string $cssClasses = '',
    public readonly bool $pinned = false,
  ) {}
}
