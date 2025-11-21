<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

final class LayoutSection {

  public function build(array &$form, array $config): void {
    $columns = (int) ($config['columns'] ?? 3);
    $rows    = (int) ($config['rows'] ?? 2);

    $form['layout'] = [
      '#type' => 'details',
      '#title' => t('Layout'),
      '#group' => 'settings',
      '#open'  => false,
    ];

    $form['layout']['columns'] = [
      '#type' => 'number',
      '#title' => t('Columns'),
      '#min' => 1,
      '#max' => 6,
      '#default_value' => $columns,
    ];

    $form['layout']['rows'] = [
      '#type' => 'number',
      '#title' => t('Rows'),
      '#min' => 1,
      '#max' => 10,
      '#default_value' => $rows,
    ];
  }
}
