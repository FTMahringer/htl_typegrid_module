<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\htl_typegrid\Service\GridQueryService;
use Drupal\htl_typegrid\Service\GridCardBuilder;
use Drupal\htl_typegrid\Service\GridConfigFactory;
use Drupal\htl_typegrid\Helper\CacheHelper;
use Drupal\htl_typegrid\Model\GridConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "htl_typegrid_block",
 *   admin_label = @Translation("HTL TypeGrid"),
 * )
 */
final class GridBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly GridQueryService $queryService,
    private readonly GridCardBuilder $cardBuilder,
    private readonly GridConfigFactory $configFactory,
    private readonly CacheHelper $cacheHelper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('htl_grid.query'),
      $container->get('htl_grid.card_builder'),
      $container->get('htl_grid.config_factory'),
      $container->get('htl_grid.cache_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'bundle' => '',
      'columns' => 3,
      'rows' => 2,
      'layout_preset' => GridConfig::PRESET_STANDARD,
      'image_position' => GridConfig::IMAGE_TOP,
      'card_gap' => 'medium',
      'card_radius' => 'medium',
      'filters' => [
        'sort_mode' => 'created',
        'alpha_field' => 'title',
        'direction' => 'DESC',
      ],
      'bundle_fields' => [],
      'css_classes' => [],
      'image_settings' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $configArray = $this->getConfiguration();

    // Create strongly typed GridConfig
    $config = $this->configFactory->fromArray($configArray);

    // Load DTO nodes
    $nodes = $this->queryService->loadNodes($config);

    // Create cards
    $cards = $this->cardBuilder->buildCards($nodes, $config);

    // Debug-Log (haben wir schon gesehen)
    \Drupal::logger('htl_typegrid')->notice(
      'Typegrid debug: bundle=@bundle, rows=@rows, cols=@cols, preset=@preset, imgPos=@imgPos, nodes=@nodes, cards=@cards, fields=@fields',
      [
        '@bundle' => $config->bundle,
        '@rows'   => $config->rows,
        '@cols'   => $config->columns,
        '@preset' => $config->layoutPreset,
        '@imgPos' => $config->imagePosition,
        '@nodes'  => count($nodes),
        '@cards'  => count($cards),
        '@fields' => count($config->fields),
      ]
    );

    // Debug individual cards
    foreach ($cards as $index => $card) {
      \Drupal::logger('htl_typegrid')->notice(
        'Card @idx: title=@title, imageHtml=@img, metaCount=@meta',
        [
          '@idx' => $index,
          '@title' => $card->title,
          '@img' => $card->imageHtml ? 'YES (' . strlen($card->imageHtml) . ' chars)' : 'NO',
          '@meta' => count($card->metaItems),
        ]
      );
    }

    // Generate View All URL if enabled in settings
    $viewAllUrl = NULL;
    $viewAllText = NULL;
    $moduleConfig = \Drupal::config('htl_typegrid.settings');
    $showViewAllLink = $moduleConfig->get('show_view_all_link') ?? TRUE;

    if ($showViewAllLink && $config->bundle) {
      // Check if view page is enabled for this bundle
      $enabledBundles = $moduleConfig->get('enabled_view_pages') ?? [];
      $viewPageEnabled = empty($enabledBundles) || in_array($config->bundle, $enabledBundles, TRUE);

      if ($viewPageEnabled) {
        try {
          $viewAllUrl = Url::fromRoute('htl_typegrid.view', ['bundle' => $config->bundle])->toString();
          $viewAllText = $moduleConfig->get('view_all_text') ?? 'View all';
        }
        catch (\Exception $e) {
          // Route may not exist yet
          $viewAllUrl = NULL;
        }
      }
    }

    // Render-Array fürs Twig-Template
    $build = [
      '#theme'   => 'htl_grid_block',
      '#cards'   => $cards,
      '#columns' => $config->columns,
      '#rows'    => $config->rows,
      '#config'  => $config,
      '#view_all_url' => $viewAllUrl,
      '#view_all_text' => $viewAllText,
      '#attached' => [
        'library' => [
          'htl_typegrid/grid',
        ],
      ],
    ];

    // Debug: Log build array structure
    \Drupal::logger('htl_typegrid')->notice(
      'Build array: cards count=@count, columns=@cols, config_bundle=@bundle',
      [
        '@count' => count($build['#cards']),
        '@cols' => $build['#columns'],
        '@bundle' => is_object($build['#config']) ? $build['#config']->bundle : 'NULL',
      ]
    );

    $nids = array_map(static fn($node) => $node->id, $nodes);
    $this->cacheHelper->addNodeListCache($build, $config->bundle, $nids);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, $form_state) {
    $form = parent::blockForm($form, $form_state);
    return \Drupal::service('htl_grid.form_builder')->build($this, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, $form_state) {
    // Debug: Log the form values structure to understand the path
    $values = $form_state->getValues();
    \Drupal::logger('htl_typegrid')->notice(
      'blockValidate - Form values keys: @keys',
      ['@keys' => implode(', ', array_keys($values))]
    );

    // Log layout specifically
    if (isset($values['layout'])) {
      \Drupal::logger('htl_typegrid')->notice(
        'blockValidate - layout keys: @keys',
        ['@keys' => implode(', ', array_keys($values['layout']))]
      );
    }

    // Also check user input
    $input = $form_state->getUserInput();
    if (isset($input['settings']['layout'])) {
      \Drupal::logger('htl_typegrid')->notice(
        'blockValidate - input settings.layout keys: @keys',
        ['@keys' => implode(', ', array_keys($input['settings']['layout']))]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, $form_state) {
    parent::blockSubmit($form, $form_state);
    \Drupal::service('htl_grid.form_builder')->submit($this, $form, $form_state);
  }
}
