<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\ai\AiProviderPluginManager;
use GuzzleHttp\ClientInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Service for analyzing content gaps between sites.
 */
class GapAnalyzer extends AiAnalyzerBase {
  use StringTranslationTrait;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a GapAnalyzer object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider
   *   The AI provider plugin manager.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $prompt_json_decoder
   *   The prompt JSON decoder.
   */
  public function __construct(
    ClientInterface $http_client,
    ContentAnalyzer $content_analyzer,
    AiProviderPluginManager $ai_provider,
    PromptJsonDecoderInterface $prompt_json_decoder
  ) {
    parent::__construct($ai_provider, $prompt_json_decoder, $content_analyzer);
    $this->httpClient = $http_client;
  }

  /**
   * Checks if the service is ready to analyze gaps.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   Error message if service is not ready, NULL if ready.
   */
  public function checkHealth(): ?TranslatableMarkup {
    // Check if we have any chat providers installed.
    if (!$this->aiProvider->hasProvidersForOperationType('chat', FALSE)) {
      return $this->t('No chat provider available. Please install a chat provider module first.');
    }

    // Check if we have a configured and usable chat provider.
    if (!$this->aiProvider->hasProvidersForOperationType('chat', TRUE)) {
      return $this->t('Chat provider is not properly configured. Please configure it in the AI settings.');
    }

    // Check if we have a default provider and model configured.
    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      return $this->t('No default chat model configured. Please configure one in the AI settings.');
    }

    // Create provider instance and check if it's usable.
    try {
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);
      if (!$provider->isUsable('chat')) {
        return $this->t('The configured chat provider @provider is not usable. Please check its configuration.', [
          '@provider' => $defaults['provider_id'],
        ]);
      }

      // Check if the configured model exists.
      $available_models = $provider->getConfiguredModels('chat');
      if (!isset($available_models[$defaults['model_id']])) {
        return $this->t('The configured model @model is not available for provider @provider.', [
          '@model' => $defaults['model_id'],
          '@provider' => $defaults['provider_id'],
        ]);
      }
    }
    catch (\Exception $e) {
      return $this->t('Error checking provider configuration: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
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
    // Run health checks first.
    if ($error = $this->checkHealth()) {
      throw new \RuntimeException($error->render());
    }

    try {
      // Get our sitemap URLs
      $our_urls = $this->contentAnalyzer->getSitemapUrls();
      if (empty($our_urls['urls'])) {
        throw new \RuntimeException($this->t('No URLs found in our sitemap')->render());
      }
      
      // Get competitor's sitemap
      $competitor_urls = $this->fetchCompetitorSitemap($competitor_url);

      // Get default provider and model
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Prepare data for AI analysis
      $variables = [
        'our_urls' => $this->formatUrls($our_urls['urls']),
        'competitor_urls' => $this->formatUrls($competitor_urls),
        'our_structure' => $this->contentAnalyzer->getSiteStructure(),
      ];

      // Set system role
      $provider->setChatSystemRole('You are a content strategist analyzing content gaps between websites. Your task is to analyze website structures and provide recommendations in a strict JSON format. Always return valid JSON that matches the provided schema exactly. Do not include any explanatory text or markdown formatting.');

      // Create chat messages
      $messages = new ChatInput([
        new ChatMessage('user', $this->createAnalysisPrompt($variables)),
      ]);

      // Get response
      $response = $provider->chat($messages, $defaults['model_id'], ['gap_analysis'])->getNormalized();
      
      // Process and return the response
      return $this->processAiResponse($response);
    }
    catch (\Exception $e) {
      throw new \RuntimeException($this->t('Analysis failed: @error', ['@error' => $e->getMessage()])->render());
    }
  }

  /**
   * Creates the analysis prompt with the given variables.
   */
  protected function createAnalysisPrompt(array $variables): string {
    $schema_example = <<<JSON
{
  "content_gaps": [
    {
      "title": "Missing Content Type",
      "description": "Content type present in competitor site but missing in ours",
      "priority": "high",
      "content_ideas": [
        "Specific content idea 1 based on competitor analysis",
        "Specific content idea 2 based on competitor analysis",
        "Specific content idea 3 based on competitor analysis",
        "Specific content idea 4 based on competitor analysis",
        "Specific content idea 5 based on competitor analysis"
      ]
    }
  ],
  "authority_topics": [
    {
      "topic": "Competitor Authority Topic",
      "rationale": "Why competitor excels in this topic area",
      "content_ideas": [
        "Topic content idea 1 based on competitor coverage",
        "Topic content idea 2 based on competitor coverage",
        "Topic content idea 3 based on competitor coverage",
        "Topic content idea 4 based on competitor coverage",
        "Topic content idea 5 based on competitor coverage"
      ]
    }
  ],
  "expertise_demonstrations": [
    {
      "content_type": "Competitor Expertise Format",
      "description": "How competitor demonstrates expertise",
      "content_ideas": [
        "Expertise content idea 1 based on competitor approach",
        "Expertise content idea 2 based on competitor approach",
        "Expertise content idea 3 based on competitor approach",
        "Expertise content idea 4 based on competitor approach",
        "Expertise content idea 5 based on competitor approach"
      ]
    }
  ],
  "trust_signals": [
    {
      "signal": "Competitor Trust Element",
      "implementation": "How competitor builds trust",
      "content_ideas": [
        "Trust content idea 1 based on competitor methods",
        "Trust content idea 2 based on competitor methods",
        "Trust content idea 3 based on competitor methods",
        "Trust content idea 4 based on competitor methods",
        "Trust content idea 5 based on competitor methods"
      ]
    }
  ]
}
JSON;

    return <<<EOT
Analyze the content gap between our website and a competitor's website. Your response must be a valid JSON object following the exact schema provided.

Our Website Structure:
Homepage: {$variables['our_structure']['homepage']['title']}
Homepage Content: {$variables['our_structure']['homepage']['content']}
Primary Navigation:
{$this->formatMenuItems($variables['our_structure']['primary_menu'])}

Our URLs:
{$variables['our_urls']}

Competitor URLs:
{$variables['competitor_urls']}

Analysis Instructions:
1. Compare content organization and identify gaps
2. Analyze competitor's authority topics and expertise demonstrations
3. Evaluate trust-building elements and implementation
4. For each category, provide specific actionable recommendations
5. Include 5 detailed content ideas for each recommendation

The response must be a valid JSON object with these exact keys:
- content_gaps: array of objects with title, description, priority, and content_ideas
- authority_topics: array of objects with topic, rationale, and content_ideas
- expertise_demonstrations: array of objects with content_type, description, and content_ideas
- trust_signals: array of objects with signal, implementation, and content_ideas

Each content_ideas array should contain 5 specific, actionable ideas based on competitor analysis.

Schema example:
{$schema_example}

Remember: Return ONLY the JSON object, no other text. The response must be parseable by PHP's json_decode().
EOT;
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
    // Ensure URL has protocol
    if (!preg_match('~^(?:f|ht)tps?://~i', $competitor_url)) {
      $competitor_url = 'https://' . $competitor_url;
    }
    
    $sitemap_url = rtrim($competitor_url, '/') . '/sitemap.xml';
    
    try {
      $response = $this->httpClient->get($sitemap_url, [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify' => FALSE,
        'headers' => [
          'User-Agent' => 'Drupal Content Analyzer',
        ],
      ]);
      
      $content = $response->getBody()->getContents();
      if (empty($content)) {
        throw new \RuntimeException('Empty sitemap response');
      }
      
      $xml = @simplexml_load_string($content);
      if ($xml === FALSE) {
        throw new \RuntimeException('Invalid XML in sitemap');
      }
      
      $urls = [];
      foreach ($xml->url as $url) {
        $urls[] = (string) $url->loc;
      }
      
      if (empty($urls)) {
        throw new \RuntimeException('No URLs found in sitemap');
      }
      
      return $urls;
    }
    catch (\Exception $e) {
      // Try HTTP if HTTPS fails
      if (strpos($competitor_url, 'https://') === 0) {
        $competitor_url = 'http://' . substr($competitor_url, 8);
        return $this->fetchCompetitorSitemap($competitor_url);
      }
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