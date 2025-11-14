<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\node\NodeInterface;

final class GridCardBuilder
{
  public function __construct(
    private readonly FieldRenderer $fieldRenderer,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * Baut die Datenstruktur für das Twig-Template.
   *
   * @param GridConfig                         $config
   * @param array<string, NodeInterface[]>     $nodesByBundle
   * @param CacheableMetadata                  $cache
   *
   * @return array<int, array<string,mixed>>
   */
  public function buildBundlesData(
    GridConfig $config,
    array $nodesByBundle,
    CacheableMetadata $cache,
  ): array {
    $bundleInfo = $this->bundleInfo->getBundleInfo("node");
    $result = [];

    foreach ($nodesByBundle as $bundle => $nodes) {
      $cards = [];
      $fieldsForBundle = $config->fieldsForBundle($bundle);

      /** @var NodeInterface $node */
      foreach ($nodes as $node) {
        $rawFields = $this->fieldRenderer->buildCardFields(
          $node,
          $fieldsForBundle,
          $cache,
        );
        [$image, $meta] = $this->splitImageField($rawFields);

        $cards[] = [
          "title" => $node->label(),
          "url" => $node->toUrl()->toString(),
          "image" => $image,
          "meta" => $meta,
        ];
      }

      $result[] = [
        "bundle" => $bundle,
        "bundle_label" => $bundleInfo[$bundle]["label"] ?? $bundle,
        "nodes" => $cards,
      ];
    }

    return $result;
  }

  /**
   * Nimmt die vom FieldRenderer gelieferten Felder und trennt das erste Bildfeld ab.
   *
   * @param array<int, mixed> $fields
   *
   * @return array{0: mixed, 1: array<int, mixed>} [image, meta]
   */
  private function splitImageField(array $fields): array
  {
    $image = null;
    $meta = [];

    foreach ($fields as $field) {
      if (is_array($field) && isset($field["value"])) {
        $class = (string) ($field["class"] ?? "");
        $value = $field["value"];

        $isImage =
          str_starts_with($class, "image") ||
          (is_array($value) &&
            isset($value["#theme"]) &&
            str_starts_with((string) $value["#theme"], "image"));

        if ($isImage && $image === null) {
          // Nur das erste Bildfeld wird als Card-Image verwendet
          $image = $value;
          continue;
        }

        $meta[] = $field;
      } else {
        // Falls FieldRenderHelper mal plain Strings zurückgibt
        $meta[] = $field;
      }
    }

    return [$image, $meta];
  }
}
