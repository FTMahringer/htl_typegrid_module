<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\htl_typegrid\Model\GridFieldValue;

/**
 * Builds meta information arrays for grid cards.
 *
 * Uses FieldRenderer to properly format different field types.
 */
final class GridMetaBuilder {

  public function __construct(
    private readonly FieldRenderer $fieldRenderer,
  ) {}

  /**
   * Builds meta items from field values.
   *
   * @param GridFieldValue[] $fields
   *   Array of field values to process.
   *
   * @return array
   *   Array of render arrays or strings for display in card meta section.
   */
  public function buildMeta(array $fields): array {
    $metaItems = [];

    // Use FieldRenderer to get properly formatted fields
    $renderedFields = $this->fieldRenderer->renderFields($fields);

    foreach ($renderedFields as $field) {
      if (!isset($field['value']) || $field['value'] === null) {
        continue;
      }
      // If it's a render array, keep it as is
      if (is_array($field['value'])) {
        $metaItems[] = $field['value'];
      }
      // If it's a string, add it directly
      elseif (is_string($field['value']) && trim($field['value']) !== '') {
        $metaItems[] = $field['value'];
      }
    }
    return $metaItems;
  }
}
