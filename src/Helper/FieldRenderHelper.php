<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Helper;

use Drupal;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Kapselt die Feld-zu-Array Logik für Cards.
 * Erkennt Media-Image Felder und liefert ein Render-Array mit class "image" (Card-Cover).
 */
final class FieldRenderHelper
{
  private const CARD_STYLE = 'htl_grid_card';

  private static function imageStyleExists(string $id): bool {
    return (bool) \Drupal\image\Entity\ImageStyle::load($id);
  }

  private static function getCardStyleName(): string {
    $cfg = Drupal::config('htl_typegrid.settings');
    $style = (string) ($cfg->get('image_style') ?? self::CARD_STYLE);
    return self::imageStyleExists($style) ? $style : 'large';
  }

  /**
   * Rendert ausgewählte Felder eines Nodes zu Card-Feld-Daten.
   *
   * @param \Drupal\node\NodeInterface $node
   * @param string[] $chosen Feld-Maschinennamen (Reihenfolge beibehalten)
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   * @return array<int,array{label:?string,value:mixed,class?:string}>
   */
  public static function buildCardFields($node, array $chosen, CacheableMetadata $cache): array
  {
    $out = [];

    foreach ($chosen as $fieldName) {
      if (!$fieldName) {
        continue;
      }
      if (!$node->hasField($fieldName)) {
        continue;
      }

      /** @var FieldItemListInterface $field */
      $field = $node->get($fieldName);
      $def   = $field->getFieldDefinition();
      $type  = $def->getType();
      $label = (string) ($def->getLabel() ?? $fieldName);
      $style = self::getCardStyleName();

      // (A) Media reference (Medienbibliothek) -> direkt image_style + Fallback
      if ($type === 'entity_reference' && ($def->getSetting('target_type') === 'media')) {
        /** @var \Drupal\media\MediaInterface|null $media */
        $media = $field->entity;
        $file = null;

        if ($media instanceof MediaInterface) {
          // Standard-Feldname im Core-Image-Media-Bundle ist "field_media_image".
          $file = $media->get('field_media_image')->entity ?? null;
        }

        if ($file instanceof FileInterface) {
          $out[] = [
            'label' => null,
            'class' => 'image',
            'value' => [
              '#theme'      => 'image_style',
              '#style_name' => $style,
              '#uri'        => $file->getFileUri(),
              '#alt'        => (string) ($media?->label() ?? $label),
              '#title'      => '',
            ],
          ];
          $cache->addCacheableDependency($file);
          if ($media) {
            $cache->addCacheableDependency($media);
          }
        } else {
          // Dummy-Fallback
          $fallback = self::getFallbackImageData();
          if ($fallback) {
            $out[] = [
              'label' => null,
              'class' => 'image',
              'value' => [
                '#theme'      => 'image_style',
                '#style_name' => $style,
                '#uri'        => $fallback['uri'],
                '#alt'        => $fallback['alt'],
                '#title'      => '',
              ],
            ];
            foreach ($fallback['cache_deps'] as $dep) {
              $cache->addCacheableDependency($dep);
            }
          }
        }
        continue;
      }

      // (B) Image field -> direkt image_style + Fallback
      if ($type === 'image') {
        $item = $field->first();
        /** @var FileInterface|null $file */
        $file = $item?->entity;

        if ($file) {
          $out[] = [
            'label' => null,
            'class' => 'image',
            'value' => [
              '#theme'      => 'image_style',
              '#style_name' => $style,
              '#uri'        => $file->getFileUri(),
              '#alt'        => (string) ($item->alt ?? $label),
              '#title'      => (string) ($item->title ?? ''),
            ],
          ];
          $cache->addCacheableDependency($file);
        } else {
          // Fallback: Dummy aus Modul-Config verwenden
          $fallback = self::getFallbackImageData();
          if ($fallback) {
            $out[] = [
              'label' => null,
              'class' => 'image',
              'value' => [
                '#theme'      => 'image_style',
                '#style_name' => $style,
                '#uri'        => $fallback['uri'],
                '#alt'        => $fallback['alt'],
                '#title'      => '',
              ],
            ];
            foreach ($fallback['cache_deps'] as $dep) {
              $cache->addCacheableDependency($dep);
            }
          }
        }
        continue;
      }

      // (C) Entity reference (non-media)
      if ($type === 'entity_reference') {
        $labels = [];
        foreach ($field->referencedEntities() as $ref) {
          $labels[] = $ref->label();
          $cache->addCacheableDependency($ref);
        }
        if ($labels) {
          $out[] = [
            'label' => $label,
            'value' => ['#markup' => implode(', ', $labels)],
            'class' => 'htl-field--reference',
          ];
        }
        continue;
      }

      // (D) text_with_summary -> Summary als HTML, sonst HTML-sicher 35 Wörter
      if ($type === 'text_with_summary') {
        $item = $field->first();
        if ($item) {
          $format  = (string) ($item->format ?? 'basic_html');
          $summary = (string) ($item->summary ?? '');
          if (trim($summary) !== '') {
            $out[] = [
              'label' => $label,
              'value' => [
                '#type'   => 'processed_text',
                '#text'   => $summary,
                '#format' => $format,
              ],
              'class' => 'htl-field--text',
            ];
          } else {
            $allowed     = ['a','strong','em','b','i','u','br','p','ul','ol','li','span'];
            $trimmedHtml = static::htmlTrimWords(Xss::filter((string) ($item->value ?? ''), $allowed), 35);
            $out[] = [
              'label' => $label,
              'value' => [
                '#type'   => 'processed_text',
                '#text'   => $trimmedHtml,
                '#format' => $format,
              ],
              'class' => 'htl-field--text',
            ];
          }
        }
        continue;
      }

      // (E) Long/Formatted text -> processed_text wenn Format vorhanden
      if (in_array($type, ['text_long', 'text'], true)) {
        $item  = $field->first();
        $value = (string) ($item->value ?? '');
        if (trim($value) !== '') {
          $format = (string) ($item->format ?? 'basic_html');
          $out[] = [
            'label' => $label,
            'value' => [
              '#type'   => 'processed_text',
              '#text'   => $value,
              '#format' => $format,
            ],
            'class' => 'htl-field--text',
          ];
        }
        continue;
      }

      // (F) Plain strings
      if (in_array($type, ['string', 'string_long'], true)) {
        $raw   = $field->value ?? $field->getString();
        $clean = trim((string) $raw);
        if ($clean !== '') {
          $out[] = [
            'label' => $label,
            'value' => ['#markup' => htmlspecialchars(static::truncate($clean, 200), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
            'class' => 'htl-field--text',
          ];
        }
        continue;
      }

      // (G) Fallback
      $raw   = $field->value ?? $field->getString();
      $clean = trim((string) $raw);
      if ($clean !== '') {
        $out[] = [
          'label' => $label,
          'value' => ['#markup' => htmlspecialchars(static::truncate($clean, 200), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
          'class' => 'htl-field--text',
        ];
      }
    }

    return $out;
  }

  /**
   * Liefert URI + ALT + Cache-Dependencies für das Dummy-Medienbild.
   * Erwartet, dass die UUID in htl_typegrid.settings:fallback_media_uuid steht.
   *
   * @return array{uri:string,alt:string,cache_deps:array<CacheableDependencyInterface>}|null
   */
  private static function getFallbackImageData(): ?array {
    $config = Drupal::config('htl_typegrid.settings');
    $uuid   = $config->get('fallback_media_uuid');
    if (!$uuid) {
      return null;
    }

    /** @var EntityRepositoryInterface $repo */
    $repo  = Drupal::service('entity.repository');
    $media = $repo->loadEntityByUuid('media', $uuid);

    if (!$media instanceof MediaInterface) {
      return null;
    }

    // Standard-Feldname im Core-Bundle "image" ist "field_media_image".
    $imageFile = $media->get('field_media_image')->entity ?? null;
    if (!$imageFile instanceof FileInterface) {
      return null;
    }

    return [
      'uri'        => $imageFile->getFileUri(),
      'alt'        => 'Dummy',
      'cache_deps' => [$config, $media, $imageFile],
    ];
  }

  /**
   * HTML-sicher auf $limit Wörter kürzen und Tags balancieren.
   */
  private static function htmlTrimWords(string $html, int $limit): string
  {
    if ($limit <= 0) return '';

    $wordCount = 0;
    $result = '';
    $open = [];

    // Match Tags oder Text
    preg_match_all('/(<[^>]+>|[^<]+)/u', $html, $parts);
    foreach ($parts[0] as $part) {
      if ($part !== '' && $part[0] === '<') {
        // Tag
        $result .= $part;

        if (preg_match('/^<\s*\/\s*([a-z0-9]+)\s*>/i', $part, $m)) {
          // closing tag
          while (!empty($open)) {
            $t = array_pop($open);
            if (strcasecmp($t, $m[1]) === 0) break;
          }
        } elseif (preg_match('/^<\s*([a-z0-9]+)\b[^>]*\/\s*>$/i', $part)) {
          // self-closing -> ignore
        } elseif (preg_match('/^<\s*([a-z0-9]+)\b[^>]*>$/i', $part, $m)) {
          // opening tag
          $tag = strtolower($m[1]);
          // Void-Tags nicht stacken
          if (!in_array($tag, ['br','hr','img','meta','link','input','source','area','col','embed','param','track','wbr'], true)) {
            $open[] = $tag;
          }
        }
        continue;
      }

      // Text: auf Wörter splitten und zählen
      $segments = preg_split('/(\s+)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
      foreach ($segments as $seg) {
        if (preg_match('/^\s+$/u', $seg)) {
          // Whitespace zählt nicht, aber behalten
          $result .= $seg;
          continue;
        }
        // Ein Wort
        if ($wordCount >= $limit) {
          // Limit erreicht: ellipsis und abbrechen
          $result = rtrim($result) . '…';
          // schließe offene Tags
          while (!empty($open)) {
            $result .= '</' . array_pop($open) . '>';
          }
          return $result;
        }
        $wordCount++;
        $result .= $seg;
      }
    }

    // Ende erreicht, ggf. Tags schließen
    while (!empty($open)) {
      $result .= '</' . array_pop($open) . '>';
    }
    return $result;
  }

  private static function truncate(string $text, int $max): string
  {
    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    return (mb_strlen($text) <= $max) ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
  }
}
