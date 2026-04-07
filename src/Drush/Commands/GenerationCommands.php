<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_content_strategy\Entity\RecommendationCategory;
use Drupal\ai_content_strategy\Service\CategoryPromptBuilder;
use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drupal\ai_content_strategy\Service\StrategyGenerator;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;

/**
 * Drush commands for generating AI content strategy recommendations.
 */
class GenerationCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly StrategyGenerator $strategyGenerator,
    protected readonly RecommendationStorageService $storage,
    protected readonly ContentAnalyzer $contentAnalyzer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AiProviderPluginManager $aiProvider,
    protected readonly PromptJsonDecoderInterface $promptJsonDecoder,
    protected readonly CategoryPromptBuilder $categoryPromptBuilder,
    protected readonly UuidInterface $uuid,
  ) {
    parent::__construct();
  }

  /**
   * Checks AI provider health and configuration.
   */
  #[CLI\Command(name: 'acs:health', aliases: ['acs-h'])]
  #[CLI\Help(description: '[YAML] Check AI provider configuration. Returns success/error with provider details.')]
  #[CLI\Usage(name: 'drush acs:health', description: 'Check if AI is configured')]
  public function health(): string {
    $this->switchToAdmin();

    $error = $this->strategyGenerator->checkHealth();
    if ($error) {
      return $this->error('AI provider not ready.', [(string) $error]);
    }

    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');

    return $this->success('AI provider is configured and ready.', [
      'provider' => $defaults['provider_id'] ?? 'unknown',
      'model' => $defaults['model_id'] ?? 'unknown',
    ]);
  }

  /**
   * Generates recommendations for all or one category.
   */
  #[CLI\Command(name: 'acs:generate', aliases: ['acs-g'])]
  #[CLI\Option(name: 'category', description: 'Generate only for this category ID')]
  #[CLI\Help(description: '[YAML] Generate AI content strategy recommendations. Requires -l for base URL.')]
  #[CLI\Usage(name: 'drush acs:generate -l https://example.com', description: 'Generate all recommendations')]
  #[CLI\Usage(name: 'drush acs:generate --category=content_gaps -l https://example.com', description: 'Generate for one category')]
  public function generate(array $options = ['category' => '']): string {
    $this->switchToAdmin();

    // If a specific category is requested, regenerate that category.
    if (!empty($options['category'])) {
      return $this->regenerateCategory($options['category']);
    }

    try {
      // Get sitemap data for metadata.
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();
      $pages_count = count($sitemap_urls['urls'] ?? []);

      // Generate recommendations.
      $recommendations = $this->strategyGenerator->generateRecommendations();

      // Ensure all items and ideas have UUIDs.
      $recommendations = $this->storage->ensureUuids($recommendations);
      $recommendations = $this->storage->ensureIdeaUuids($recommendations);

      // Store results.
      $timestamp = $this->storage->saveRecommendations($recommendations, $pages_count);

      // Count results.
      $total_cards = 0;
      foreach ($recommendations as $cards) {
        if (is_array($cards)) {
          $total_cards += count($cards);
        }
      }

      return $this->success('Recommendations generated.', [
        'generated_at' => date('c', $timestamp),
        'pages_analyzed' => $pages_count,
        'total_cards' => $total_cards,
        'categories' => array_keys($recommendations),
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Generation failed.', [$e->getMessage()]);
    }
  }

  /**
   * Generates more ideas for a specific card.
   */
  #[CLI\Command(name: 'acs:generate:more', aliases: ['acs-gm', 'acs:g:more'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Help(description: '[YAML] Generate 5 more content ideas for a specific card.')]
  #[CLI\Usage(name: 'drush acs:generate:more content_gaps UUID -l https://example.com', description: 'Generate more ideas for a card')]
  public function generateMore(string $section, string $uuid): string {
    $this->switchToAdmin();

    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    $title = $card['title'] ?? '';

    try {
      // Get site context.
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();

      // Get default provider and model.
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Build prompt.
      $prompt = sprintf(
        "Based on this website's content (homepage: %s, %d pages in sitemap), " .
        "generate 5 additional, unique content ideas for the topic \"%s\" in the " .
        "\"%s\" category. Return ONLY a JSON array of strings.",
        $site_structure['homepage']['title'] ?? 'Unknown',
        count($sitemap_urls['urls'] ?? []),
        $title,
        $section,
      );

      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      $response = $provider->chat($messages, $defaults['model_id'], ['content_strategy']);
      $text = $response->getNormalized()->getText();

      // Extract JSON array from response.
      $start = strpos($text, '[');
      if ($start === FALSE) {
        return $this->error('AI returned invalid response format.');
      }
      $json_candidate = substr($text, $start);
      $ideas = Json::decode($json_candidate);
      if (!is_array($ideas)) {
        return $this->error('AI returned invalid JSON.');
      }

      // Normalize and append ideas.
      $normalized_ideas = $this->storage->normalizeIdeasWithUuids($ideas);
      $this->storage->appendIdeasByUuid($section, $uuid, $normalized_ideas);

      return $this->success(sprintf('Generated %d new ideas for "%s".', count($normalized_ideas), $title), [
        'card_uuid' => $uuid,
        'new_ideas' => array_map(fn($idea) => ['uuid' => $idea['uuid'], 'text' => $idea['text']], $normalized_ideas),
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Failed to generate more ideas.', [$e->getMessage()]);
    }
  }

  /**
   * Adds more recommendation cards to a category.
   */
  #[CLI\Command(name: 'acs:generate:add', aliases: ['acs-ga', 'acs:g:add'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Help(description: '[YAML] Add more recommendation cards to a category.')]
  #[CLI\Usage(name: 'drush acs:generate:add authority_topics -l https://example.com', description: 'Add more cards to a category')]
  public function generateAdd(string $section): string {
    $this->switchToAdmin();

    return $this->generateForCategory($section);
  }

  /**
   * Regenerates recommendations for a single category, replacing existing.
   *
   * Unlike generateForCategory() (used by acs:generate:add), this replaces
   * the category's cards instead of appending.
   */
  protected function regenerateCategory(string $section): string {
    $category_storage = $this->entityTypeManager->getStorage('recommendation_category');
    $category = $category_storage->load($section);

    if (!$category instanceof RecommendationCategory) {
      return $this->notFound('Category', $section, 'acs:category:list');
    }

    try {
      $recommendations = $this->strategyGenerator->generateRecommendations();

      if (!isset($recommendations[$section])) {
        return $this->error(
          sprintf('No recommendations generated for "%s".', $section)
        );
      }

      // Extract only the requested category and ensure UUIDs.
      $category_cards = [$section => $recommendations[$section]];
      $category_cards = $this->storage->ensureUuids($category_cards);
      $category_cards = $this->storage->ensureIdeaUuids($category_cards);

      // Replace this category in stored data.
      $this->storage->replaceSection($section, $category_cards[$section]);

      $count = count($category_cards[$section]);
      return $this->success(
        sprintf('Regenerated %d cards for "%s".', $count, $category->label()),
        [
          'category' => $section,
          'total_cards' => $count,
        ]
      );
    }
    catch (\Exception $e) {
      return $this->error('Generation failed.', [$e->getMessage()]);
    }
  }

  /**
   * Generates additional recommendations for a category (appends).
   */
  protected function generateForCategory(string $section): string {
    // Load the category entity.
    $category_storage = $this->entityTypeManager->getStorage('recommendation_category');
    $category = $category_storage->load($section);

    if (!$category instanceof RecommendationCategory) {
      return $this->notFound('Category', $section, 'acs:category:list');
    }

    try {
      // Get site data for context.
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();

      // Get existing recommendations for context.
      $existing_recommendations = '';
      $stored = $this->storage->getStoredData();
      if ($stored && isset($stored['data'][$section])) {
        $existing = $stored['data'][$section];
        $formatted = [];
        foreach ($existing as $item) {
          $title = $item['title'] ?? '';
          $desc = $item['description'] ?? '';
          if ($title) {
            $formatted[] = "- {$title}: {$desc}";
          }
        }
        $existing_recommendations = implode("\n", $formatted);
      }

      // Build prompts.
      $prompts = $this->categoryPromptBuilder->buildAddMorePrompts(
        $category,
        $existing_recommendations
      );

      // Prepare context for token replacement.
      $front_content = $site_structure['homepage']['content'] ?? '';
      $menu_items = $site_structure['primary_menu'] ?? [];
      $menu_formatted = [];
      foreach ($menu_items as $item) {
        $menu_formatted[] = '- ' . $item['title'] . (!empty($item['url']) ? ' (' . $item['url'] . ')' : '');
      }

      $url_formatted = [];
      foreach (array_slice($sitemap_urls['urls'] ?? [], 0, 50) as $url) {
        $url_formatted[] = '- ' . $url;
      }

      $user_prompt = strtr($prompts['user'], [
        '{homepage_title}' => $site_structure['homepage']['title'] ?? '',
        '{homepage_content}' => $front_content,
        '{primary_menu}' => implode("\n", $menu_formatted),
        '{urls}' => implode("\n", $url_formatted),
        '{existing_recommendations}' => $existing_recommendations,
        '{{ homepage.title }}' => $site_structure['homepage']['title'] ?? '',
        '{{ homepage.content }}' => $front_content,
        '{{ navigation }}' => implode("\n", $menu_formatted),
        '{{ content_urls }}' => implode("\n", $url_formatted),
        '{{ existing_recommendations }}' => $existing_recommendations,
      ]);

      // Get default provider and model.
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      $messages = new ChatInput([
        new ChatMessage('system', $prompts['system']),
        new ChatMessage('user', $user_prompt),
      ]);

      $chat_response = $provider->chat($messages, $defaults['model_id'], ['content_strategy']);
      $data = $this->promptJsonDecoder->decode($chat_response->getNormalized());

      if (!isset($data[$section])) {
        return $this->error('AI returned invalid response format.', ['Expected key: ' . $section]);
      }

      $new_cards = $data[$section];

      // Ensure UUIDs.
      $new_cards = array_map(function ($card) {
        if (!isset($card['uuid'])) {
          $card['uuid'] = $this->uuid->generate();
        }
        if (isset($card['content_ideas'])) {
          $card['content_ideas'] = $this->storage
            ->normalizeIdeasWithUuids($card['content_ideas']);
        }
        return $card;
      }, $new_cards);

      // Add to storage.
      $this->storage->addToSection($section, $new_cards);

      return $this->success(sprintf('Generated %d new cards for "%s".', count($new_cards), $category->label()), [
        'category' => $section,
        'new_cards' => array_map(fn($card) => [
          'uuid' => $card['uuid'],
          'title' => $card['title'] ?? '',
          'priority' => $card['priority'] ?? 'medium',
        ], $new_cards),
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Failed to generate recommendations.', [$e->getMessage()]);
    }
  }

}
