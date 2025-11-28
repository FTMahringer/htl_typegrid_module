<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Service to manage HTL Grid fields on content types.
 */
final class GridFieldManager {
  use StringTranslationTrait;

  /**
   * Field name for the pinned checkbox.
   */
  public const FIELD_PINNED = 'field_htl_grid_pinned';

  /**
   * Field name for the image style selection.
   */
  public const FIELD_IMAGE_STYLE = 'field_htl_grid_image_style';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityDisplayRepositoryInterface $displayRepository,
  ) {}

  /**
   * Adds HTL Grid fields to a content type.
   *
   * @param string $bundle
   *   The node bundle to add fields to.
   *
   * @return bool
   *   TRUE if fields were added, FALSE if they already existed.
   */
  public function addImageStyleFieldsToBundle(string $bundle): bool {
    $added = false;

    // Create field storages if they don't exist
    $this->ensureFieldStoragesExist();

    // Add field instances to the bundle
    if (!$this->bundleHasField($bundle, self::FIELD_IMAGE_STYLE)) {
      $this->createStyleFieldInstance($bundle);
      $added = true;
    }

    if (!$this->bundleHasField($bundle, self::FIELD_PINNED)) {
      $this->createPinnedFieldInstance($bundle);
      $added = true;
    }

    if ($added) {
      $this->configureFormDisplay($bundle);
    }

    return $added;
  }

  /**
   * Checks if a bundle has a specific field.
   *
   * @param string $bundle
   *   The bundle name.
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if the field exists on the bundle.
   */
  private function bundleHasField(string $bundle, string $field_name): bool {
    $field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.' . $bundle . '.' . $field_name);

    return $field_config !== null;
  }

  /**
   * Ensures all required field storages exist.
   */
  private function ensureFieldStoragesExist(): void {
    $this->ensureStyleFieldStorageExists();
    $this->ensurePinnedFieldStorageExists();
  }

  /**
   * Creates the image style field storage if it doesn't exist.
   */
  private function ensureStyleFieldStorageExists(): void {
    $storage = $this->entityTypeManager->getStorage('field_storage_config');

    if (!$storage->load('node.' . self::FIELD_IMAGE_STYLE)) {
      FieldStorageConfig::create([
        'field_name' => self::FIELD_IMAGE_STYLE,
        'entity_type' => 'node',
        'type' => 'list_string',
        'cardinality' => 1,
        'settings' => [
          'allowed_values_function' => 'htl_typegrid_image_style_allowed_values',
        ],
      ])->save();
    }
  }

  /**
   * Creates the pinned checkbox field storage if it doesn't exist.
   */
  private function ensurePinnedFieldStorageExists(): void {
    $storage = $this->entityTypeManager->getStorage('field_storage_config');

    if (!$storage->load('node.' . self::FIELD_PINNED)) {
      FieldStorageConfig::create([
        'field_name' => self::FIELD_PINNED,
        'entity_type' => 'node',
        'type' => 'boolean',
        'cardinality' => 1,
        'settings' => [],
      ])->save();
    }
  }

  /**
   * Creates the style field instance for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  private function createStyleFieldInstance(string $bundle): void {
    FieldConfig::create([
      'field_name' => self::FIELD_IMAGE_STYLE,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $this->t('Grid Image Style'),
      'description' => $this->t('Select which image style to use when this node appears in the HTL Grid.'),
      'required' => FALSE,
      'default_value' => [['value' => 'default']],
    ])->save();
  }

  /**
   * Creates the pinned checkbox field instance for a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  private function createPinnedFieldInstance(string $bundle): void {
    FieldConfig::create([
      'field_name' => self::FIELD_PINNED,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $this->t('Pin for TypeGrid'),
      'description' => $this->t('When checked, this content will appear in the Pinned TypeGrid block and will be prioritized in the regular TypeGrid.'),
      'required' => FALSE,
      'default_value' => [['value' => 0]],
      'settings' => [
        'on_label' => $this->t('Pinned'),
        'off_label' => $this->t('Not pinned'),
      ],
    ])->save();
  }

  /**
   * Configures the form display for the HTL Grid fields.
   *
   * @param string $bundle
   *   The bundle name.
   */
  private function configureFormDisplay(string $bundle): void {
    $form_display = $this->displayRepository->getFormDisplay('node', $bundle, 'default');

    if ($form_display) {
      $form_display
        ->setComponent(self::FIELD_PINNED, [
          'type' => 'boolean_checkbox',
          'weight' => 99,
          'settings' => [
            'display_label' => TRUE,
          ],
        ])
        ->setComponent(self::FIELD_IMAGE_STYLE, [
          'type' => 'options_select',
          'weight' => 100,
          'settings' => [],
        ])
        ->save();
    }
  }

  /**
   * Removes HTL Grid fields from a bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  public function removeFieldsFromBundle(string $bundle): void {
    $fields = [
      self::FIELD_IMAGE_STYLE,
      self::FIELD_PINNED,
    ];

    foreach ($fields as $field_name) {
      $field_config = $this->entityTypeManager
        ->getStorage('field_config')
        ->load('node.' . $bundle . '.' . $field_name);

      if ($field_config) {
        $field_config->delete();
      }
    }
  }

  /**
   * Gets the image style settings from a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get settings from.
   *
   * @return array
   *   Array with 'style' key.
   */
  public function getNodeImageStyleSettings($node): array {
    $settings = [
      'style' => 'default',
    ];

    if ($node->hasField(self::FIELD_IMAGE_STYLE) && !$node->get(self::FIELD_IMAGE_STYLE)->isEmpty()) {
      $settings['style'] = $node->get(self::FIELD_IMAGE_STYLE)->value;
    }

    return $settings;
  }

  /**
   * Checks if a node is pinned for the TypeGrid.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node is pinned, FALSE otherwise.
   */
  public function isNodePinned($node): bool {
    if ($node->hasField(self::FIELD_PINNED) && !$node->get(self::FIELD_PINNED)->isEmpty()) {
      return (bool) $node->get(self::FIELD_PINNED)->value;
    }


    return false;

  }


  /**
   * Public check if a bundle has a specific field attached.
   *
   * This is a safe wrapper around the internal bundleHasField() to allow
   * external callers (other services or forms) to verify field existence.
   *
   * @param string $bundle
   *   The node bundle machine name.
   * @param string $field_name
   *   The field machine name (e.g., 'field_htl_grid_pinned').
   *
   * @return bool
   *   TRUE if the field exists on the bundle, FALSE otherwise.
   */

    public function hasField(string $bundle, string $field_name): bool {

      return $this->bundleHasField($bundle, $field_name);

    }


    /**
     * Build allowed field options for the pinned field selector.
     * Allowed: image fields, media image references, short text (string), datetime.
     * Disallowed: long text types (text_long, text_with_summary, string_long, text), and internal grid fields.
     *
     * @param string $bundle
     *   The node bundle machine name.
     *
     * @return array
     *   Options array: machine_name => "Label (machine) [type]".
     */
    public function getAllowedPinnedFieldOptions(string $bundle): array {
      $options = [];
      $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

      foreach ($definitions as $name => $def) {
        // Skip base fields.
        if ($def->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }

        // Skip internal TypeGrid fields.
        if (in_array($name, [
          self::FIELD_IMAGE_STYLE,
          self::FIELD_PINNED,
          'field_htl_grid_custom_width',
          'field_htl_grid_custom_height',
        ], true)) {
          continue;
        }

        $type = $def->getType();
        $allowed = false;

        // Images.
        if ($type === 'image') {
          $allowed = true;
        }

        // Media image references.
        if ($type === 'entity_reference' && $def->getSetting('target_type') === 'media') {
          $allowed = true;
        }

        // Short texts.
        if ($type === 'string') {
          $allowed = true;
        }

        // Datetime.
        if ($type === 'datetime') {
          $allowed = true;
        }

        // Exclude long texts explicitly.
        if (in_array($type, ['text_long', 'text_with_summary', 'string_long', 'text'], true)) {
          $allowed = false;
        }

        if ($allowed) {
          $label = (string) ($def->getLabel() ?? $name);
          $options[$name] = $label . ' (' . $name . ') [' . $type . ']';
        }
      }

      asort($options);
      return $options;
    }
  }
