<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\htl_typegrid\Service\FieldRenderer;
use Drupal\htl_typegrid\Service\GridCardBuilder;
use Drupal\htl_typegrid\Service\GridConfigFactory;
use Drupal\htl_typegrid\Service\GridQueryService;
use Drupal\htl_typegrid\Service\GridExtraFieldInstaller;
use Drupal\htl_typegrid\Form\GridBlockFormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the HTL Grid block.
 *
 * @Block(
 *   id = "htl_grid_block",
 *   admin_label = @Translation("HTL-Typegrid")
 * )
 */
final class GridBlock extends BlockBase implements
  ContainerFactoryPluginInterface
{
  private GridConfigFactory $configFactory;
  private GridBlockFormBuilder $formBuilder;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly GridQueryService $queryService,
    private readonly GridCardBuilder $cardBuilder,
    GridConfigFactory $configFactory,
    GridBlockFormBuilder $formBuilder,
    private readonly GridExtraFieldInstaller $extraFieldInstaller, // <--- neu
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
    $this->formBuilder = $formBuilder;
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    // Core-Services aus dem Container holen
    /** @var EntityTypeManagerInterface $etm */
    $etm = $container->get("entity_type.manager");
    /** @var EntityTypeBundleInfoInterface $bundleInfo */
    $bundleInfo = $container->get("entity_type.bundle.info");
    /** @var EntityRepositoryInterface $entityRepo */
    $entityRepo = $container->get("entity.repository");
    /** @var ConfigFactoryInterface $configFactory */
    $configFactory = $container->get("config.factory");

    // Unsere "Services" manuell bauen – völlig okay
    $gridQuery = new GridQueryService($etm);
    $fieldRenderer = new FieldRenderer($entityRepo, $configFactory);
    $cardBuilder = new GridCardBuilder($fieldRenderer, $bundleInfo);
    $gridConfigFactory = new GridConfigFactory();
    $formBuilder = new GridBlockFormBuilder($bundleInfo);


    /** @var GridExtraFieldInstaller $extraFieldInstaller */
    $extraFieldInstaller = $container->get('htl_typegrid.grid_extra_field_installer');

    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $gridQuery,
      $cardBuilder,
      $gridConfigFactory,
      $formBuilder,
      $extraFieldInstaller,
    );
  }

  public function build(): array
  {
    $config = $this->configFactory->fromBlockConfiguration(
      $this->getConfiguration(),
    );

    if (!$config->bundle) {
      return [
        "#markup" => $this->t(
          "Please configure a content type for this grid block.",
        ),
      ];
    }

    $cache = new CacheableMetadata();

    // 1) Nodes für das Bundle holen
    $nodes = $this->queryService->loadNodesForBundle(
      $config,
      $config->bundle,
      $cache,
    );

    // 2) Struktur für Twig bauen
    $nodesByBundle = [$config->bundle => $nodes];
    $bundlesData = $this->cardBuilder->buildBundlesData(
      $config,
      $nodesByBundle,
      $cache,
    );

    // 3) Render-Array bauen
    $build = [
      "#theme" => "htl_grid_block",
      "#bundles_data" => $bundlesData,
      "#columns" => $config->columns,
      "#rows" => $config->rows,
      "#filters" => [
        "sort_mode" => $config->filters->sortMode,
        "alpha_field" => $config->filters->alphaField,
        "direction" => $config->filters->normalizedDirection(),
      ],
    ];

    $cache->applyTo($build);
    return $build;
  }

  public function blockForm($form, FormStateInterface $form_state): array
  {
    // erst den Standard-Kram von BlockBase
    $form = parent::blockForm($form, $form_state);
    // dann unsere Extras über den FormBuilder
    return $this->formBuilder->build($this, $form, $form_state);
  }

  public function blockSubmit($form, FormStateInterface $form_state): void
  {
    parent::blockSubmit($form, $form_state);
    $this->formBuilder->submit($this, $form, $form_state);

    $config = $this->configFactory->fromBlockConfiguration(
      $this->getConfiguration()
    );

    if (!empty($config->bundle)) {
      // Nur dann, wenn Bundle ein Bild/Media-Feld hat, wird wirklich was angelegt.
      $this->extraFieldInstaller->ensureImageStyleFieldsForBundle($config->bundle);
    }
  }



  // dein blockForm()/blockSubmit() usw. kannst du unterhalb weiter drin lassen
}
