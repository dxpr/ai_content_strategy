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

# AI Content Strategy (Drush CLI)

Recommendations are stored in Drupal's key-value store, not as nodes.
Categories are config entities. All commands return structured YAML
with a `success`/`message`/`data` envelope. All state-changing
commands support `--dry-run`.

## Gotchas

- `acs:generate` without `--category` replaces ALL existing
  recommendations. Use `--category` to target one, or
  `acs:generate:add` to append without replacing.
- Card and idea UUIDs are auto-generated. Use `acs:report` or
  `acs:report:card` to discover them before editing or deleting.
- The AI provider must be configured via the drupal/ai module before
  any generation commands work. Run `acs:health` first.

## Commands

### Generation
- `drush acs:generate`: Generate recommendations (`--category` to filter)
- `drush acs:generate:more <section> <uuid>`: Generate more ideas for a card
- `drush acs:generate:add <section>`: Add more cards to a category
- `drush acs:health`: Check AI provider configuration

### Reports
- `drush acs:report`: Full report (`--category`, `--priority`)
- `drush acs:report:card <section> <uuid>`: View single card
- `drush acs:report:status`: Last-run info
- `drush acs:sitemap`: Site structure

### Card & Idea CRUD
- `drush acs:card:edit <section> <uuid> --title="X"`: Edit card
- `drush acs:card:delete <section> <uuid>`: Delete card
- `drush acs:idea:edit <section> <uuid> <idea_uuid> --text="X"`: Edit idea
- `drush acs:idea:implement <section> <uuid> <idea_uuid> --link=URL`: Mark done
- `drush acs:idea:delete <section> <uuid> <idea_uuid>`: Delete idea

### Categories
- `drush acs:category:list`: List all categories
- `drush acs:category:get <id>`: Category detail
- `drush acs:category:create <id> <label>`: Create category
- `drush acs:category:update <id>`: Update category
- `drush acs:category:delete <id>`: Delete category

### Settings & Export
- `drush acs:settings:get`: View settings
- `drush acs:settings:set --system-prompt="..."`: Update prompt
- `drush acs:export --format=yaml|json|csv`: Export recommendations
