<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Helper;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Helper service for managing cache metadata in grid blocks.
 */
final class CacheHelper {

  /**
   * Adds cache metadata for a list of nodes to a render array.
   *
   * @param array &$build
   *   Render array to apply cache metadata to (modified by reference).
   * @param string $bundle
   *   The node bundle/content type.
   * @param int[] $nids
   *   Array of node IDs that are displayed.
   */
  public function addNodeListCache(array &$build, string $bundle, array $nids = []): void {
    $cache = new CacheableMetadata();

    // Tag individual nodes so cache invalidates when they change.
    foreach ($nids as $nid) {
      $cache->addCacheTags(["node:$nid"]);
    }

    // Add general node list cache tag.
    $cache->addCacheTags(['node_list']);

    // Add bundle-specific cache tag if bundle is provided.
    if ($bundle !== '') {
      $cache->addCacheTags(["node_list:$bundle"]);
    }

    // Add cache contexts for URL and language variations.
    $cache->addCacheContexts(['url.path', 'languages']);

    // Apply all cache metadata to the render array.
    $cache->applyTo($build);
  }

  /**
   * Creates basic cache metadata for a list of nodes.
   *
   * @param int[] $nids
   *   Array of node IDs.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Cache metadata object.
   */
  public function createNodeCache(array $nids): CacheableMetadata {
    $cache = new CacheableMetadata();

    foreach ($nids as $nid) {
      $cache->addCacheTags(["node:$nid"]);
    }

    $cache->addCacheTags(['node_list']);
    $cache->addCacheContexts(['url.path', 'languages']);
    return $cache;
  }
}
