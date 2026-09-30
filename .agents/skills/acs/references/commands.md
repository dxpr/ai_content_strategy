# ACS Command Reference

## Generation

| Command | Alias | Purpose |
|---|---|---|
| `acs:generate` | `acs-g` | Generate recommendations (`--category` to filter) |
| `acs:generate:more` | `acs-gm` | Generate 5 more ideas for a card |
| `acs:generate:add` | `acs-ga` | Add more cards to a category |
| `acs:health` | `acs-h` | Check AI provider configuration |

## Reports & Reading

| Command | Alias | Purpose |
|---|---|---|
| `acs:report` | `acs-r` | Full report (`--category`, `--priority`) |
| `acs:report:card` | `acs-rc` | View single card with all ideas |
| `acs:report:status` | `acs-rs` | Last-run timestamp, pages analyzed |
| `acs:sitemap` | `acs-s` | Site structure and content types |

## Card Management

| Command | Alias | Purpose |
|---|---|---|
| `acs:card:edit` | `acs-ce` | Edit card title/description (`--dry-run`) |
| `acs:card:delete` | `acs-cd` | Delete recommendation card (`--dry-run`) |

## Idea Management

| Command | Alias | Purpose |
|---|---|---|
| `acs:idea:edit` | `acs-ie` | Edit idea text (`--dry-run`) |
| `acs:idea:implement` | `acs-ii` | Mark implemented (`--link`, `--undo`, `--dry-run`) |
| `acs:idea:delete` | `acs-id` | Delete content idea (`--dry-run`) |

## Categories

| Command | Alias | Purpose |
|---|---|---|
| `acs:category:list` | `acs-catl` | List all with status/weight |
| `acs:category:get` | `acs-catg` | Full category detail |
| `acs:category:create` | `acs-catc` | Create category (`--dry-run`) |
| `acs:category:update` | `acs-catu` | Update category (`--dry-run`) |
| `acs:category:delete` | `acs-catd` | Delete category (`--dry-run`) |

## Settings & Export

| Command | Alias | Purpose |
|---|---|---|
| `acs:settings:get` | `acs-sg` | View global settings |
| `acs:settings:set` | `acs-ss` | Update system prompt (`--dry-run`) |
| `acs:export` | `acs-e` | Export (`--format=yaml/json/csv`, `--file`) |

## Setup

| Command | Alias | Purpose |
|---|---|---|
| `acs:setup-ai` | `acs-sa` | Install AI skill files (`--host`, `--check`) |
