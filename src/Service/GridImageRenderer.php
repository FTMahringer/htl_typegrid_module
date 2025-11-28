<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\htl_typegrid\Model\GridFieldValue;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Config\ConfigFactoryInterface;

final class GridImageRenderer {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Renders an image field value with optional style settings.
   *
   * @param \Drupal\htl_typegrid\Model\GridFieldValue|null $image
   *   The image field value.
   * @param array $styleSettings
   *   Optional array with 'style', 'width', 'height' keys from node.
   *
   * @return string|null
   *   Rendered HTML or null if no image.
   */
  public function render(?GridFieldValue $image, array $styleSettings = []): ?string {
    if (!$image || !$image->isImage) {
      return null;
    }

    $uri = $image->meta['uri'] ?? null;
    if (!$uri) {
      return null;
    }

    $alt = htmlspecialchars($image->meta['alt'] ?? '', ENT_QUOTES);
    $style = $styleSettings['style'] ?? 'default';

    // Handle custom dimensions
    if ($style === 'custom') {
      return $this->renderCustomImage($uri, $alt, $styleSettings);
    }

    // Handle default style (use grid's default)
    if ($style === 'default' || empty($style)) {
      $style = $this->getDefaultImageStyle();
    }

    // Handle image style rendering
    return $this->renderStyledImage($uri, $alt, $style);
  }

  /**
   * Renders an image with a Drupal image style.
   *
   * @param string $uri
   *   The file URI.
   * @param string $alt
   *   The alt text.
   * @param string $styleName
   *   The image style name.
   *
   * @return string
   *   Rendered HTML.
   */
  private function renderStyledImage(string $uri, string $alt, string $styleName): string {
    $imageStyle = ImageStyle::load($styleName);

    if ($imageStyle) {
      $styledUrl = $imageStyle->buildUrl($uri);

      return <<<HTML
<picture>
  <img src="$styledUrl" alt="$alt" loading="lazy" class="htl-card__image">
</picture>
HTML;
    }

    // Fallback to original image if style doesn't exist
    $url = $this->fileUrlGenerator->generateAbsoluteString($uri);

    return <<<HTML
<picture>
  <img src="$url" alt="$alt" loading="lazy" class="htl-card__image">
</picture>
HTML;
  }

  /**
   * Renders an image with custom dimensions.
   *
   * @param string $uri
   *   The file URI.
   * @param string $alt
   *   The alt text.
   * @param array $settings
   *   Array with 'width' and 'height' keys.
   *
   * @return string
   *   Rendered HTML.
   */
  private function renderCustomImage(string $uri, string $alt, array $settings): string {
    $url = $this->fileUrlGenerator->generateAbsoluteString($uri);
    $width = $settings['width'] ?? null;
    $height = $settings['height'] ?? null;

    $styleAttr = '';
    $widthAttr = '';
    $heightAttr = '';

    if ($width && $height) {
      $widthAttr = " width=\"$width\"";
      $heightAttr = " height=\"$height\"";
      $styleAttr = " style=\"width: {$width}px; height: {$height}px; object-fit: cover;\"";
    } elseif ($width) {
      $widthAttr = " width=\"$width\"";
      $styleAttr = " style=\"width: {$width}px; height: auto;\"";
    } elseif ($height) {
      $heightAttr = " height=\"$height\"";
      $styleAttr = " style=\"height: {$height}px; width: auto;\"";
    }

    return <<<HTML
<picture>
  <img src="$url" alt="$alt" loading="lazy" class="htl-card__image"$widthAttr$heightAttr$styleAttr>
</picture>
HTML;
  }

  /**
   * Gets the default image style name from config.
   *
   * @return string
   *   The image style name.
   */
  private function getDefaultImageStyle(): string {
    $style = $this->configFactory
      ->get('htl_typegrid.settings')
      ->get('image_style');

    return $style ?: 'htl_grid_card';
  }
}
