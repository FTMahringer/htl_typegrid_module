<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;



use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\htl_typegrid\Model\GridNode;

use Drupal\htl_typegrid\Model\GridFieldValue;

use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\htl_typegrid\Model\GridFilters;

use Drupal\Core\Config\ConfigFactoryInterface;

use Drupal\Core\Entity\EntityRepositoryInterface;


final class GridQueryService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly GridFieldProcessor $fieldProcessor,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly GridFieldManager $fieldManager,
  ) {}

  /**
   * @return GridNode[]
   */
  public function loadNodes(GridConfig $config): array {
    $storage = $this->entityTypeManager->getStorage('node');

    // Basic query based on bundle
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $config->bundle)
      ->range(0, $config->rows * $config->columns);

    // Sort modes - match the form options in FilterSection
    $direction = $config->filters->direction;

    switch ($config->filters->sortMode) {
      case 'created':
        // Created date - use direction from settings
        $query->sort('created', $direction);
        break;

      case 'changed':
        // Last updated - use direction from settings
        $query->sort('changed', $direction);
        break;

      case 'title':
        // Title - use direction from settings
        $query->sort('title', $direction);
        break;

      case 'alpha':
        // Alphabetical on custom field - use direction from settings
        $query->sort($config->filters->alphaField, $direction);
        break;

      case 'random':
        // No sort → random after load
        break;

      // Legacy support for old config values
      case 'newest':
        $query->sort('created', 'DESC');
        break;

      case 'oldest':
        $query->sort('created', 'ASC');
        break;
    }

    $nids = $query->execute();
    if (!$nids) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);

    // Randomize in PHP if needed
    if ($config->filters->sortMode === 'random') {
      shuffle($nodes);
    }

    // Convert to DTOs
    $result = [];
    foreach ($nodes as $node) {
      $result[] = $this->buildGridNode($node, $config);
    }

    return $result;
  }

  private function buildGridNode($node, GridConfig $config): GridNode {
    $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString();

    $fields = [];
    $imageFieldValue = null;

    // Extract configured fields
    foreach ($config->fields as $fieldName) {
      if (!$node->hasField($fieldName)) {
        continue;
      }

      $value = $this->fieldProcessor->extractField($node, $fieldName);

      if ($value->isImage) {
        $imageFieldValue = $value;
      } else {
        $fields[] = $value;
      }
    }

    // If no image field was selected, try to find any image field on the node
    if ($imageFieldValue === null) {
      $imageFieldValue = $this->findNodeImageField($node);
      if ($imageFieldValue !== null) {
        \Drupal::logger('htl_typegrid')->notice(
          'Node @nid: Using auto-detected image field',
          ['@nid' => $node->id()]
        );
      }
    }

    // If still no image, use the fallback dummy image
    if ($imageFieldValue === null) {
      $imageFieldValue = $this->getFallbackImage();
      if ($imageFieldValue !== null) {
        \Drupal::logger('htl_typegrid')->notice(
          'Node @nid: Using fallback dummy image',
          ['@nid' => $node->id()]
        );
      } else {
        \Drupal::logger('htl_typegrid')->warning(
          'Node @nid: No image available (no field, no fallback configured)',
          ['@nid' => $node->id()]
        );
      }
    } else {
      \Drupal::logger('htl_typegrid')->notice(
        'Node @nid: Using selected image field',
        ['@nid' => $node->id()]
      );
    }

    // Extract image style settings from node
    $imageStyleSettings = $this->fieldManager->getNodeImageStyleSettings($node);


    return new GridNode(
      id: (int) $node->id(),
      bundle: $node->bundle(),
      title: $node->label(),
      url: $url,
      fields: $fields,
      image: $imageFieldValue,
      imageStyleSettings: $imageStyleSettings,
      pinned: $this->fieldManager->isNodePinned($node),
    );

  }

  /**
   * Finds the first image field on a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to search.
   *
   * @return GridFieldValue|null
   *   The image field value or null if none found.
   */
  /**
   * Loads pinned nodes for a given bundle.
   *
   * Reuses buildGridNode() so card building and image/style logic stay consistent.
   *
   * @param string $bundle
   *   The node bundle machine name.
   * @param int $limit
   *   Maximum number of pinned nodes to load (defaults to 3).
   * @param string[]|null $fieldNames
   *   Optional explicit list of field machine names to extract for meta. If null,
   *   no additional fields are extracted (image auto-detection still applies).
   *
   * @return \Drupal\htl_typegrid\Model\GridNode[]
   *   Array of GridNode DTOs.
   */
  public function loadPinnedNodes(string $bundle, int $limit = 3, ?array $fieldNames = null): array {
    $storage = $this->entityTypeManager->getStorage('node');

    // Query pinned nodes only for the given bundle.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $bundle)
      ->condition(GridFieldManager::FIELD_PINNED, 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    $nids = $query->execute();
    if (!$nids) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);

    // Build a minimal GridConfig to drive field extraction and image handling.
    $filters = new GridFilters(
      sortMode: 'created',
      alphaField: 'title',
      direction: 'DESC',
    );

    $config = new GridConfig(
      bundle: $bundle,
      columns: 1,
      rows: max(1, $limit),
      filters: $filters,
      fields: $fieldNames ?? [],
      cssClasses: [],
      imageSettings: [],
      layoutPreset: GridConfig::PRESET_STANDARD,
      imagePosition: GridConfig::IMAGE_TOP,
    );

    $result = [];
    foreach ($nodes as $node) {
      $result[] = $this->buildGridNode($node, $config);
    }

    return $result;
  }

  private function findNodeImageField($node): ?GridFieldValue {
    $fieldDefinitions = $node->getFieldDefinitions();

    foreach ($fieldDefinitions as $fieldName => $definition) {
      // Skip base fields
      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }

      $fieldType = $definition->getType();

      // Check for image fields
      if ($fieldType === 'image') {
        $field = $node->get($fieldName);
        if (!$field->isEmpty()) {
          return $this->fieldProcessor->extractField($node, $fieldName);
        }
      }

      // Check for media reference fields (media library)
      if ($fieldType === 'entity_reference' && $definition->getSetting('target_type') === 'media') {
        $field = $node->get($fieldName);
        if (!$field->isEmpty()) {
          $firstItem = $field->first();
          $media = $firstItem ? ($firstItem->entity ?? null) : null;
          if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
            return $this->fieldProcessor->extractField($node, $fieldName);
          }
        }
      }
    }

    return null;
  }

  /**
   * Gets the fallback dummy image from module configuration.
   *
   * @return GridFieldValue|null
   *   The fallback image field value or null if not configured.
   */
  private function getFallbackImage(): ?GridFieldValue {
    $config = $this->configFactory->get('htl_typegrid.settings');
    $uuid = (string) $config->get('fallback_media_uuid');

    if (!$uuid) {
      return null;
    }

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
    if (!$media) {
      return null;
    }

    $imageField = $media->get('field_media_image');
    if (!$imageField || $imageField->isEmpty()) {
      return null;
    }

    $item = $imageField->first();
    $file = $item ? ($item->entity ?? null) : null;

    if (!$file) {
      return null;
    }

    return new GridFieldValue(
      name: 'fallback_image',
      raw: $file,
      type: 'image',
      formatted: null,
      isImage: true,
      meta: [
        'alt' => (string) ($item->alt ?? 'Dummy Image'),
        'uri' => $file->getFileUri(),
      ],
    );
  }
}
