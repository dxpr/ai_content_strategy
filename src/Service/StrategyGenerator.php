<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The category schema builder.
   *
   * @var \Drupal\ai_content_strategy\Service\CategorySchemaBuilder
   */
  protected $categorySchemaBuilder;

  /**
   * The category prompt builder.
   *
   * @var \Drupal\ai_content_strategy\Service\CategoryPromptBuilder
   */
  protected $categoryPromptBuilder;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\ai_content_strategy\Service\CategorySchemaBuilder $category_schema_builder
   *   The category schema builder service.
   * @param \Drupal\ai_content_strategy\Service\CategoryPromptBuilder $category_prompt_builder
   *   The category prompt builder service.
   */
  public function __construct(
    AiProviderPluginManager $ai_provider,
    ContentAnalyzer $content_analyzer,
    PromptJsonDecoderInterface $prompt_json_decoder,
    MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory,
    CategorySchemaBuilder $category_schema_builder,
    CategoryPromptBuilder $category_prompt_builder,
  ) {
    $this->aiProvider = $ai_provider;
    $this->contentAnalyzer = $content_analyzer;
    $this->promptJsonDecoder = $prompt_json_decoder;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->categorySchemaBuilder = $category_schema_builder;
    $this->categoryPromptBuilder = $category_prompt_builder;
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
      return $this->t('No chat provider available. Please install a compatible provider module first.');
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
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();

      // Check sitemap data.
      if (empty($sitemap_urls['urls'])) {
        throw new \RuntimeException('No URLs found in sitemap. Please ensure your sitemap is properly configured.');
      }

      // Get default provider and model.
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Set system role with more explicit JSON formatting instructions.
      $provider->setChatSystemRole('You are a content strategy expert focused on Google\'s EEAT framework. Your task is to analyze website structure and provide recommendations in a strict JSON format. Always return valid JSON that matches the provided schema exactly. Do not include any explanatory text or markdown formatting.');

      // Create chat input.
      $prompt = $this->buildEeatPrompt($site_structure, $sitemap_urls);
      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      // Get response.
      $response = $provider->chat($messages, $defaults['model_id'], ['content_strategy'])->getNormalized();

      // Decode JSON response.
      $decoded = $this->promptJsonDecoder->decode($response);
      if (is_array($decoded)) {
        return $decoded;
      }

      throw new \RuntimeException('Failed to parse AI response into valid JSON');
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

    // Get enabled categories.
    $categories = $this->categorySchemaBuilder->getEnabledCategories();

    if (empty($categories)) {
      throw new \RuntimeException('No enabled recommendation categories found. Please enable at least one category.');
    }

    // Build prompt using category prompt builder.
    return $this->categoryPromptBuilder->buildStrategyPrompt(
      $categories,
      $site_structure,
      $sitemap_urls
    );
  }

}
