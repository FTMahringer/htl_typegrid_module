<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Installiert die extra Bildstil-Felder für Bundles, die im Grid verwendet werden.
 */
final class GridExtraFieldInstaller {

  public function __construct(
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Sorgt dafür, dass die Bildstil-Felder für das gegebene Bundle existieren.
   *
   * - Dropdown: field_htl_grid_image_style (list_string, allowed_values_function)
   * - Width:    field_htl_grid_custom_width (integer)
   * - Height:   field_htl_grid_custom_height (integer)
   *
   * Nur wenn das Bundle ein Bild/Media-Feld hat.
   */
  public function ensureImageStyleFieldsForBundle(string $bundle): void {
    $entity_type = 'node';

    // 0) Nur weitermachen, wenn der Inhaltstyp überhaupt ein Bild-/Media-Feld hat.
    if (!$this->bundleHasImageOrMediaField($bundle)) {
      return;
    }

    // --- 1) Dropdown-Feld für Bildstil-Auswahl ---
    $style_field_name = 'field_htl_grid_image_style';

    if (!FieldStorageConfig::loadByName($entity_type, $style_field_name)) {
      FieldStorageConfig::create([
        'field_name'  => $style_field_name,
        'entity_type' => $entity_type,
        'type'        => 'list_string',
        'settings'    => [
          // allowed_values_function liefert Default, Custom + alle Bildstile.
          'allowed_values_function' => 'htl_typegrid_image_style_allowed_values',
        ],
        'cardinality' => 1,
        'translatable' => FALSE,
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $style_field_name)) {
      FieldConfig::create([
        'field_name'  => $style_field_name,
        'entity_type' => $entity_type,
        'bundle'      => $bundle,
        'label'       => 'HTL Grid Bildstil',
        'required'    => FALSE,
        'settings'    => [],
      ])->save();
    }

    // --- 2) Custom Width / Height als Integer-Felder ---
    $width_field_name = 'field_htl_grid_custom_width';
    $height_field_name = 'field_htl_grid_custom_height';

    if (!FieldStorageConfig::loadByName($entity_type, $width_field_name)) {
      FieldStorageConfig::create([
        'field_name'  => $width_field_name,
        'entity_type' => $entity_type,
        'type'        => 'integer',
        'settings'    => [
          'min' => 1,
        ],
        'cardinality' => 1,
        'translatable' => FALSE,
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $width_field_name)) {
      FieldConfig::create([
        'field_name'  => $width_field_name,
        'entity_type' => $entity_type,
        'bundle'      => $bundle,
        'label'       => 'HTL Grid Custom Width',
        'required'    => FALSE,
        'settings'    => [],
      ])->save();
    }

    if (!FieldStorageConfig::loadByName($entity_type, $height_field_name)) {
      FieldStorageConfig::create([
        'field_name'  => $height_field_name,
        'entity_type' => $entity_type,
        'type'        => 'integer',
        'settings'    => [
          'min' => 1,
        ],
        'cardinality' => 1,
        'translatable' => FALSE,
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $height_field_name)) {
      FieldConfig::create([
        'field_name'  => $height_field_name,
        'entity_type' => $entity_type,
        'bundle'      => $bundle,
        'label'       => 'HTL Grid Custom Height',
        'required'    => FALSE,
        'settings'    => [],
      ])->save();
    }

    // --- 3) Form / View Display konfigurieren ---

    // Node-Formular.
    $form_display = $this->entityDisplayRepository->getFormDisplay(
      $entity_type,
      $bundle,
      'default'
    );

    $form_display
      ->setComponent($style_field_name, [
        'type' => 'options_select',
        'weight' => 40,
      ])
      ->setComponent($width_field_name, [
        'type' => 'number',
        'weight' => 41,
        'settings' => [
          'min' => 1,
          'step' => 1,
        ],
      ])
      ->setComponent($height_field_name, [
        'type' => 'number',
        'weight' => 42,
        'settings' => [
          'min' => 1,
          'step' => 1,
        ],
      ])
      ->save();

    // View Display (Frontend) – kannst du bei Bedarf anpassen oder nur intern nutzen.
    $view_display = $this->entityDisplayRepository->getViewDisplay(
      $entity_type,
      $bundle,
      'default'
    );

    $view_display
      ->setComponent($style_field_name, [
        'label' => 'hidden',
        'type'  => 'string',
        'weight'=> 40,
      ])
      ->setComponent($width_field_name, [
        'label' => 'hidden',
        'type'  => 'number_integer',
        'weight'=> 41,
      ])
      ->setComponent($height_field_name, [
        'label' => 'hidden',
        'type'  => 'number_integer',
        'weight'=> 42,
      ])
      ->save();
  }

  /**
   * Checkt, ob das Bundle ein Bild- oder Media-Feld hat.
   */
  private function bundleHasImageOrMediaField(string $bundle): bool {
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);

    foreach ($fields as $fieldDefinition) {
      $type = $fieldDefinition->getType();

      // Klassisches Image-Feld.
      if ($type === 'image') {
        return TRUE;
      }

      // Media-Referenz.
      if ($type === 'entity_reference') {
        $settings = $fieldDefinition->getSettings();
        if (($settings['target_type'] ?? '') === 'media') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
