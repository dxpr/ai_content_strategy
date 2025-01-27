<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\ai\PromptManagerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for analyzing content gaps between sites.
 */
class GapAnalyzer extends AnalyzerBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The content analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\ContentAnalyzer
   */
  protected $contentAnalyzer;

  /**
   * The AI prompt manager.
   *
   * @var \Drupal\ai\PromptManagerInterface
   */
  protected $promptManager;

  /**
   * Constructs a GapAnalyzer object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   * @param \Drupal\ai\PromptManagerInterface $prompt_manager
   *   The prompt manager service.
   */
  public function __construct(
    ClientInterface $http_client,
    ContentAnalyzer $content_analyzer,
    PromptManagerInterface $prompt_manager
  ) {
    $this->httpClient = $http_client;
    $this->contentAnalyzer = $content_analyzer;
    $this->promptManager = $prompt_manager;
  }

  /**
   * Analyzes content gap between current site and competitor.
   *
   * @param string $competitor_url
   *   The competitor's website URL.
   *
   * @return array
   *   Analysis results with prioritized gaps.
   */
  public function analyzeContentGap(string $competitor_url): array {
    // Get our sitemap URLs
    $our_urls = $this->contentAnalyzer->getSitemapUrls();
    
    // Get competitor's sitemap
    $competitor_urls = $this->fetchCompetitorSitemap($competitor_url);

    // Prepare data for AI analysis
    $variables = [
      'our_urls' => $this->formatUrls($our_urls),
      'competitor_urls' => $this->formatUrls($competitor_urls),
      'our_structure' => $this->contentAnalyzer->getSiteStructure(),
    ];

    // Get AI analysis
    $response = $this->promptManager->prompt('content_gap_analysis', $variables);

    return $this->parseAnalysisResponse($response);
  }

  /**
   * Fetches and parses competitor's sitemap.
   *
   * @param string $competitor_url
   *   The competitor's website URL.
   *
   * @return array
   *   Array of URLs from competitor's sitemap.
   */
  protected function fetchCompetitorSitemap(string $competitor_url): array {
    $sitemap_url = rtrim($competitor_url, '/') . '/sitemap.xml';
    
    try {
      $response = $this->httpClient->get($sitemap_url);
      $xml = simplexml_load_string($response->getBody()->getContents());
      
      $urls = [];
      foreach ($xml->url as $url) {
        $urls[] = (string) $url->loc;
      }
      
      return $urls;
    }
    catch (\Exception $e) {
      throw new \RuntimeException('Unable to fetch competitor sitemap: ' . $e->getMessage());
    }
  }

  /**
   * Parses the AI analysis response.
   */
  protected function parseAnalysisResponse(string $response): array {
    $results = [
      'gaps' => [
        'high' => [],
        'medium' => [],
        'low' => [],
      ],
    ];

    $current_priority = '';
    foreach (explode("\n", $response) as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      // Check for priority headers
      if (preg_match('/^(High|Medium|Low) Priority:$/i', $line, $matches)) {
        $current_priority = strtolower($matches[1]);
        continue;
      }

      // Add suggestions to current priority
      if ($current_priority && str_starts_with($line, '-')) {
        $suggestion = trim(substr($line, 1));
        $results['gaps'][$current_priority][] = $suggestion;
      }
    }

    return $results;
  }

} 