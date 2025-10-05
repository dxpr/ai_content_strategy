# AI Content Strategy :)

AI-powered content strategy recommendations based on your existing content and
EEAT framework.

## Features

- **Content Gap Analysis**: Identifies missing content opportunities
- **Authority Topics**: Suggests topics for domain expertise
- **Expertise Demonstrations**: Recommends content formats to showcase knowledge
- **Trust Signals**: Builds credibility with your audience
- **Priority-based Recommendations**: High/medium/low prioritization
- **Dynamic Idea Generation**: "Generate More Ideas" for each recommendation

## Requirements

- Drupal 10.2+
- [AI module](https://www.drupal.org/project/ai)
- Menu UI module (core)

## Installation

```bash
composer require drupal/ai_content_strategy
drush en ai_content_strategy
```

## Configuration

1. Configure AI provider at `/admin/config/ai/providers`
2. Set permissions at `/admin/people/permissions`
3. Access recommendations at `/admin/reports/ai/content-strategy`
4. Click "Generate Recommendations" to analyze your site
5. Use "Generate More Ideas" for specific content suggestions

## Usage

The module analyzes your site structure, navigation, and existing content to
recommend:

- Missing content types for comprehensive coverage
- Topics that establish your authority
- Content formats that demonstrate expertise
- Trust-building elements for credibility

Each recommendation includes priority level and specific content ideas. 
