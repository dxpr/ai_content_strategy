<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush command for sitemap data.
 */
class SitemapCommands extends DrushCommands {

  /**
   * Constructs SitemapCommands.
   */
  public function __construct(
    protected readonly ContentAnalyzer $contentAnalyzer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ai_content_strategy.content_analyzer'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Gets sitemap URLs with content statistics as JSON.
   */
  #[CLI\Command(name: 'acs:sitemap', aliases: ['acs-s'])]
  #[CLI\Help(
    description: 'Gets sitemap URLs and content statistics as JSON.',
    synopsis: <<<'END'

## Important: Base URL

When running via Drush CLI, you must specify the site URL with -l:

```bash
drush acs:sitemap -l https://your-site.com
```

Without -l, Drupal may use the wrong base URL for fetching the sitemap.

## Output

Returns JSON with sitemap URLs and content statistics by type.

END
  )]
  #[CLI\Usage(name: 'drush acs:sitemap -l https://example.com', description: 'Get sitemap (specify site URL)')]
  public function getSitemap(): void {
    // Get sitemap URLs.
    $sitemapResult = $this->contentAnalyzer->getSitemapUrls();

    // Get content statistics by type.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');

    $contentTypes = [];
    $totalNodes = 0;

    foreach ($nodeTypeStorage->loadMultiple() as $type) {
      $count = $nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $type->id())
        ->condition('status', 1)
        ->count()
        ->execute();

      $contentTypes[$type->id()] = [
        'label' => $type->label(),
        'count' => (int) $count,
      ];
      $totalNodes += $count;
    }

    if ($sitemapResult['error']) {
      $this->output()->writeln(json_encode([
        'success' => FALSE,
        'error' => $sitemapResult['error'],
        'warning' => 'Sitemap unavailable. Content strategy recommendations may be inaccurate. Ensure sitemap is configured and use -l option to specify site URL.',
        'stats' => [
          'total_nodes' => $totalNodes,
          'content_types' => $contentTypes,
        ],
        'urls' => [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return;
    }

    $urlCount = count($sitemapResult['urls']);
    $warning = NULL;
    if ($urlCount < 5) {
      $warning = "Sitemap contains only $urlCount URLs. Content strategy recommendations may be incomplete. Ensure your sitemap is properly configured.";
    }

    $output = [
      'success' => TRUE,
      'stats' => [
        'total_urls' => $urlCount,
        'total_nodes' => $totalNodes,
        'content_types' => $contentTypes,
      ],
      'urls' => $sitemapResult['urls'],
    ];

    if ($warning) {
      $output['warning'] = $warning;
    }

    $this->output()->writeln(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

}
