<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\htl_typegrid\Model\GridConfig;

final class LayoutSection {

  public function build(array &$form, array $config): void {
    $form['layout'] = [
      '#type' => 'details',
      '#title' => t('Layout'),
      '#group' => 'settings',
      '#open' => FALSE,
    ];

    // Layout preset dropdown.
    $form['layout']['layout_preset'] = [
      '#type' => 'select',
      '#title' => t('Layout Preset'),
      '#description' => t('Choose how the cards are displayed in the grid.'),
      '#options' => GridConfig::getLayoutPresets(),
      '#default_value' => $config['layout_preset'] ?? GridConfig::PRESET_STANDARD,
    ];

    // Preset descriptions container.
    $form['layout']['preset_descriptions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['htl-typegrid-preset-descriptions'],
      ],
    ];

    $form['layout']['preset_descriptions']['info'] = [
      '#type' => 'markup',
      '#markup' => '<div class="htl-typegrid-preset-info">' .
        '<strong>' . t('Preset Info:') . '</strong><br>' .
        '<em>' . t('Standard') . ':</em> ' . t('Regular grid with equal-sized cards.') . '<br>' .
        '<em>' . t('Compact') . ':</em> ' . t('Smaller cards with reduced padding.') . '<br>' .
        '<em>' . t('Hero') . ':</em> ' . t('First card spans 2 columns, rest are normal.') . '<br>' .
        '<em>' . t('Featured') . ':</em> ' . t('First card is full-width, rest in grid below.') . '<br>' .
        '<em>' . t('Horizontal') . ':</em> ' . t('Cards with image on the side.') .
        '</div>',
    ];

    // Image position dropdown.
    $form['layout']['image_position'] = [
      '#type' => 'select',
      '#title' => t('Image Position'),
      '#description' => t('Choose where the image appears on each card.'),
      '#options' => GridConfig::getImagePositions(),
      '#default_value' => $config['image_position'] ?? GridConfig::IMAGE_TOP,
    ];

    // Wrapper container for side-by-side layout (columns & rows).
    $form['layout']['layout_row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['htl-typegrid-form-row'],
      ],
    ];

    $form['layout']['layout_row']['columns'] = [
      '#type' => 'number',
      '#title' => t('Columns'),
      '#default_value' => (int) ($config['columns'] ?? 3),
      '#min' => 1,
      '#max' => 6,
    ];

    $form['layout']['layout_row']['rows'] = [
      '#type' => 'number',
      '#title' => t('Rows'),
      '#default_value' => (int) ($config['rows'] ?? 2),
      '#min' => 1,
      '#max' => 50,
    ];

    // Card spacing option.
    $form['layout']['card_gap'] = [
      '#type' => 'select',
      '#title' => t('Card Spacing'),
      '#description' => t('Space between cards in the grid.'),
      '#options' => [
        'none' => t('None'),
        'small' => t('Small (0.5rem)'),
        'medium' => t('Medium (1rem)'),
        'large' => t('Large (1.5rem)'),
        'xlarge' => t('Extra Large (2rem)'),
      ],
      '#default_value' => $config['card_gap'] ?? 'medium',
    ];

    // Card border radius option.
    $form['layout']['card_radius'] = [
      '#type' => 'select',
      '#title' => t('Card Border Radius'),
      '#description' => t('Roundness of card corners.'),
      '#options' => [
        'none' => t('None (square)'),
        'small' => t('Small (8px)'),
        'medium' => t('Medium (16px)'),
        'large' => t('Large (24px)'),
        'pill' => t('Pill (32px)'),
      ],
      '#default_value' => $config['card_radius'] ?? 'medium',
    ];
  }

}
