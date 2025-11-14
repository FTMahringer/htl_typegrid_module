<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\htl_typegrid\Model\GridConfig;
use Drupal\node\NodeInterface;

final readonly class GridQueryService
{

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  )
  {
  }

  /**
   * Holt die Nodes für ein Bundle entsprechend der Filter.
   *
   * @return NodeInterface[]
   */
  public function loadNodesForBundle(GridConfig $config, string $bundle, CacheableMetadata $cache): array
  {
    $filters = $config->filters;

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->accessCheck(TRUE);

    // Sortierung
    switch ($filters->sortMode) {
      case 'oldest':
        $query->sort('created', 'ASC');
        break;

      case 'alpha':
        $query->sort($filters->alphaField, $filters->normalizedDirection());
        break;

      case 'random':
        $query->sort('RAND()');
        break;

      case 'newest':
      default:
        $query->sort('created', 'DESC');
        break;
    }

    $query->range(0, $config->limitPerType());
    $ids = $query->execute();

    if (!$ids) {
      return [];
    }

    /** @var NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);

    foreach ($nodes as $node) {
      $cache->addCacheableDependency($node);
    }

    return $nodes;
  }
}
