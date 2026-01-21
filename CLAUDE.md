# AI Content Strategy - Architecture Guide

## Module Architecture

This module follows Drupal best practices with a service-oriented architecture:

### Services

| Service | Purpose |
|---------|---------|
| `ai_content_strategy.recommendation_storage` | CRUD operations for key-value stored recommendations |
| `ai_content_strategy.idea_row_builder` | Renders idea row HTML using Twig templates |
| `ai_content_strategy.ajax_response_builder` | Factory for standardized AJAX responses |

### Templates

All UI markup is defined in Twig templates:

```
templates/
  ai-content-strategy-recommendations.html.twig     # Main page structure
  ai-content-strategy-recommendations-items.html.twig  # Card rendering
  components/
    idea-row.html.twig        # Single idea row with checkbox, link, delete
    link-display.html.twig    # Link display with edit button
    link-add-button.html.twig # "Add link" button
    link-input.html.twig      # Link input form
```

### JavaScript

All JS modules use Drupal's AJAX framework (`Drupal.ajax()`) for server communication:

| File | Purpose |
|------|---------|
| `content-strategy-utils.js` | Shared utilities and AJAX handler factory |
| `content-strategy-generate.js` | Generate recommendations and ideas |
| `content-strategy-delete.js` | Delete cards and ideas |
| `content-strategy-links.js` | Add/edit idea links |
| `content-strategy-checkbox.js` | Toggle implemented status |
| `content-strategy-editable.js` | Inline editing with auto-save |
| `content-strategy-export.js` | CSV export (client-side only) |

## Key Design Decisions

### 1. Server-Side HTML Rendering

All HTML is rendered server-side via Twig templates. The controller returns AJAX commands that replace DOM content with pre-rendered HTML. This ensures:
- Single source of truth for markup
- Drupal's theme layer handles rendering
- Behaviors automatically attach to new content

### 2. Drupal AJAX Framework

All server communication uses `Drupal.ajax()` instead of raw `fetch()`:
- Server returns `AjaxResponse` with commands (`HtmlCommand`, `RemoveCommand`, etc.)
- Drupal automatically processes commands and calls `attachBehaviors()`
- Consistent error handling

### 3. Optimistic UI Updates

For perceived performance, some operations update the UI immediately:
- Checkbox toggle shows state change before server confirms
- Reverts on error with user notification

### 4. Thin Controller

The `ContentStrategyController` delegates to services:
- `RecommendationStorageService` handles data operations
- `IdeaRowBuilder` handles template rendering
- Controller methods are simple wrappers

## Development Notes

### Adding a New Feature

1. Add template in `templates/components/` if new UI needed
2. Register in `ai_content_strategy.module` hook_theme()
3. Add render method to `IdeaRowBuilder` if applicable
4. Create controller endpoint returning `AjaxResponse`
5. Add JS behavior using `Drupal.ajax()`

### Testing

```bash
# Clear caches after template changes
ddev drush cr

# Verify services load
ddev drush ev "Drupal::service('ai_content_strategy.idea_row_builder');"

# Check PHP syntax
ddev exec php -l ./web/modules/contrib/ai_content_strategy/src/Controller/ContentStrategyController.php
```

## Refactoring History

Completed January 2026:
- Extracted 3 services from controller
- Created Twig template for idea rows
- Replaced all `fetch()` with `Drupal.ajax()`
- Removed manual command processing
- Controller reduced from ~1,500 to ~800 lines
