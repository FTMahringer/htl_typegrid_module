<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\htl_typegrid\Helper\CacheHelper;
use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\htl_typegrid\Model\GridFilters;
use Drupal\htl_typegrid\Service\GridBlockResolver;
use Drupal\htl_typegrid\Service\GridCardBuilder;
use Drupal\htl_typegrid\Service\GridFieldManager;
use Drupal\htl_typegrid\Service\GridQueryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays only pinned content from a selected HTL TypeGrid block.
 *
 * @Block(
 *   id = "htl_typegrid_pinned_block",
 *   admin_label = @Translation("HTL TypeGrid - Pinned"),
 *   category = @Translation("HTL")
 * )
 */
final class PinnedGridBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly GridCardBuilder $cardBuilder,
    private readonly GridFieldManager $fieldManager,
    private readonly GridBlockResolver $blockResolver,
    private readonly GridQueryService $queryService,
    private readonly CacheHelper $cacheHelper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('htl_grid.card_builder'),
      $container->get('htl_grid.field_manager'),
      $container->get('htl_grid.block_resolver'),
      $container->get('htl_grid.query'),
      $container->get('htl_grid.cache_helper'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'source_block' => '',
      'layout_preset' => GridConfig::PRESET_STANDARD,
      'card_gap' => 'medium',
      'card_radius' => 'medium',
      'selected_fields' => [],
    ];
  }

  public function build(): array {
    $config = $this->getConfiguration();
    $source = (string) ($config['source_block'] ?? '');
    if ($source === '') {
      return ['#markup' => $this->t('Please configure the block and select a source HTL Grid.')];
    }

    $bundle = (string) ($this->blockResolver->getBundleFromGridBlockId($source) ?? '');
    if ($bundle === '') {
      return ['#markup' => $this->t('Selected HTL Grid could not be found or has no content type configured.')];
    }

    $gridFields = $this->blockResolver->getConfiguredFieldsFromGridBlockId($source);
    $selected = (array) ($config['selected_fields'] ?? []);
    $effectiveFields = !empty($selected) ? array_values($selected) : $gridFields;

    $columns = 1;
    $rows = 3;
    $limit = $columns * $rows;
    $nodes = $this->queryService->loadPinnedNodes($bundle, $limit, $effectiveFields);

    if (empty($nodes)) {
      return [
        '#markup' => $this->t('No pinned content available.'),
        '#cache' => ['tags' => ['node_list:' . $bundle]],
      ];
    }

    $gridConfig = new GridConfig(
      bundle: $bundle,
      columns: $columns,
      rows: $rows,
      filters: new GridFilters(sortMode: 'created', alphaField: 'title', direction: 'DESC'),
      fields: $effectiveFields,
      cssClasses: [],
      imageSettings: ['card_gap' => $config['card_gap'] ?? 'medium', 'card_radius' => $config['card_radius'] ?? 'medium'],
      layoutPreset: $config['layout_preset'] ?? GridConfig::PRESET_STANDARD,
      imagePosition: GridConfig::IMAGE_TOP,
    );

    $cards = $this->cardBuilder->buildCards($nodes, $gridConfig);
    $build = [
      '#theme' => 'htl_grid_pinned_block',
      '#cards' => $cards,
      '#columns' => $gridConfig->columns,
      '#rows' => $gridConfig->rows,
      '#config' => $gridConfig,
      '#attached' => ['library' => ['htl_typegrid/grid']],
    ];

    $nids = array_map(static fn($n) => $n->id, $nodes);
    $this->cacheHelper->addNodeListCache($build, $bundle, $nids);
    return $build;
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['source_block'] = [
      '#type' => 'select',
      '#title' => $this->t('Source HTL Grid'),
      '#description' => $this->t('Select the HTL TypeGrid block to source pinned items from. Content type and fields are taken from that grid unless overridden below.'),
      '#options' => $this->blockResolver->listHtlGridBlockOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $config['source_block'] ?? '',
      '#required' => TRUE,
      '#ajax' => ['callback' => [static::class, 'ajaxSourceChange'], 'wrapper' => 'htl-pinned-fields-wrapper', 'event' => 'change'],
    ];

    $form['layout'] = ['#type' => 'details', '#title' => $this->t('Layout'), '#open' => TRUE];
    $form['layout']['layout_preset'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout Preset'),
      '#options' => GridConfig::getLayoutPresets(),
      '#default_value' => $config['layout_preset'] ?? GridConfig::PRESET_STANDARD,
    ];
    $form['layout']['card_gap'] = [
      '#type' => 'select',
      '#title' => $this->t('Card Spacing'),
      '#options' => ['none' => $this->t('None'), 'small' => $this->t('Small'), 'medium' => $this->t('Medium'), 'large' => $this->t('Large'), 'xlarge' => $this->t('Extra Large')],
      '#default_value' => $config['card_gap'] ?? 'medium',
    ];
    $form['layout']['card_radius'] = [
      '#type' => 'select',
      '#title' => $this->t('Card Border Radius'),
      '#options' => ['none' => $this->t('None'), 'small' => $this->t('Small'), 'medium' => $this->t('Medium'), 'large' => $this->t('Large'), 'pill' => $this->t('Pill')],
      '#default_value' => $config['card_radius'] ?? 'medium',
    ];

    $form['fields'] = ['#type' => 'details', '#title' => $this->t('Fields'), '#open' => TRUE];
    $form['fields']['wrapper'] = ['#type' => 'container', '#attributes' => ['id' => 'htl-pinned-fields-wrapper']];

    // Get the value from either the user input (during AJAX) or the saved config.
    // We cannot use $form_state->getValue() or hasValue() here because SubformState
    // requires #parents to be set, which only happens during form processing.
    $userInput = $form_state->getUserInput();
    $selectedSource = '';
    if (!empty($userInput['settings']['source_block'])) {
      $selectedSource = (string) $userInput['settings']['source_block'];
    }
    elseif (!empty($userInput['source_block'])) {
      // Fallback for direct input without settings wrapper.
      $selectedSource = (string) $userInput['source_block'];
    }
    else {
      $selectedSource = (string) ($config['source_block'] ?? '');
    }
    $bundle = $selectedSource ? (string) ($this->blockResolver->getBundleFromGridBlockId($selectedSource) ?? '') : '';
    if ($bundle === '') {
      $form['fields']['wrapper']['msg'] = ['#markup' => $this->t('Select a source HTL Grid to choose fields.')];
      return $form;
    }

    $options = $this->fieldManager->getAllowedPinnedFieldOptions($bundle);
    // Get selected fields from user input (during AJAX) or saved config.
    // Avoid using $form_state->getValue() due to SubformState #parents issue.
    $rawSelected = [];
    if (!empty($userInput['settings']['fields']['selected_fields'])) {
      $rawSelected = (array) $userInput['settings']['fields']['selected_fields'];
    }
    elseif (!empty($userInput['fields']['selected_fields'])) {
      $rawSelected = (array) $userInput['fields']['selected_fields'];
    }
    $savedSelected = (array) ($config['selected_fields'] ?? []);
    $selectedKeys = !empty($rawSelected) ? array_keys(array_filter($rawSelected)) : $savedSelected;
    $default = $selectedKeys ? array_combine($selectedKeys, $selectedKeys) : [];

    $form['fields']['wrapper']['selected_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select fields to show'),
      '#options' => $options,
      '#default_value' => $default,
      '#description' => $this->t('Only images, short text and date/time fields can be selected.'),
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['source_block'] = (string) ($values['source_block'] ?? '');
    $this->configuration['layout_preset'] = (string) ($values['layout']['layout_preset'] ?? GridConfig::PRESET_STANDARD);
    $this->configuration['card_gap'] = (string) ($values['layout']['card_gap'] ?? 'medium');
    $this->configuration['card_radius'] = (string) ($values['layout']['card_radius'] ?? 'medium');

    // The selected_fields checkboxes are nested under fields > wrapper > selected_fields.
    // Checkboxes return field_name => field_name for checked, field_name => 0 for unchecked.
    // array_filter removes the 0 values, array_values re-indexes the array.
    $selected = (array) ($values['fields']['wrapper']['selected_fields'] ?? []);
    $this->configuration['selected_fields'] = array_values(array_filter($selected));

    $bundle = (string) ($this->blockResolver->getBundleFromGridBlockId($this->configuration['source_block']) ?? '');
    if ($bundle !== '') {
      $this->fieldManager->addImageStyleFieldsToBundle($bundle);
    }
  }

  public static function ajaxSourceChange(array &$form, FormStateInterface $form_state): array {
    // The block form is nested under 'settings' in the complete form.
    return $form['settings']['fields']['wrapper'];
  }
}
