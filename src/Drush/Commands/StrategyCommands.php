<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for AI content strategy reports.
 */
class StrategyCommands extends DrushCommands {

  /**
   * Constructs StrategyCommands.
   */
  public function __construct(
    protected readonly RecommendationStorageService $recommendationStorage,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ai_content_strategy.recommendation_storage'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Gets the full content strategy report as JSON.
   */
  #[CLI\Command(name: 'acs:report', aliases: ['acs-r'])]
  #[CLI\Help(description: 'Gets the full AI content strategy report as JSON.')]
  #[CLI\Usage(name: 'drush acs:report', description: 'Get full report as JSON')]
  public function getReport(): void {
    $storedData = $this->recommendationStorage->getStoredData();

    if ($storedData === NULL || empty($storedData['data'])) {
      $this->output()->writeln(json_encode([
        'success' => FALSE,
        'message' => 'No report generated yet. Visit /admin/config/ai/content-strategy.',
        'categories' => [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return;
    }

    $recommendations = $storedData['data'];
    $timestamp = $storedData['timestamp'] ?? NULL;

    // Load enabled categories.
    $categoryEntities = $this->entityTypeManager
      ->getStorage('recommendation_category')
      ->loadByProperties(['status' => TRUE]);
    uasort($categoryEntities, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    // Build output.
    $categories = [];
    foreach ($categoryEntities as $categoryId => $categoryEntity) {
      $categories[$categoryId] = [
        'label' => $categoryEntity->label(),
        'cards' => $recommendations[$categoryId] ?? [],
      ];
    }

    $this->output()->writeln(json_encode([
      'success' => TRUE,
      'generated_at' => $timestamp ? date('c', $timestamp) : NULL,
      'pages_analyzed' => $storedData['pages_analyzed'] ?? NULL,
      'categories' => $categories,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

}
