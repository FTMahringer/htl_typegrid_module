<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\SubformStateInterface;

final class ValueReader {

  public static function readValue(array $form, FormStateInterface $form_state, array $relativePath, mixed $default=null): mixed {
    $parents = $form['#parents'] ?? [];
    $full = array_merge($parents, $relativePath);

    $candidates = [$full, array_merge(['settings'], $full)];

    $input = $form_state->getUserInput() ?? [];

    foreach ($candidates as $path) {
      $value = NestedArray::getValue($input, $path, $exists);
      if ($exists) return $value;
    }

    // Fallback: komplettes FormState
    $root = $form_state instanceof SubformStateInterface
      ? $form_state->getCompleteFormState()
      : $form_state;

    $all = $root->getValues();
    foreach ($candidates as $path) {
      $value = NestedArray::getValue($all, $path, $exists2);
      if ($exists2) return $value;
    }

    return $default;
  }
}
