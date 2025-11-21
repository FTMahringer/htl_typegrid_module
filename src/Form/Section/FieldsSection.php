<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\htl_typegrid\Form\Section\ValueReader;

final class FieldsSection {

  public function build(array &$form, array $config, BlockPluginInterface $block, FormStateInterface $form_state): void {

    $bundle = ValueReader::readValue($form, $form_state, ['general','bundle'], $config['bundle'] ?? null);

    $form['fields'] = [
      '#type' => 'details',
      '#title' => t('Fields'),
      '#group' => 'settings',
      '#open'  => true,
    ];

    $form['fields']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'htl-typegrid-fields-wrapper'],
    ];

    if (!$bundle) {
      $form['fields']['wrapper']['msg'] = [
        '#markup' => t('Please select a content type.'),
      ];
      return;
    }

    $store = \Drupal::service('tempstore.private')->get('htl_grid');
    $bundleFields = $store->get("picker.$bundle")
      ?? ($config['bundle_fields'][$bundle] ?? []);

    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', $bundle);

    $rows = [];
    foreach ($bundleFields as $fieldName) {
      $def = $definitions[$fieldName] ?? null;

      $rows[] = [
        'label' => ['data' => ['#markup' => '<strong>' . ($def?->getLabel() ?? $fieldName) . '</strong>']],
        'name'  => ['data' => ['#markup' => $fieldName]],
        'type'  => ['data' => ['#markup' => ($def?->getType() ?? 'unknown')]],
      ];
    }

    $form['fields']['wrapper']['table'] = [
      '#type' => 'table',
      '#header' => ['label'=>t('Label'), 'name'=>t('Machine name'), 'type'=>t('Field type')],
      '#rows'   => $rows,
      '#empty'  => t('No fields selected.'),
    ];

    $url = Url::fromRoute('htl_typegrid.field_picker', ['bundle' => $bundle]);

    $form['fields']['wrapper']['picker'] = [
      '#type' => 'link',
      '#title' => t('Choose fields'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['use-ajax','button','button--primary'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode(['width'=>700]),
      ],
    ];
  }
}
