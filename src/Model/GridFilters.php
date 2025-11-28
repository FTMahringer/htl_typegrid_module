<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

final class GridFilters {

  public function __construct(
    public readonly string $sortMode,   // newest, oldest, alpha, random
    public readonly string $alphaField,
    public readonly string $direction,  // ASC / DESC
  ) {}
}
