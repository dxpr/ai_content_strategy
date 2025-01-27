<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Service for generating content strategy recommendations.
 */
class StrategyGenerator extends AnalyzerBase {
  use StringTranslationTrait;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The content analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\ContentAnalyzer
   */
  protected $contentAnalyzer;

  /**
   * The prompt JSON decoder.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected $promptJsonDecoder;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a StrategyGenerator object.
   *
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider
   *   The AI provider plugin manager.
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $prompt_json_decoder
   *   The prompt JSON decoder.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    AiProviderPluginManager $ai_provider,
    ContentAnalyzer $content_analyzer,
    PromptJsonDecoderInterface $prompt_json_decoder,
    MessengerInterface $messenger
  ) {
    $this->aiProvider = $ai_provider;
    $this->contentAnalyzer = $content_analyzer;
    $this->promptJsonDecoder = $prompt_json_decoder;
    $this->messenger = $messenger;
  }

  /**
   * Checks if the service is ready to generate recommendations.
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
   * Generates content strategy recommendations.
   *
   * @return array
   *   Array of content recommendations.
   *
   * @throws \RuntimeException
   *   When service is not ready or encounters an error.
   */
  public function generateRecommendations(): array {
    // Run health checks first.
    if ($error = $this->checkHealth()) {
      throw new \RuntimeException($error->render());
    }

    try {
      // Get site data.
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $this->messenger->addStatus($this->t('Site structure: @structure', [
        '@structure' => print_r($site_structure, TRUE),
      ]));

      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();
      $this->messenger->addStatus($this->t('Sitemap URLs: @urls', [
        '@urls' => print_r($sitemap_urls, TRUE),
      ]));

      // Check sitemap data.
      if (empty($sitemap_urls['urls'])) {
        throw new \RuntimeException($this->t('No URLs found in sitemap. Please ensure your sitemap is properly configured.')->render());
      }

      // Get default provider and model.
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);
      
      $this->messenger->addStatus($this->t('Using provider: @provider with model: @model', [
        '@provider' => $defaults['provider_id'],
        '@model' => $defaults['model_id'],
      ]));

      // Set system role with more explicit JSON formatting instructions.
      $provider->setChatSystemRole('You are a content strategy expert focused on Google\'s EEAT framework. Your task is to analyze website structure and provide recommendations in a strict JSON format. Always return valid JSON that matches the provided schema exactly. Do not include any explanatory text or markdown formatting.');

      // Create chat input.
      $prompt = $this->buildEeatPrompt($site_structure, $sitemap_urls);
      $this->messenger->addStatus($this->t('Generated prompt: @prompt', [
        '@prompt' => $prompt,
      ]));

      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      // Get response.
      $response = $provider->chat($messages, $defaults['model_id'], ['content_strategy'])->getNormalized();
      $this->messenger->addStatus($this->t('Raw AI response: @response', [
        '@response' => $response->getText(),
      ]));
      
      // Decode JSON response.
      $decoded = $this->promptJsonDecoder->decode($response);
      $this->messenger->addStatus($this->t('Decoded response: @decoded', [
        '@decoded' => print_r(is_array($decoded) ? $decoded : 'Decoding failed', TRUE),
      ]));

      if (is_array($decoded)) {
        return $decoded;
      }
      
      // If decoding failed, try to extract JSON from the response text
      $text = $response->getText();
      if (preg_match('/\{(?:[^{}]|(?R))*\}/', $text, $matches)) {
        $json = json_decode($matches[0], TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
          $this->messenger->addStatus($this->t('Extracted JSON: @json', [
            '@json' => print_r($json, TRUE),
          ]));
          return $json;
        }
      }
      
      throw new \RuntimeException($this->t('Failed to parse AI response into valid JSON')->render());
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Error: @error', [
        '@error' => $e->getMessage(),
      ]));
      throw $e;
    }
  }

  /**
   * Builds the EEAT-focused prompt for content recommendations.
   *
   * @param array $site_structure
   *   The site structure data.
   * @param array $sitemap_urls
   *   The sitemap URLs array with 'urls' and optional 'error' keys.
   *
   * @return string
   *   The formatted prompt.
   */
  protected function buildEeatPrompt(array $site_structure, array $sitemap_urls): string {
    // Handle potential sitemap errors.
    if (!empty($sitemap_urls['error'])) {
      throw new \RuntimeException('Could not analyze sitemap: ' . $sitemap_urls['error']);
    }

    $schema_example = <<<JSON
{
  "content_gaps": [
    {
      "title": "Content Type Gap",
      "description": "Missing content type identified from site structure",
      "priority": "high",
      "content_ideas": [
        "Example content idea 1 based on site context",
        "Example content idea 2 based on site context",
        "Example content idea 3 based on site context",
        "Example content idea 4 based on site context",
        "Example content idea 5 based on site context"
      ]
    }
  ],
  "authority_topics": [
    {
      "topic": "Domain-Specific Topic",
      "rationale": "Topic relevance based on existing content",
      "content_ideas": [
        "Example topic content 1 based on site focus",
        "Example topic content 2 based on site focus",
        "Example topic content 3 based on site focus",
        "Example topic content 4 based on site focus",
        "Example topic content 5 based on site focus"
      ]
    }
  ],
  "expertise_demonstrations": [
    {
      "content_type": "Expertise Format",
      "description": "Content format aligned with site purpose",
      "content_ideas": [
        "Example expertise content 1 based on site type",
        "Example expertise content 2 based on site type",
        "Example expertise content 3 based on site type",
        "Example expertise content 4 based on site type",
        "Example expertise content 5 based on site type"
      ]
    }
  ],
  "trust_signals": [
    {
      "signal": "Trust Element",
      "implementation": "Implementation approach based on site context",
      "content_ideas": [
        "Example trust content 1 based on site needs",
        "Example trust content 2 based on site needs",
        "Example trust content 3 based on site needs",
        "Example trust content 4 based on site needs",
        "Example trust content 5 based on site needs"
      ]
    }
  ]
}
JSON;

    $prompt = <<<EOT
Analyze the provided website structure and generate content strategy recommendations. Your response must be a valid JSON object following the exact schema provided. Base all recommendations entirely on analyzing the actual site content and structure.

Analysis Instructions:
1. First analyze the site structure, URLs, and navigation to understand:
   - The site's primary purpose and domain
   - Existing content types and formats
   - Current content organization
   - Target audience indicators
   - Industry/sector context

2. Then identify:
   - Missing content types compared to similar sites in the domain
   - Underrepresented topics within the site's focus area
   - Opportunities to demonstrate expertise in the site's domain
   - Trust-building elements appropriate for the site type

Rules for Recommendations:
1. ALL recommendations must be directly inferred from the site's actual content and structure
2. NO generic suggestions - each recommendation should clearly relate to the site's specific domain and purpose
3. Content ideas must be specific and actionable
4. Prioritize recommendations based on:
   - Alignment with existing content strategy
   - Gaps in current content coverage
   - Potential impact for the site's purpose
5. Generate 5-10 highly specific content ideas for each recommendation
6. Avoid assumptions about industry or purpose - base everything on the provided site data

The response must be a valid JSON object with these exact keys:
- content_gaps: array of objects with title, description, priority, and content_ideas fields
- authority_topics: array of objects with topic, rationale, and content_ideas fields
- expertise_demonstrations: array of objects with content_type, description, and content_ideas fields
- trust_signals: array of objects with signal, implementation, and content_ideas fields

Schema example:
{$schema_example}

Website to analyze:
Homepage: {$site_structure['homepage']['title']}

Primary Navigation:
{$this->formatMenuItems($site_structure['primary_menu'])}

Existing Content URLs:
{$this->formatUrls($sitemap_urls['urls'])}

Remember: Return ONLY the JSON object, no other text. The response must be parseable by PHP's json_decode().
EOT;

    return $prompt;
  }

} 