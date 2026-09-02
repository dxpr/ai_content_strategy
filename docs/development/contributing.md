# Contributing

AI Content Strategy is an open source Drupal module. Contributions are welcome through the issue queues and merge requests on drupal.org.

## Issue queue

Report bugs and request features at the [drupal.org issue queue](https://www.drupal.org/project/issues/ai_content_strategy).

Before opening a new issue, search for existing reports. Include the Drupal version, AI provider in use, and steps to reproduce.

## Development setup

```bash
git clone https://git.drupalcode.org/project/ai_content_strategy.git
cd ai_content_strategy
```

The module requires a working Drupal installation with the [AI module](https://www.drupal.org/project/ai) and a configured AI provider.

## Code standards

Follow [Drupal coding standards](https://www.drupal.org/docs/develop/standards). The module uses:

- PHP 8.1 or later
- Drupal 10.2+ / 11 APIs
- Configuration entities for recommendation categories
- State API for recommendation storage

## Testing

The module uses Drupal's testing framework. Run tests with:

```bash
phpunit --group ai_content_strategy
```

## Architecture

Key services and their responsibilities:

| Service | Role |
|---|---|
| `ContentAnalyzer` | Fetches sitemap and page content for analysis |
| `StrategyGenerator` | Orchestrates the AI call and parses the response |
| `CategoryPromptBuilder` | Assembles the prompt from system prompt and category instructions |
| `CategorySchemaBuilder` | Generates the JSON schema for structured AI output |
| `RecommendationStorageService` | Persists recommendations to Drupal's State API |
| `BuiltInCategoryManager` | Manages the default shipped categories |

Recommendation categories are `ConfigEntityBase` entities (`RecommendationCategory`), exportable through standard configuration management.
