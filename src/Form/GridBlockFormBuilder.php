<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\SubformStateInterface;

/**
 * Kapselt das Block-Formular für den GridBlock.
 *
 * Der Block ruft nur noch ->build() und ->submit() auf.
 */
final class GridBlockFormBuilder
{
  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  public function build(
    BlockPluginInterface $block,
    array $form,
    FormStateInterface $form_state,
  ): array {
    $config = $block->getConfiguration();

    $form["settings"] = [
      "#type" => "vertical_tabs",
      "#title" => t("Grid settings"),
    ];

    $this->buildBundleSection($form, $config);
    $this->buildLayoutSection($form, $config);
    $this->buildFilterSection($form, $config);
    $this->buildFieldsSection($form, $config, $block, $form_state);

    return $form;
  }

  public function submit(
    BlockPluginInterface $block,
    array $form,
    FormStateInterface $form_state,
  ): void {
    $values = $form_state->getValues();

    $bundle = $values["general"]["bundle"] ?? null;
    $columns = (int) ($values["layout"]["columns"] ?? 3);
    $rows = (int) ($values["layout"]["rows"] ?? 2);
    $filters = $values["filters"] ?? [];

    $config = $block->getConfiguration();
    $config["bundle"] = $bundle;
    $config["columns"] = $columns;
    $config["rows"] = $rows;
    $config["filters"] = [
      "sort_mode" => $filters["sort_mode"] ?? "newest",
      "alpha_field" => $filters["alpha_field"] ?? "title",
      "direction" => $filters["direction"] ?? "ASC",
    ];

    // Tempstore → bundle_fields
    if ($bundle) {
      $store = \Drupal::service("tempstore.private")->get("htl_grid");
      $picked = $store->get("picker." . $bundle) ?? [];

      if (is_array($picked)) {
        $config["bundle_fields"] ??= [];
        $config["bundle_fields"][$bundle] = $picked;
      }
    }

    $block->setConfiguration($config);
  }

  // ---------------------------------------------------------------------------
  //  Form-Sektionen
  // ---------------------------------------------------------------------------

  private function buildBundleSection(array &$form, array $config): void
  {
    $selectedBundle = $config["bundle"] ?? "";

    $bundles = $this->bundleInfo->getBundleInfo("node");
    $options = [];
    foreach ($bundles as $machine => $info) {
      $options[$machine] = $info["label"] ?? $machine;
    }

    $form["general"] = [
      "#type" => "details",
      "#title" => t("Content"),
      "#group" => "settings",
      "#open" => true,
    ];

    $form["general"]["bundle"] = [
      "#type" => "select",
      "#title" => t("Content type"),
      "#description" => t(
        "Select which content type this grid should display.",
      ),
      "#options" => $options,
      "#empty_option" => t("- Select -"),
      "#default_value" => $selectedBundle,
      "#required" => true,
      "#ajax" => [
        "callback" => [static::class, "bundleChangeAjax"],
        "wrapper" => "htl-typegrid-fields-wrapper",
      ],
    ];
  }

  private function buildLayoutSection(array &$form, array $config): void
  {
    $columns = (int) ($config["columns"] ?? 3);
    $rows = (int) ($config["rows"] ?? 2);

    $form["layout"] = [
      "#type" => "details",
      "#title" => t("Layout"),
      "#group" => "settings",
      "#open" => true,
    ];

    $form["layout"]["columns"] = [
      "#type" => "number",
      "#title" => t("Columns"),
      "#min" => 1,
      "#max" => 6,
      "#default_value" => $columns,
      "#description" => t("Number of columns in the grid (desktop)."),
      "#required" => true,
    ];

    $form["layout"]["rows"] = [
      "#type" => "number",
      "#title" => t("Rows"),
      "#min" => 1,
      "#max" => 10,
      "#default_value" => $rows,
      "#description" => t("Number of rows (used for maximum items per type)."),
      "#required" => true,
    ];
  }

  private function buildFilterSection(array &$form, array $config): void
  {
    $filters = (array) ($config["filters"] ?? []);
    $sortMode = $filters["sort_mode"] ?? "newest";
    $alphaField = $filters["alpha_field"] ?? "title";
    $direction = $filters["direction"] ?? "ASC";

    $form["filters"] = [
      "#type" => "details",
      "#title" => t("Filters & sorting"),
      "#group" => "settings",
      "#open" => false,
    ];

    $form["filters"]["sort_mode"] = [
      "#type" => "select",
      "#title" => t("Sort mode"),
      "#options" => [
        "newest" => t("Newest first"),
        "oldest" => t("Oldest first"),
        "alpha" => t("Alphabetical"),
        "random" => t("Random"),
      ],
      "#default_value" => $sortMode,
    ];

    $form["filters"]["direction"] = [
      "#type" => "select",
      "#title" => t("Direction"),
      "#options" => [
        "ASC" => t("Ascending"),
        "DESC" => t("Descending"),
      ],
      "#default_value" => $direction,
      "#states" => [
        "visible" => [
          ':input[name="settings[filters][sort_mode]"]' => ["value" => "alpha"],
        ],
      ],
    ];

    $form["filters"]["alpha_field"] = [
      "#type" => "textfield",
      "#title" => t("Alphabetic field"),
      "#description" => t(
        'Machine name of the field used for alphabetical sorting (e.g. "title").',
      ),
      "#default_value" => $alphaField,
      "#states" => [
        "visible" => [
          ':input[name="settings[filters][sort_mode]"]' => ["value" => "alpha"],
        ],
      ],
    ];
  }

  private function buildFieldsSection(
    array &$form,
    array $config,
    BlockPluginInterface $block,
    FormStateInterface $form_state,
  ): void {
    // Statt nur Config auch Form-Wert beachten:
    $bundle = $this->readValue(
      $form,
      $form_state,
      ["general", "bundle"],
      $config["bundle"] ?? null,
    );

    $form["fields"] = [
      "#type" => "details",
      "#title" => t("Fields"),
      "#group" => "settings",
      "#open" => true,
    ];

    $form["fields"]["wrapper"] = [
      "#type" => "container",
      "#attributes" => ["id" => "htl-typegrid-fields-wrapper"],
    ];

    if (!$bundle) {
      $form["fields"]["wrapper"]["message"] = [
        "#markup" => t("Please select a content type."),
      ];
      return;
    }

    // Tempstore lesen (vom FieldPickerForm gesetzt)
    $store = \Drupal::service("tempstore.private")->get("htl_grid");
    $tempPicked = $store->get("picker." . $bundle);

    if (is_array($tempPicked)) {
      $bundleFields = $tempPicked;
    } else {
      $bundleFields = (array) ($config["bundle_fields"][$bundle] ?? []);
    }

    $rows = [];
    foreach ($bundleFields as $fieldName) {
      $rows[] = [
        "field" => [
          "data" => $fieldName,
        ],
      ];
    }

    $form["fields"]["wrapper"]["current"] = [
      "#type" => "table",
      "#header" => [
        "field" => t("Selected fields"),
      ],
      "#rows" => $rows,
      "#empty" => t("No fields selected yet."),
    ];

    // FieldPicker-Link (Route-Namen ggf. anpassen!)
    $routeName = "htl_typegrid.field_picker"; // <- hier ggf. deinen echten Routennamen verwenden
    $url = Url::fromRoute($routeName, [
      "bundle" => $bundle,
    ]);

    $form["fields"]["wrapper"]["pick"] = [
      "#type" => "link",
      "#title" => t("Choose fields"),
      "#url" => $url,
      "#attributes" => [
        "class" => ["use-ajax", "button", "button--primary"],
        "data-dialog-type" => "modal",
        "data-dialog-options" => json_encode(["width" => 700]),
      ],
    ];
  }

  /**
   * Ajax-Callback für Bundle-Wechsel.
   */
  public static function bundleChangeAjax(
    array &$form,
    FormStateInterface $form_state,
  ): array {
    // Pfad entsprechend dem Aufbau in build():
    return $form["settings"]["fields"]["wrapper"];
  }

  /**
   * Liest einen Wert sicher – ohne direkt SubformState->getValues() zu verwenden.
   *
   * @param array $form
   *   Das aktuelle (Sub-)Form-Array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $relativePath
   *   Pfad relativ zu den #parents dieses Subforms.
   * @param mixed|null $default
   *
   * @return mixed
   */
  private function readValue(
    array $form,
    FormStateInterface $form_state,
    array $relativePath,
    mixed $default = null,
  ): mixed {
    $parents = $form["#parents"] ?? [];
    $fullPath = array_merge($parents, $relativePath);

    // Kandidaten: direkt und mit "settings" davor (Block-Form-Struktur)
    $candidates = [$fullPath, array_merge(["settings"], $fullPath)];

    // 1) Rohes User-Input prüfen (funktioniert auch bei Ajax/Subforms)
    $input = $form_state->getUserInput() ?? [];
    foreach ($candidates as $path) {
      $value = NestedArray::getValue($input, $path, $exists);
      if ($exists) {
        return $value;
      }
    }

    // 2) Falls SubformState: kompletten FormState holen
    $rootState =
      $form_state instanceof SubformStateInterface
        ? $form_state->getCompleteFormState()
        : $form_state;

    if (method_exists($rootState, "getValues")) {
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
