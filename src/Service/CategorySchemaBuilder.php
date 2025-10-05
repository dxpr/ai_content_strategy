<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\ai_content_strategy\Entity\RecommendationCategory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for building JSON schemas from recommendation categories.
 */
class CategorySchemaBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a CategorySchemaBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cache = $cache;
  }

  /**
   * Builds the composite JSON schema from all enabled categories.
   *
   * @return array
   *   The complete JSON schema.
   */
  public function buildSchema(): array {
    // Try to get from cache first.
    $cid = 'ai_content_strategy:composite_schema';
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $schema = [
      '$schema' => 'http://json-schema.org/draft-07/schema#',
      'type' => 'object',
      'required' => [],
      'properties' => [],
    ];

    $categories = $this->getEnabledCategories();

    foreach ($categories as $category) {
      $category_id = $category->id();
      $category_schema = $category->getSchemaDefinition();

      if (!empty($category_schema)) {
        $schema['required'][] = $category_id;
        $schema['properties'][$category_id] = $category_schema;
      }
    }

    // Cache the schema.
    $this->cache->set($cid, $schema, CacheBackendInterface::CACHE_PERMANENT, ['recommendation_categories']);

    return $schema;
  }

  /**
   * Gets the schema for a specific category.
   *
   * @param string $category_id
   *   The category ID.
   *
   * @return array|null
   *   The category schema or NULL if not found.
   */
  public function getCategorySchema(string $category_id): ?array {
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $category = $storage->load($category_id);

    if ($category instanceof RecommendationCategory && $category->status()) {
      return $category->getSchemaDefinition();
    }

    return NULL;
  }

  /**
   * Gets all enabled recommendation categories.
   *
   * @return \Drupal\ai_content_strategy\Entity\RecommendationCategory[]
   *   Array of enabled categories, sorted by weight.
   */
  public function getEnabledCategories(): array {
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $categories = $storage->loadByProperties(['status' => TRUE]);

    // Filter to ensure we only have RecommendationCategory entities.
    $categories = array_filter($categories, function ($entity) {
      return $entity instanceof RecommendationCategory;
    });

    // Sort by weight. After array_filter, all items are RecommendationCategory.
    uasort($categories, function (RecommendationCategory $a, RecommendationCategory $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    return $categories;
  }

  /**
   * Invalidates the schema cache.
   */
  public function invalidateCache(): void {
    $this->cache->delete('ai_content_strategy:composite_schema');
  }

}
