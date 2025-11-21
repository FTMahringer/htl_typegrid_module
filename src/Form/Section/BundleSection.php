<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

final class BundleSection {

  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  public function build(array &$form, array $config): void {
    $selected = $config['bundle'] ?? '';
    $bundles = $this->bundleInfo->getBundleInfo('node');

    $options = [];
    foreach ($bundles as $machine => $info) {
      $options[$machine] = $info['label'] ?? $machine;
    }

    $form['general'] = [
      '#type' => 'details',
      '#title' => t('Content'),
      '#group' => 'settings',
      '#open' => true,
    ];

    $form['general']['bundle'] = [
      '#type' => 'select',
      '#title' => t('Content type'),
      '#options' => $options,
      '#empty_option' => t('- Select -'),
      '#default_value' => $selected,
      '#required' => true,
      '#ajax' => [
        'callback' => ['Drupal\htl_typegrid\Form\GridBlockFormBuilder', 'bundleChangeAjax'],
        'wrapper' => 'htl-typegrid-fields-wrapper',
      ],
    ];
  }
}
