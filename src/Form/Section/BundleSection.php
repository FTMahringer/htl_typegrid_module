<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form\Section;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;

final class BundleSection {

  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  public function build(array &$form, array $config): void {
    $savedBundle = $config['bundle'] ?? '';

    // Check if there's a bundle selection in tempstore (from AJAX change or modal)
    $store = \Drupal::service('tempstore.private')->get('htl_grid');
    $tempBundle = $store->get('selected_bundle');

    // Use tempstore bundle if available, otherwise use saved config
    $selected = $tempBundle ?: $savedBundle;

    $options = [];
    foreach ($this->bundleInfo->getBundleInfo('node') as $machine => $info) {
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
      '#ajax' => [
        'callback' => [static::class, 'bundleChangeAjax'],
        'wrapper' => 'htl-typegrid-fields-wrapper',
        'event' => 'change',
      ],
    ];
  }

  public static function bundleChangeAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Get the newly selected bundle from form state
    $values = $form_state->getValues();
    $newBundle = $values['general']['bundle'] ?? $values['settings']['general']['bundle'] ?? null;

    if ($newBundle) {
      $store = \Drupal::service('tempstore.private')->get('htl_grid');

      // Store the newly selected bundle in tempstore
      // This ensures when the modal closes and page reloads, we remember the new bundle
      $store->set('selected_bundle', $newBundle);

      // Clear the field picker selection for the new bundle (so old fields don't persist)
      $store->delete('picker.' . $newBundle);

      \Drupal::logger('htl_typegrid')->notice(
        'Bundle changed to @bundle, stored in tempstore and cleared field selection',
        ['@bundle' => $newBundle]
      );
    }

    // Return the updated fields wrapper
    $response->addCommand(new ReplaceCommand(
      '#htl-typegrid-fields-wrapper',
      $form['fields']['wrapper'] ?? $form['settings']['fields']['wrapper']
    ));

    return $response;
  }
}
