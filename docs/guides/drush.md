# Drush Commands

AI Content Strategy ships 23 Drush commands organised into seven groups. All commands use the `acs:` prefix.

!!! note
    Commands that analyse your site need the `-l` (URI) option so the module can resolve sitemap and page URLs. For example: `drush acs:generate -l https://example.com`

## Health and setup

| Command | Description |
|---|---|
| `acs:health` | Check whether the AI provider is configured and the sitemap is reachable |
| `acs:sitemap` | Fetch and display the sitemap URLs the module would analyse |
| `acs:setup-ai` | Install AI coding assistant skill files to the project root |

## Generation

| Command | Description |
|---|---|
| `acs:generate` | Generate recommendations for all enabled categories |
| `acs:generate:more` | Generate more ideas for a specific card |
| `acs:generate:add` | Add new recommendation cards to a category |

### Options

- `--category=ID`: generate only for a specific category (e.g. `--category=content_gaps`)
- `--dry-run`: preview what would be generated without saving

### Examples

```bash
drush acs:generate -l https://example.com
drush acs:generate --category=content_gaps -l https://example.com
drush acs:generate --dry-run -l https://example.com
drush acs:generate:more content_gaps CARD-UUID -l https://example.com
drush acs:generate:add authority_topics -l https://example.com
```

## Reporting

| Command | Description |
|---|---|
| `acs:report` | Display all recommendations grouped by category |
| `acs:report:card` | Show details for a single card |
| `acs:report:status` | Summary of implemented vs. pending ideas per category |

## Card management

| Command | Description |
|---|---|
| `acs:card:edit` | Update a card's title, description, or priority |
| `acs:card:delete` | Delete a card and all its ideas |

### Examples

```bash
drush acs:card:edit content_gaps UUID --title="New Title"
drush acs:card:delete content_gaps UUID
```

## Idea management

| Command | Description |
|---|---|
| `acs:idea:edit` | Update an idea's text |
| `acs:idea:delete` | Delete a single idea from a card |
| `acs:idea:implement` | Mark an idea as implemented (optionally with a URL) |

## Category administration

| Command | Description |
|---|---|
| `acs:category:list` | List all categories with their status and weight |
| `acs:category:get` | Show details for a single category |
| `acs:category:create` | Create a new custom category |
| `acs:category:update` | Update a category's label, instructions, status, or weight |
| `acs:category:delete` | Delete a custom category |
| `acs:category:reset` | Restore missing built-in categories to their defaults |

### Examples

```bash
drush acs:category:list
drush acs:category:create seasonal "Seasonal Content" --instructions="Identify seasonal content opportunities"
drush acs:category:update content_gaps --status=0
drush acs:category:reset --dry-run
```

## Export and settings

| Command | Description |
|---|---|
| `acs:export` | Export all recommendations (YAML, JSON, or CSV) |
| `acs:settings:get` | Display current settings |
| `acs:settings:set` | Update a setting value |

### Export examples

```bash
drush acs:export
drush acs:export --format=json
drush acs:export --format=csv --file=export.csv
drush acs:export --format=json | jq .
```
