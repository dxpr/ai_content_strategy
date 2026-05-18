---
name: acs
description: >
  Manage AI Content Strategy recommendations via Drush CLI.
  Generate, curate, and export content strategy recommendations
  powered by AI analysis of existing site content. Use this skill
  when the user asks to generate content ideas, manage or curate
  recommendations, work with content cards or ideas, export content
  plans, check AI provider health, or inspect site structure for
  content gaps, even if they don't mention "ACS" or "Drush" directly.
---

# AI Content Strategy

Recommendations are stored in Drupal's key-value store, not as nodes.
Categories are config entities. All commands return structured YAML
with a `success`/`message`/`data` envelope. All state-changing
commands support `--dry-run`.

## Auto-discover current state

```bash
drush acs:health
drush acs:category:list
drush acs:report:status
drush acs:sitemap
```

## Command reference

Read [references/commands.md](references/commands.md) when you need
the full command table, aliases, or flag details.

## Gotchas

- `acs:generate` without `--category` replaces ALL existing
  recommendations. Use `--category` to target one, or
  `acs:generate:add` to append without replacing.
- Card and idea UUIDs are auto-generated. Use `acs:report` or
  `acs:report:card` to discover them before editing or deleting.
- The AI provider must be configured via the drupal/ai module before
  any generation commands work. Run `acs:health` first.
- `acs:generate` requires `-l <site-uri>` in multisite setups so
  Drush knows which site to analyze.

## Workflows

### Generate and review

1. Verify AI provider: `drush acs:health`
   - If unhealthy, configure the provider in the drupal/ai module first.
2. Generate: `drush acs:generate -l https://example.com`
   - To target one category: `--category=content_gaps`
3. Review: `drush acs:report`
   - Filter by priority: `--priority=high`
4. Drill into a card: `drush acs:report:card <section> <uuid>`
5. If more ideas needed: `drush acs:generate:more <section> <uuid>`

### Curate and implement

1. Find high-priority cards: `drush acs:report --priority=high`
2. Mark an idea implemented:
   `drush acs:idea:implement <section> <card-uuid> <idea-uuid> --link=https://example.com/new-page`
3. Export results: `drush acs:export --format=json`

### Manage categories

1. List categories: `drush acs:category:list`
2. Create: `drush acs:category:create seasonal_content "Seasonal Content" --instructions="Identify seasonal and timely content opportunities"`
3. Adjust weight: `drush acs:category:update trust_signals --weight=5`
