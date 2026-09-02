# Recommendation Categories

Categories define the types of content recommendations the module generates. Each category has its own AI instructions that tell the model what to analyse and what kind of recommendations to produce.

## Managing categories

Navigate to `/admin/config/ai/content-strategy/categories` to see all categories in a drag-and-drop table. From here you can:

- **Reorder** categories by dragging them; the order determines how sections appear on the dashboard
- **Enable or disable** categories using the status toggle
- **Edit** a category's label, description, and AI instructions
- **Delete** custom categories (built-in categories cannot be deleted; disable them instead)

## Built-in categories

The module ships with seven categories that cover the core EEAT (Experience, Expertise, Authoritativeness, Trustworthiness) framework:

| Category | Focus |
|---|---|
| Content Gaps | Pages visitors would expect but the site lacks |
| Authority Topics | Subjects for establishing thought leadership |
| Content Series | Opportunities for multi-part sequences |
| Expertise Demonstrations | Ways to showcase domain knowledge |
| Orphaned Topics | Existing pages that need supporting content |
| Trust Signals | Credibility content (case studies, testimonials) |
| Underutilised Types | Content formats the site could explore |

Built-in categories are marked as locked. You can edit their labels, descriptions, and AI instructions, but you cannot delete them. If you want to temporarily exclude a category from generation, disable it.

## Creating a custom category

1. Visit `/admin/config/ai/content-strategy/categories/add`
2. Enter a machine name, label, and description
3. Write the AI instructions: tell the model what to look for in the site's content and what kind of recommendations to produce
4. Set the weight to control where it appears relative to other categories
5. Save

### Writing effective instructions

The instructions field is the prompt sent to the AI model for this category. Good instructions:

- Describe what the model should look for in the site's structure and content
- Specify the format of recommendations (titles, descriptions, actionable ideas)
- Set boundaries: what to include and what to exclude
- Reference the site's domain where relevant; the model receives the sitemap and page data alongside these instructions

## Configuration management

Categories are configuration entities. You can export and import them across sites using Drupal's standard configuration management system (`drush cex` / `drush cim`). Each category is stored as `ai_content_strategy.recommendation_category.{id}.yml`.

## Restoring built-in categories

If you have edited built-in categories and want to reset them to their defaults, visit `/admin/config/ai/content-strategy/categories/restore` or run:

```bash
drush acs:category:reset
```

This restores any missing built-in categories without affecting custom categories or categories you have edited.
