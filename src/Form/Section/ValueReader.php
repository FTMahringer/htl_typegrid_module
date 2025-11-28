<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;

final class ValueReader {

  public static function read(array $form, FormStateInterface $state, array $relativePath, mixed $default = null): mixed {
    $parents = $form['#parents'] ?? [];
    $fullPath = array_merge($parents, $relativePath);

    $candidates = [
      $fullPath,
      array_merge(['settings'], $fullPath),
    ];

    $input = $state->getUserInput() ?? [];

    foreach ($candidates as $path) {
      $value = NestedArray::getValue($input, $path, $exists);
      if ($exists) {
        return $value;
      }
    }

    $rootState =
      $state instanceof SubformStateInterface
        ? $state->getCompleteFormState()
        : $state;

    foreach ($candidates as $path) {
      $value = NestedArray::getValue($rootState->getValues(), $path, $exists);
      if ($exists) {
        return $value;
      }
    }

    return $default;
  }
}
