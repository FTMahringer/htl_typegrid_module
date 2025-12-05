<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\htl_typegrid\Model\GridFilters;
use Drupal\htl_typegrid\Service\GridCardBuilder;
use Drupal\htl_typegrid\Service\GridFieldManager;
use Drupal\htl_typegrid\Service\GridBlockResolver;
use Drupal\htl_typegrid\Service\GridQueryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;

/**
 * Controller for the TypeGrid full view page.
 */
final class GridViewController extends ControllerBase {

  /**
   * Default items per page for pagination.
   */
  private const DEFAULT_ITEMS_PER_PAGE = 12;


  public function __construct(

    private readonly EntityTypeManagerInterface $entityTypeManagerService,

    private readonly EntityTypeBundleInfoInterface $bundleInfoService,

    private readonly GridCardBuilder $cardBuilder,

    private readonly GridFieldManager $fieldManager,

    private readonly GridBlockResolver $blockResolver,

    private readonly GridQueryService $queryService,
    private readonly ConfigFactoryInterface $configFactoryService,

  ) {}



  public static function create(ContainerInterface $container): self {

    return new self(

      $container->get('entity_type.manager'),

      $container->get('entity_type.bundle.info'),

      $container->get('htl_grid.card_builder'),

      $container->get('htl_grid.field_manager'),

      $container->get('htl_grid.block_resolver'),

      $container->get('htl_grid.query'),
      $container->get('config.factory'),

    );

  }


  /**
   * Get module configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  private function getConfig() {
    return $this->configFactoryService->get('htl_typegrid.settings');
  }

  /**
   * Get items per page from config or default.
   *
   * @return int
   */
  private function getItemsPerPage(): int {
    return (int) ($this->getConfig()->get('items_per_page') ?? self::DEFAULT_ITEMS_PER_PAGE);
  }

  /**
   * Check if view page is enabled for a bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return bool
   *   TRUE if enabled.
   */
  private function isViewPageEnabled(string $bundle): bool {
    $enabledBundles = $this->getConfig()->get('enabled_view_pages') ?? [];
    // If no bundles are configured yet, allow all bundles with TypeGrid fields.
    if (empty($enabledBundles)) {
      return $this->fieldManager->hasField($bundle, GridFieldManager::FIELD_SHOW);
    }
    return in_array($bundle, $enabledBundles, TRUE);
  }

  /**
   * Renders the full grid view page.
   *
   * @param string $bundle
   *   The content type machine name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   Render array for the view page.
   */
  public function view(string $bundle, Request $request): array {
    // Validate bundle exists.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      throw new NotFoundHttpException($this->t('Content type "@bundle" not found.', ['@bundle' => $bundle]));
    }

    // Check if view page is enabled for this bundle.
    if (!$this->isViewPageEnabled($bundle)) {
      throw new NotFoundHttpException($this->t('View page is not enabled for this content type.'));
    }

    $config = $this->getConfig();
    $bundleLabel = $bundles[$bundle]['label'] ?? $bundle;

    // Get filter parameters from request.
    $filters = $this->extractFilters($request);
    $page = max(1, (int) $request->query->get('page', 1));
    $defaultViewMode = $config->get('default_view_mode') ?? 'grid';
    $viewMode = $request->query->get('view', $defaultViewMode);

    // Load nodes with pagination.
    $itemsPerPage = $this->getItemsPerPage();
    $result = $this->loadNodesWithPagination($bundle, $filters, $page, $itemsPerPage);

    // Get field options for filter dropdowns.
    $fieldOptions = $this->getFilterFieldOptions($bundle);

    // Get taxonomy filter options if available.
    $taxonomyFilters = $this->getTaxonomyFilterOptions($bundle);

    // Get display settings from config.
    // Global fallback for filters, but allow per-bundle override.
    $globalShowFilters = $config->get('show_filters') ?? TRUE;
    $filtersEnabledBundles = $config->get('filters_enabled_bundles') ?? [];

    if (!empty($filtersEnabledBundles)) {
      // If specific bundles are configured, only show filters for those.
      $showFilters = in_array($bundle, $filtersEnabledBundles, TRUE);
    }
    else {
      // If nothing configured yet, fall back to global setting.
      $showFilters = $globalShowFilters;
    }

    $showViewToggle = $config->get('show_view_toggle') ?? TRUE;

    // Number of columns for the full view page (3–6).
    $fullViewColumns = (int) ($config->get('full_view_columns') ?? 3);
    if ($fullViewColumns < 3) {
      $fullViewColumns = 3;
    }
    if ($fullViewColumns > 6) {
      $fullViewColumns = 6;
    }

    // Build the render array.
    $build = [
      '#theme' => 'htl_grid_view_page',
      '#bundle' => $bundle,
      '#bundle_label' => $bundleLabel,
      '#cards' => $result['cards'],
      '#total_count' => $result['total'],
      '#current_page' => $page,
      '#total_pages' => $result['total_pages'],
      '#items_per_page' => $itemsPerPage,
      '#view_mode' => $viewMode,
      '#filters' => $filters,
      '#field_options' => $fieldOptions,
      '#taxonomy_filters' => $taxonomyFilters,
      '#has_more' => $page < $result['total_pages'],
      '#show_filters' => $showFilters,
      '#show_view_toggle' => $showViewToggle,
      '#full_view_columns' => $fullViewColumns,
      '#attached' => [
        'library' => [
          'htl_typegrid/grid',
          'htl_typegrid/view_page',
        ],
        'drupalSettings' => [
          'htlTypegrid' => [
            'bundle' => $bundle,
            'currentPage' => $page,
            'totalPages' => $result['total_pages'],
            'itemsPerPage' => $itemsPerPage,
            'viewMode' => $viewMode,
            'apiEndpoint' => Url::fromRoute('htl_typegrid.api.list', ['bundle' => $bundle])->toString(),
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['node_list:' . $bundle],
        'contexts' => ['url.query_args'],
      ],
    ];

    return $build;
  }

  /**
   * AJAX endpoint for loading more items or filtering.
   *
   * @param string $bundle
   *   The content type machine name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with cards and pagination info.
   */
  public function ajaxLoad(string $bundle, Request $request): JsonResponse {
    // Validate bundle exists.
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    if (!isset($bundles[$bundle])) {
      return new JsonResponse(['error' => 'Bundle not found'], 404);
    }

    // Check if view page is enabled.
    if (!$this->isViewPageEnabled($bundle)) {
      return new JsonResponse(['error' => 'View page not enabled for this bundle'], 403);
    }

    $filters = $this->extractFilters($request);
    $page = max(1, (int) $request->query->get('page', 1));
    $itemsPerPage = $this->getItemsPerPage();

    $result = $this->loadNodesWithPagination($bundle, $filters, $page, $itemsPerPage);

    // Render cards to HTML.
    $cardsHtml = [];
    foreach ($result['cards'] as $card) {
      $cardsHtml[] = $this->renderCard($card);
    }

    return new JsonResponse([
      'cards' => $cardsHtml,
      'total' => $result['total'],
      'current_page' => $page,
      'total_pages' => $result['total_pages'],
      'has_more' => $page < $result['total_pages'],
    ]);
  }

  /**
   * Extract filter parameters from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Array of filter values.
   */
  private function extractFilters(Request $request): array {
    return [
      'search' => trim((string) $request->query->get('search', '')),
      'sort' => $request->query->get('sort', 'created'),
      'direction' => $request->query->get('direction', 'DESC'),
      'date_from' => $request->query->get('date_from', ''),
      'date_to' => $request->query->get('date_to', ''),
      'taxonomy' => $request->query->all('taxonomy') ?: [],
    ];
  }

  /**
   * Load nodes with pagination and filtering.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param array $filters
   *   Filter parameters.
   * @param int $page
   *   Current page number.
   * @param int $itemsPerPage
   *   Number of items per page.
   *
   * @return array
   *   Array with 'cards', 'total', 'total_pages'.
   */
  private function loadNodesWithPagination(string $bundle, array $filters, int $page, int $itemsPerPage = 12): array {
    $storage = $this->entityTypeManagerService->getStorage('node');

    // Build count query first.
    $countQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $bundle);


    // Do not filter by 'Show in TypeGrid' on the view page (show all nodes).


    // Apply search filter.
    if (!empty($filters['search'])) {
      $countQuery->condition('title', '%' . $filters['search'] . '%', 'LIKE');
    }

    // Apply date range filter.
    if (!empty($filters['date_from'])) {
      $timestamp = strtotime($filters['date_from']);
      if ($timestamp) {
        $countQuery->condition('created', $timestamp, '>=');
      }
    }
    if (!empty($filters['date_to'])) {
      $timestamp = strtotime($filters['date_to'] . ' 23:59:59');
      if ($timestamp) {
        $countQuery->condition('created', $timestamp, '<=');
      }
    }

    // Apply taxonomy filters.
    if (!empty($filters['taxonomy'])) {
      foreach ($filters['taxonomy'] as $fieldName => $termIds) {
        if (!empty($termIds) && is_array($termIds)) {
          $countQuery->condition($fieldName, $termIds, 'IN');
        }
        elseif (!empty($termIds)) {
          $countQuery->condition($fieldName, $termIds);
        }
      }
    }

    $total = (int) $countQuery->count()->execute();
    $totalPages = max(1, (int) ceil($total / $itemsPerPage));
    $page = min($page, $totalPages);

    // Build the main query.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $bundle);


    // Do not filter by 'Show in TypeGrid' on the view page (show all nodes).


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

    // Apply sorting.
    $sortField = $filters['sort'] ?? 'created';
    $sortDirection = strtoupper($filters['direction'] ?? 'DESC');
    if (!in_array($sortDirection, ['ASC', 'DESC'])) {
      $sortDirection = 'DESC';
    }
    $query->sort($sortField, $sortDirection);

    // Apply pagination.
    $offset = ($page - 1) * $itemsPerPage;
    $query->range($offset, $itemsPerPage);

    $nids = $query->execute();

    if (empty($nids)) {
      return [
        'cards' => [],
        'total' => $total,
        'total_pages' => $totalPages,
      ];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);

    // Build GridConfig for card rendering.
    $gridConfig = new GridConfig(
      bundle: $bundle,
      columns: 3,
      rows: (int) ceil(count($nodes) / 3),
      filters: new GridFilters(
        sortMode: $sortField,
        alphaField: 'title',
        direction: $sortDirection,
      ),
      fields: $this->getConfiguredFields($bundle),
      cssClasses: [],
      imageSettings: ['card_gap' => 'medium', 'card_radius' => 'medium'],
      layoutPreset: GridConfig::PRESET_STANDARD,
      imagePosition: GridConfig::IMAGE_TOP,
    );

    // Build GridNodes from loaded nodes.
    $gridNodes = [];
    foreach ($nodes as $node) {
      $gridNodes[] = $this->buildGridNode($node, $gridConfig);
    }

    // Build cards.
    $cards = $this->cardBuilder->buildCards($gridNodes, $gridConfig);

    return [
      'cards' => $cards,
      'total' => $total,
      'total_pages' => $totalPages,
    ];
  }

  /**
   * Build a GridNode from a node entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Drupal\htl_typegrid\Model\GridConfig $config
   *   The grid config.
   *
   * @return \Drupal\htl_typegrid\Model\GridNode
   *   The grid node DTO.
   */

    private function buildGridNode($node, GridConfig $config): \Drupal\htl_typegrid\Model\GridNode {

      // Delegate to the query service so image detection and fallbacks
      // exactly match the main grid behaviour.
      return $this->queryService->buildNodeDtoFromEntity($node, $config);
    }


  /**
   * Get configured fields for a bundle from existing grid blocks.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return array
   *   Array of field names.
   */
  private function getConfiguredFields(string $bundle): array {
    // Try to get fields from existing grid block configuration.
    $blocks = $this->blockResolver->listHtlGridBlockOptions();
    foreach ($blocks as $blockId => $label) {
      $blockBundle = $this->blockResolver->getBundleFromGridBlockId($blockId);
      if ($blockBundle === $bundle) {
        return $this->blockResolver->getConfiguredFieldsFromGridBlockId($blockId);
      }
    }
    return [];
  }

  /**
   * Get filter field options for the bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return array
   *   Array of field options for sorting.
   */
  private function getFilterFieldOptions(string $bundle): array {
    return [
      'created' => $this->t('Date Created'),
      'changed' => $this->t('Date Updated'),
      'title' => $this->t('Title'),
    ];
  }

  /**
   * Get taxonomy filter options for the bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return array
   *   Array of taxonomy field info with term options.
   */
  private function getTaxonomyFilterOptions(string $bundle): array {
    $options = [];
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    foreach ($definitions as $fieldName => $definition) {
      if ($definition->getType() !== 'entity_reference') {
        continue;
      }

      $targetType = $definition->getSetting('target_type');
      if ($targetType !== 'taxonomy_term') {
        continue;
      }

      $handlerSettings = $definition->getSetting('handler_settings');
      $targetBundles = $handlerSettings['target_bundles'] ?? [];

      if (empty($targetBundles)) {
        continue;
      }

      // Load terms for each vocabulary.
      $termStorage = $this->entityTypeManagerService->getStorage('taxonomy_term');
      $terms = $termStorage->loadByProperties([
        'vid' => array_keys($targetBundles),
        'status' => 1,
      ]);

      if (empty($terms)) {
        continue;
      }

      $termOptions = [];
      foreach ($terms as $term) {
        $termOptions[$term->id()] = $term->label();
      }

      if (!empty($termOptions)) {
        asort($termOptions);
        $options[$fieldName] = [
          'label' => $definition->getLabel(),
          'options' => $termOptions,
        ];
      }
    }

    return $options;
  }

  /**
   * Render a single card to HTML.
   *
   * @param \Drupal\htl_typegrid\Model\GridCard $card
   *   The card to render.
   *
   * @return string
   *   Rendered HTML.
   */
  private function renderCard($card): string {
    $build = [
      '#theme' => 'htl_grid_card',
      '#card' => $card,
    ];
    return (string) \Drupal::service('renderer')->renderRoot($build);
  }

  /**
   * Title callback for the view page.
   *
   * @param string $bundle
   *   The content type machine name.
   *
   * @return string
   *   The page title.
   */
  public function title(string $bundle): string {
    $bundles = $this->bundleInfoService->getBundleInfo('node');
    $label = $bundles[$bundle]['label'] ?? $bundle;
    return (string) $this->t('All @type', ['@type' => $label]);
  }

  /**
   * Check if a view exists for a given bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return bool
   *   TRUE if view route exists.
   */
  public static function viewExists(string $bundle): bool {
    $routeProvider = \Drupal::service('router.route_provider');
    try {
      $routeProvider->getRouteByName('htl_typegrid.view.' . $bundle);
      return TRUE;
    }
    catch (\Exception $e) {
      // Route doesn't exist, check if the generic view route works.
      return TRUE; // We always have the generic route.
    }
  }

  /**
   * Get the URL to the view page for a bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return \Drupal\Core\Url|null
   *   The URL or null if not available.
   */
  public static function getViewUrl(string $bundle): ?Url {
    return Url::fromRoute('htl_typegrid.view', ['bundle' => $bundle]);
  }
}
