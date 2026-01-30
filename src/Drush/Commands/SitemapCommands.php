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
  #[CLI\Help(description: 'Gets sitemap URLs and content statistics as JSON.')]
  #[CLI\Usage(name: 'drush acs:sitemap', description: 'Get sitemap with stats')]
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
        'stats' => [
          'total_nodes' => $totalNodes,
          'content_types' => $contentTypes,
        ],
        'urls' => [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return;
    }

    $this->output()->writeln(json_encode([
      'success' => TRUE,
      'stats' => [
        'total_urls' => count($sitemapResult['urls']),
        'total_nodes' => $totalNodes,
        'content_types' => $contentTypes,
      ],
      'urls' => $sitemapResult['urls'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

}
