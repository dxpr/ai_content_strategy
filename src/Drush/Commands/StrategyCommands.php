<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for AI content strategy reports.
 *
 * Provides AI-optimized access to content strategy recommendations.
 */
class StrategyCommands extends DrushCommands {

  /**
   * Constructs StrategyCommands.
   *
   * @param \Drupal\ai_content_strategy\Service\RecommendationStorageService $recommendationStorage
   *   The recommendation storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    protected readonly RecommendationStorageService $recommendationStorage,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
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
      $container->get('date.formatter'),
    );
  }

  /**
   * Gets the full content strategy report.
   */
  #[CLI\Command(name: 'acs:report', aliases: ['acs-r', 'acs:recommendations'])]
  #[CLI\Help(
    description: 'Gets the full AI content strategy report with all categories and recommendations.',
    synopsis: <<<'END'

## Purpose

Returns the complete AI-generated content strategy report as structured JSON.
Designed for AI assistant consumption to understand content recommendations.

## What This Command Returns

The full report includes:
- Generation metadata (timestamp, pages analyzed)
- All recommendation categories (dynamically configured)
- Cards within each category with content ideas
- Implementation status for each idea

## Output Structure

```json
{
  "success": true,
  "generated_at": "2024-01-30T12:00:00+00:00",
  "generated_ago": "2 hours ago",
  "pages_analyzed": 150,
  "categories": {
    "content_gaps": {
      "id": "content_gaps",
      "label": "Content Gaps",
      "description": "Identifies missing content opportunities...",
      "weight": 0,
      "card_count": 5,
      "cards": [
        {
          "uuid": "abc-123-def",
          "title": "Product Comparison Pages",
          "content_ideas": [
            {
              "uuid": "ghi-456-jkl",
              "text": "Create comparison: Product A vs Product B",
              "implemented": false,
              "link": ""
            },
            {
              "uuid": "mno-789-pqr",
              "text": "Add feature matrix table",
              "implemented": true,
              "link": "/node/123"
            }
          ]
        }
      ]
    },
    "topical_authority": {
      "id": "topical_authority",
      "label": "Topical Authority",
      "description": "Build expertise in key topic areas...",
      "weight": 1,
      "card_count": 3,
      "cards": [...]
    }
  },
  "summary": {
    "total_categories": 4,
    "total_cards": 15,
    "total_ideas": 47,
    "implemented_ideas": 12,
    "pending_ideas": 35
  }
}
```

## Category Structure

Categories are **dynamically configured** via the admin UI at:
`/admin/config/ai/content-strategy/categories`

Each category has:
- `id`: Machine name
- `label`: Human-readable name
- `description`: What this category analyzes
- `weight`: Sort order
- `cards`: Array of recommendation cards

## Card Structure

Each card represents a content recommendation:
- `uuid`: Unique identifier for the card
- `title`: The recommendation title/topic
- `content_ideas`: Array of specific content ideas

## Idea Structure

Each idea is an actionable content suggestion:
- `uuid`: Unique identifier
- `text`: The content idea description
- `implemented`: Boolean - whether this has been done
- `link`: URL to the created content (if implemented)

## Examples

### Get Full Report
```bash
drush acs:report --format=json
```

### Save Report to File
```bash
drush acs:report --format=json > content-strategy.json
```

### Extract Specific Category with jq
```bash
drush acs:report --format=json | jq '.categories.content_gaps'
```

### Get Only Pending Ideas
```bash
drush acs:report --format=json | jq '[.categories[].cards[].content_ideas[] | select(.implemented == false)]'
```

### Count Ideas by Category
```bash
drush acs:report --format=json | jq '.categories | to_entries | map({category: .key, ideas: ([.value.cards[].content_ideas[]] | length)})'
```

## When No Report Exists

If no report has been generated yet:
```json
{
  "success": false,
  "message": "No content strategy report has been generated yet. Generate one via the admin UI or API.",
  "categories": {}
}
```

## Use Cases for AI Assistants

1. **Content Planning**: Review all recommendations before creating content
2. **Progress Tracking**: Check which ideas have been implemented
3. **Priority Assessment**: Analyze categories to focus efforts
4. **Gap Analysis**: Identify areas needing more content
5. **Reporting**: Generate status reports on content strategy progress

## Related Commands

- `acs:sitemap:urls` - Get all site URLs for context
- `acs:sitemap:stats` - Quick site size check

## Generating New Reports

Reports are generated via:
- Admin UI: `/admin/config/ai/content-strategy`
- The generation process analyzes sitemap URLs with AI

END
  )]
  #[CLI\Usage(name: 'drush acs:report --format=json', description: 'Get full content strategy report')]
  #[CLI\Usage(name: 'drush acs:report --format=json > report.json', description: 'Save report to file')]
  public function getReport(): array {
    $storedData = $this->recommendationStorage->getStoredData();

    // Handle case where no report exists.
    if ($storedData === NULL || empty($storedData['data'])) {
      return [
        'success' => FALSE,
        'message' => 'No content strategy report has been generated yet. Generate one via the admin UI at /admin/config/ai/content-strategy.',
        'generated_at' => NULL,
        'pages_analyzed' => NULL,
        'categories' => [],
        'summary' => [
          'total_categories' => 0,
          'total_cards' => 0,
          'total_ideas' => 0,
          'implemented_ideas' => 0,
          'pending_ideas' => 0,
        ],
      ];
    }

    $recommendations = $storedData['data'];
    $timestamp = $storedData['timestamp'] ?? NULL;
    $pagesAnalyzed = $storedData['pages_analyzed'] ?? NULL;

    // Load all enabled recommendation categories.
    $categoryEntities = $this->entityTypeManager
      ->getStorage('recommendation_category')
      ->loadByProperties(['status' => TRUE]);

    // Sort by weight.
    uasort($categoryEntities, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    // Build categories output.
    $categories = [];
    $totalCards = 0;
    $totalIdeas = 0;
    $implementedIdeas = 0;

    foreach ($categoryEntities as $categoryId => $categoryEntity) {
      $cards = $recommendations[$categoryId] ?? [];
      $cardCount = count($cards);
      $totalCards += $cardCount;

      // Count ideas in this category.
      foreach ($cards as &$card) {
        $ideas = $card['content_ideas'] ?? [];
        $totalIdeas += count($ideas);

        // Normalize ideas and count implemented.
        foreach ($ideas as $idea) {
          if (is_array($idea) && !empty($idea['implemented'])) {
            $implementedIdeas++;
          }
        }
      }

      $categories[$categoryId] = [
        'id' => $categoryId,
        'label' => $categoryEntity->label(),
        'description' => $categoryEntity->getDescription(),
        'weight' => $categoryEntity->getWeight(),
        'card_count' => $cardCount,
        'cards' => $cards,
      ];
    }

    // Format timestamp.
    $generatedAt = NULL;
    $generatedAgo = NULL;
    if ($timestamp) {
      $generatedAt = date('c', $timestamp);
      $generatedAgo = $this->dateFormatter->formatTimeDiffSince($timestamp);
    }

    return [
      'success' => TRUE,
      'generated_at' => $generatedAt,
      'generated_ago' => $generatedAgo,
      'pages_analyzed' => $pagesAnalyzed,
      'categories' => $categories,
      'summary' => [
        'total_categories' => count($categories),
        'total_cards' => $totalCards,
        'total_ideas' => $totalIdeas,
        'implemented_ideas' => $implementedIdeas,
        'pending_ideas' => $totalIdeas - $implementedIdeas,
      ],
    ];
  }

  /**
   * Lists available recommendation categories.
   */
  #[CLI\Command(name: 'acs:categories', aliases: ['acs-c'])]
  #[CLI\Help(
    description: 'Lists all configured recommendation categories.',
    synopsis: <<<'END'

## Purpose

Lists all recommendation categories configured in the system.
Categories are dynamically configurable and define what types
of content recommendations the AI generates.

## Output Structure

```json
[
  {
    "id": "content_gaps",
    "label": "Content Gaps",
    "description": "Identifies missing content...",
    "weight": 0,
    "status": true
  }
]
```

## Examples

```bash
drush acs:categories --format=json
```

## Managing Categories

Categories are managed at:
`/admin/config/ai/content-strategy/categories`

END
  )]
  #[CLI\Usage(name: 'drush acs:categories --format=json', description: 'List all categories')]
  public function listCategories(): array {
    $categoryEntities = $this->entityTypeManager
      ->getStorage('recommendation_category')
      ->loadMultiple();

    // Sort by weight.
    uasort($categoryEntities, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    $categories = [];
    foreach ($categoryEntities as $categoryEntity) {
      $categories[] = [
        'id' => $categoryEntity->id(),
        'label' => $categoryEntity->label(),
        'description' => $categoryEntity->getDescription(),
        'weight' => $categoryEntity->getWeight(),
        'status' => $categoryEntity->status(),
      ];
    }

    return $categories;
  }

}
