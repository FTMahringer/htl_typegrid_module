<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

/**
 * Data Transfer Object for grid node rendering.
 */
final class GridNode {

  /**
   * @param GridFieldValue[] $fields
   *   Field values prepared for rendering (non-image fields).
   * @param array $imageStyleSettings
   *   Image style settings derived from the node (e.g., selected style).
   * @param bool $pinned
   *   Whether this node is marked as pinned for TypeGrid.
   * @param bool $shown
   *   Whether this node should be shown in TypeGrid (always true when loaded via query).
   */
  public function __construct(
    public readonly int $id,
    public readonly string $bundle,
    public readonly string $title,
    public readonly string $url,
    public readonly array $fields,
    public readonly ?GridFieldValue $image = null,
    public readonly array $imageStyleSettings = [],
    public readonly bool $pinned = false,
    public readonly bool $shown = true,
  ) {}
}
