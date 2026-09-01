<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Manages built-in (locked) recommendation categories shipped with the module.
 */
class BuiltInCategoryManager {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Returns config data arrays for locked categories missing from active storage.
   *
   * @return array
   *   Array of config data arrays, keyed by config name.
   */
  public function getMissing(): array {
    $config_path = $this->moduleExtensionList->getPath('ai_content_strategy') . '/config/install';
    $source = new FileStorage($config_path);
    $storage = $this->entityTypeManager->getStorage('recommendation_category');

    $missing = [];
    foreach ($source->listAll('ai_content_strategy.recommendation_category') as $name) {
      $data = $source->read($name);
      if (!empty($data['locked']) && !$storage->load($data['id'])) {
        $missing[$name] = $data;
      }
    }
    return $missing;
  }

  /**
   * Restores missing built-in categories via the entity API.
   *
   * @return int
   *   The number of categories restored.
   */
  public function restoreMissing(): int {
    $missing = $this->getMissing();
    if (empty($missing)) {
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $restored = 0;

    foreach ($missing as $data) {
      $category = $storage->create($data);
      $category->save();
      $restored++;
    }

    return $restored;
  }

}
