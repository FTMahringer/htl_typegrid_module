<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

final class GridFilters {
  public function __construct(
    public readonly string $sortMode = 'newest',
    public readonly string $alphaField = 'title',
    public readonly string $direction = 'ASC',
  ) {}

  public function normalizedDirection(): string {
    return strtoupper($this->direction) === 'DESC' ? 'DESC' : 'ASC';
  }
}
