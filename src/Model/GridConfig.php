<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

final class GridConfig {

  // Layout preset constants.
  public const PRESET_STANDARD = 'standard';
  public const PRESET_COMPACT = 'compact';
  public const PRESET_HERO = 'hero';
  public const PRESET_FEATURED = 'featured';
  public const PRESET_HORIZONTAL = 'horizontal';

  // Image position constants.
  public const IMAGE_TOP = 'top';
  public const IMAGE_LEFT = 'left';
  public const IMAGE_RIGHT = 'right';
  public const IMAGE_BOTTOM = 'bottom';
  public const IMAGE_OVERLAY = 'overlay';
  public const IMAGE_NONE = 'none';

  /**
   * @param string[] $fields
   */
  public function __construct(
    public readonly string $bundle,
    public readonly int $columns,
    public readonly int $rows,
    public readonly GridFilters $filters,
    public readonly array $fields,
    public readonly array $cssClasses = [],
    public readonly array $imageSettings = [],
    public readonly string $layoutPreset = self::PRESET_STANDARD,
    public readonly string $imagePosition = self::IMAGE_TOP,
  ) {}

  /**
   * Get available layout presets with labels.
   *
   * @return array<string, string>
   */
  public static function getLayoutPresets(): array {
    return [
      self::PRESET_STANDARD => t('Standard Grid'),
      self::PRESET_COMPACT => t('Compact'),
      self::PRESET_HERO => t('Hero (first card large)'),
      self::PRESET_FEATURED => t('Featured (first card full-width)'),
      self::PRESET_HORIZONTAL => t('Horizontal Cards'),
    ];
  }

  /**
   * Get available image positions with labels.
   *
   * @return array<string, string>
   */
  public static function getImagePositions(): array {
    return [
      self::IMAGE_TOP => t('Top'),
      self::IMAGE_LEFT => t('Left'),
      self::IMAGE_RIGHT => t('Right'),
      self::IMAGE_BOTTOM => t('Bottom'),
      self::IMAGE_OVERLAY => t('Overlay'),
      self::IMAGE_NONE => t('No Image'),
    ];
  }

  /**
   * Check if this preset uses horizontal card layout.
   *
   * @return bool
   */
  public function isHorizontalLayout(): bool {
    return $this->layoutPreset === self::PRESET_HORIZONTAL
      || $this->imagePosition === self::IMAGE_LEFT
      || $this->imagePosition === self::IMAGE_RIGHT;
  }

  /**
   * Check if this preset has a hero/featured first card.
   *
   * @return bool
   */
  public function hasHeroCard(): bool {
    return $this->layoutPreset === self::PRESET_HERO
      || $this->layoutPreset === self::PRESET_FEATURED;
  }

  /**
   * Get CSS classes for the grid container based on preset.
   *
   * @return string
   */
  public function getGridCssClasses(): string {
    $classes = ['htl-grid'];
    $classes[] = 'htl-grid--' . $this->layoutPreset;
    $classes[] = 'htl-grid--image-' . $this->imagePosition;

    return implode(' ', $classes);
  }

  /**
   * Get CSS classes for a specific card based on its index.
   *
   * @param int $index
   *   The card index (0-based).
   *
   * @return string
   */
  public function getCardCssClasses(int $index): string {
    $classes = ['htl-card'];
    $classes[] = 'htl-card--image-' . $this->imagePosition;

    // Hero preset: first card is large.
    if ($this->layoutPreset === self::PRESET_HERO && $index === 0) {
      $classes[] = 'htl-card--hero';
    }

    // Featured preset: first card is full-width.
    if ($this->layoutPreset === self::PRESET_FEATURED && $index === 0) {
      $classes[] = 'htl-card--featured';
    }

    // Compact preset.
    if ($this->layoutPreset === self::PRESET_COMPACT) {
      $classes[] = 'htl-card--compact';
    }

    // Horizontal preset.
    if ($this->layoutPreset === self::PRESET_HORIZONTAL) {
      $classes[] = 'htl-card--horizontal';
    }

    return implode(' ', $classes);
  }
}
