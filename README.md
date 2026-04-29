> **AI Content Strategy** is a Drupal module by [DXPR](https://dxpr.com) that
> uses AI to generate data-driven content recommendations based on your existing
> site content, audience gaps, and publishing patterns.
>
> [Getting Started](https://dxpr.com/c/getting-started) |
> [Pricing](https://dxpr.com/pricing) |
> [Try Free Demo](https://dxpr.com/try)

# AI Content Strategy: AI-Powered Content Recommendations for Drupal

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

## Drush CLI Commands

AI Content Strategy ships Drush commands for CLI and AI-agent
content strategy workflows. All commands use structured YAML
output for machine parseability.

### Quick Start

```bash
# Set up AI coding assistant integration
drush acs:setup-ai

# Check AI provider health
drush acs:health

# Generate recommendations for all categories
drush acs:generate -l https://example.com

# View recommendations report
drush acs:report

# Filter by category and priority
drush acs:report --category=content_gaps --priority=high

# Export as JSON
drush acs:export --format=json
```

### All Commands

| Command | Alias | Description |
|---------|-------|-------------|
| `acs:generate` | `acs-g` | Generate recommendations (all or `--category`) |
| `acs:generate:more` | `acs-gm` | Generate more ideas for a card |
| `acs:generate:add` | `acs-ga` | Add more cards to a category |
| `acs:health` | `acs-h` | Check AI provider configuration |
| `acs:report` | `acs-r` | Full report (`--category`, `--priority`) |
| `acs:report:card` | `acs-rc` | View single card with ideas |
| `acs:report:status` | `acs-rs` | Last-run timestamp, pages analyzed |
| `acs:sitemap` | `acs-s` | Site structure and content types |
| `acs:card:edit` | `acs-ce` | Edit card title/description |
| `acs:card:delete` | `acs-cd` | Delete recommendation card |
| `acs:idea:edit` | `acs-ie` | Edit idea text |
| `acs:idea:implement` | `acs-ii` | Mark idea implemented (`--link`, `--undo`) |
| `acs:idea:delete` | `acs-id` | Delete content idea |
| `acs:category:list` | `acs-catl` | List categories with status/weight |
| `acs:category:get` | `acs-catg` | Full category detail |
| `acs:category:create` | `acs-catc` | Create category |
| `acs:category:update` | `acs-catu` | Update category |
| `acs:category:delete` | `acs-catd` | Delete category |
| `acs:settings:get` | `acs-sg` | View global settings |
| `acs:settings:set` | `acs-ss` | Update system prompt |
| `acs:export` | `acs-e` | Export (`--format=yaml/json/csv`, `--file`) |
| `acs:setup-ai` | `acs-sa` | Install AI skill files |

Run `drush <command> --help` for full options on any command.
All state-changing commands support `--dry-run`.

### AI Coding Assistant Integration

AI Content Strategy supports the [Agent Skills](https://agentskills.io)
standard. Run `drush acs:setup-ai` to install skill files,
then use `/acs` in your AI tool to manage content strategy:

```
/acs generate content recommendations for my site
/acs show high-priority content gaps
/acs export recommendations as JSON
```

Supports Claude Code, Codex, Gemini CLI, Copilot, and Cursor.

## Related Modules

- [AI](https://www.drupal.org/project/ai) - Required. Provides the LLM provider layer used for all content analysis and recommendation generation
- [Menu UI](https://www.drupal.org/docs/core-modules-and-themes/core-modules/menu-ui-module) - Optional core module. When enabled, AI Content Strategy analyzes your site navigation to produce more contextual recommendations
- [AI Social Posts](https://www.drupal.org/project/ai_social_posts) - Turn your content recommendations into social media posts across 14+ platforms
- [Drush](https://www.drush.org/) - AI Content Strategy ships 20+ Drush commands for CLI and AI-agent workflows

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

### E2E Tests

```bash
# Run all Drush command e2e tests
docker compose --profile test run --rm e2e-test

# Run specific test file
docker compose --profile test run --rm e2e-test report

# Run setup-ai tests only
docker compose --profile test run --rm e2e-test setup-ai
```
