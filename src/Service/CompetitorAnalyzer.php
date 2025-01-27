<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\ai\AiProviderPluginManagerInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Service for analyzing competitor websites.
 */
class CompetitorAnalyzer {
  use StringTranslationTrait;

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
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManagerInterface
   */
  protected $aiProvider;

  /**
   * The prompt JSON decoder.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected $promptJsonDecoder;

  /**
   * Constructs a CompetitorAnalyzer object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   * @param \Drupal\ai\AiProviderPluginManagerInterface $ai_provider
   *   The AI provider plugin manager.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $prompt_json_decoder
   *   The prompt JSON decoder.
   */
  public function __construct(
    ClientInterface $http_client,
    ContentAnalyzer $content_analyzer,
    AiProviderPluginManagerInterface $ai_provider,
    PromptJsonDecoderInterface $prompt_json_decoder
  ) {
    $this->httpClient = $http_client;
    $this->contentAnalyzer = $content_analyzer;
    $this->aiProvider = $ai_provider;
    $this->promptJsonDecoder = $prompt_json_decoder;
  }

  /**
   * Checks if all required components are available and configured.
   *
   * @param string $competitor_url
   *   The competitor's website URL.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   An error message if there are issues, NULL if everything is OK.
   */
  public function checkHealth(string $competitor_url): ?TranslatableMarkup {
    try {
      // Check if any chat providers are installed
      $providers = $this->aiProvider->getProvidersForOperationType('chat');
      if (empty($providers)) {
        return $this->t('No chat providers are installed.');
      }

      // Check if a configured and usable chat provider exists
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      if (!$defaults) {
        return $this->t('No default chat provider is configured.');
      }

      // Create provider instance and check if it's usable
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);
      if (!$provider) {
        return $this->t('Failed to create provider instance.');
      }

      // Validate competitor URL format
      if (!filter_var($competitor_url, FILTER_VALIDATE_URL)) {
        return $this->t('Invalid competitor URL format.');
      }

      // Check if competitor sitemap is accessible
      $sitemap_url = rtrim($competitor_url, '/') . '/sitemap.xml';
      try {
        $response = $this->httpClient->request('GET', $sitemap_url, ['timeout' => 10]);
        if ($response->getStatusCode() !== 200) {
          return $this->t('Competitor sitemap.xml is not accessible (HTTP @code).', [
            '@code' => $response->getStatusCode(),
          ]);
        }
        
        $content_type = $response->getHeaderLine('Content-Type');
        if (!str_contains($content_type, 'xml')) {
          return $this->t('Competitor sitemap.xml is not a valid XML file (Content-Type: @type).', [
            '@type' => $content_type,
          ]);
        }
      }
      catch (RequestException $e) {
        return $this->t('Failed to access competitor sitemap.xml: @error', [
          '@error' => $e->getMessage(),
        ]);
      }

      // Check our own sitemap
      $our_sitemap = $this->contentAnalyzer->getSitemapUrls();
      if (empty($our_sitemap['urls'])) {
        return $this->t('Our sitemap.xml is empty or inaccessible.');
      }

      return NULL;
    }
    catch (\Exception $e) {
      return $this->t('Health check failed: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Gets the competitor's sitemap URLs.
   *
   * @param string $competitor_url
   *   The competitor's website URL.
   *
   * @return array
   *   Array of URLs from the competitor's sitemap.
   *
   * @throws \RuntimeException
   *   If the sitemap cannot be accessed or parsed.
   */
  public function getCompetitorSitemap(string $competitor_url): array {
    try {
      $sitemap_url = rtrim($competitor_url, '/') . '/sitemap.xml';
      $response = $this->httpClient->request('GET', $sitemap_url);
      
      if ($response->getStatusCode() !== 200) {
        throw new \RuntimeException('Failed to access sitemap.xml');
      }

      $xml = simplexml_load_string($response->getBody()->getContents());
      if (!$xml) {
        throw new \RuntimeException('Invalid sitemap XML');
      }

      $urls = [];
      foreach ($xml->url as $url) {
        $urls[] = (string) $url->loc;
      }

      return $urls;
    }
    catch (\Exception $e) {
      throw new \RuntimeException('Failed to fetch competitor sitemap: ' . $e->getMessage());
    }
  }

  /**
   * Generates a competitive analysis report.
   *
   * @param string $competitor_url
   *   The competitor's website URL.
   *
   * @return array
   *   The analysis results.
   *
   * @throws \RuntimeException
   *   If the analysis fails.
   */
  public function generateAnalysis(string $competitor_url): array {
    // Check health first
    if ($error = $this->checkHealth($competitor_url)) {
      throw new \RuntimeException($error->render());
    }

    try {
      // Get site data
      $our_structure = $this->contentAnalyzer->getSiteStructure();
      $our_urls = $this->contentAnalyzer->getSitemapUrls()['urls'];
      $competitor_urls = $this->getCompetitorSitemap($competitor_url);

      // Get default provider and model
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Create the analysis prompt
      $prompt = <<<EOT
Analyze and compare our website structure with a competitor's website. Your response must be a valid JSON object following the exact schema provided.

Our Website Structure:
Homepage: {$our_structure['homepage']['title']}
Primary Navigation:
{$this->formatMenuItems($our_structure['primary_menu'])}

Our URLs:
{$this->formatUrls($our_urls)}

Competitor URLs:
{$this->formatUrls($competitor_urls)}

Analysis Instructions:
1. Compare content organization and URL structures
2. Identify gaps in our content compared to competitor
3. Find unique opportunities based on competitor's approach
4. Analyze content type distribution and coverage
5. Evaluate navigation and site structure differences

The response must be a valid JSON object with these exact keys:
- content_gaps: array of objects with title, description, priority, and action_items fields
- competitive_advantages: array of objects with area, description, and leverage_ideas fields
- improvement_opportunities: array of objects with category, findings, and recommendations fields
- structural_insights: array of objects with aspect, comparison, and optimization_steps fields

Schema example:
{
  "content_gaps": [
    {
      "title": "Missing Content Category",
      "description": "Specific content type present in competitor site but missing in ours",
      "priority": "high|medium|low",
      "action_items": [
        "Specific action to address the gap",
        "Another specific action step"
      ]
    }
  ],
  "competitive_advantages": [
    {
      "area": "Advantage Area",
      "description": "How our site excels in this area",
      "leverage_ideas": [
        "Specific way to leverage this advantage",
        "Another leverage opportunity"
      ]
    }
  ],
  "improvement_opportunities": [
    {
      "category": "Improvement Category",
      "findings": "Specific findings from comparison",
      "recommendations": [
        "Specific recommendation based on competitor analysis",
        "Another specific recommendation"
      ]
    }
  ],
  "structural_insights": [
    {
      "aspect": "Site Structure Aspect",
      "comparison": "Direct comparison with competitor approach",
      "optimization_steps": [
        "Specific step to optimize structure",
        "Another optimization step"
      ]
    }
  ]
}

Remember: Return ONLY the JSON object, no other text. The response must be parseable by PHP's json_decode().
EOT;

      $messages = new ChatInput([
        new ChatMessage('system', 'You are a web content strategist performing competitive analysis.'),
        new ChatMessage('user', $prompt),
      ]);

      // Get response
      $response = $provider->chat($messages, $defaults['model_id'], ['competitor_analysis'])->getNormalized();
      
      // Decode JSON response
      $decoded = $this->promptJsonDecoder->decode($response);
      
      if (!is_array($decoded)) {
        throw new \RuntimeException('Failed to decode AI response as JSON');
      }

      return $decoded;
    }
    catch (\Exception $e) {
      throw new \RuntimeException('Analysis failed: ' . $e->getMessage());
    }
  }

  /**
   * Formats menu items for the prompt.
   */
  protected function formatMenuItems(array $menu_items): string {
    $output = [];
    foreach ($menu_items as $item) {
      $output[] = "- {$item['title']}" . (!empty($item['url']) ? " ({$item['url']})" : "");
    }
    return implode("\n", $output);
  }

  /**
   * Formats URLs for the prompt.
   */
  protected function formatUrls(array $urls): string {
    $output = [];
    foreach ($urls as $url) {
      $output[] = "- {$url}";
    }
    return implode("\n", $output);
  }

} 