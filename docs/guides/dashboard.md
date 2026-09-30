# Dashboard

The recommendations dashboard at `/admin/reports/ai/content-strategy` is the primary interface for viewing, editing, and tracking content recommendations.

## Generating recommendations

Click **Generate recommendations** to start a new analysis. The module:

1. Fetches your XML sitemap to discover published pages
2. Reads menu structures (when Menu UI is enabled)
3. Sends the site data and category instructions to the AI provider
4. Displays the results grouped by category

Generation makes one API call per enabled category. Depending on your site's size and the AI provider's speed, this typically takes a few seconds per category.

## Dashboard layout

Recommendations are displayed as cards grouped under category sections. Each card contains:

- **Title**: a short name for the recommendation
- **Description**: context explaining why this recommendation matters
- **Priority badge**: high, medium, or low
- **Content ideas**: specific actionable items within the recommendation

### Inline editing

Click any card title, description, or idea text to edit it in place. Changes save automatically when you click away or press Tab.

### Progress tracking

Each idea has an **Implemented** checkbox. Tick it to mark the idea as done; an optional text field appears where you can paste a link to the published content. Implemented ideas display with a strikethrough and a green checkmark.

### Generate more

Click **Generate more** on any card to request additional ideas for that specific recommendation. The AI receives the existing ideas so it does not repeat them.

Click **Add more recommendations** at the bottom of a category section to generate entirely new cards for that category.

### Deleting

Hover over a card title or idea to reveal the delete button. Deleting a card removes it and all its ideas; deleting an idea removes only that item.

## CSV export

Click **Export CSV** to download all recommendations and their statuses. The export includes category, card title, description, priority, each idea's text, implementation status, and linked URL. Use this for editorial calendars or reporting in external tools.

## Regenerating

Clicking **Generate recommendations** again replaces existing recommendations with a fresh analysis. If you want to keep your current recommendations and add more, use the per-card and per-category "Generate more" options instead.
