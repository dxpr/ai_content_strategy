<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for sitemap operations.
 */
class SitemapCommands extends DrushCommands {

  /**
   * Constructs SitemapCommands.
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
   * Lists all URLs from the site's sitemap.xml as JSON.
   */
  #[CLI\Command(name: 'acs:sitemap:urls', aliases: ['acs-su'])]
  #[CLI\Help(description: 'Lists all URLs from sitemap.xml as JSON.')]
  #[CLI\Usage(name: 'drush acs:sitemap:urls', description: 'Get all sitemap URLs')]
  public function listSitemapUrls(): void {
    $result = $this->contentAnalyzer->getSitemapUrls();

    if ($result['error']) {
      $this->output()->writeln(json_encode([
        'success' => FALSE,
        'error' => $result['error'],
        'urls' => [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return;
    }

    $this->output()->writeln(json_encode([
      'success' => TRUE,
      'url_count' => count($result['urls']),
      'urls' => $result['urls'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

}
