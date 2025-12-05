<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\htl_typegrid\Form\Section\BundleSection;
use Drupal\htl_typegrid\Form\Section\LayoutSection;
use Drupal\htl_typegrid\Form\Section\FilterSection;
use Drupal\htl_typegrid\Form\Section\FieldsSection;
use Drupal\htl_typegrid\Service\GridFieldManager;
use Drupal\htl_typegrid\Model\GridConfig;

final class GridBlockFormBuilder {

  public function __construct(
    private readonly BundleSection $bundleSection,
    private readonly LayoutSection $layoutSection,
    private readonly FilterSection $filterSection,
    private readonly FieldsSection $fieldsSection,
    private readonly GridFieldManager $fieldManager,
  ) {}

  /**
   * Block-Formular aufbauen.
   */
  public function build(BlockPluginInterface $block, array $form, FormStateInterface $form_state): array {
    $config = $block->getConfiguration();

    // wichtig, damit die verschachtelten Arrays so bleiben
    $form['#tree'] = TRUE;

    // Attach admin library for form styling.
    $form['#attached']['library'][] = 'htl_typegrid/admin';

    $form['settings'] = [
      '#type'  => 'vertical_tabs',
      '#title' => t('Grid settings'),
    ];

    $this->bundleSection->build($form, $config);
    $this->layoutSection->build($form, $config);
    $this->filterSection->build($form, $config);
    $this->fieldsSection->build($form, $config, $block, $form_state);

    return $form;
  }

  /**
   * Block-Formular speichern.
   */
  public function submit(BlockPluginInterface $block, array $form, FormStateInterface $form_state): void {
    $config = $block->getConfiguration();

    // Get form values - try multiple sources
    $values = $form_state->getValues();
    $userInput = $form_state->getUserInput();


    \Drupal\htl_typegrid\Helper\DebugLogger::notice(
      'SUBMIT - values keys: @vkeys, userInput keys: @ukeys',

      [

        '@vkeys' => implode(', ', array_keys($values)),
        '@ukeys' => implode(', ', array_keys($userInput)),
      ]
    );

    // Extract sections - check multiple possible locations
    // Priority: form values > user input (settings nested) > user input (direct) > existing config

    // GENERAL section (for bundle)
    $general = $this->extractSection($values, $userInput, 'general');

    // LAYOUT section
    $layout = $this->extractSection($values, $userInput, 'layout');

    // FILTERS section
    $filtersInput = $this->extractSection($values, $userInput, 'filters');

    \Drupal\htl_typegrid\Helper\DebugLogger::notice(
      'SUBMIT - Extracted layout keys: @keys',
      ['@keys' => is_array($layout) ? implode(', ', array_keys($layout)) : 'EMPTY']
    );

    // === Extract BUNDLE ===
    $oldBundle = (string) ($config['bundle'] ?? '');
    $bundle = $oldBundle;
    if (is_array($general) && !empty($general['bundle'])) {
      $bundle = (string) $general['bundle'];
    }

    // === Extract LAYOUT values ===
    // Start with existing config values as defaults
    $columns = (int) ($config['columns'] ?? 3);
    $rows = (int) ($config['rows'] ?? 2);
    $layoutPreset = (string) ($config['layout_preset'] ?? GridConfig::PRESET_STANDARD);
    $imagePosition = (string) ($config['image_position'] ?? GridConfig::IMAGE_TOP);
    $cardGap = (string) ($config['card_gap'] ?? 'medium');
    $cardRadius = (string) ($config['card_radius'] ?? 'medium');

    if (is_array($layout) && !empty($layout)) {
      // Columns and rows might be in layout_row container
      if (isset($layout['layout_row']) && is_array($layout['layout_row'])) {
        $layoutRow = $layout['layout_row'];
        if (isset($layoutRow['columns'])) {
          $columns = (int) $layoutRow['columns'];
        }
        if (isset($layoutRow['rows'])) {
          $rows = (int) $layoutRow['rows'];
        }
      }

      // Direct layout values
      if (isset($layout['layout_preset']) && $layout['layout_preset'] !== '') {
        $layoutPreset = (string) $layout['layout_preset'];
      }
      if (isset($layout['image_position']) && $layout['image_position'] !== '') {
        $imagePosition = (string) $layout['image_position'];
      }
      if (isset($layout['card_gap']) && $layout['card_gap'] !== '') {
        $cardGap = (string) $layout['card_gap'];
      }
      if (isset($layout['card_radius']) && $layout['card_radius'] !== '') {
        $cardRadius = (string) $layout['card_radius'];
      }
    }


    \Drupal\htl_typegrid\Helper\DebugLogger::notice(
      'SUBMIT - Values: preset=@preset, imgPos=@imgPos, gap=@gap, radius=@radius, cols=@cols, rows=@rows',
      [
        '@preset' => $layoutPreset,
        '@imgPos' => $imagePosition,
        '@gap' => $cardGap,
        '@radius' => $cardRadius,
        '@cols' => $columns,
        '@rows' => $rows,
      ]
    );


    // Check if bundle has changed
    $bundleChanged = ($bundle !== $oldBundle && $oldBundle !== '');

    // === Save configuration ===
    $config['bundle'] = $bundle;
    $config['columns'] = $columns;
    $config['rows'] = $rows;
    $config['layout_preset'] = $layoutPreset;
    $config['image_position'] = $imagePosition;
    $config['card_gap'] = $cardGap;
    $config['card_radius'] = $cardRadius;

    // === Extract FILTER values ===
    $sortMode = (string) ($config['filters']['sort_mode'] ?? 'created');
    $alphaField = (string) ($config['filters']['alpha_field'] ?? 'title');
    $direction = (string) ($config['filters']['direction'] ?? 'ASC');

    if (is_array($filtersInput) && !empty($filtersInput)) {
      // Filter values might be in 'row' container
      $filterRow = $filtersInput['row'] ?? $filtersInput;
      if (is_array($filterRow)) {
        if (isset($filterRow['sort_mode']) && $filterRow['sort_mode'] !== '') {
          $sortMode = (string) $filterRow['sort_mode'];
        }
        if (isset($filterRow['alpha_field']) && $filterRow['alpha_field'] !== '') {
          $alphaField = (string) $filterRow['alpha_field'];
        }
        if (isset($filterRow['direction']) && $filterRow['direction'] !== '') {
          $direction = (string) $filterRow['direction'];
        }
      }
    }

    $config['filters'] = [
      'sort_mode' => $sortMode,
      'alpha_field' => $alphaField,
      'direction' => $direction,
    ];

    // Automatically add image style fields to the bundle
    if ($bundle) {
      $added = $this->fieldManager->addImageStyleFieldsToBundle($bundle);
      if ($added) {
        \Drupal\htl_typegrid\Helper\DebugLogger::notice(
          'Added image style fields to bundle @bundle',
          ['@bundle' => $bundle]
        );
      }
    }

    // Handle bundle fields
    if ($bundle) {
      // Initialize bundle_fields if needed
      if (!isset($config['bundle_fields'])) {
        $config['bundle_fields'] = [];
      }

      $store = \Drupal::service('tempstore.private')->get('htl_grid');
      $picked = $store->get('picker.' . $bundle) ?? [];
      $existing = $config['bundle_fields'][$bundle] ?? [];

      // If bundle changed, clear old bundle fields
      if ($bundleChanged) {
        $config['bundle_fields'][$bundle] = [];
        \Drupal::logger('htl_typegrid')->notice('Bundle changed from @old to @new, cleared fields', [
          '@old' => $oldBundle,
          '@new' => $bundle,
        ]);
      }
      // If there are new picked fields from the picker, use them
      elseif (!empty($picked)) {
        $config['bundle_fields'][$bundle] = $picked;
        $store->delete('picker.' . $bundle);
        \Drupal::logger('htl_typegrid')->notice('Using picked fields: @fields', ['@fields' => implode(', ', $picked)]);
      }
      // Otherwise, preserve existing fields
      else {
        $config['bundle_fields'][$bundle] = $existing;
      }
    }


    \Drupal\htl_typegrid\Helper\DebugLogger::notice(
      'FINAL SAVE: bundle=@b, cols=@c, rows=@r, preset=@preset, imgPos=@imgPos',
      [
        '@b' => $config['bundle'],
        '@c' => $config['columns'],
        '@r' => $config['rows'],
        '@preset' => $config['layout_preset'],
        '@imgPos' => $config['image_position'],
      ]
    );

    // Clear tempstore bundle selection after saving
    // This ensures the saved config is used on next load, not the tempstore value
    $store = \Drupal::service('tempstore.private')->get('htl_grid');
    $store->delete('selected_bundle');
    $block->setConfiguration($config);
  }

  /**
   * Extract a section from form values or user input.
   *
   * @param array $values
   *   Form state values.
   * @param array $userInput
   *   Raw user input.
   * @param string $section
   *   Section name (e.g., 'layout', 'general', 'filters').
   *
   * @return array
   *   The extracted section data.
   */
  private function extractSection(array $values, array $userInput, string $section): array {
    // Try form values first (direct)
    if (isset($values[$section]) && is_array($values[$section])) {
      return $values[$section];
    }

    // Try user input with 'settings' wrapper (common in block forms)
    if (isset($userInput['settings'][$section]) && is_array($userInput['settings'][$section])) {
      return $userInput['settings'][$section];
    }

    // Try user input direct
    if (isset($userInput[$section]) && is_array($userInput[$section])) {
      return $userInput[$section];
    }
    return [];
  }
}
