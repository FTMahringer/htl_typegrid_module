<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

final class GridConfig {
  public function __construct(
    public readonly ?string $bundle,
    public readonly int $columns,
    public readonly int $rows,
    public readonly GridFilters $filters,
    /** @var array<string, string[]> */
    public readonly array $bundleFields = [],
  ) {}

  public function limitPerType(): int {
    return min(20, $this->columns * $this->rows);
  }

  /**
   * Felder, die für das Bundle ausgewählt wurden.
   *
   * @return string[]
   */
  public function fieldsForBundle(string $bundle): array {
    return $this->bundleFields[$bundle] ?? [];
  }
}
