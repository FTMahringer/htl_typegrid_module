<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\htl_typegrid\Model\GridFilters;

final class GridConfigFactory {

  public function fromArray(array $config): GridConfig {
    // --- Bundle ermitteln ---
    $bundle = '';
    if (isset($config['bundle'])) {
      $bundle = (string) $config['bundle'];
    } elseif (isset($config['settings']['bundle'])) {
      $bundle = (string) $config['settings']['bundle'];
    } elseif (isset($config['general']['bundle'])) {
      $bundle = (string) $config['general']['bundle'];
    }

    // --- Layout ---
    $layout = $config['layout'] ?? [];
    $columns = (int) ($config['columns'] ?? $layout['columns'] ?? 3);
    $rows = (int) ($config['rows'] ?? $layout['rows'] ?? 2);



    // --- Layout Preset ---
    $layoutPreset = $config['layout_preset'] ?? $layout['layout_preset'] ?? GridConfig::PRESET_STANDARD;

    // --- Image Position ---
    $imagePosition = $config['image_position'] ?? $layout['image_position'] ?? GridConfig::IMAGE_TOP;

    // --- Card Gap ---
    $cardGap = $config['card_gap'] ?? $layout['card_gap'] ?? 'medium';

    // --- Card Radius ---
    $cardRadius = $config['card_radius'] ?? $layout['card_radius'] ?? 'medium';

    // --- Filter ---
    $filtersArray = $config['filters'] ?? [];
    $filters = new GridFilters(sortMode: $filtersArray['sort_mode'] ?? 'created', alphaField: $filtersArray['alpha_field'] ?? 'title', direction: $filtersArray['direction'] ?? 'DESC');

    $fields = $config['bundle_fields'][$bundle] ?? [];

    // Merge layout settings into imageSettings for backwards compatibility.
    $imageSettings = $config['image_settings'][$bundle] ?? [];
    $imageSettings['card_gap'] = $cardGap;
    $imageSettings['card_radius'] = $cardRadius;

    return new GridConfig(
      bundle: $bundle,
      columns: $columns,
      rows: $rows,
      filters: $filters,
      fields: $fields,
      cssClasses: $config['css_classes'][$bundle] ?? [],
      imageSettings: $imageSettings,
      layoutPreset: $layoutPreset,
      imagePosition: $imagePosition,
    );
  }
}
