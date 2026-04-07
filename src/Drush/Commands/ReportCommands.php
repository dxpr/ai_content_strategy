<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Entity\RecommendationCategory;
use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;

/**
 * Drush commands for reading AI Content Strategy reports.
 */
class ReportCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly RecommendationStorageService $storage,
    protected readonly ContentAnalyzer $contentAnalyzer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Gets the full AI content strategy report.
   */
  #[CLI\Command(name: 'acs:report', aliases: ['acs-r'])]
  #[CLI\Option(name: 'category', description: 'Filter by category ID')]
  #[CLI\Option(name: 'priority', description: 'Filter by priority (high, medium, low)')]
  #[CLI\Help(description: '[YAML] Full recommendation report, optionally filtered by category or priority.')]
  #[CLI\Usage(name: 'drush acs:report', description: 'Show all recommendations')]
  #[CLI\Usage(name: 'drush acs:report --category=content_gaps', description: 'Show only content gaps')]
  #[CLI\Usage(name: 'drush acs:report --priority=high', description: 'Show only high priority recommendations')]
  public function report(array $options = ['category' => '', 'priority' => '']): string {
    $this->switchToAdmin();

    $stored = $this->storage->getStoredData();
    if (!$stored || empty($stored['data'])) {
      return $this->error('No report generated yet.', ['Use acs:generate to create recommendations.']);
    }

    $recommendations = $stored['data'];

    // Load enabled categories sorted by weight.
    $category_storage = $this->entityTypeManager->getStorage('recommendation_category');
    /** @var \Drupal\ai_content_strategy\Entity\RecommendationCategory[] $categories */
    $categories = $category_storage->loadByProperties(['status' => TRUE]);
    uasort($categories, static fn(RecommendationCategory $a, RecommendationCategory $b): int => $a->getWeight() <=> $b->getWeight());

    $result = [];
    foreach ($categories as $category) {
      $cat_id = $category->id();

      // Filter by category if specified.
      if (!empty($options['category']) && $cat_id !== $options['category']) {
        continue;
      }

      $cards = $recommendations[$cat_id] ?? [];

      // Filter by priority if specified.
      if (!empty($options['priority'])) {
        $cards = array_values(array_filter($cards, fn($card) => ($card['priority'] ?? '') === $options['priority']));
      }

      if (!empty($cards)) {
        $result[$cat_id] = [
          'label' => $category->label(),
          'cards' => $this->formatCards($cards),
        ];
      }
    }

    if (empty($result)) {
      return $this->error('No recommendations match the specified filters.');
    }

    $extra = [
      'generated_at' => $stored['timestamp'] ? date('c', $stored['timestamp']) : NULL,
      'pages_analyzed' => $stored['pages_analyzed'] ?? NULL,
      'categories' => $result,
    ];

    if (($stored['pages_analyzed'] ?? 0) < 5) {
      $extra['warning'] = 'Analysis based on fewer than 5 pages — results may be limited.';
    }

    return $this->success('Report retrieved.', $extra);
  }

  /**
   * Gets a single recommendation card.
   */
  #[CLI\Command(name: 'acs:report:card', aliases: ['acs-rc', 'acs:r:card'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Help(description: '[YAML] View a single recommendation card with all its ideas.')]
  #[CLI\Usage(name: 'drush acs:report:card content_gaps UUID', description: 'View a specific card')]
  public function reportCard(string $section, string $uuid): string {
    $this->switchToAdmin();

    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    return $this->success('Card found.', ['card' => $this->formatCard($card)]);
  }

  /**
   * Gets report status overview.
   */
  #[CLI\Command(name: 'acs:report:status', aliases: ['acs-rs', 'acs:r:status'])]
  #[CLI\Help(description: '[YAML] Last-run timestamp, pages analyzed, active category count.')]
  #[CLI\Usage(name: 'drush acs:report:status', description: 'Show report status')]
  public function reportStatus(): string {
    $this->switchToAdmin();

    $stored = $this->storage->getStoredData();

    $category_storage = $this->entityTypeManager->getStorage('recommendation_category');
    $active_count = $category_storage->getQuery()
      ->condition('status', TRUE)
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    $total_cards = 0;
    if ($stored && !empty($stored['data'])) {
      foreach ($stored['data'] as $cards) {
        if (is_array($cards)) {
          $total_cards += count($cards);
        }
      }
    }

    $timestamp = $stored['timestamp'] ?? NULL;

    return $this->success('Status retrieved.', [
      'generated_at' => $timestamp ? date('c', (int) $timestamp) : NULL,
      'pages_analyzed' => $stored['pages_analyzed'] ?? NULL,
      'active_categories' => (int) $active_count,
      'total_cards' => $total_cards,
      'has_report' => !empty($stored['data']),
    ]);
  }

  /**
   * Gets sitemap URLs with content statistics.
   */
  #[CLI\Command(name: 'acs:sitemap', aliases: ['acs-s'])]
  #[CLI\Help(description: '[YAML] Site structure: sitemap URLs with content type statistics.')]
  #[CLI\Usage(name: 'drush acs:sitemap -l https://example.com', description: 'Get sitemap data (requires -l for base URL)')]
  public function sitemap(): string {
    $this->switchToAdmin();

    $sitemap_data = $this->contentAnalyzer->getSitemapUrls();

    if (!empty($sitemap_data['error'])) {
      return $this->error('Failed to retrieve sitemap.', [(string) $sitemap_data['error']]);
    }

    $urls = $sitemap_data['urls'] ?? [];

    // Get content type statistics.
    $stats = [];
    try {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $type_storage = $this->entityTypeManager->getStorage('node_type');
      $types = $type_storage->loadMultiple();

      foreach ($types as $type) {
        $count = $node_storage->getQuery()
          ->condition('type', $type->id())
          ->condition('status', 1)
          ->accessCheck(FALSE)
          ->count()
          ->execute();
        if ($count > 0) {
          $stats[$type->id()] = [
            'label' => $type->label(),
            'count' => (int) $count,
          ];
        }
      }
    }
    catch (\Exception $e) {
      // Stats are best-effort.
    }

    $extra = [
      'total_urls' => count($urls),
      'total_nodes' => array_sum(array_column($stats, 'count')),
      'content_types' => $stats,
      'urls' => $urls,
    ];

    if (count($urls) < 5) {
      $extra['warning'] = 'Sitemap has fewer than 5 URLs — analysis may be limited.';
    }

    return $this->success('Sitemap retrieved.', $extra);
  }

  /**
   * Formats a list of cards for output.
   */
  protected function formatCards(array $cards): array {
    return array_map([$this, 'formatCard'], $cards);
  }

  /**
   * Formats a single card for output.
   */
  protected function formatCard(array $card): array {
    $formatted = [
      'uuid' => $card['uuid'] ?? NULL,
      'title' => $card['title'] ?? '',
      'description' => $card['description'] ?? '',
      'priority' => $card['priority'] ?? 'medium',
    ];

    if (!empty($card['content_ideas'])) {
      $formatted['content_ideas'] = array_map(function ($idea) {
        if (is_string($idea)) {
          return ['text' => $idea, 'implemented' => FALSE, 'link' => ''];
        }
        return [
          'uuid' => $idea['uuid'] ?? NULL,
          'text' => $idea['text'] ?? '',
          'implemented' => $idea['implemented'] ?? FALSE,
          'link' => $idea['link'] ?? '',
        ];
      }, $card['content_ideas']);
    }

    return $formatted;
  }

}
