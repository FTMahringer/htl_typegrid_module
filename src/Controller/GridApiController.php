<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\htl_typegrid\Model\GridFilters;
use Drupal\htl_typegrid\Model\GridNode;
use Drupal\htl_typegrid\Service\GridCardBuilder;
use Drupal\htl_typegrid\Service\GridFieldManager;
use Drupal\htl_typegrid\Service\GridBlockResolver;
use Drupal\htl_typegrid\Service\GridFieldProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * REST API Controller for TypeGrid.
 *
 * Provides endpoints for:
 * - GET: List nodes with filtering and pagination
 * - GET (single): Get a single node
 * - POST: Create new content
 * - PATCH: Update existing content
 * - DELETE: Remove content
 */
final class GridApiController extends ControllerBase {

  /**
   * Default items per page.
   */
  private const DEFAULT_LIMIT = 12;

  /**
   * Maximum items per page.
   */
  private const MAX_LIMIT = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly EntityTypeBundleInfoInterface $bundleInfoService,
    private readonly GridCardBuilder $cardBuilder,
    private readonly GridFieldManager $fieldManager,
    private readonly GridBlockResolver $blockResolver,
    private readonly GridFieldProcessor $fieldProcessor,
    private readonly AccountProxyInterface $currentUserService,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('htl_grid.card_builder'),
      $container->get('htl_grid.field_manager'),
      $container->get('htl_grid.block_resolver'),
      $container->get('htl_grid.field_processor'),
      $container->get('current_user'),
    );
  }

  /**
   * List nodes of a bundle with filtering and pagination.
   *
   * GET /api/typegrid/{bundle}
   *
   * Query parameters:
   * - page: Page number (default: 1)
   * - limit: Items per page (default: 12, max: 100)
   * - sort: Sort field (created, changed, title)
   * - direction: Sort direction (ASC, DESC)
   * - search: Search in title
   * - date_from: Filter by created date (YYYY-MM-DD)
   * - date_to: Filter by created date (YYYY-MM-DD)
   * - taxonomy[field_name]: Filter by taxonomy term ID
   * - format: Response format (full, minimal, ids)
   *
   * @param string $bundle
   *   The content type machine name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with nodes data.
   */
  public function list(string $bundle, Request $request): JsonResponse {
    // Validate bundle.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      return $this->errorResponse('Bundle not found', 404);
    }

    // Extract parameters.
    $page = max(1, (int) $request->query->get('page', 1));
    $limit = min(self::MAX_LIMIT, max(1, (int) $request->query->get('limit', self::DEFAULT_LIMIT)));
    $format = $request->query->get('format', 'full');

    $filters = [
      'search' => trim((string) $request->query->get('search', '')),
      'sort' => $request->query->get('sort', 'created'),
      'direction' => strtoupper($request->query->get('direction', 'DESC')),
      'date_from' => $request->query->get('date_from', ''),
      'date_to' => $request->query->get('date_to', ''),
      'taxonomy' => $request->query->all('taxonomy') ?: [],
    ];

    // Validate direction.
    if (!in_array($filters['direction'], ['ASC', 'DESC'])) {
      $filters['direction'] = 'DESC';
    }

    try {
      $result = $this->loadNodes($bundle, $filters, $page, $limit, $format);

      return new JsonResponse([
        'success' => true,
        'data' => $result['items'],
        'meta' => [
          'bundle' => $bundle,
          'bundle_label' => $bundles[$bundle]['label'] ?? $bundle,
          'total' => $result['total'],
          'page' => $page,
          'limit' => $limit,
          'total_pages' => $result['total_pages'],
          'has_more' => $page < $result['total_pages'],
          'filters' => $filters,
        ],
        'links' => $this->buildPaginationLinks($bundle, $page, $result['total_pages'], $filters, $limit),
      ]);
    }
    catch (\Exception $e) {
      return $this->errorResponse('Error loading nodes: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Get a single node by ID.
   *
   * GET /api/typegrid/{bundle}/{nid}
   *
   * @param string $bundle
   *   The content type machine name.
   * @param int $nid
   *   The node ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with node data.
   */
  public function get(string $bundle, int $nid, Request $request): JsonResponse {
    // Validate bundle.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      return $this->errorResponse('Bundle not found', 404);
    }

    $storage = $this->entityTypeManagerService->getStorage('node');
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $storage->load($nid);

    if (!$node instanceof NodeInterface) {
      return $this->errorResponse('Node not found', 404);
    }

    if ($node->bundle() !== $bundle) {
      return $this->errorResponse('Node does not belong to the specified bundle', 400);
    }


    if (!$node->isPublished() && !$this->currentUserService->hasPermission('view unpublished content')) {

      return $this->errorResponse('Access denied', 403);
    }

    $format = $request->query->get('format', 'full');

    return new JsonResponse([
      'success' => true,
      'data' => $this->formatNode($node, $format),
    ]);
  }

  /**
   * Create a new node.
   *
   * POST /api/typegrid/{bundle}
   *
   * Request body (JSON):
   * {
   *   "title": "Node title",
   *   "status": 1,
   *   "fields": {
   *     "field_name": "value",
   *     ...
   *   }
   * }
   *
   * @param string $bundle
   *   The content type machine name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with created node data.
   */
  public function createNode(string $bundle, Request $request): JsonResponse {
    // Check permissions.
    if (!$this->currentUserService->hasPermission('create ' . $bundle . ' content')) {
      return $this->errorResponse('Access denied. You do not have permission to create this content type.', 403);
    }

    // Validate bundle.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      return $this->errorResponse('Bundle not found', 404);
    }

    // Parse request body.
    $data = json_decode($request->getContent(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->errorResponse('Invalid JSON in request body', 400);
    }

    // Validate required fields.
    if (empty($data['title'])) {
      return $this->errorResponse('Title is required', 400);
    }

    try {
      $storage = $this->entityTypeManagerService->getStorage('node');

      // Build node values.
      $values = [
        'type' => $bundle,
        'title' => $data['title'],
        'status' => $data['status'] ?? 1,
        'uid' => $this->currentUserService->id(),
      ];

      // Set the "Show in TypeGrid" field to enabled by default.
      if ($this->fieldManager->hasField($bundle, GridFieldManager::FIELD_SHOW)) {
        $values[GridFieldManager::FIELD_SHOW] = $data['show_in_grid'] ?? 1;
      }

      // Set pinned status if provided.
      if ($this->fieldManager->hasField($bundle, GridFieldManager::FIELD_PINNED)) {
        $values[GridFieldManager::FIELD_PINNED] = $data['pinned'] ?? 0;
      }

      // Add custom fields.
      if (!empty($data['fields']) && is_array($data['fields'])) {
        foreach ($data['fields'] as $fieldName => $value) {
          // Skip internal grid fields - they're handled above.
          if (in_array($fieldName, [GridFieldManager::FIELD_SHOW, GridFieldManager::FIELD_PINNED, GridFieldManager::FIELD_IMAGE_STYLE])) {
            continue;
          }
          $values[$fieldName] = $value;
        }
      }

      /** @var \Drupal\node\NodeInterface $node */
      $node = $storage->create($values);
      $node->save();

      return new JsonResponse([
        'success' => true,
        'message' => 'Node created successfully',
        'data' => $this->formatNode($node, 'full'),
      ], 201);
    }
    catch (\Exception $e) {
      return $this->errorResponse('Error creating node: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Update an existing node.
   *
   * PATCH /api/typegrid/{bundle}/{nid}
   *
   * @param string $bundle
   *   The content type machine name.
   * @param int $nid
   *   The node ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with updated node data.
   */
  public function update(string $bundle, int $nid, Request $request): JsonResponse {
    // Validate bundle.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      return $this->errorResponse('Bundle not found', 404);
    }

    $storage = $this->entityTypeManagerService->getStorage('node');
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $storage->load($nid);

    if (!$node instanceof NodeInterface) {
      return $this->errorResponse('Node not found', 404);
    }

    if ($node->bundle() !== $bundle) {
      return $this->errorResponse('Node does not belong to the specified bundle', 400);
    }

    // Check permissions.
    if (!$node->access('update', $this->currentUserService)) {
      return $this->errorResponse('Access denied. You do not have permission to update this content.', 403);
    }

    // Parse request body.
    $data = json_decode($request->getContent(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->errorResponse('Invalid JSON in request body', 400);
    }

    try {
      // Update fields.
      if (isset($data['title'])) {
        $node->setTitle($data['title']);
      }

      if (isset($data['status'])) {
        if ($data['status']) {
          $node->setPublished();
        }
        else {
          $node->setUnpublished();
        }
      }

      if (isset($data['show_in_grid']) && $node->hasField(GridFieldManager::FIELD_SHOW)) {
        $node->set(GridFieldManager::FIELD_SHOW, $data['show_in_grid']);
      }

      if (isset($data['pinned']) && $node->hasField(GridFieldManager::FIELD_PINNED)) {
        $node->set(GridFieldManager::FIELD_PINNED, $data['pinned']);
      }

      // Update custom fields.
      if (!empty($data['fields']) && is_array($data['fields'])) {
        foreach ($data['fields'] as $fieldName => $value) {
          if ($node->hasField($fieldName)) {
            $node->set($fieldName, $value);
          }
        }
      }

      $node->save();

      return new JsonResponse([
        'success' => true,
        'message' => 'Node updated successfully',
        'data' => $this->formatNode($node, 'full'),
      ]);
    }
    catch (\Exception $e) {
      return $this->errorResponse('Error updating node: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Delete a node.
   *
   * DELETE /api/typegrid/{bundle}/{nid}
   *
   * @param string $bundle
   *   The content type machine name.
   * @param int $nid
   *   The node ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response confirming deletion.
   */
  public function delete(string $bundle, int $nid): JsonResponse {
    // Validate bundle.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      return $this->errorResponse('Bundle not found', 404);
    }

    $storage = $this->entityTypeManagerService->getStorage('node');
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $storage->load($nid);

    if (!$node instanceof NodeInterface) {
      return $this->errorResponse('Node not found', 404);
    }

    if ($node->bundle() !== $bundle) {
      return $this->errorResponse('Node does not belong to the specified bundle', 400);
    }

    // Check permissions.
    if (!$node->access('delete', $this->currentUserService)) {
      return $this->errorResponse('Access denied. You do not have permission to delete this content.', 403);
    }

    try {
      $node->delete();

      return new JsonResponse([
        'success' => true,
        'message' => 'Node deleted successfully',
        'data' => ['nid' => $nid],
      ]);
    }
    catch (\Exception $e) {
      return $this->errorResponse('Error deleting node: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Get available bundles that have TypeGrid configured.
   *
   * GET /api/typegrid
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with available bundles.
   */
  public function listBundles(): JsonResponse {
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    $availableBundles = [];

    foreach ($bundles as $bundleId => $bundleInfo) {
      // Check if this bundle has TypeGrid fields.
      if ($this->fieldManager->hasField($bundleId, GridFieldManager::FIELD_SHOW)) {
        $availableBundles[] = [
          'id' => $bundleId,
          'label' => $bundleInfo['label'] ?? $bundleId,
          'endpoints' => [
            'list' => Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundleId])->setAbsolute()->toString(),
            'view' => Url::fromRoute('htl_typegrid.view', ['bundle' => $bundleId])->setAbsolute()->toString(),
          ],
        ];
      }
    }

    return new JsonResponse([
      'success' => true,
      'data' => $availableBundles,
    ]);
  }

  /**
   * Load nodes with filtering and pagination.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param array $filters
   *   Filter parameters.
   * @param int $page
   *   Page number.
   * @param int $limit
   *   Items per page.
   * @param string $format
   *   Response format (full, minimal, ids).
   *
   * @return array
   *   Array with 'items', 'total', 'total_pages'.
   */
  private function loadNodes(string $bundle, array $filters, int $page, int $limit, string $format): array {
    $storage = $this->entityTypeManagerService->getStorage('node');

    // Build count query.
    $countQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $bundle);



    $this->applyFilters($countQuery, $filters);

    $total = (int) $countQuery->count()->execute();
    $totalPages = max(1, (int) ceil($total / $limit));

    // Build main query.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $bundle);



    $this->applyFilters($query, $filters);

    // Apply sorting.
    $query->sort($filters['sort'], $filters['direction']);

    // Apply pagination.
    $offset = ($page - 1) * $limit;
    $query->range($offset, $limit);

    $nids = $query->execute();

    if (empty($nids)) {
      return [
        'items' => [],
        'total' => $total,
        'total_pages' => $totalPages,
      ];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $items = [];

    foreach ($nodes as $node) {
      $items[] = $this->formatNode($node, $format);
    }

    return [
      'items' => $items,
      'total' => $total,
      'total_pages' => $totalPages,
    ];
  }

  /**
   * Apply filters to a query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to modify.
   * @param array $filters
   *   Filter parameters.
   */
  private function applyFilters($query, array $filters): void {
    if (!empty($filters['search'])) {
      $query->condition('title', '%' . $filters['search'] . '%', 'LIKE');
    }

    if (!empty($filters['date_from'])) {
      $timestamp = strtotime($filters['date_from']);
      if ($timestamp) {
        $query->condition('created', $timestamp, '>=');
      }
    }

    if (!empty($filters['date_to'])) {
      $timestamp = strtotime($filters['date_to'] . ' 23:59:59');
      if ($timestamp) {
        $query->condition('created', $timestamp, '<=');
      }
    }

    if (!empty($filters['taxonomy'])) {
      foreach ($filters['taxonomy'] as $fieldName => $termIds) {
        if (!empty($termIds) && is_array($termIds)) {
          $query->condition($fieldName, $termIds, 'IN');
        }
        elseif (!empty($termIds)) {
          $query->condition($fieldName, $termIds);
        }
      }
    }
  }

  /**
   * Format a node for API response.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $format
   *   Response format (full, minimal, ids).
   *
   * @return array
   *   Formatted node data.
   */
  private function formatNode(NodeInterface $node, string $format): array {
    $baseData = [
      'nid' => (int) $node->id(),
      'uuid' => $node->uuid(),
      'bundle' => $node->bundle(),
      'title' => $node->label(),
      'url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->setAbsolute()->toString(),
    ];

    if ($format === 'ids') {
      return ['nid' => (int) $node->id()];
    }

    if ($format === 'minimal') {
      return $baseData;
    }

    // Full format - include all data.
    $fullData = $baseData + [
      'status' => $node->isPublished(),
      'created' => (int) $node->getCreatedTime(),
      'created_formatted' => date('c', (int) $node->getCreatedTime()),
      'changed' => (int) $node->getChangedTime(),
      'changed_formatted' => date('c', (int) $node->getChangedTime()),
      'author' => [
        'uid' => (int) $node->getOwnerId(),
        'name' => $node->getOwner()->getDisplayName(),
      ],
    ];

    // Add TypeGrid-specific fields.
    if ($node->hasField(GridFieldManager::FIELD_SHOW)) {
      $fullData['show_in_grid'] = (bool) $node->get(GridFieldManager::FIELD_SHOW)->value;
    }

    if ($node->hasField(GridFieldManager::FIELD_PINNED)) {
      $fullData['pinned'] = (bool) $node->get(GridFieldManager::FIELD_PINNED)->value;
    }

    if ($node->hasField(GridFieldManager::FIELD_IMAGE_STYLE)) {
      $fullData['image_style'] = $node->get(GridFieldManager::FIELD_IMAGE_STYLE)->value;
    }

    // Add other fields.
    $fullData['fields'] = $this->extractNodeFields($node);

    return $fullData;
  }

  /**
   * Extract custom fields from a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Array of field values.
   */
  private function extractNodeFields(NodeInterface $node): array {
    $fields = [];
    $fieldDefinitions = $node->getFieldDefinitions();

    // Fields to skip (base fields and internal TypeGrid fields).
    $skipFields = [
      'nid', 'uuid', 'vid', 'langcode', 'type', 'revision_timestamp',
      'revision_uid', 'revision_log', 'status', 'uid', 'title', 'created',
      'changed', 'promote', 'sticky', 'default_langcode', 'revision_default',
      'revision_translation_affected', 'path', 'menu_link', 'content_translation_source',
      'content_translation_outdated',
      GridFieldManager::FIELD_SHOW,
      GridFieldManager::FIELD_PINNED,
      GridFieldManager::FIELD_IMAGE_STYLE,
    ];

    foreach ($fieldDefinitions as $fieldName => $definition) {
      if (in_array($fieldName, $skipFields)) {
        continue;
      }

      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }

      if ($node->hasField($fieldName) && !$node->get($fieldName)->isEmpty()) {
        $fieldValue = $this->fieldProcessor->extractField($node, $fieldName);
        $fields[$fieldName] = [
          'type' => $fieldValue->type,
          'value' => $fieldValue->formatted ?? $fieldValue->raw,
          'is_image' => $fieldValue->isImage,
          'meta' => $fieldValue->meta,
        ];
      }
    }

    return $fields;
  }

  /**
   * Build pagination links.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param int $currentPage
   *   Current page number.
   * @param int $totalPages
   *   Total number of pages.
   * @param array $filters
   *   Current filters.
   * @param int $limit
   *   Items per page.
   *
   * @return array
   *   Array of pagination links.
   */
  private function buildPaginationLinks(string $bundle, int $currentPage, int $totalPages, array $filters, int $limit): array {
    $baseParams = array_filter([
      'limit' => $limit !== self::DEFAULT_LIMIT ? $limit : null,
      'sort' => $filters['sort'] !== 'created' ? $filters['sort'] : null,
      'direction' => $filters['direction'] !== 'DESC' ? $filters['direction'] : null,
      'search' => $filters['search'] ?: null,
      'date_from' => $filters['date_from'] ?: null,
      'date_to' => $filters['date_to'] ?: null,
    ]);

    $links = [
      'self' => Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundle], ['query' => $baseParams + ['page' => $currentPage]])->setAbsolute()->toString(),
      'first' => Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundle], ['query' => $baseParams + ['page' => 1]])->setAbsolute()->toString(),
      'last' => Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundle], ['query' => $baseParams + ['page' => $totalPages]])->setAbsolute()->toString(),
    ];

    if ($currentPage > 1) {
      $links['prev'] = Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundle], ['query' => $baseParams + ['page' => $currentPage - 1]])->setAbsolute()->toString();
    }

    if ($currentPage < $totalPages) {
      $links['next'] = Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundle], ['query' => $baseParams + ['page' => $currentPage + 1]])->setAbsolute()->toString();
    }

    return $links;
  }

  /**
   * Create an error response.
   *
   * @param string $message
   *   Error message.
   * @param int $code
   *   HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON error response.
   */
  private function errorResponse(string $message, int $code): JsonResponse {
    return new JsonResponse([
      'success' => false,
      'error' => [
        'code' => $code,
        'message' => $message,
      ],
    ], $code);
  }
}
