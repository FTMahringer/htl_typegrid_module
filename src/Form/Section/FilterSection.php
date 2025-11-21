<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

final class FilterSection {

  public function build(array &$form, array $config): void {
    $filters    = $config['filters'] ?? [];
    $sortMode   = $filters['sort_mode'] ?? 'newest';
    $alphaField = $filters['alpha_field'] ?? 'title';
    $direction  = $filters['direction'] ?? 'ASC';

    $form['filters'] = [
      '#type' => 'details',
      '#title' => t('Filters & sorting'),
      '#group' => 'settings',
    ];

    $form['filters']['sort_mode'] = [
      '#type' => 'select',
      '#title' => t('Sort mode'),
      '#options' => [
        'newest' => t('Newest first'),
        'oldest' => t('Oldest first'),
        'alpha'  => t('Alphabetical'),
        'random' => t('Random'),
      ],
      '#default_value' => $sortMode,
    ];

    $form['filters']['direction'] = [
      '#type' => 'select',
      '#title' => t('Direction'),
      '#options' => ['ASC' => t('ASC'), 'DESC' => t('DESC')],
      '#default_value' => $direction,
      '#states' => [
        'visible' => [
          ':input[name="settings[filters][sort_mode]"]' => ['value' => 'alpha'],
        ],
      ],
    ];

    $form['filters']['alpha_field'] = [
      '#type' => 'textfield',
      '#title' => t('Alphabetic field'),
      '#default_value' => $alphaField,
      '#states' => [
        'visible' => [
          ':input[name="settings[filters][sort_mode]"]' => ['value' => 'alpha'],
        ],
      ],
    ];
  }
}
