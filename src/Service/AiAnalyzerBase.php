<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for AI-powered analyzers.
 */
abstract class AiAnalyzerBase {
  use StringTranslationTrait;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The prompt JSON decoder.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected $promptJsonDecoder;

  /**
   * The content analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\ContentAnalyzer
   */
  protected $contentAnalyzer;

  /**
   * Constructs an AiAnalyzerBase object.
   *
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider
   *   The AI provider plugin manager.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $prompt_json_decoder
   *   The prompt JSON decoder.
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   */
  public function __construct(
    AiProviderPluginManager $ai_provider,
    PromptJsonDecoderInterface $prompt_json_decoder,
    ContentAnalyzer $content_analyzer,
  ) {
    $this->aiProvider = $ai_provider;
    $this->promptJsonDecoder = $prompt_json_decoder;
    $this->contentAnalyzer = $content_analyzer;
  }

  /**
   * Checks if the service is ready to analyze.
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

  /**
   * Processes an AI response and extracts JSON data.
   *
   * @param mixed $response
   *   The AI response to process.
   *
   * @return array
   *   The decoded JSON data.
   *
   * @throws \RuntimeException
   *   If the response cannot be parsed into valid JSON.
   */
  protected function processAiResponse($response): array {
    // Decode JSON response.
    $decoded = $this->promptJsonDecoder->decode($response);

    if (is_array($decoded)) {
      return $decoded;
    }

    // If decoding failed, try to extract JSON from the response text.
    $text = $response->getText();
    if (preg_match('/\{(?:[^{}]|(?R))*\}/', $text, $matches)) {
      $json = json_decode($matches[0], TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
      }
    }

    throw new \RuntimeException('Failed to parse AI response into valid JSON');
  }

}
