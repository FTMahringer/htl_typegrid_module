<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\htl_typegrid\Form\Section\BundleSection;
use Drupal\htl_typegrid\Form\Section\LayoutSection;
use Drupal\htl_typegrid\Form\Section\FilterSection;
use Drupal\htl_typegrid\Form\Section\FieldsSection;

final class GridBlockFormBuilder {

  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  public function build(BlockPluginInterface $block, array $form, FormStateInterface $form_state): array {
    $config = $block->getConfiguration();

    $form['settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => t('Grid settings'),
    ];

    (new BundleSection($this->bundleInfo))->build($form, $config);
    (new LayoutSection())->build($form, $config);
    (new FilterSection())->build($form, $config);
    (new FieldsSection())->build($form, $config, $block, $form_state);

    return $form;
  }

  public function submit(BlockPluginInterface $block, array $form, FormStateInterface $form_state): void {
    $config = $block->getConfiguration();

    $values = $form_state->getValues();

    $config['bundle'] = $values['general']['bundle'] ?? null;
    $config['columns'] = (int) ($values['layout']['columns'] ?? 3);
    $config['rows']    = (int) ($values['layout']['rows'] ?? 2);

    $filters = $values['filters'] ?? [];
    $config['filters'] = [
      'sort_mode' => $filters['sort_mode'] ?? 'newest',
      'alpha_field' => $filters['alpha_field'] ?? 'title',
      'direction' => $filters['direction'] ?? 'ASC',
    ];

    // Tempstore persistieren
    if ($bundle = $config['bundle']) {
      $store = \Drupal::service('tempstore.private')->get('htl_grid');
      if ($picked = $store->get("picker.$bundle")) {
        $config['bundle_fields'][$bundle] = $picked;
      }
    }

    $block->setConfiguration($config);
  }

  public static function bundleChangeAjax(array &$form, FormStateInterface $form_state): array {
    return $form['settings']['fields']['wrapper'];
  }
}
