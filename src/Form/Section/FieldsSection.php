<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the Fields section of the Grid block form.
 *
 * Important: do NOT call $form_state->getValues() here. When this builder is
 * invoked with a SubformState that has not been initialised with #parents,
 * calling getValues() will throw a RuntimeException. Instead read raw user
 * input via getUserInput() (safe during form build / AJAX) and fall back to
 * configuration.
 */
final class FieldsSection {

  /**
   * Build the section.
   *
   * @param array $form
   *   The form array (by reference).
   * @param array $config
   *   Block configuration.
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block plugin instance (unused here but kept for parity).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function build(array &$form, array $config, BlockPluginInterface $block, FormStateInterface $form_state): void {

    $form['fields'] = [
      '#type' => 'details',
      '#title' => t('Fields'),
      '#group' => 'settings',
      '#open' => TRUE,
    ];

    $form['fields']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'htl-typegrid-fields-wrapper'],
    ];

    // Determine the currently selected bundle.
    // Priority:
    // 1) user input (for AJAX updates during the form request)
    // 2) tempstore (for bundle changes that survive page reload after modal)
    // 3) saved block configuration
    $bundle = $config['bundle'] ?? NULL;

    // Safely read raw user input. Avoid getValues() on $form_state here.
    $input = [];
    if (method_exists($form_state, 'getUserInput')) {
      $input = $form_state->getUserInput();
    }

    // getUserInput() can sometimes return a ParameterBag or other object,
    // normalise to an array if necessary.
    if ($input instanceof \Symfony\Component\HttpFoundation\ParameterBag) {
      $input = $input->all();
    }
    elseif (is_object($input)) {
      // Cast objects to array where appropriate (defensive).
      $input = (array) $input;
    }

    // Check user input first (AJAX updates)
    if (is_array($input) && !empty($input['settings']['general']['bundle'])) {
      $bundle = $input['settings']['general']['bundle'];
    }
    // Then check tempstore (survives page reload after modal closes)
    else {
      $store = \Drupal::service('tempstore.private')->get('htl_grid');
      $tempBundle = $store->get('selected_bundle');
      if ($tempBundle) {
        $bundle = $tempBundle;
      }
    }

    if (empty($bundle)) {
      $form['fields']['wrapper']['msg'] = [
        '#markup' => t('Select a content type.'),
      ];
      return;
    }

    $store = \Drupal::service('tempstore.private')->get('htl_grid');

    // Get fields for the current bundle from tempstore or config.
    $fields = $store->get('picker.' . $bundle) ?? ($config['bundle_fields'][$bundle] ?? []);

    $rows = [];
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    foreach ($fields as $name) {
      $def = $definitions[$name] ?? NULL;

      $rows[] = [
        'label' => ['data' => $def?->getLabel() ?? $name],
        'machine' => ['data' => $name],
        'type' => ['data' => $def?->getType() ?? 'unknown'],
      ];
    }

    $form['fields']['wrapper']['table'] = [
      '#type' => 'table',
      '#header' => [
        'label' => t('Label'),
        'machine' => t('Machine name'),
        'type' => t('Type'),
      ],
      '#rows' => $rows,
      '#empty' => t('No fields selected.'),
    ];

    $url = Url::fromRoute('htl_typegrid.field_picker', ['bundle' => $bundle]);

    $form['fields']['wrapper']['button'] = [
      '#type' => 'link',
      '#title' => t('Choose fields'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['button', 'button--primary', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode(['width' => 700]),
      ],
    ];
  }

}
