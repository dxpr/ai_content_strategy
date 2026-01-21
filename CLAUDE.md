# AI Content Strategy - Development Guide

## Refactoring Plan: Drupal Architecture Improvements

This plan addresses three architectural improvements to make the codebase more Drupal-idiomatic:

1. **Move inline HTML to Twig templates** - Single source of truth for markup
2. **Use Drupal's AJAX Framework** - Replace raw `fetch()` with `Drupal.ajax()`
3. **Extract Controller into Services** - Thin controllers, fat services

### Current State

- `ContentStrategyController.php`: 1,528 lines (too large)
- Inline HTML in: `generateMore()`, JS files
- Raw `fetch()` in: `content-strategy-delete.js`, `content-strategy-links.js`
- Orphaned templates in `templates/components/` (created but unused)

---

## Phase 1: Extract Services (Foundation)

**Goal:** Create service layer before touching templates/JS, so we have clean APIs to work with.

### 1.1 Create RecommendationStorageService

Handles all key-value store operations for recommendations.

```
src/Service/RecommendationStorageService.php
```

**Methods to extract from controller:**
- `getStoredRecommendations()` - Get all stored data
- `saveRecommendations(array $data)` - Save with timestamp
- `getRecommendationsForSection(string $section)` - Get section data
- `findCardByTitle(string $section, string $title)` - Find card index
- `appendIdeasToCard(string $section, string $title, array $ideas)` - Add ideas
- `updateCardField(string $section, string $title, string $field, $value)` - Update field
- `deleteCard(string $section, string $title)` - Delete card
- `deleteIdea(string $section, string $title, int $ideaIndex)` - Delete idea
- `updateIdeaField(string $section, string $title, int $index, string $field, $value)` - Update idea

**Sanity Check 1.1:**
```bash
# After creating service, run:
docker compose --profile lint run drupal-lint
docker compose --profile lint run drupal-check

# Verify service is injectable:
ddev drush eval "print_r(\Drupal::service('ai_content_strategy.recommendation_storage'));"
```

### 1.2 Create IdeaRowBuilder Service

Renders idea row HTML using Twig templates.

```
src/Service/IdeaRowBuilder.php
```

**Methods:**
- `buildRow(string $section, string $title, int $index, array $idea)` - Returns render array
- `buildRows(string $section, string $title, array $ideas, int $startIndex)` - Multiple rows
- `renderRows(...)` - Returns rendered HTML string

**Sanity Check 1.2:**
```bash
# Unit test the builder
ddev drush eval "
  \$builder = \Drupal::service('ai_content_strategy.idea_row_builder');
  \$html = \$builder->renderRows('test_section', 'Test Title', ['Idea 1', 'Idea 2'], 0);
  print \$html;
"
# Verify HTML matches template structure
```

### 1.3 Create AjaxResponseBuilder Service

Standardizes AJAX response creation.

```
src/Service/AjaxResponseBuilder.php
```

**Methods:**
- `createSuccessResponse(string $message)` - Standard success
- `createErrorResponse(string $message, int $statusCode)` - Standard error
- `createAppendCommand(string $selector, string $html)` - Append HTML
- `createRemoveCommand(string $selector)` - Remove element
- `createUpdateTimestampCommand(int $timestamp)` - Update status area

**Sanity Check 1.3:**
```bash
docker compose --profile lint run drupal-lint
docker compose --profile lint run drupal-check
```

### 1.4 Refactor Controller to Use Services

Replace inline logic with service calls. Controller methods become thin wrappers.

**Before:**
```php
public function deleteCard($section, $title) {
  // 50 lines of logic
}
```

**After:**
```php
public function deleteCard($section, $title) {
  try {
    $this->recommendationStorage->deleteCard($section, $title);
    return $this->ajaxResponseBuilder->createSuccessResponse(
      $this->t('Card deleted successfully.')
    );
  } catch (\Exception $e) {
    return $this->ajaxResponseBuilder->createErrorResponse($e->getMessage(), 500);
  }
}
```

**Sanity Check 1.4:**
```bash
# Full test suite
docker compose --profile lint run drupal-lint
docker compose --profile lint run drupal-check
docker compose --profile test run drupal-test

# Manual smoke test - all CRUD operations should still work:
# 1. Generate recommendations
# 2. Edit a card title
# 3. Add/edit content ideas
# 4. Toggle implemented checkbox
# 5. Delete an idea
# 6. Delete a card
# 7. Export CSV
```

**Phase 1 Complete Checklist:**
- [ ] RecommendationStorageService created and tested
- [ ] IdeaRowBuilder created and tested
- [ ] AjaxResponseBuilder created and tested
- [ ] Controller refactored to use services
- [ ] All lint checks pass
- [ ] All existing functionality works
- [ ] Controller reduced by ~400 lines

---

## Phase 2: Twig Templates for Server-Side Rendering

**Goal:** Move all inline HTML to Twig templates, rendered server-side.

### 2.1 Create idea-row.html.twig Template

```
templates/components/idea-row.html.twig
```

**Variables:**
- `section` - Section identifier
- `title` - Card title
- `idea_index` - Row index
- `idea_text` - The idea content
- `idea_implemented` - Boolean
- `idea_link` - Optional URL

This template replaces:
- Inline HTML in `ContentStrategyController::generateMore()`
- The loop in `ai-content-strategy-recommendations-items.html.twig` (lines 52-80)

**Sanity Check 2.1:**
```bash
# Render template directly
ddev drush eval "
  \$build = [
    '#theme' => 'ai_content_strategy_idea_row',
    '#section' => 'content_gaps',
    '#title' => 'Test Card',
    '#idea_index' => 0,
    '#idea_text' => 'Test idea',
    '#idea_implemented' => false,
    '#idea_link' => '',
  ];
  print \Drupal::service('renderer')->renderRoot(\$build);
"
# Verify output matches expected HTML structure
```

### 2.2 Update IdeaRowBuilder to Use Template

Modify `IdeaRowBuilder::buildRow()` to return render array using new template.

**Sanity Check 2.2:**
```bash
# Generate new ideas and verify HTML matches template
# 1. Click "Generate more ideas" on a card
# 2. Inspect the new rows in browser DevTools
# 3. Compare structure to template
```

### 2.3 Refactor Main Items Template

Update `ai-content-strategy-recommendations-items.html.twig` to include the component:

```twig
{% for key, idea in item.content_ideas %}
  {% include 'ai-content-strategy-idea-row.html.twig' with {
    'section': section,
    'title': item_title,
    'idea_index': key,
    'idea_text': idea.text|default(idea),
    'idea_implemented': idea.implemented|default(false),
    'idea_link': idea.link|default(''),
  } %}
{% endfor %}
```

**Sanity Check 2.3:**
```bash
# Clear caches and verify page renders correctly
ddev drush cr

# Load recommendations page
# Verify all existing ideas display correctly
# Verify checkboxes, delete buttons, edit functionality work
```

### 2.4 Use Existing Link Component Templates

The templates already exist but are unused. Wire them up:

- `link-display.html.twig` - Used when idea has a link
- `link-add-button.html.twig` - Used when no link exists
- `link-input.html.twig` - Used when editing a link

Update `idea-row.html.twig` to include these:

```twig
<div class="idea-link-area"{{ idea_implemented ? '' : ' style="display: none;"' }}>
  {% if idea_link %}
    {% include 'components/link-display.html.twig' %}
  {% else %}
    {% include 'components/link-add-button.html.twig' %}
  {% endif %}
</div>
```

**Sanity Check 2.4:**
```bash
ddev drush cr
# Test link functionality:
# 1. Mark idea as implemented - link area appears
# 2. Click "Add link" - input form appears
# 3. Save link - link displays with edit button
# 4. Edit link - input form with current value
```

**Phase 2 Complete Checklist:**
- [ ] idea-row.html.twig created
- [ ] IdeaRowBuilder uses template
- [ ] Main items template uses include
- [ ] Link component templates wired up
- [ ] All lint checks pass
- [ ] Page renders correctly
- [ ] Generate more ideas works with new template
- [ ] No inline HTML in controller

---

## Phase 3: Drupal AJAX Framework

**Goal:** Replace raw `fetch()` with `Drupal.ajax()` for proper command processing.

### 3.1 Understand Current vs Target Architecture

**Current (wrong):**
```javascript
fetch(url)
  .then(r => r.json())
  .then(commands => {
    // Manually process commands
    // Manually call attachBehaviors
  });
```

**Target (Drupal way):**
```javascript
const ajax = Drupal.ajax({
  url: url,
  // Drupal automatically:
  // - Processes all AJAX commands
  // - Calls attachBehaviors on new content
  // - Handles errors consistently
});
ajax.execute();
```

### 3.2 Create AJAX Endpoint for Link Operations

Current flow makes link changes client-side then saves. Change to:

1. JS calls AJAX endpoint with new link value
2. Controller saves and renders updated link area HTML
3. Controller returns `HtmlCommand` to replace link area
4. Drupal.ajax processes command and attaches behaviors

**New controller method:**
```php
public function updateIdeaLink(string $section, string $title, int $ideaIndex) {
  $link = $this->requestStack->getCurrentRequest()->request->get('link');

  $this->recommendationStorage->updateIdeaField($section, $title, $ideaIndex, 'link', $link);

  $html = $this->ideaRowBuilder->renderLinkArea($section, $title, $ideaIndex, $link);

  $response = new AjaxResponse();
  $response->addCommand(new HtmlCommand(
    ".idea-link-area[data-idea-index='$ideaIndex']",
    $html
  ));
  return $response;
}
```

**Sanity Check 3.2:**
```bash
# Test endpoint directly
curl -X POST "https://yoursite/admin/reports/ai/content-strategy/update-idea-link/content_gaps/Test%20Card/0" \
  -d "link=https://example.com" \
  -H "X-Requested-With: XMLHttpRequest"
# Should return AJAX commands JSON
```

### 3.3 Refactor content-strategy-links.js

Replace `saveIdeaLink()` function:

**Before:**
```javascript
fetch(url, { method: 'POST', body: formData })
  .then(response => response.json())
  .then(data => {
    // Build HTML manually
    linkArea.innerHTML = `<a href="${link}"...`;
    Drupal.attachBehaviors(linkArea);
  });
```

**After:**
```javascript
const ajax = Drupal.ajax({
  url: Drupal.url(`admin/reports/ai/content-strategy/update-idea-link/${section}/${encodeURIComponent(title)}/${ideaIndex}`),
  submit: { link: link },
  // Commands processed automatically, behaviors attached automatically
});
ajax.execute();
```

**Sanity Check 3.3:**
```bash
# Test in browser:
# 1. Add a link to an idea
# 2. Verify link saves and displays
# 3. Check Network tab - should see AJAX request/response
# 4. Verify no JS errors in console
```

### 3.4 Refactor content-strategy-delete.js

Replace both delete handlers to use `Drupal.ajax()`.

**Card deletion - Before:**
```javascript
fetch(url, { method: 'GET' })
  .then(response => response.json())
  .then(commands => {
    processDeleteCommands(commands);
    Drupal.attachBehaviors(...);
  });
```

**Card deletion - After:**
```javascript
const ajax = Drupal.ajax({
  url: Drupal.url(`admin/reports/ai/content-strategy/delete-card/${section}/${encodeURIComponent(title)}`),
  dialogType: 'ajax', // Ensures proper command handling
});
ajax.execute();
```

**Sanity Check 3.4:**
```bash
# Test deletions:
# 1. Delete a content idea - row should disappear
# 2. Delete a card - card should disappear
# 3. Success message should appear
# 4. No JS errors
```

### 3.5 Refactor content-strategy-utils.js

The custom `createAjaxHandler` duplicates Drupal's AJAX. Options:

**Option A: Remove entirely** - Use `Drupal.ajax()` directly in each behavior

**Option B: Thin wrapper** - Keep as convenience but delegate to `Drupal.ajax()`:
```javascript
Drupal.aiContentStrategy.createAjaxHandler = function(options) {
  return Drupal.ajax({
    url: Drupal.url(options.url),
    element: options.element,
    progress: { type: 'throbber', message: options.loadingText },
    // ... map other options
  });
};
```

**Recommended: Option A** - Less code, more Drupal-native.

**Sanity Check 3.5:**
```bash
# Full functional test:
# 1. Generate recommendations
# 2. Generate more ideas for a card
# 3. Edit card title
# 4. Edit idea text
# 5. Toggle implemented
# 6. Add/edit link
# 7. Delete idea
# 8. Delete card
# All should work with Drupal.ajax()
```

### 3.6 Remove processDeleteCommands and Manual Command Handling

After migrating to `Drupal.ajax()`, remove:
- `processDeleteCommands()` function
- All manual `Drupal.attachBehaviors()` calls after AJAX
- Custom command processing loops

**Sanity Check 3.6:**
```bash
docker compose --profile lint run drupal-lint
# Verify no eslint errors
# Verify behaviors still attach to new content
```

**Phase 3 Complete Checklist:**
- [ ] Link operations use Drupal.ajax()
- [ ] Delete operations use Drupal.ajax()
- [ ] Generate operations use Drupal.ajax()
- [ ] Removed processDeleteCommands()
- [ ] Removed manual attachBehaviors() calls
- [ ] All AJAX returns proper Drupal commands
- [ ] No inline HTML in JavaScript
- [ ] All functionality works

---

## Phase 4: Final Cleanup and Validation

### 4.1 Remove Dead Code

- Delete unused functions from JS files
- Remove orphaned CSS if any
- Clean up controller methods that are no longer needed

### 4.2 Update Library Definitions

Ensure `ai_content_strategy.libraries.yml` has correct dependencies:
```yaml
content_strategy:
  dependencies:
    - core/drupal.ajax
    - core/drupal.announce
    - core/once
```

### 4.3 Full Test Suite

```bash
# Lint checks
docker compose --profile lint run drupal-lint
docker compose --profile lint run drupal-check

# Automated tests
docker compose --profile test run drupal-test

# Manual regression test checklist:
# [ ] Fresh install works
# [ ] Generate recommendations (empty state)
# [ ] Generate recommendations (with existing)
# [ ] Edit card title (inline)
# [ ] Edit card description (inline)
# [ ] Edit idea text (inline)
# [ ] Toggle implemented checkbox
# [ ] Add link to idea
# [ ] Edit existing link
# [ ] Remove link (save empty)
# [ ] Delete individual idea
# [ ] Delete entire card
# [ ] Generate more ideas
# [ ] Add more recommendations
# [ ] Export to CSV
# [ ] Multiple browser tabs (no conflicts)
```

### 4.4 Documentation

Update README.md with:
- Architecture overview
- Service descriptions
- Template customization guide

**Phase 4 Complete Checklist:**
- [ ] No dead code
- [ ] Libraries correct
- [ ] All tests pass
- [ ] Manual regression complete
- [ ] Documentation updated

---

## File Changes Summary

### New Files
```
src/Service/RecommendationStorageService.php
src/Service/IdeaRowBuilder.php
src/Service/AjaxResponseBuilder.php
templates/components/idea-row.html.twig
```

### Modified Files
```
src/Controller/ContentStrategyController.php (significantly reduced)
ai_content_strategy.services.yml (add new services)
ai_content_strategy.module (update hook_theme)
js/content-strategy-links.js (use Drupal.ajax)
js/content-strategy-delete.js (use Drupal.ajax)
js/content-strategy-utils.js (simplify or remove)
js/content-strategy-generate.js (use Drupal.ajax)
templates/ai-content-strategy-recommendations-items.html.twig (use includes)
```

### Potentially Removed Files
```
js/content-strategy-utils.js (if fully replaced by Drupal.ajax)
```

---

## Success Metrics

After refactoring:

| Metric | Before | After |
|--------|--------|-------|
| Controller lines | ~1,528 | ~500 |
| Inline HTML locations | 3 (controller + 2 JS) | 0 |
| Raw fetch() calls | 4 | 0 |
| Template includes | 0 | 4+ |
| Service classes | 3 | 6 |
| Manual attachBehaviors | 8+ | 0 |

---

## Notes

- Each phase can be merged independently
- Always run lint checks before committing
- Test in multiple browsers (Chrome, Firefox, Safari)
- Test with Drupal's BigPipe enabled
- Consider adding PHPUnit tests for new services
