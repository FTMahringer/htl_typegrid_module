<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Model;

final class GridFieldValue {

  public function __construct(
    public readonly string $name,
    public readonly mixed $raw,
    public readonly string $type,
    public readonly ?string $formatted = null,
    public readonly bool $isImage = false,
    public readonly array $meta = [],
  ) {}
}
