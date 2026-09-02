# FAQ

## Does AI Content Strategy require a paid AI subscription?

The module itself is free and open source under the GPL licence. You need an AI provider to generate recommendations. [DXPR AI Provider](https://www.drupal.org/project/ai_provider_dxpr) offers a free tier (10,000 monthly credits) that works out of the box. Alternatively, bring your own API keys from OpenAI, Anthropic, Google, or any other provider supported by the [Drupal AI module](https://www.drupal.org/project/ai). The module makes one API call per enabled category per generation request.

## How is this different from asking ChatGPT for content ideas?

AI Content Strategy feeds your actual site structure, published pages, and navigation to the AI model before asking for recommendations. The result is advice grounded in what you already have, not generic suggestions. Ideas persist on a shared dashboard where your team can edit, prioritise, track implementation, and export to CSV; a chat conversation disappears when you close the tab.

## How do I track which recommendations I have acted on?

Each content idea includes an "Implemented" toggle. Tick it to mark the idea as done and optionally add a link to the published content. The dashboard shows implemented ideas with a strikethrough and green checkmark so you can see progress at a glance. Export all recommendations and their status to CSV for tracking in external tools.

## Does AI Content Strategy work with multilingual sites?

The module analyses whichever URLs your XML sitemap lists. If your sitemap includes translated pages, those translations are part of the analysis. The module itself has no language-specific logic; coverage depends on your sitemap configuration.

## Can I use AI Content Strategy from the command line?

Yes. The module ships with 23 Drush commands covering generation, reporting, card and idea management, category administration, CSV and YAML export, settings, and health checks. See the [Drush commands guide](drush.md) for the full reference. Run `drush acs:setup-ai` to also enable AI coding assistant support.

## Can I create my own recommendation categories?

Yes. Categories are configuration entities with a machine name, label, description, and AI instructions. Create them through the admin UI at `/admin/config/ai/content-strategy/categories/add` or via Drush with `drush acs:category:create`. Custom categories work alongside the built-in ones and can be exported through Drupal's configuration management. See the [categories guide](categories.md) for details.

## What happens if I regenerate recommendations?

Clicking **Generate recommendations** on the dashboard replaces all existing recommendations with a fresh analysis. If you want to keep your current work and add more, use the per-card **Generate more** button or the per-category **Add more recommendations** option instead.

## Does the module store recommendations in the database?

Yes. Recommendations are stored as state data, not as content entities. They persist across sessions and are shared among all users who have the "Access AI content strategy recommendations" permission.
