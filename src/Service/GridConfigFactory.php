<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\htl_typegrid\Model\GridFilters;

final class GridConfigFactory {

  public function fromBlockConfiguration(array $configuration): GridConfig {
    $bundle = $configuration['bundle'] ?? null;

    $columns = (int) ($configuration['columns'] ?? 3);
    $rows = (int) ($configuration['rows'] ?? 2);

    $filtersRaw = (array) ($configuration['filters'] ?? []);
    $filters = new GridFilters(
      sortMode: (string) ($filtersRaw['sort_mode'] ?? 'newest'),
      alphaField: (string) ($filtersRaw['alpha_field'] ?? 'title'),
      direction: (string) ($filtersRaw['direction'] ?? 'ASC'),
    );

    $bundleFields = (array) ($configuration['bundle_fields'] ?? []);

    return new GridConfig(
      bundle: $bundle ? (string) $bundle : null,
      columns: $columns,
      rows: $rows,
      filters: $filters,
      bundleFields: $bundleFields,
    );
  }
}
