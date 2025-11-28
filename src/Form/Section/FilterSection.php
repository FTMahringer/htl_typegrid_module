<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

/**
 * Builds the Filters & Sorting section of the Grid block form.
 */
final class FilterSection {

  /**
   * Build the filters section.
   *
   * @param array $form
   *   The form array (by reference).
   * @param array $config
   *   Block configuration.
   */
  public function build(array &$form, array $config): void {
    $filters = $config['filters'] ?? [];

    $form['filters'] = [
      '#type' => 'details',
      '#title' => t('Filters & sorting'),
      '#group' => 'settings',
      '#open' => FALSE,
    ];

    // Wrapper for side-by-side layout.
    $form['filters']['row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['htl-typegrid-form-row'],
      ],
    ];

    $form['filters']['row']['sort_mode'] = [
      '#type' => 'select',
      '#title' => t('Sort mode'),
      '#default_value' => $filters['sort_mode'] ?? 'created',
      '#options' => [
        'created' => t('Created date'),
        'changed' => t('Last updated'),
        'title' => t('Title'),
        'alpha' => t('Alphabetical (custom field)'),
        'random' => t('Random'),
      ],
    ];

    $form['filters']['row']['alpha_field'] = [
      '#type' => 'textfield',
      '#title' => t('Sort field'),
      '#default_value' => $filters['alpha_field'] ?? 'title',
      '#description' => t('Machine name of the field to sort by.'),
      '#states' => [
        'visible' => [
          ':input[name="settings[filters][row][sort_mode]"]' => ['value' => 'alpha'],
        ],
      ],
    ];

    $form['filters']['row']['direction'] = [
      '#type' => 'select',
      '#title' => t('Direction'),
      '#options' => [
        'ASC' => t('Ascending'),
        'DESC' => t('Descending'),
      ],
      '#default_value' => $filters['direction'] ?? 'DESC',
    ];
  }

}
