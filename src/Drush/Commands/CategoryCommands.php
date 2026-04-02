<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Entity\RecommendationCategory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for managing recommendation categories.
 */
class CategoryCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Lists all recommendation categories.
   */
  #[CLI\Command(name: 'acs:category:list', aliases: ['acs-catl', 'acs:cat:list'])]
  #[CLI\Help(description: '[YAML] List all categories with status, weight, and instruction preview.')]
  #[CLI\Usage(name: 'drush acs:category:list', description: 'List all categories')]
  public function listCategories(): string {
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $categories = $storage->loadMultiple();

    if (empty($categories)) {
      return $this->error('No categories found.');
    }

    // Sort by weight.
    uasort($categories, fn(RecommendationCategory $a, RecommendationCategory $b) => $a->getWeight() <=> $b->getWeight());

    $items = [];
    foreach ($categories as $category) {
      $instructions = $category->getInstructions();
      $items[] = [
        'id' => $category->id(),
        'label' => $category->label(),
        'status' => $category->status() ? 'enabled' : 'disabled',
        'weight' => $category->getWeight(),
        'instructions' => strlen($instructions) > 80 ? substr($instructions, 0, 80) . '...' : $instructions,
      ];
    }

    return $this->successList($items);
  }

  /**
   * Gets full details for a single category.
   */
  #[CLI\Command(name: 'acs:category:get', aliases: ['acs-catg', 'acs:cat:get'])]
  #[CLI\Argument(name: 'id', description: 'Category machine name')]
  #[CLI\Help(description: '[YAML] Full detail for a single category.')]
  #[CLI\Usage(name: 'drush acs:category:get content_gaps', description: 'Get category details')]
  public function getCategory(string $id): string {
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $category = $storage->load($id);

    if (!$category instanceof RecommendationCategory) {
      return $this->notFound('Category', $id, 'acs:category:list');
    }

    return $this->success('Category found.', [
      'category' => [
        'id' => $category->id(),
        'label' => $category->label(),
        'description' => $category->getDescription(),
        'status' => $category->status() ? 'enabled' : 'disabled',
        'weight' => $category->getWeight(),
        'instructions' => $category->getInstructions(),
      ],
    ]);
  }

  /**
   * Creates a new recommendation category.
   */
  #[CLI\Command(name: 'acs:category:create', aliases: ['acs-catc', 'acs:cat:create'])]
  #[CLI\Argument(name: 'id', description: 'Machine name for the category')]
  #[CLI\Argument(name: 'label', description: 'Human-readable label')]
  #[CLI\Option(name: 'instructions', description: 'AI instructions for this category')]
  #[CLI\Option(name: 'description', description: 'Admin description')]
  #[CLI\Option(name: 'weight', description: 'Display weight (default 0)')]
  #[CLI\Option(name: 'status', description: 'Enabled (1) or disabled (0), default 1')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without creating')]
  #[CLI\Help(description: '[YAML] Create a new recommendation category.')]
  #[CLI\Usage(name: 'drush acs:category:create seasonal "Seasonal Content" --instructions="Identify seasonal opportunities"', description: 'Create a category')]
  public function createCategory(
    string $id,
    string $label,
    array $options = [
      'instructions' => '',
      'description' => '',
      'weight' => 0,
      'status' => 1,
      'dry-run' => FALSE,
    ],
  ): string {
    // Validate ID format.
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      return $this->error('Invalid ID format.', ['ID must contain only lowercase letters, numbers, and underscores, and start with a letter.']);
    }

    // Check if already exists.
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    if ($storage->load($id)) {
      return $this->error(sprintf('Category "%s" already exists.', $id), ['Use acs:category:update to modify it.']);
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: category would be created.', [
        'category' => [
          'id' => $id,
          'label' => $label,
          'status' => (int) $options['status'] ? 'enabled' : 'disabled',
        ],
      ]);
    }

    try {
      $category = $storage->create([
        'id' => $id,
        'label' => $label,
        'instructions' => $options['instructions'],
        'description' => $options['description'],
        'weight' => (int) $options['weight'],
        'status' => (bool) (int) $options['status'],
      ]);
      $category->save();

      return $this->success('Category created.', [
        'category' => [
          'id' => $category->id(),
          'label' => $category->label(),
          'status' => $category->status() ? 'enabled' : 'disabled',
        ],
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Failed to create category.', [$e->getMessage()]);
    }
  }

  /**
   * Updates an existing category.
   */
  #[CLI\Command(name: 'acs:category:update', aliases: ['acs-catu', 'acs:cat:update'])]
  #[CLI\Argument(name: 'id', description: 'Category machine name')]
  #[CLI\Option(name: 'label', description: 'New label')]
  #[CLI\Option(name: 'instructions', description: 'New AI instructions')]
  #[CLI\Option(name: 'description', description: 'New admin description')]
  #[CLI\Option(name: 'weight', description: 'New weight')]
  #[CLI\Option(name: 'status', description: 'Enabled (1) or disabled (0)')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without updating')]
  #[CLI\Help(description: '[YAML] Update an existing category.')]
  #[CLI\Usage(name: 'drush acs:category:update content_gaps --status=0', description: 'Disable a category')]
  #[CLI\Usage(name: 'drush acs:category:update trust_signals --weight=5', description: 'Change category weight')]
  public function updateCategory(
    string $id,
    array $options = [
      'label' => '',
      'instructions' => '',
      'description' => '',
      'weight' => NULL,
      'status' => NULL,
      'dry-run' => FALSE,
    ],
  ): string {
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $category = $storage->load($id);

    if (!$category instanceof RecommendationCategory) {
      return $this->notFound('Category', $id, 'acs:category:list');
    }

    $changes = [];
    if (!empty($options['label'])) {
      $changes['label'] = $options['label'];
    }
    if (!empty($options['instructions'])) {
      $changes['instructions'] = $options['instructions'];
    }
    if (!empty($options['description'])) {
      $changes['description'] = $options['description'];
    }
    if ($options['weight'] !== NULL) {
      $changes['weight'] = (int) $options['weight'];
    }
    if ($options['status'] !== NULL) {
      $changes['status'] = (bool) (int) $options['status'];
    }

    if (empty($changes)) {
      return $this->noChanges();
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: category would be updated.', [
        'changes' => $changes,
      ]);
    }

    try {
      foreach ($changes as $field => $value) {
        $category->set($field, $value);
      }
      $category->save();

      return $this->success('Category updated.', [
        'category' => [
          'id' => $category->id(),
          'label' => $category->label(),
          'status' => $category->status() ? 'enabled' : 'disabled',
          'weight' => $category->getWeight(),
        ],
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Failed to update category.', [$e->getMessage()]);
    }
  }

  /**
   * Deletes a category.
   */
  #[CLI\Command(name: 'acs:category:delete', aliases: ['acs-catd', 'acs:cat:delete'])]
  #[CLI\Argument(name: 'id', description: 'Category machine name')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without deleting')]
  #[CLI\Help(description: '[YAML] Delete a category.')]
  #[CLI\Usage(name: 'drush acs:category:delete seasonal', description: 'Delete a category')]
  public function deleteCategory(string $id, array $options = ['dry-run' => FALSE]): string {
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $category = $storage->load($id);

    if (!$category instanceof RecommendationCategory) {
      return $this->notFound('Category', $id, 'acs:category:list');
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: category would be deleted.', [
        'category' => ['id' => $id, 'label' => $category->label()],
      ]);
    }

    try {
      $label = $category->label();
      $category->delete();
      return $this->success('Category deleted.', [
        'deleted' => ['id' => $id, 'label' => $label],
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Failed to delete category.', [$e->getMessage()]);
    }
  }

}
