<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Rendert Felder eines Nodes in ein einheitliches Card-Format.
 *
 * Rückgabe-Struktur pro Feld:
 * [
 *   'label' => string|null,
 *   'value' => Render-Array oder string,
 *   'class' => string,
 * ]
 */
final class FieldRenderer
{
  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Baut die Feld-Infos für eine Card.
   *
   * @param NodeInterface     $node
   * @param string[]          $fieldNames
   * @param CacheableMetadata $cache
   *
   * @return array<int, array<string,mixed>>
   */
  public function buildCardFields(
    NodeInterface $node,
    array $fieldNames,
    CacheableMetadata $cache,
  ): array {
    $out = [];

    foreach ($fieldNames as $fieldName) {
      if (!$fieldName || !$node->hasField($fieldName)) {
        continue;
      }

      /** @var FieldItemListInterface $field */
      $field = $node->get($fieldName);
      $def = $field->getFieldDefinition();
      $type = $def->getType();
      $label = (string) ($def->getLabel() ?? $fieldName);

      // lokales Feld-Ergebnis
      $item = null;

      // 1) Media-Referenz (Medienbibliothek)
      if (
        $type === "entity_reference" &&
        $def->getSetting("target_type") === "media"
      ) {
        $item = $this->buildMediaImageField($field, $label, $cache);
      }
      // 2) Klassisches Image-Feld
      elseif ($type === "image") {
        $item = $this->buildImageField($field, $label, $cache);
      }
      // 3) Entity-Reference allgemein (z.B. Taxonomie)
      elseif ($type === "entity_reference") {
        $item = $this->buildEntityReferenceField($field, $label, $cache);
      }
      // 4) text_with_summary
      elseif ($type === "text_with_summary") {
        $item = $this->buildTextWithSummaryField($field, $label);
      }
      // 5) Long/Formatted text
      elseif (in_array($type, ["text_long", "text"], true)) {
        $item = $this->buildLongTextField($field, $label);
      }
      // 6) Plain strings
      elseif (in_array($type, ["string", "string_long"], true)) {
        $item = $this->buildStringField($field, $label);
      }
      // 7) Fallback – alles andere als Text
      else {
        $item = $this->buildFallbackField($field, $label);
      }

      if ($item !== null) {
        $out[] = $item;
      }
    }

    return $out;
  }

  // ---------------------------------------------------------------------------
  //  Spezialisierte Feld-Handler
  // ---------------------------------------------------------------------------

  private function buildMediaImageField(
    FieldItemListInterface $field,
    string $label,
    CacheableMetadata $cache,
  ): ?array {
    $style = $this->getCardStyleName();

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $field->entity;
    $file = null;

    if ($media instanceof MediaInterface) {
      // Standard-Core: field_media_image
      $file = $media->get("field_media_image")->entity ?? null;
      $cache->addCacheableDependency($media);
    }

    if ($file instanceof FileInterface) {
      $cache->addCacheableDependency($file);

      return [
        "label" => null,
        "class" => "image image--media",
        "value" => [
          "#theme" => "image_style",
          "#style_name" => $style,
          "#uri" => $file->getFileUri(),
          "#alt" => $label,
          "#title" => "",
        ],
      ];
    }

    // Fallback-Bild (Dummy aus Config)
    $fallback = $this->getFallbackImageData();
    if ($fallback) {
      foreach ($fallback["cache_deps"] as $dep) {
        $cache->addCacheableDependency($dep);
      }

      return [
        "label" => null,
        "class" => "image image--fallback",
        "value" => [
          "#theme" => "image_style",
          "#style_name" => $style,
          "#uri" => $fallback["uri"],
          "#alt" => $fallback["alt"],
          "#title" => "",
        ],
      ];
    }

    return null;
  }

  private function buildImageField(
    FieldItemListInterface $field,
    string $label,
    CacheableMetadata $cache,
  ): ?array {
    $style = $this->getCardStyleName();
    $item = $field->first();
    /** @var FileInterface|null $file */
    $file = $item?->entity;

    if ($file instanceof FileInterface) {
      $cache->addCacheableDependency($file);

      return [
        "label" => null,
        "class" => "image",
        "value" => [
          "#theme" => "image_style",
          "#style_name" => $style,
          "#uri" => $file->getFileUri(),
          "#alt" => (string) ($item->alt ?? $label),
          "#title" => (string) ($item->title ?? ""),
        ],
      ];
    }

    // Fallback-Bild
    $fallback = $this->getFallbackImageData();
    if ($fallback) {
      foreach ($fallback["cache_deps"] as $dep) {
        $cache->addCacheableDependency($dep);
      }

      return [
        "label" => null,
        "class" => "image image--fallback",
        "value" => [
          "#theme" => "image_style",
          "#style_name" => $style,
          "#uri" => $fallback["uri"],
          "#alt" => $fallback["alt"],
          "#title" => "",
        ],
      ];
    }

    return null;
  }

  private function buildEntityReferenceField(
    FieldItemListInterface $field,
    string $label,
    CacheableMetadata $cache,
  ): ?array {
    $labels = [];
    foreach ($field->referencedEntities() as $ref) {
      $labels[] = $ref->label();
      $cache->addCacheableDependency($ref);
    }

    if (!$labels) {
      return null;
    }

    return [
      "label" => $label,
      "value" => ["#markup" => implode(", ", $labels)],
      "class" => "htl-field--reference",
    ];
  }

  private function buildTextWithSummaryField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $item = $field->first();
    if (!$item) {
      return null;
    }

    $format = (string) ($item->format ?? "basic_html");
    $summary = (string) ($item->summary ?? "");

    // Falls Summary vorhanden: voll anzeigen
    if (trim($summary) !== "") {
      return [
        "label" => $label,
        "value" => [
          "#type" => "processed_text",
          "#text" => $summary,
          "#format" => $format,
        ],
        "class" => "htl-field--text",
      ];
    }

    // Sonst: gekürzte Version des Volltextes, HTML-sicher, 35 Wörter
    $allowed = [
      "a",
      "strong",
      "em",
      "b",
      "i",
      "u",
      "br",
      "p",
      "ul",
      "ol",
      "li",
      "span",
    ];
    $filtered = Xss::filter((string) ($item->value ?? ""), $allowed);
    $trimmedHtml = $this->htmlTrimWords($filtered, 35);

    if ($trimmedHtml === "") {
      return null;
    }

    return [
      "label" => $label,
      "value" => [
        "#type" => "processed_text",
        "#text" => $trimmedHtml,
        "#format" => $format,
      ],
      "class" => "htl-field--text",
    ];
  }

  private function buildLongTextField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $item = $field->first();
    $value = (string) ($item->value ?? "");

    if (trim($value) === "") {
      return null;
    }

    $format = (string) ($item->format ?? "basic_html");

    return [
      "label" => $label,
      "value" => [
        "#type" => "processed_text",
        "#text" => $value,
        "#format" => $format,
      ],
      "class" => "htl-field--text",
    ];
  }

  private function buildStringField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $raw = $field->value ?? $field->getString();
    $clean = trim((string) $raw);

    if ($clean === "") {
      return null;
    }

    $maxLength = 200; // TODO: später aus Config holen
    $text = $this->truncate($clean, $maxLength);

    return [
      "label" => $label,
      "value" => [
        "#markup" => htmlspecialchars(
          $text,
          ENT_QUOTES | ENT_SUBSTITUTE,
          "UTF-8",
        ),
      ],
      "class" => "htl-field--text",
    ];
  }

  private function buildFallbackField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $raw = $field->value ?? $field->getString();
    $clean = trim((string) $raw);

    if ($clean === "") {
      return null;
    }

    $maxLength = 200;
    $text = $this->truncate($clean, $maxLength);

    return [
      "label" => $label,
      "value" => [
        "#markup" => htmlspecialchars(
          $text,
          ENT_QUOTES | ENT_SUBSTITUTE,
          "UTF-8",
        ),
      ],
      "class" => "htl-field--text",
    ];
  }

  // ---------------------------------------------------------------------------
  //  Helpers: Fallback-Bild + Text-Helfer
  // ---------------------------------------------------------------------------

  private function getCardStyleName(): string
  {
    $style =
      (string) $this->configFactory
        ->get("htl_typegrid.settings")
        ->get("image_style") ?:
      "large";
    // hier könntest du später mit imageStyleExists() prüfen, ob der Style existiert
    return $style;
  }

  /**
   * Liefert URI + ALT + Cache-Dependencies für das Dummy-Medienbild.
   *
   * @return array{uri:string,alt:string,cache_deps:array<CacheableDependencyInterface>}|null
   */
  private function getFallbackImageData(): ?array
  {
    $config = $this->configFactory->get("htl_typegrid.settings");
    $uuid = (string) $config->get("fallback_media_uuid");

    if (!$uuid) {
      return null;
    }

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityRepository->loadEntityByUuid("media", $uuid);
    if (!$media instanceof MediaInterface) {
      return null;
    }

    $imageField = $media->get("field_media_image") ?? null;
    $file = $imageField?->entity;
    if (!$file instanceof FileInterface) {
      return null;
    }

    $deps = [];
    if ($media instanceof CacheableDependencyInterface) {
      $deps[] = $media;
    }
    if ($file instanceof CacheableDependencyInterface) {
      $deps[] = $file;
    }

    return [
      "uri" => $file->getFileUri(),
      "alt" => (string) ($imageField->alt ?? $media->label()),
      "cache_deps" => $deps,
    ];
  }

  /**
   * Schneidet HTML nach einer maximalen Anzahl Wörter ab
   * und versucht offene Tags wieder korrekt zu schließen.
   */
  private function htmlTrimWords(string $html, int $maxWords): string
  {
    $doc = new \DOMDocument("1.0", "UTF-8");
    $wrapped = "<div>" . $html . "</div>";

    // Fehler unterdrücken, falls HTML nicht perfekt ist
    libxml_use_internal_errors(true);
    $doc->loadHTML(
      '<?xml encoding="UTF-8">' . $wrapped,
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();

    $body = $doc->getElementsByTagName("div")->item(0);
    if (!$body) {
      return "";
    }

    $wordCount = 0;
    $stop = false;

    $walker = function (\DOMNode $node) use (
      &$walker,
      &$wordCount,
      $maxWords,
      &$stop,
    ): void {
      if ($stop) {
        // entferne restliche Kinder
        while ($node->hasChildNodes()) {
          $node->removeChild($node->lastChild);
        }
        return;
      }

      for ($i = 0; $i < $node->childNodes->length; $i++) {
        $child = $node->childNodes->item($i);
        if (!$child) {
          continue;
        }

        if ($child->nodeType === XML_TEXT_NODE) {
          $words = preg_split(
            "/(\s+)/u",
            $child->nodeValue,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
          );
          $buffer = "";
          foreach ($words as $part) {
            if (trim($part) === "") {
              $buffer .= $part;
              continue;
            }
            $wordCount++;
            if ($wordCount > $maxWords) {
              $stop = true;
              $buffer .= "…";
              $child->nodeValue = $buffer;
              // restliche Geschwister löschen
              while ($child->nextSibling) {
                $child->parentNode->removeChild($child->nextSibling);
              }
              return;
            }
            $buffer .= $part;
          }
          $child->nodeValue = $buffer;
        } elseif ($child->hasChildNodes()) {
          $walker($child);
        }

        if ($stop) {
          // nach Limit: alle weiteren Geschwister entfernen
          while ($child->nextSibling) {
            $child->parentNode->removeChild($child->nextSibling);
          }
          return;
        }
      }
    };

    $walker($body);

    $innerHTML = "";
    foreach ($body->childNodes as $child) {
      $innerHTML .= $doc->saveHTML($child);
    }

    return $innerHTML;
  }

  private function truncate(string $text, int $max): string
  {
    $text = preg_replace("/\s+/", " ", trim($text)) ?? "";
    return mb_strlen($text) <= $max
      ? $text
      : rtrim(mb_substr($text, 0, $max - 1)) . "…";
  }
}
