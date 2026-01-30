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
  #[CLI\Help(
    description: 'Gets the full AI content strategy report as JSON. Returns the same data visible in the admin UI.',
    synopsis: <<<'END'

## Purpose

Returns the complete AI-generated content strategy report as JSON. This is the
read-only CLI equivalent of the admin UI at /admin/config/ai/content-strategy.

Use this command to programmatically access content recommendations for:
- AI coding assistants analyzing content needs
- Automated reporting and dashboards
- Integration with external tools
- Content planning workflows

## Output Structure

```json
{
  "success": true,
  "generated_at": "2024-01-30T12:00:00+00:00",
  "pages_analyzed": 150,
  "categories": {
    "content_gaps": {
      "label": "Content Gaps",
      "cards": [
        {
          "uuid": "abc-123",
          "title": "Product Comparison Pages",
          "description": "Create comparison content...",
          "priority": "high",
          "content_ideas": [
            {
              "uuid": "def-456",
              "text": "Compare Product A vs Product B",
              "implemented": false,
              "link": ""
            },
            {
              "uuid": "ghi-789",
              "text": "Feature comparison matrix",
              "implemented": true,
              "link": "/node/123"
            }
          ]
        }
      ]
    }
  }
}
```

## Data Structure

### Root Level
| Field | Type | Description |
|-------|------|-------------|
| success | boolean | Whether report data exists |
| generated_at | string | ISO 8601 timestamp of last generation |
| pages_analyzed | integer | Number of sitemap URLs analyzed |
| categories | object | Recommendation categories keyed by ID |

### Category
| Field | Type | Description |
|-------|------|-------------|
| label | string | Human-readable category name |
| cards | array | Recommendation cards in this category |

### Card
| Field | Type | Description |
|-------|------|-------------|
| uuid | string | Unique identifier for the card |
| title | string | Recommendation title/topic |
| description | string | Detailed description |
| priority | string | Priority level (high/medium/low) |
| content_ideas | array | Specific actionable ideas |

### Content Idea
| Field | Type | Description |
|-------|------|-------------|
| uuid | string | Unique identifier for the idea |
| text | string | The content idea description |
| implemented | boolean | Whether this has been completed |
| link | string | URL to created content (if implemented) |

## Categories

Categories are dynamically configured via the admin UI. Default categories include:
- **Content Gaps**: Missing content opportunities
- **Authority Topics**: Topics to build expertise in
- **Trust Signals**: E-E-A-T improvement suggestions
- **Content Series**: Related content groupings
- **Expertise Demonstrations**: Ways to showcase knowledge

## Examples

### Basic Usage
```bash
drush acs:report
```

### Save to File
```bash
drush acs:report > strategy-report.json
```

### Extract with jq
```bash
# Get specific category
drush acs:report | jq '.categories.content_gaps'

# List all pending ideas
drush acs:report | jq '[.categories[].cards[].content_ideas[] | select(.implemented == false)]'

# Count ideas per category
drush acs:report | jq '.categories | to_entries | map({key: .key, count: ([.value.cards[].content_ideas[]] | length)})'
```

## When No Report Exists

If no report has been generated:
```json
{
  "success": false,
  "message": "No report generated yet. Visit /admin/config/ai/content-strategy.",
  "categories": []
}
```

Generate a report via the admin UI before using this command.

## Related Commands

- `acs:sitemap` - Get sitemap URLs and content statistics

END
  )]
  #[CLI\Usage(name: 'drush acs:report', description: 'Get full report as JSON')]
  #[CLI\Usage(name: 'drush acs:report > report.json', description: 'Save report to file')]
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
