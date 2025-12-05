<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Url;
use Drupal\htl_typegrid\Service\GridFieldManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin settings form for HTL TypeGrid module.
 */
class TypeGridSettingsForm extends ConfigFormBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * The grid field manager service.
   *
   * @var \Drupal\htl_typegrid\Service\GridFieldManager
   */
  protected GridFieldManager $fieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->fieldManager = $container->get('htl_grid.field_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['htl_typegrid.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'htl_typegrid_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('htl_typegrid.settings');
    $bundles = $this->bundleInfo->getBundleInfo('node');


    $form['#attached']['library'][] = 'htl_typegrid/admin';
    $form['#attributes']['class'][] = 'htl-typegrid-settings-form';


    // Introduction text.
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Configure which content types have TypeGrid view pages enabled. View pages provide a full listing with filters, pagination, and grid/list toggle.') . '</p>',
    ];

    // Enabled view pages section.
    $form['view_pages'] = [
      '#type' => 'details',
      '#title' => $this->t('View Pages'),
      '#description' => $this->t('Enable or disable the view page for each content type. The view page URL will be <code>/typegrid/{bundle}</code>.'),
      '#open' => TRUE,
    ];

    // Get currently enabled bundles.
    $enabledBundles = $config->get('enabled_view_pages') ?? [];

    // Get bundles where filters should be shown on the full view page.
    $filtersEnabledBundles = $config->get('filters_enabled_bundles') ?? [];

    // Get bundles that have TypeGrid fields.
    $typegridBundles = [];
    foreach ($bundles as $bundleId => $bundleInfo) {
      if ($this->fieldManager->hasField($bundleId, GridFieldManager::FIELD_SHOW)) {
        $typegridBundles[$bundleId] = $bundleInfo['label'];
      }
    }

    if (empty($typegridBundles)) {
      $form['view_pages']['no_bundles'] = [
        '#type' => 'markup',
        '#markup' => '<p class="messages messages--warning">' . $this->t('No content types have TypeGrid fields yet. Create an HTL TypeGrid block and select a content type to get started.') . '</p>',
      ];
    }
    else {
      $form['view_pages']['enabled_bundles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Enable view pages for'),
        '#options' => $typegridBundles,
        '#default_value' => $enabledBundles,
        '#description' => $this->t('Check the content types that should have a public view page.'),
      ];

      $form['view_pages']['filters_enabled_bundles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show filters on full view pages for'),
        '#options' => $typegridBundles,
        '#default_value' => $filtersEnabledBundles,
        '#description' => $this->t('Select the content types where the filter bar (search, sort, dates, taxonomy) should be visible on the full TypeGrid view page.'),
      ];

      // Show links to enabled view pages.
      $form['view_pages']['links'] = [
        '#type' => 'details',
        '#title' => $this->t('View Page URLs'),
        '#open' => FALSE,
      ];

      $links = [];
      foreach ($typegridBundles as $bundleId => $label) {
        $url = Url::fromRoute('htl_typegrid.view', ['bundle' => $bundleId])->toString();
        $status = in_array($bundleId, $enabledBundles) ? '✅' : '❌';
        $links[] = [
          '#type' => 'markup',
          '#markup' => '<div>' . $status . ' <strong>' . $label . '</strong>: <a href="' . $url . '" target="_blank">' . $url . '</a></div>',
        ];
      }

      $form['view_pages']['links']['list'] = $links;
    }

    // View page settings.
    $form['view_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('View Page Settings'),
      '#open' => TRUE,
    ];

    $form['view_settings']['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#default_value' => $config->get('items_per_page') ?? 12,
      '#min' => 1,
      '#max' => 100,
      '#description' => $this->t('Number of items to display per page on view pages.'),
    ];


    $form['view_settings']['default_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Default view mode'),
      '#options' => [
        'grid' => $this->t('Grid'),
        'list' => $this->t('List'),
      ],
      '#default_value' => $config->get('default_view_mode') ?? 'grid',
      '#description' => $this->t('Default display mode when visiting a view page.'),
      '#attributes' => [
        'class' => ['htl-typegrid-admin-select'],
      ],
    ];

    $form['view_settings']['full_view_columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Full view: cards per row'),
      '#default_value' => $config->get('full_view_columns') ?? 3,
      '#min' => 3,
      '#max' => 6,
      '#description' => $this->t('How many cards should be shown per row on the full TypeGrid view page (3–6).'),
    ];


    $form['view_settings']['show_filters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show filters'),
      '#default_value' => $config->get('show_filters') ?? TRUE,
      '#description' => $this->t('Display the filter bar on view pages.'),
    ];

    $form['view_settings']['show_view_toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show grid/list toggle'),
      '#default_value' => $config->get('show_view_toggle') ?? TRUE,
      '#description' => $this->t('Allow users to switch between grid and list view.'),
    ];

    // API settings.
    $form['api_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('REST API Settings'),
      '#open' => FALSE,
    ];

    $form['api_settings']['api_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable REST API'),
      '#default_value' => $config->get('api_enabled') ?? TRUE,
      '#description' => $this->t('Allow access to the TypeGrid REST API endpoints.'),
    ];

    $form['api_settings']['api_max_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum items per API request'),
      '#default_value' => $config->get('api_max_limit') ?? 100,
      '#min' => 10,
      '#max' => 500,
      '#description' => $this->t('Maximum number of items that can be requested in a single API call.'),
    ];

    $form['api_settings']['api_allow_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow content creation via API'),
      '#default_value' => $config->get('api_allow_create') ?? TRUE,
      '#description' => $this->t('Enable POST endpoint for creating new content. Users still need appropriate permissions.'),
    ];

    $form['api_settings']['api_allow_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow content updates via API'),
      '#default_value' => $config->get('api_allow_update') ?? TRUE,
      '#description' => $this->t('Enable PATCH endpoint for updating content. Users still need appropriate permissions.'),
    ];

    $form['api_settings']['api_allow_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow content deletion via API'),
      '#default_value' => $config->get('api_allow_delete') ?? FALSE,
      '#description' => $this->t('Enable DELETE endpoint for removing content. Users still need appropriate permissions.'),
    ];

    // API endpoints info.
    $form['api_settings']['endpoints_info'] = [
      '#type' => 'details',
      '#title' => $this->t('API Endpoints'),
      '#open' => FALSE,
    ];

    $baseUrl = $this->getRequest()->getSchemeAndHttpHost();
    $form['api_settings']['endpoints_info']['list'] = [
      '#type' => 'markup',
      '#markup' => '
        <table class="htl-api-endpoints">
          <thead>
            <tr>
              <th>' . $this->t('Method') . '</th>
              <th>' . $this->t('Endpoint') . '</th>
              <th>' . $this->t('Description') . '</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>GET</code></td>
              <td><code>' . $baseUrl . '/api/typegrid</code></td>
              <td>' . $this->t('List available bundles') . '</td>
            </tr>
            <tr>
              <td><code>GET</code></td>
              <td><code>' . $baseUrl . '/api/typegrid/{bundle}</code></td>
              <td>' . $this->t('List nodes with filtering') . '</td>
            </tr>
            <tr>
              <td><code>GET</code></td>
              <td><code>' . $baseUrl . '/api/typegrid/{bundle}/{nid}</code></td>
              <td>' . $this->t('Get single node') . '</td>
            </tr>
            <tr>
              <td><code>POST</code></td>
              <td><code>' . $baseUrl . '/api/typegrid/{bundle}</code></td>
              <td>' . $this->t('Create new node') . '</td>
            </tr>
            <tr>
              <td><code>PATCH</code></td>
              <td><code>' . $baseUrl . '/api/typegrid/{bundle}/{nid}</code></td>
              <td>' . $this->t('Update node') . '</td>
            </tr>
            <tr>
              <td><code>DELETE</code></td>
              <td><code>' . $baseUrl . '/api/typegrid/{bundle}/{nid}</code></td>
              <td>' . $this->t('Delete node') . '</td>
            </tr>
          </tbody>
        </table>
      ',
    ];


    // Debug settings.
    $form['debug_settings'] = [
      '#type' => 'details',

      '#title' => $this->t('Debug Settings'),
      '#open' => FALSE,
      '#description' => $this->t('Control HTL TypeGrid module-specific debugging and logging.'),
    ];

    $form['debug_settings']['debug_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable module debug logging'),
      '#default_value' => $config->get('debug_logging') ?? TRUE,
      '#description' => $this->t('When enabled, the HTL TypeGrid module will log debug information. Disable this to reduce log noise. This affects only this module.'),
    ];

    // Main grid settings.
    $form['grid_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Main Grid Block Settings'),

      '#open' => FALSE,

    ];


    $form['grid_settings']['show_view_all_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "View All" link'),
      '#default_value' => $config->get('show_view_all_link') ?? TRUE,
      '#description' => $this->t('Display a "View All" link at the bottom of main grid blocks that links to the view page.'),
    ];

    $form['grid_settings']['view_all_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"View All" link text'),
      '#default_value' => $config->get('view_all_text') ?? 'View all',
      '#description' => $this->t('Text to display on the "View All" link.'),
      '#states' => [
        'visible' => [
          ':input[name="show_view_all_link"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('htl_typegrid.settings');

    // Get enabled bundles (filter out unchecked values).
    $enabledBundles = array_filter($form_state->getValue('enabled_bundles') ?? []);
    $config->set('enabled_view_pages', array_values($enabledBundles));

    // View page settings.
    $config->set('items_per_page', (int) $form_state->getValue('items_per_page'));
    $config->set('default_view_mode', $form_state->getValue('default_view_mode'));
    $config->set('show_filters', (bool) $form_state->getValue('show_filters'));
    $config->set('show_view_toggle', (bool) $form_state->getValue('show_view_toggle'));
    $config->set('full_view_columns', (int) $form_state->getValue('full_view_columns'));

    // Per-bundle filter visibility on full view pages.
    $filtersEnabledBundles = array_filter($form_state->getValue('filters_enabled_bundles') ?? []);
    $config->set('filters_enabled_bundles', array_values($filtersEnabledBundles));


    // API settings.

    $config->set('api_enabled', (bool) $form_state->getValue('api_enabled'));
    $config->set('api_max_limit', (int) $form_state->getValue('api_max_limit'));
    $config->set('api_allow_create', (bool) $form_state->getValue('api_allow_create'));
    $config->set('api_allow_update', (bool) $form_state->getValue('api_allow_update'));
    $config->set('api_allow_delete', (bool) $form_state->getValue('api_allow_delete'));

    // Debug settings.
    $config->set('debug_logging', (bool) $form_state->getValue('debug_logging'));

    // Grid settings.
    $config->set('show_view_all_link', (bool) $form_state->getValue('show_view_all_link'));
    $config->set('view_all_text', $form_state->getValue('view_all_text'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
