# AI Content Strategy

AI-powered content strategy recommendations based on your existing content and
EEAT framework.

## Features

- **Content Gap Analysis**: Identifies missing content opportunities
- **Authority Topics**: Suggests topics for domain expertise
- **Expertise Demonstrations**: Recommends content formats to showcase knowledge
- **Trust Signals**: Builds credibility with your audience
- **Priority-based Recommendations**: High/medium/low prioritization
- **Dynamic Idea Generation**: "Generate More Ideas" for each recommendation

## Requirements

- Drupal 10.2+
- [AI module](https://www.drupal.org/project/ai)
- Menu UI module (core)

## Installation

```bash
composer require drupal/ai_content_strategy
drush en ai_content_strategy
```

## Configuration

1. Configure AI provider at `/admin/config/ai/providers`
2. Set permissions at `/admin/people/permissions`
3. Configure recommendation categories at
   `/admin/config/ai/content-strategy/categories`
4. Customize global AI settings at
   `/admin/config/ai/content-strategy/settings`
5. Access recommendations at `/admin/reports/ai/content-strategy`
6. Click "Generate Recommendations" to analyze your site
7. Use "Generate More Ideas" for specific content suggestions

### Customizing Categories

You can customize the recommendation categories to match your content
strategy needs:

- **Add new categories**: Create custom recommendation types
- **Modify instructions**: Tailor AI analysis per category
- **Enable/disable categories**: Control which recommendations appear
- **Reorder categories**: Drag and drop to change display order

### Exporting Configuration

Categories are configuration entities and can be exported/imported:

```bash
# Export configuration
drush config:export

# Import configuration
drush config:import
```

The category configs are stored in:
- `ai_content_strategy.recommendation_category.*`
- `ai_content_strategy.settings`

## Upgrading

If upgrading from a version prior to 2.0.0:

1. Run database updates: `drush updatedb`
2. Clear caches: `drush cr`
3. Review and customize categories at
   `/admin/config/ai/content-strategy/categories`

The module will automatically install default categories (Content Gaps,
Authority Topics, Expertise Demonstrations, Trust Signals) with
pre-configured instructions.

## Usage

The module analyzes your site structure, navigation, and existing content to
recommend:

- Missing content types for comprehensive coverage
- Topics that establish your authority
- Content formats that demonstrate expertise
- Trust-building elements for credibility

Each recommendation includes priority level and specific content ideas.

## Development

### Running Code Quality Tools

This project uses Docker for running code quality checks:

```bash
# Run Drupal coding standards lint
docker compose --profile lint run drupal-lint

# Auto-fix coding standards issues
docker compose --profile lint run drupal-lint-auto-fix

# Run drupal-check for deprecation analysis
docker compose --profile lint run drupal-check

# Run tests
docker compose --profile test run drupal-test
```
