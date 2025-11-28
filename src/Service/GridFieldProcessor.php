<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\htl_typegrid\Model\GridFieldValue;
use Drupal\node\NodeInterface;

final class GridFieldProcessor {

  public function extractField(NodeInterface $node, string $fieldName): GridFieldValue {
    $field = $node->get($fieldName);
    $fieldDefinition = $field->getFieldDefinition();
    $fieldType = $fieldDefinition->getType();

    \Drupal::logger('htl_typegrid')->debug(
      'Extracting field @field (type: @type) from node @nid',
      [
        '@field' => $fieldName,
        '@type' => $fieldType,
        '@nid' => $node->id(),
      ]
    );

    // Detect direct image fields
    if ($fieldType === 'image') {
      $item = $field->first();
      $file = $item ? ($item->entity ?? null) : null;

      return new GridFieldValue(
        name: $fieldName,
        raw: $file,
        type: 'image',
        formatted: null,
        isImage: true,
        meta: [
          'alt' => $item ? ($item->alt ?? '') : '',
          'uri' => $file?->getFileUri() ?? '',
        ],
      );
    }

    // Detect media reference fields (media library)
    if ($fieldType === 'entity_reference' && $fieldDefinition->getSetting('target_type') === 'media') {
      $item = $field->first();
      $media = $item ? ($item->entity ?? null) : null;

      \Drupal::logger('htl_typegrid')->notice(
        'Found media reference field @field on node @nid, media loaded: @loaded',
        [
          '@field' => $fieldName,
          '@nid' => $node->id(),
          '@loaded' => $media ? 'YES (ID: ' . $media->id() . ')' : 'NO',
        ]
      );

      if ($media && $media->hasField('field_media_image')) {
        $imageField = $media->get('field_media_image');
        if (!$imageField->isEmpty()) {
          $imageItem = $imageField->first();
          $file = $imageItem ? ($imageItem->entity ?? null) : null;

          if ($file) {
            \Drupal::logger('htl_typegrid')->notice(
              'Media @mid has image file @fid with URI: @uri',
              [
                '@mid' => $media->id(),
                '@fid' => $file->id(),
                '@uri' => $file->getFileUri(),
              ]
            );

            return new GridFieldValue(
              name: $fieldName,
              raw: $file,
              type: 'image',
              formatted: null,
              isImage: true,
              meta: [
                'alt' => (string) ($imageItem->alt ?? ''),
                'uri' => $file->getFileUri(),
              ],
            );
          }
        } else {
          \Drupal::logger('htl_typegrid')->warning(
            'Media @mid exists but field_media_image is empty',
            ['@mid' => $media->id()]
          );
        }
      }

      // Media reference without image - treat as non-image field
      return new GridFieldValue(
        name: $fieldName,
        raw: $media?->label() ?? '',
        type: 'entity_reference',
        formatted: $media?->label() ?? '',
        isImage: false,
        meta: [],
      );
    }

    // Generic text / number / other field
    $value = $field->value ?? '';

    return new GridFieldValue(
      name: $fieldName,
      raw: $value,
      type: $fieldType,
      formatted: (string) $value,
      isImage: false,
      meta: [],
    );
  }
}
