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
 *     "field_mapping",
 *     "prompt_template",
 *     "system_prompt",
 *     "add_more_prompt",
 *     "schema_definition"
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
   * Field mapping configuration.
   *
   * @var array
   */
  protected $field_mapping = [];

  /**
   * Main prompt template.
   *
   * @var string
   */
  protected $prompt_template = '';

  /**
   * System prompt for AI instructions.
   *
   * @var string
   */
  protected $system_prompt = '';

  /**
   * Template for "add more" functionality.
   *
   * @var string
   */
  protected $add_more_prompt = '';

  /**
   * JSON schema definition for this category.
   *
   * @var array
   */
  protected $schema_definition = [];

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
   * Gets the field mapping.
   *
   * @return array
   *   The field mapping array.
   */
  public function getFieldMapping() {
    return $this->field_mapping;
  }

  /**
   * Gets the prompt template.
   *
   * @return string
   *   The prompt template.
   */
  public function getPromptTemplate() {
    return $this->prompt_template;
  }

  /**
   * Gets the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt() {
    return $this->system_prompt;
  }

  /**
   * Gets the add more prompt.
   *
   * @return string
   *   The add more prompt template.
   */
  public function getAddMorePrompt() {
    return $this->add_more_prompt;
  }

  /**
   * Gets the schema definition.
   *
   * @return array
   *   The JSON schema definition.
   */
  public function getSchemaDefinition() {
    return $this->schema_definition;
  }

  /**
   * Gets a specific field name from the mapping.
   *
   * @param string $role
   *   The field role (primary_field, secondary_field, etc.).
   *
   * @return string|null
   *   The field name or NULL if not set.
   */
  public function getFieldName(string $role) {
    return $this->field_mapping[$role] ?? NULL;
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
