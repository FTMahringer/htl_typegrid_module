<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\htl_typegrid\Model\GridFieldValue;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Renders node fields into a standardized card format.
 *
 * Handles various field types including:
 * - Images (direct and media reference)
 * - Text fields (plain, formatted, with summary)
 * - Entity references
 * - Generic fallback for other types
 */
final class FieldRenderer {

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Builds field information for a card from GridFieldValue objects.
   *
   * @param GridFieldValue[] $fields
   *   Array of field values to render.
   *
   * @return array
   *   Array of render arrays, each with 'value', 'label', and 'class' keys.
   */
  public function renderFields(array $fields): array {
    $out = [];

    foreach ($fields as $field) {
      if ($field->isImage) {
        continue; // Images are handled separately
      }

      $item = $this->renderFieldValue($field);
      if ($item !== null) {
        $out[] = $item;
      }
    }

    return $out;
  }

  /**
   * Renders a single GridFieldValue.
   *
   * @param GridFieldValue $field
   *
   * @return array|null
   *   Render array with 'label', 'value', and 'class', or null if empty.
   */
  private function renderFieldValue(GridFieldValue $field): ?array {
    $formatted = $field->formatted;

    if ($formatted === null || trim($formatted) === '') {
      return null;
    }

    // Determine if this is HTML content or plain text
    $isHtml = $this->containsHtml($formatted);

    if ($isHtml) {
      // Allow safe HTML tags
      $allowed = ['a', 'strong', 'em', 'b', 'i', 'u', 'br', 'p', 'ul', 'ol', 'li', 'span'];
      $filtered = Xss::filter($formatted, $allowed);

      // Trim long HTML content to ~35 words
      $trimmed = $this->htmlTrimWords($filtered, 35);

      return [
        'label' => null,
        'value' => [
          '#type' => 'processed_text',
          '#text' => $trimmed,
          '#format' => 'basic_html',
        ],
        'class' => 'htl-field--text htl-field--' . $field->type,
      ];
    }

    // Plain text - truncate and escape
    $maxLength = 200;
    $text = $this->truncate($formatted, $maxLength);

    return [
      'label' => null,
      'value' => [
        '#markup' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
      ],
      'class' => 'htl-field--text htl-field--' . $field->type,
    ];
  }

  /**
   * Builds field information for a card (legacy method for direct node access).
   *
   * @param NodeInterface $node
   *   The node to extract fields from.
   * @param string[] $fieldNames
   *   Array of field machine names to render.
   * @param CacheableMetadata $cache
   *   Cache metadata to add dependencies to.
   *
   * @return array
   *   Array of render arrays.
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

      $item = null;

      // 1) Media reference (media library)
      if ($type === 'entity_reference' && $def->getSetting('target_type') === 'media') {
        $item = $this->buildMediaImageField($field, $label, $cache);
      }
      // 2) Classic image field
      elseif ($type === 'image') {
        $item = $this->buildImageField($field, $label, $cache);
      }
      // 3) Entity reference (e.g., taxonomy)
      elseif ($type === 'entity_reference') {
        $item = $this->buildEntityReferenceField($field, $label, $cache);
      }
      // 4) Text with summary
      elseif ($type === 'text_with_summary') {
        $item = $this->buildTextWithSummaryField($field, $label);
      }
      // 5) Long/formatted text
      elseif (in_array($type, ['text_long', 'text'], true)) {
        $item = $this->buildLongTextField($field, $label);
      }
      // 6) Plain strings
      elseif (in_array($type, ['string', 'string_long'], true)) {
        $item = $this->buildStringField($field, $label);
      }
      // 7) Fallback for everything else
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
  // Specialized field handlers
  // ---------------------------------------------------------------------------

  private function buildMediaImageField(
    FieldItemListInterface $field,
    string $label,
    CacheableMetadata $cache,
  ): ?array {
    $style = $this->getCardStyleName();

    $firstItem = $field->first();
    if (!$firstItem) {
      return $this->buildFallbackImage($cache);
    }

    /** @var MediaInterface|null $media */
    $media = $firstItem->entity ?? null;
    $file = null;

    if ($media instanceof MediaInterface) {
      $imageField = $media->get('field_media_image');
      if ($imageField && !$imageField->isEmpty()) {
        $imageItem = $imageField->first();
        if ($imageItem && isset($imageItem->entity)) {
          $file = $imageItem->entity;
        }
      }
      $cache->addCacheableDependency($media);
    }

    if ($file instanceof FileInterface) {
      $cache->addCacheableDependency($file);

      return [
        'label' => null,
        'class' => 'image image--media',
        'value' => [
          '#theme' => 'image_style',
          '#style_name' => $style,
          '#uri' => $file->getFileUri(),
          '#alt' => $label,
          '#title' => '',
        ],
      ];
    }

    return $this->buildFallbackImage($cache);
  }

  private function buildImageField(
    FieldItemListInterface $field,
    string $label,
    CacheableMetadata $cache,
  ): ?array {
    $style = $this->getCardStyleName();
    $item = $field->first();
    if (!$item) {
      return $this->buildFallbackImage($cache);
    }

    /** @var FileInterface|null $file */
    $file = $item->entity ?? null;

    if ($file instanceof FileInterface) {
      $cache->addCacheableDependency($file);

      return [
        'label' => null,
        'class' => 'image',
        'value' => [
          '#theme' => 'image_style',
          '#style_name' => $style,
          '#uri' => $file->getFileUri(),
          '#alt' => (string) ($item->alt ?? $label),
          '#title' => (string) ($item->title ?? ''),
        ],
      ];
    }

    return $this->buildFallbackImage($cache);
  }

  private function buildEntityReferenceField(
    FieldItemListInterface $field,
    string $label,
    CacheableMetadata $cache,
  ): ?array {
    $labels = [];

    foreach ($field as $item) {
      $entity = $item->entity ?? null;
      if ($entity) {
        $labels[] = $entity->label();
        $cache->addCacheableDependency($entity);
      }
    }

    if (!$labels) {
      return null;
    }

    return [
      'label' => $label,
      'value' => ['#markup' => implode(', ', $labels)],
      'class' => 'htl-field--reference',
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

    $format = (string) ($item->format ?? 'basic_html');
    $summary = (string) ($item->summary ?? '');

    // If summary exists, use it
    if (trim($summary) !== '') {
      return [
        'label' => null,
        'value' => [
          '#type' => 'processed_text',
          '#text' => $summary,
          '#format' => $format,
        ],
        'class' => 'htl-field--text',
      ];
    }

    // Otherwise: truncated version of full text, HTML-safe, 35 words
    $allowed = ['a', 'strong', 'em', 'b', 'i', 'u', 'br', 'p', 'ul', 'ol', 'li', 'span'];
    $filtered = Xss::filter((string) ($item->value ?? ''), $allowed);
    $trimmedHtml = $this->htmlTrimWords($filtered, 35);

    if ($trimmedHtml === '') {
      return null;
    }

    return [
      'label' => null,
      'value' => [
        '#type' => 'processed_text',
        '#text' => $trimmedHtml,
        '#format' => $format,
      ],
      'class' => 'htl-field--text',
    ];
  }

  private function buildLongTextField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $item = $field->first();
    $value = (string) ($item->value ?? '');

    if (trim($value) === '') {
      return null;
    }

    $format = (string) ($item->format ?? 'basic_html');

    // Truncate HTML to 35 words
    $allowed = ['a', 'strong', 'em', 'b', 'i', 'u', 'br', 'p', 'ul', 'ol', 'li', 'span'];
    $filtered = Xss::filter($value, $allowed);
    $trimmed = $this->htmlTrimWords($filtered, 35);

    return [
      'label' => null,
      'value' => [
        '#type' => 'processed_text',
        '#text' => $trimmed,
        '#format' => $format,
      ],
      'class' => 'htl-field--text',
    ];
  }

  private function buildStringField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $raw = $field->value ?? $field->getString();
    $clean = trim((string) $raw);

    if ($clean === '') {
      return null;
    }

    $maxLength = 200;
    $text = $this->truncate($clean, $maxLength);

    return [
      'label' => null,
      'value' => [
        '#markup' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
      ],
      'class' => 'htl-field--string',
    ];
  }

  private function buildFallbackField(
    FieldItemListInterface $field,
    string $label,
  ): ?array {
    $raw = $field->value ?? $field->getString();
    $clean = trim((string) $raw);

    if ($clean === '') {
      return null;
    }

    $maxLength = 200;
    $text = $this->truncate($clean, $maxLength);

    return [
      'label' => null,
      'value' => [
        '#markup' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
      ],
      'class' => 'htl-field--text',
    ];
  }

  // ---------------------------------------------------------------------------
  // Helpers: Fallback image + text helpers
  // ---------------------------------------------------------------------------

  private function buildFallbackImage(CacheableMetadata $cache): ?array {
    $fallback = $this->getFallbackImageData();
    if (!$fallback) {
      return null;
    }

    foreach ($fallback['cache_deps'] as $dep) {
      $cache->addCacheableDependency($dep);
    }

    return [
      'label' => null,
      'class' => 'image image--fallback',
      'value' => [
        '#theme' => 'image_style',
        '#style_name' => $this->getCardStyleName(),
        '#uri' => $fallback['uri'],
        '#alt' => $fallback['alt'],
        '#title' => '',
      ],
    ];
  }

  private function getCardStyleName(): string {
    $style = (string) $this->configFactory
      ->get('htl_typegrid.settings')
      ->get('image_style');

    return $style ?: 'large';
  }

  /**
   * Returns URI + ALT + cache dependencies for the fallback media image.
   *
   * @return array{uri:string,alt:string,cache_deps:array}|null
   */
  private function getFallbackImageData(): ?array {
    $config = $this->configFactory->get('htl_typegrid.settings');
    $uuid = (string) $config->get('fallback_media_uuid');

    if (!$uuid) {
      return null;
    }

    /** @var MediaInterface|null $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
    if (!$media instanceof MediaInterface) {
      return null;
    }

    $imageField = $media->get('field_media_image') ?? null;
    $file = $imageField?->entity;
    if (!$file instanceof FileInterface) {
      return null;
    }

    $deps = [];
    if ($media) {
      $deps[] = $media;
    }
    if ($file) {
      $deps[] = $file;
    }

    return [
      'uri' => $file->getFileUri(),
      'alt' => (string) ($imageField->alt ?? $media->label()),
      'cache_deps' => $deps,
    ];
  }

  /**
   * Checks if a string contains HTML tags.
   */
  private function containsHtml(string $text): bool {
    return $text !== strip_tags($text);
  }

  /**
   * Truncates HTML after a maximum number of words.
   * Attempts to properly close open tags.
   */
  private function htmlTrimWords(string $html, int $maxWords): string {
    if (trim($html) === '') {
      return '';
    }

    $doc = new \DOMDocument('1.0', 'UTF-8');
    $wrapped = '<div>' . $html . '</div>';

    // Suppress errors if HTML is not perfect
    libxml_use_internal_errors(true);
    $doc->loadHTML(
      '<?xml encoding="UTF-8">' . $wrapped,
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();

    $body = $doc->getElementsByTagName('div')->item(0);
    if (!$body) {
      return '';
    }

    $wordCount = 0;
    $stop = false;

    $walker = function (\DOMNode $node) use (&$walker, &$wordCount, $maxWords, &$stop): void {
      if ($stop) {
        // Remove remaining children
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

        if ($child->nodeType === XML_TEXT_NODE && $child instanceof \DOMText) {
          $words = preg_split('/(\s+)/u', $child->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
          $buffer = '';
          foreach ($words as $part) {
            if (trim($part) === '') {
              $buffer .= $part;
              continue;
            }
            $wordCount++;
            if ($wordCount > $maxWords) {
              $stop = true;
              $buffer .= '…';
              $child->nodeValue = $buffer;
              // Remove remaining siblings
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
          // After limit: remove all further siblings
          while ($child->nextSibling) {
            $child->parentNode->removeChild($child->nextSibling);
          }
          return;
        }
      }
    };

    $walker($body);

    $innerHTML = '';
    foreach ($body->childNodes as $child) {
      $innerHTML .= $doc->saveHTML($child);
    }

    return $innerHTML;
  }

  /**
   * Truncates plain text to a maximum length.
   */
  private function truncate(string $text, int $max): string {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    return mb_strlen($text) <= $max
      ? $text
      : rtrim(mb_substr($text, 0, $max - 1)) . '…';
  }
}
