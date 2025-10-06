<?php

namespace Drupal\ai_content_strategy\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Recommendation Category entity.
 *
 * @ConfigEntityType(
 *   id = "recommendation_category",
 *   label = @Translation("Recommendation Category"),
 *   label_collection = @Translation("Recommendation Categories"),
 *   label_singular = @Translation("recommendation category"),
 *   label_plural = @Translation("recommendation categories"),
 *   label_count = @PluralTranslation(
 *     singular = "@count recommendation category",
 *     plural = "@count recommendation categories",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\ai_content_strategy\Entity\RecommendationCategoryListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_content_strategy\Form\RecommendationCategoryForm",
 *       "edit" = "Drupal\ai_content_strategy\Form\RecommendationCategoryForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "recommendation_category",
 *   admin_permission = "administer ai content strategy",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/config/ai/content-strategy/categories",
 *     "add-form" = "/admin/config/ai/content-strategy/categories/add",
 *     "edit-form" = "/admin/config/ai/content-strategy/categories/{recommendation_category}/edit",
 *     "delete-form" = "/admin/config/ai/content-strategy/categories/{recommendation_category}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "weight",
 *     "status",
 *     "instructions"
 *   }
 * )
 */
class RecommendationCategory extends ConfigEntityBase {

  /**
   * The category ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The category label.
   *
   * @var string
   */
  protected $label;

  /**
   * The category description.
   *
   * @var string
   */
  protected $description;

  /**
   * The category weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * Whether the category is enabled.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * Instructions for the AI on what to analyze.
   *
   * @var string
   */
  protected $instructions = '';

  /**
   * Gets the description.
   *
   * @return string
   *   The category description.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Gets the weight.
   *
   * @return int
   *   The category weight.
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * Gets the instructions.
   *
   * @return string
   *   The AI instructions for this category.
   */
  public function getInstructions() {
    return $this->instructions;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Invalidate schema builder cache.
    \Drupal::service('ai_content_strategy.category_schema_builder')->invalidateCache();

    // Invalidate category list cache tag.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['recommendation_category_list']);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Invalidate caches when categories are deleted.
    \Drupal::service('ai_content_strategy.category_schema_builder')->invalidateCache();
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['recommendation_category_list']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $tags[] = 'recommendation_category_list';
    return $tags;
  }

}
