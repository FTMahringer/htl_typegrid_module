<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Resolves information from an existing HTL TypeGrid block (config entity).
 *
 * This helper centralizes "read-only" lookups for:
 * - The bundle (content type) the grid is configured to use.
 * - The configured meta fields for that bundle.
 * - A safe way to list available HTL Grid blocks (for dropdowns, etc.).
 *
 * It is intentionally defensive and will return empty values when:
 * - The block does not exist.
 * - It is not an HTL TypeGrid block.
 * - The expected "settings" are not present.
 */
final class GridBlockResolver {

  /**
   * The Block config entity storage ID.
   */
  private const BLOCK_ENTITY_TYPE = 'block';

  /**
   * The plugin ID for the main HTL TypeGrid block plugin.
   */
  private const GRID_PLUGIN_ID = 'htl_typegrid_block';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * Get the bundle machine name configured on a given HTL Grid block.
   *
   * @param string $blockId
   *   The config entity ID of the block (e.g. "my_theme_htl_grid_block").
   *
   * @return string|null
   *   The bundle machine name (e.g. "article") or NULL if not resolvable.
   */
  public function getBundleFromGridBlockId(string $blockId): ?string {
    $settings = $this->getGridSettings($blockId);
    if ($settings === null) {
      return null;
    }

    $bundle = (string) ($settings['bundle'] ?? '');
    return $bundle !== '' ? $bundle : null;
  }

  /**
   * Get the field machine names configured for the bundle on a grid block.
   *
   * This resolves the field selection that the source HTL Grid uses
   * for rendering card meta. If the bundle or configuration is missing,
   * an empty list is returned.
   *
   * @param string $blockId
   *   The config entity ID of the HTL Grid block.
   *
   * @return string[]
   *   A list of field machine names selected in the grid, or an empty list.
   */
  public function getConfiguredFieldsFromGridBlockId(string $blockId): array {
    $settings = $this->getGridSettings($blockId);
    if ($settings === null) {
      return [];
    }

    $bundle = (string) ($settings['bundle'] ?? '');
    if ($bundle === '') {
      return [];
    }

    $bundleFields = $settings['bundle_fields'][$bundle] ?? [];
    if (!is_array($bundleFields)) {
      return [];
    }

    // Normalize to a flat list of strings.
    $result = [];
    foreach ($bundleFields as $field) {
      if (is_string($field) && $field !== '') {
        $result[] = $field;
      }
    }

    return $result;
  }

  /**
   * Get the "settings" array from a TypeGrid block, or NULL if not valid.
   *
   * @param string $blockId
   *   The config entity ID of the block.
   *
   * @return array<string, mixed>|null
   *   The settings array, or NULL if the block doesn't exist or is not an
   *   HTL TypeGrid block.
   */
  public function getGridSettings(string $blockId): ?array {
    $block = $this->loadBlock($blockId);
    if (!$block) {
      return null;
    }

    // Verify the plugin is the HTL TypeGrid main block.
    $pluginId = null;
    if (method_exists($block, 'getPluginId')) {
      $pluginId = (string) $block->getPluginId();
    }
    else {
      // Fallback: config property (defensive).
      $pluginId = (string) ($block->get('plugin') ?? '');
    }

    if ($pluginId !== self::GRID_PLUGIN_ID) {
      return null;
    }

    $settings = $block->get('settings');
    return is_array($settings) ? $settings : null;
  }

  /**
   * Build a list of available HTL Grid blocks for selection UIs.
   *
   * Label format: "<Block Label> - <Bundle Label>".
   *
   * @return array<string, string>
   *   An array of block_id => label pairs.
   */
  public function listHtlGridBlockOptions(): array {
    $storage = $this->entityTypeManager->getStorage(self::BLOCK_ENTITY_TYPE);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $blocks */
    $blocks = $storage->loadMultiple();

    $bundleLabels = $this->bundleInfo->getBundleInfo('node');
    $options = [];

    foreach ($blocks as $block) {
      // Validate plugin type.
      $pluginId = method_exists($block, 'getPluginId') ? (string) $block->getPluginId() : (string) ($block->get('plugin') ?? '');
      if ($pluginId !== self::GRID_PLUGIN_ID) {
        continue;
      }

      $settings = $block->get('settings');
      if (!is_array($settings)) {
        continue;
      }

      $bundle = (string) ($settings['bundle'] ?? '');
      if ($bundle === '') {
        continue;
      }

      $bundleLabel = $bundleLabels[$bundle]['label'] ?? $bundle;
      $label = sprintf('%s - %s', $block->label(), $bundleLabel);
      $options[$block->id()] = $label;
    }

    asort($options);
    return $options;
  }

  /**
   * Safely load a Block config entity by ID.
   *
   * @param string $blockId
   *   The config entity ID.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|null
   *   The block entity or NULL if not found.
   */
  private function loadBlock(string $blockId): ?object {
    $storage = $this->entityTypeManager->getStorage(self::BLOCK_ENTITY_TYPE);
    $block = $storage->load($blockId);

    return is_object($block) ? $block : null;
  }

}
