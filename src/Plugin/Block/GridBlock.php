<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Plugin\Block;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Url;
use Drupal\htl_typegrid\Helper\FieldRenderHelper;

/**
 * Provides the HTL Grid block.
 *
 * @Block(
 *   id = "htl_grid_block",
 *   admin_label = @Translation("HTL Grid"),
 *   category = @Translation("Custom")
 * )
 */
final class GridBlock extends BlockBase
{

  public function defaultConfiguration(): array
  {
    return [
      'bundle' => '',
      'columns' => 3,
      'rows' => 2,
      'bundle_fields' => [],
      // Neu: Filter-Defaults
      'filters' => [
        'sort_mode' => 'newest', // newest | oldest | alpha | random
        'alpha_field' => 'title',
        'direction' => 'ASC',    // ASC | DESC (bei alpha oder created)
      ],
    ];
  }

  /**
   * @throws InvalidPluginDefinitionException
   * @throws EntityMalformedException
   * @throws PluginNotFoundException
   */
  public function build(): array
  {
    $c = $this->getConfiguration();
    $bundle = (string)($c['bundle'] ?? '');
    $columns = (int)($c['columns']);
    $rows = (int)($c['rows']);
    $limitPerType = min(20, $columns * $rows);

    // Filter lesen
    $filters = (array)($c['filters'] ?? []);
    $sortMode = (string)($filters['sort_mode'] ?? 'newest');
    $direction = strtoupper((string)($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
    $alphaField = (string)($filters['alpha_field'] ?? 'title');

    /** @var EntityTypeBundleInfoInterface $bundle_info_service */
    $bundle_info_service = Drupal::service('entity_type.bundle.info');
    $bundle_info = $bundle_info_service->getBundleInfo('node');

    $bundles_data = [];
    $cache = new CacheableMetadata();
    $cache->setCacheTags(['node_list']);
    $cache->setCacheContexts(['user.permissions']);

    if ($bundle && isset($bundle_info[$bundle])) {
      $q = Drupal::entityQuery('node')
        ->condition('type', $bundle)
        ->condition('status', 1)
        ->accessCheck(TRUE);

      // Sortierung anwenden
      if ($sortMode === 'newest') {
        $q->sort('created', 'DESC');
      } elseif ($sortMode === 'oldest') {
        $q->sort('created', 'ASC');
      } elseif ($sortMode === 'alpha') {
        // title (Basisfeld) oder Feldwert sortieren
        if ($alphaField === 'title') {
          $q->sort('title', $direction);
        } else {
          // typischer String-Feldpfad
          $q->sort($alphaField . '.value', $direction);
        }
      } elseif ($sortMode === 'random') {
        // keine Sortierung auf Query; wir shufflen nach dem Laden
      } else {
        // Fallback
        $q->sort('created', 'DESC');
      }

      // Bei random laden wir etwas mehr, um sinnvoll mischen zu können
      $range = $sortMode === 'random' ? max($limitPerType, 30) : $limitPerType;
      $q->range(0, $range);

      $ids = $q->execute();
      $nodes = $ids ? Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids) : [];

      if ($sortMode === 'random' && $nodes) {
        shuffle($nodes);
        // auf Anzeige-Limit einkürzen
        $nodes = array_slice($nodes, 0, $limitPerType);
      }

      $chosen = (array)($c['bundle_fields'][$bundle] ?? []);
      $chosen = array_values($chosen);

      $cards = [];
      foreach ($nodes as $node) {
        $fieldsOut = FieldRenderHelper::buildCardFields($node, $chosen, $cache);
        $cards[] = [
          'title' => $node->label(),
          'fields' => $fieldsOut,
          'url' => $node->toUrl()->toString(),
        ];

        $cache->addCacheableDependency($node);
      }

      $bundles_data[] = [
        'bundle' => $bundle,
        'bundle_label' => $bundle_info[$bundle]['label'] ?? $bundle,
        'nodes' => $cards,
      ];
      $cache->setCacheTags(array_merge($cache->getCacheTags(), ['config:node.type.' . $bundle]));
    }

    $build = [
      '#theme' => 'htl_grid_block',
      '#bundles_data' => $bundles_data,
      '#columns' => $columns,
      '#rows' => $rows,
      '#filters' => $filters,
    ];
    $cache->applyTo($build);
    return $build;

  }

  private function truncate(string $text, int $max): string
  {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    return (mb_strlen($text) <= $max) ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
  }

  public function blockForm($form, FormStateInterface $form_state): array
  {
    $c = $this->getConfiguration();

    // Strukturiertes Formular + Dialog-Lib (für Modal-Link).
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // Bundle-Optionen laden.
    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $bundleOpts = [];
    foreach ($bundleInfo as $machine => $info) {
      $bundleOpts[$machine] = $info['label'] ?? $machine;
    }

    // Tabs
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Einstellungen'),
    ];

    // Inhaltstyp (Single-Select)
    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Inhaltstyp'),
      '#group' => 'tabs',
      '#open' => TRUE,
    ];
    $form['content']['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Inhaltstyp'),
      '#options' => $bundleOpts,
      '#default_value' => $c['bundle'] ?? '',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [static::class, 'ajaxRebuild'],
        'event' => 'change',
        'wrapper' => 'htl-grid-bundle-tabs',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Lade …')],
      ],
      '#description' => $this->t('Wähle genau einen Inhaltstyp.'),
    ];

    // --- NEU: Layout (Columns/Rows)
    $form['layout'] = [
      '#type' => 'details',
      '#title' => $this->t('Layout'),
      '#group' => 'tabs',
      '#open' => FALSE,
    ];
    $form['layout']['columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Spalten'),
      '#default_value' => (int)($c['columns'] ?? 3),
      '#min' => 1,
      '#max' => 12,
      '#step' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Anzahl Spalten (1–12).'),
    ];
    $form['layout']['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Zeilen'),
      '#default_value' => (int)($c['rows'] ?? 2),
      '#min' => 1,
      '#max' => 12,
      '#step' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Anzahl Zeilen (1–12).'),
    ];


    // Wrapper
    $form['bundle_tabs_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'htl-grid-bundle-tabs'],
    ];

    // Ausgewähltes Bundle robust lesen
    $selectedBundle = (string)$this->readValue($form, $form_state, ['content', 'bundle'], $c['bundle'] ?? '');

    if ($selectedBundle !== '') {
      // 1) Feldoptionen holen (für Anzeige + Picker-Modal).
      [$fieldOptions] = $this->getBundleFieldMeta($selectedBundle);

      // 2) Auswahl aus Config / UserInput laden …
      $currentChosen = (array)($c['bundle_fields'][$selectedBundle] ?? []);
      $currentChosen = (array)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'fields'], $currentChosen);

      // … und ggf. Tempstore-Übernahme (vom Modal-Form).
      $store = \Drupal::service('tempstore.private')->get('htl_grid');
      $key = 'picker.' . $selectedBundle;
      $modalValue = $store->get($key);           // NULL = kein Eintrag; [] = bewusst leer
      if ($modalValue !== NULL) {
        $currentChosen = array_values((array)$modalValue);
        $store->delete($key);
      }

      // 3) Tab "Felder" – zeigt NUR die gewählten Felder + Button fürs Modal
      $form['bundle_tabs_wrapper']['fields_tab'] = [
        '#type' => 'details',
        '#title' => $this->t('Felder'),
        '#group' => 'tabs',
        '#open' => FALSE,
      ];

      $fieldDefs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $selectedBundle);
      $formDisplay = \Drupal::service('entity_display.repository')->getFormDisplay('node', $selectedBundle, 'default');

      // Tabelle der aktuell gewählten Felder
      $rows = [];
      foreach ($currentChosen as $name) {
        if (!isset($fieldDefs[$name])) continue;

        /** @var FieldDefinitionInterface $def */
        $def = $fieldDefs[$name];
        $label = (string)($def->getLabel() ?? $name);
        $type = $def->getType();
        $card = $def->getFieldStorageDefinition()->getCardinality();
        $req = $def->isRequired() ? $this->t('ja') : $this->t('nein');

        // Widget aus dem (default) Form-Display lesen:
        $widget = '—';
        if ($formDisplay) {
          $comp = $formDisplay->getComponent($name);
          if (is_array($comp) && !empty($comp['type'])) {
            $widget = (string)$comp['type'];
          }
        }


        $rows[] = [
          ['data' => ['#markup' => $label]],
          ['data' => ['#markup' => $name]],
          ['data' => ['#markup' => $type]],
          ['data' => ['#markup' => $widget]],
          ['data' => ['#markup' => ($card === -1 ? $this->t('unbegrenzt') : (string)$card)]],
          ['data' => ['#markup' => $req]],
        ];
      }

      // Tabelle mit erweiterten Spalten:
      $form['bundle_tabs_wrapper']['fields_tab']['chosen_list'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Beschriftung'),
          $this->t('Systemname'),
          $this->t('Field-Type'),
          $this->t('Widget'),
          $this->t('Cardinality'),
          $this->t('Pflicht?'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Noch keine Felder ausgewählt.'),
      ];

      // Hidden/Value zur Übergabe in Validate/Submit
      $form['bundle_tabs_wrapper']['fields'] = [
        '#type' => 'value',
        '#value' => array_values($currentChosen),
      ];

      $form['bundle_tabs_wrapper']['fields_json'] = [
        '#type' => 'hidden',
        '#value' => json_encode(array_values($currentChosen), JSON_UNESCAPED_SLASHES),
      ];

      // 4) Link öffnet separate Route als Modal (Core Dialog)
      $url = Url::fromRoute(
        'htl_grid.field_picker',
        [],
        [
          'query' => [
            'bundle' => $selectedBundle,
            // NEU: aktuelle Auswahl ins Modal mitschicken
            'selected' => array_values($currentChosen),
          ],
          'attributes' => [
            'class' => ['use-ajax', 'button'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
          ],
        ]
      );
      $form['bundle_tabs_wrapper']['fields_tab']['actions'] = ['#type' => 'actions'];
      $form['bundle_tabs_wrapper']['fields_tab']['actions']['add_fields'] = [
        '#type' => 'link',
        '#title' => $this->t('Felder ändern'),
        '#url' => $url,
        '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      ];

      // --- NEU: Tab "Filter"
      // Textuelle Feld-Optionen für alphabetische Sortierung
      $textFieldOpts = ['title' => $this->t('Titel (Basisfeld)')];
      foreach ($fieldDefs as $fname => $def) {
        if ($def->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }
        $type = $def->getType();
        // typische Text-Typen
        if (in_array($type, ['string', 'string_long', 'text', 'text_long', 'text_with_summary'], TRUE)) {
          $label = (string)($def->getLabel() ?? $fname);
          $textFieldOpts[$fname] = $label . ' (' . $fname . ')';
        }
      }

      $filters = (array)($c['filters'] ?? []);
      $sortMode = (string)($filters['sort_mode'] ?? 'newest');
      $direction = strtoupper((string)($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
      $alphaField = array_key_exists(($filters['alpha_field'] ?? 'title'), $textFieldOpts)
        ? (string)$filters['alpha_field']
        : 'title';

      $form['bundle_tabs_wrapper']['filters_tab'] = [
        '#type' => 'details',
        '#title' => $this->t('Filter & Sortierung'),
        '#group' => 'tabs',
        '#open' => TRUE,
      ];

      $form['bundle_tabs_wrapper']['filters_tab']['sort_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Sortieren nach'),
        '#options' => [
          'newest' => $this->t('Neueste zuerst'),
          'oldest' => $this->t('Älteste zuerst'),
          'alpha'  => $this->t('Alphabetisch'),
          'random' => $this->t('Zufällig'),
        ],
        '#default_value' => $sortMode,
        '#description' => $this->t('Wähle die Reihenfolge der Inhalte.'),
      ];

      $form['bundle_tabs_wrapper']['filters_tab']['direction'] = [
        '#type' => 'select',
        '#title' => $this->t('Richtung'),
        '#options' => ['ASC' => 'ASC', 'DESC' => 'DESC'],
        '#default_value' => $direction,
        '#states' => [
          'visible' => [
            // Richtung nur zeigen bei alpha oder created-Sort?
            [':input[name$="[sort_mode]"]' => ['value' => 'alpha']],
          ],
        ],
      ];

      $form['bundle_tabs_wrapper']['filters_tab']['alpha_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Feld für alphabetische Sortierung'),
        '#options' => $textFieldOpts,
        '#default_value' => $alphaField,
        '#states' => [
          'visible' => [
            [':input[name$="[sort_mode]"]' => ['value' => 'alpha']],
          ],
        ],
        '#description' => $this->t('Wähle ein textuelles Feld (oder Titel).'),
      ];
    }

    return $form;
  }

  /**
   * Liefert Feld-Optionen (für Checkboxes) und Meta-Zeilen (für Tabelle) für ein Bundle.
   *
   * @return array{0: array<string,string>, 1: array<int,array<int,string>>}
   */
  private function getBundleFieldMeta(string $bundle): array
  {
    $fieldDefs = Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
    // Form-Display laden (für Widget-Typ)
    $formDisplay = Drupal::service('entity_display.repository')->getFormDisplay('node', $bundle, 'default');
    $fieldOptions = [];
    $rows = [];

    /** @var FieldDefinitionInterface $def */
    foreach ($fieldDefs as $name => $def) {
      // Basisfelder überspringen, wenn du nur konfigurierbare willst:
      if ($def->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      $label = (string)($def->getLabel() ?? $name);
      $type = (string)$def->getType();
      $card = (int)$def->getFieldStorageDefinition()->getCardinality();
      $req = $def->isRequired() ? 'yes' : 'no';
      // Widget-Typ (falls im Form-Display vorhanden)
      $widget = '';
      if ($formDisplay) {
        $comp = $formDisplay->getComponent($name);
        if (is_array($comp) && !empty($comp['type'])) {
          $widget = (string)$comp['type'];
        }
      }
      // Für deine Checkbox-Auswahl (du kannst hier auch nach Typ filtern)
      $fieldOptions[$name] = $label . " ($name)";
      // Tabellenzeile
      $rows[] = [
        $label,
        $name,
        $type,
        $widget ?: '—',
        $card === -1 ? 'unlimited' : (string)$card,
        $req,
      ];
    }
    // Optional: sortieren (z.B. nach Label)
    asort($fieldOptions);
    return [$fieldOptions, $rows];
  }

  /**
   * AJAX callback – gibt zuverlässig den Wrapper zurück (egal ob unter 'settings').
   */
  public static function ajaxRebuild(array $form, FormStateInterface $form_state)
  {
    // Helper: rohen Input-Wert für bundle lesen, mit/ohne "settings".
    $getInput = static function (array $path) use ($form_state) {
      $input = $form_state->getUserInput() ?? [];
      $paths = [$path, array_merge(['settings'], $path)];
      foreach ($paths as $p) {
        $v = NestedArray::getValue($input, $p, $exists);
        if ($exists) return [$v, $input, $p];
      }
      return [NULL, $input, $path];
    };

    // Aktuellen und vorherigen Bundle-Wert bestimmen
    [$curr, $input, $currPath] = $getInput(['content', 'bundle']);
    $prev = $form_state->get('prev_bundle');

    // Mini-Zusatz: wenn Bundle geändert -> Checkbox-Auswahl löschen
    if ($curr !== NULL && $prev !== NULL && $curr !== $prev) {
      // Felder liegen unter bundle_tabs_wrapper -> fields (mit/ohne settings)
      $fieldsPaths = [
        ['bundle_tabs_wrapper', 'fields'],
        ['settings', 'bundle_tabs_wrapper', 'fields'],
      ];
      foreach ($fieldsPaths as $fp) {
        if (NestedArray::getValue($input, $fp, $exists)) {
          NestedArray::setValue($input, $fp, []);
        }
      }
      $form_state->setUserInput($input);
    }

    // Aktuellen Bundle merken
    $form_state->set('prev_bundle', $curr);
    // Rebuild erzwingen
    $form_state->setRebuild(TRUE);

    // Wrapper zurückgeben (Block-Form liegt oft unter 'settings')
    if (isset($form['settings']['bundle_tabs_wrapper'])) {
      return $form['settings']['bundle_tabs_wrapper'];
    }
    return $form['bundle_tabs_wrapper'] ?? $form;
  }

  public function blockValidate($form, FormStateInterface $form_state): void
  {
    // Feld-Auswahl Limit
    $json = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'fields_json'], '[]');
    $chosen = json_decode($json, true);
    if (!is_array($chosen)) {
      $chosen = (array)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'fields'], []);
    }
    $chosen = array_values(array_filter($chosen, fn($v) => (string)$v !== '0'));
    if (count($chosen) > 3) {
      $form_state->setErrorByName('bundle_tabs_wrapper][fields_json', $this->t('Bitte maximal 3 Felder auswählen.'));
    }

    // Layout validieren
    $cols = (int)$this->readValue($form, $form_state, ['layout', 'columns'], 3);
    $rows = (int)$this->readValue($form, $form_state, ['layout', 'rows'], 2);
    if ($cols < 1 || $cols > 12) {
      $form_state->setErrorByName('layout][columns', $this->t('Spalten müssen zwischen 1 und 12 liegen.'));
    }
    if ($rows < 1 || $rows > 12) {
      $form_state->setErrorByName('layout][rows', $this->t('Zeilen müssen zwischen 1 und 12 liegen.'));
    }

    // Filter validieren (alpha benötigt ein Feld)
    $sortMode = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'filters_tab', 'sort_mode'], 'newest');
    if ($sortMode === 'alpha') {
      $alphaField = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'filters_tab', 'alpha_field'], 'title');
      if ($alphaField === '') {
        $form_state->setErrorByName('bundle_tabs_wrapper][filters_tab][alpha_field', $this->t('Bitte ein Feld für alphabetische Sortierung wählen.'));
      }
    }
  }


  public function blockSubmit($form, FormStateInterface $form_state): void
  {
    $bundle = (string)$this->readValue($form, $form_state, ['content', 'bundle'], '');
    $this->setConfigurationValue('bundle', $bundle);

    $columns = (int)$this->readValue($form, $form_state, ['layout', 'columns'], 3);
    $rows = (int)$this->readValue($form, $form_state, ['layout', 'rows'], 2);
    $this->setConfigurationValue('columns', $columns);
    $this->setConfigurationValue('rows', $rows);

    // Filter speichern
    $sortMode = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'filters_tab', 'sort_mode'], 'newest');
    $direction = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'filters_tab', 'direction'], 'ASC');
    $alphaField = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'filters_tab', 'alpha_field'], 'title');
    $this->setConfigurationValue('filters', [
      'sort_mode' => $sortMode,
      'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
      'alpha_field' => $alphaField ?: 'title',
    ]);

    $bundleFields = [];
    if ($bundle !== '') {
      $json = (string)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'fields_json'], '[]');
      $chosen = json_decode($json, true);
      if (!is_array($chosen)) {
        $chosen = (array)$this->readValue($form, $form_state, ['bundle_tabs_wrapper', 'fields'], []);
      }
      $chosen = array_values(array_filter($chosen, fn($v) => (string)$v !== '0'));
      $bundleFields[$bundle] = array_slice($chosen, 0, 3);
    }

    $this->setConfigurationValue('bundle_fields', $bundleFields);
  }


  /**
   * Liest einen Wert sicher – ohne direkt SubformState->getValues() zu verwenden.
   *
   * Strategie:
   *  - In build/AJAX-Phasen zuerst aus getUserInput() lesen (roh, unabhängig von SubformState).
   *  - Falls nicht vorhanden und wir im Submit/Validate sind, aus dem "complete" FormState lesen.
   *  - Fallback auf $default.
   *
   * @param array $form Das aktuelle (Sub-)Form-Array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $relativePath Pfad relativ zu den #parents dieses Subforms.
   * @param mixed|null $default
   * @return mixed
   */
  private function readValue(array $form, FormStateInterface $form_state, array $relativePath, mixed $default = NULL): mixed
  {
    $parents = $form['#parents'] ?? [];
    $fullPath = array_merge($parents, $relativePath);

    $candidates = [
      $fullPath,
      array_merge(['settings'], $fullPath),
    ];

    $input = $form_state->getUserInput() ?? [];
    foreach ($candidates as $path) {
      $value = NestedArray::getValue($input, $path, $exists);
      if ($exists) {
        return $value;
      }
    }

    $rootState = ($form_state instanceof SubformStateInterface)
      ? $form_state->getCompleteFormState()
      : $form_state;

    if (method_exists($rootState, 'getValues')) {
      $allValues = $rootState->getValues();
      foreach ($candidates as $path) {
        $value = NestedArray::getValue($allValues, $path, $exists2);
        if ($exists2) {
          return $value;
        }
      }
    }

    return $default;
  }

}
