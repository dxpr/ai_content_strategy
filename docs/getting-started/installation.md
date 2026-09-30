# Installation

## Requirements

- Drupal 10.2 or later (including Drupal 11)
- [AI module](https://www.drupal.org/project/ai)
- An XML sitemap at `/sitemap.xml` (provided by [Simple XML Sitemap](https://www.drupal.org/project/simple_sitemap) or similar)
- A configured AI provider (for example, [DXPR AI Provider](https://www.drupal.org/project/ai_provider_dxpr) for a free tier, or bring your own API keys)

### Optional

- [Menu UI](https://www.drupal.org/docs/core-modules-and-themes/core-modules/menu-ui-module) (core): when enabled, the module also analyses your navigation structure for more contextual recommendations

## Install the module

```bash
composer require drupal/ai_content_strategy
drush en ai_content_strategy
```

## Configure an AI provider

The module needs a working AI provider for the `chat` operation. If you do not already have one configured:

1. Install [DXPR AI Provider](https://www.drupal.org/project/ai_provider_dxpr) for a free tier (10,000 monthly credits)
2. Or configure any other provider supported by the [AI module](https://www.drupal.org/project/ai) (OpenAI, Anthropic, Google, and others)

Visit `/admin/config/ai/providers` to verify that at least one provider is available for the `chat` operation type.

## Verify

Navigate to `/admin/reports/ai/content-strategy` and click **Generate recommendations**. The module fetches your sitemap, analyses your content, and returns categorised recommendations. If the AI provider is not configured, the page displays an error with a link to the provider settings.
