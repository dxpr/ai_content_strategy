<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for sitemap operations.
 *
 * Provides AI-optimized sitemap URL listing with JSON output support.
 */
class SitemapCommands extends DrushCommands {

  /**
   * Constructs SitemapCommands.
   *
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $contentAnalyzer
   *   The content analyzer service.
   */
  public function __construct(
    protected readonly ContentAnalyzer $contentAnalyzer,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ai_content_strategy.content_analyzer'),
    );
  }

  /**
   * Lists all URLs from the site's sitemap.xml.
   */
  #[CLI\Command(name: 'acs:sitemap:urls', aliases: ['acs-su', 'acs:sitemap'])]
  #[CLI\Option(name: 'limit', description: 'Maximum number of URLs to return (default: all)')]
  #[CLI\Option(name: 'offset', description: 'Number of URLs to skip (for pagination)')]
  #[CLI\Help(
    description: 'Lists all URLs from sitemap.xml. Handles sitemap indexes with multiple sub-sitemaps.',
    synopsis: <<<'END'

## Purpose

Extracts all URLs from the site's sitemap.xml file. Designed for AI consumption
to understand the full scope of a site's published content.

## What This Command Does

1. Fetches `/sitemap.xml` from the site
2. Parses XML structure (handles both `<urlset>` and `<sitemapindex>`)
3. Recursively processes nested sitemaps (sitemap indexes)
4. Returns flat list of all discovered URLs
5. Handles circular references safely

## Sitemap Types Supported

### Direct URL List (`<urlset>`)
Standard sitemap containing page URLs directly:
```xml
<urlset>
  <url><loc>https://example.com/page1</loc></url>
  <url><loc>https://example.com/page2</loc></url>
</urlset>
```

### Sitemap Index (`<sitemapindex>`)
Index pointing to multiple sub-sitemaps:
```xml
<sitemapindex>
  <sitemap><loc>https://example.com/sitemap-pages.xml</loc></sitemap>
  <sitemap><loc>https://example.com/sitemap-posts.xml</loc></sitemap>
</sitemapindex>
```

## Options

### `--limit`
Maximum URLs to return. Useful for large sites or testing.
```bash
--limit=100
```

### `--offset`
Skip first N URLs (for pagination on large sitemaps).
```bash
--offset=100
```

## Examples

### Get All URLs
```bash
drush acs:sitemap:urls --format=json
```

### Get First 50 URLs
```bash
drush acs:sitemap:urls --limit=50 --format=json
```

### Paginate Through Large Sitemap
```bash
# First 100
drush acs:sitemap:urls --limit=100 --format=json

# Next 100
drush acs:sitemap:urls --limit=100 --offset=100 --format=json
```

### Count Total URLs
```bash
drush acs:sitemap:urls --format=json | jq '.[] | length'
```

### Filter URLs by Pattern
```bash
drush acs:sitemap:urls --format=json | jq '.[] | select(.url | contains("/blog/"))'
```

## Output Structure

```json
[
  {"url": "https://example.com/"},
  {"url": "https://example.com/about"},
  {"url": "https://example.com/blog/post-1"},
  {"url": "https://example.com/blog/post-2"}
]
```

## Use Cases for AI Assistants

1. **Site Audit**: Get complete URL inventory before content analysis
2. **Content Gap Analysis**: Compare sitemap URLs against content strategy
3. **SEO Analysis**: Identify URL patterns and structure
4. **Migration Planning**: Inventory all pages before migration
5. **Broken Link Detection**: Get URL list for validation

## Error Handling

### Sitemap Not Found
```json
{
  "success": false,
  "error": "Could not fetch sitemap.xml. Error: 404 Not Found"
}
```

### Invalid XML
```json
{
  "success": false,
  "error": "The sitemap.xml file could not be parsed. Please ensure it contains valid XML."
}
```

## Requirements

- Site must have a sitemap.xml at the root
- Sitemap must be publicly accessible (no authentication)
- Compatible with: simple_sitemap, xmlsitemap, or any standard sitemap

## Related Commands

- `acs:content-strategy:analyze` - Analyze content using sitemap URLs

END
  )]
  #[CLI\Usage(name: 'drush acs:sitemap:urls --format=json', description: 'Get all sitemap URLs as JSON')]
  #[CLI\Usage(name: 'drush acs:sitemap:urls --limit=50 --format=json', description: 'Get first 50 URLs')]
  #[CLI\Usage(name: 'drush acs:sitemap:urls --limit=100 --offset=100', description: 'Paginate (skip 100, get next 100)')]
  #[CLI\FieldLabels(labels: [
    'url' => 'URL',
  ])]
  #[CLI\DefaultFields(fields: ['url'])]
  public function listSitemapUrls(
    array $options = [
      'limit' => NULL,
      'offset' => 0,
    ],
  ): RowsOfFields {
    $result = $this->contentAnalyzer->getSitemapUrls();

    if ($result['error']) {
      throw new \RuntimeException($result['error']);
    }

    $urls = $result['urls'];

    // Apply offset.
    $offset = (int) $options['offset'];
    if ($offset > 0) {
      $urls = array_slice($urls, $offset);
    }

    // Apply limit.
    if ($options['limit'] !== NULL) {
      $limit = (int) $options['limit'];
      $urls = array_slice($urls, 0, $limit);
    }

    // Format for RowsOfFields output.
    $rows = array_map(fn($url) => ['url' => $url], $urls);

    return new RowsOfFields($rows);
  }

  /**
   * Gets sitemap statistics without listing all URLs.
   */
  #[CLI\Command(name: 'acs:sitemap:stats', aliases: ['acs-ss'])]
  #[CLI\Help(
    description: 'Gets sitemap statistics (URL count) without returning all URLs.',
    synopsis: <<<'END'

## Purpose

Quick check of sitemap size without downloading all URLs.
Useful for understanding site scale before full sitemap fetch.

## Output

```json
{
  "success": true,
  "url_count": 1250,
  "sitemap_url": "https://example.com/sitemap.xml"
}
```

## Examples

```bash
drush acs:sitemap:stats --format=json
```

## Use Cases

- Quick site size assessment
- Verify sitemap is accessible
- Plan pagination strategy for large sitemaps

END
  )]
  #[CLI\Usage(name: 'drush acs:sitemap:stats --format=json', description: 'Get sitemap URL count')]
  #[CLI\FieldLabels(labels: [
    'success' => 'Success',
    'url_count' => 'URL Count',
    'sitemap_url' => 'Sitemap URL',
    'error' => 'Error',
  ])]
  public function getSitemapStats(): array {
    $result = $this->contentAnalyzer->getSitemapUrls();

    if ($result['error']) {
      return [
        'success' => FALSE,
        'url_count' => 0,
        'sitemap_url' => '',
        'error' => $result['error'],
      ];
    }

    return [
      'success' => TRUE,
      'url_count' => count($result['urls']),
      'sitemap_url' => \Drupal::request()->getSchemeAndHttpHost() . '/sitemap.xml',
      'error' => NULL,
    ];
  }

}
