<?php

namespace Drupal\ai_content_strategy\Controller;

use Drupal\ai_content_strategy\Entity\RecommendationCategory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_content_strategy\Service\StrategyGenerator;
use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drupal\ai_content_strategy\Service\CategoryPromptBuilder;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Controller for content strategy functionality.
 */
class ContentStrategyController extends ControllerBase {

  /**
   * Key-Value collection for content strategy recommendations.
   */
  const KV_COLLECTION = 'ai_content_strategy.recommendations';

  /**
   * Key for storing recommendations.
   */
  const KV_KEY = 'recommendations';

  /**
   * The strategy generator service.
   *
   * @var \Drupal\ai_content_strategy\Service\StrategyGenerator
   */
  protected $strategyGenerator;

  /**
   * The content analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\ContentAnalyzer
   */
  protected $contentAnalyzer;

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
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The category prompt builder service.
   *
   * @var \Drupal\ai_content_strategy\Service\CategoryPromptBuilder
   */
  protected $categoryPromptBuilder;

  /**
   * Constructs a ContentStrategyController object.
   *
   * @param \Drupal\ai_content_strategy\Service\StrategyGenerator $strategy_generator
   *   The strategy generator service.
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   * @param \Drupal\ai\AiProviderPluginManager $ai_provider
   *   The AI provider plugin manager.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $prompt_json_decoder
   *   The prompt JSON decoder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\ai_content_strategy\Service\CategoryPromptBuilder $category_prompt_builder
   *   The category prompt builder service.
   */
  public function __construct(
    StrategyGenerator $strategy_generator,
    ContentAnalyzer $content_analyzer,
    AiProviderPluginManager $ai_provider,
    PromptJsonDecoderInterface $prompt_json_decoder,
    RendererInterface $renderer,
    CacheBackendInterface $cache,
    DateFormatterInterface $date_formatter,
    KeyValueFactoryInterface $key_value_factory,
    TimeInterface $time,
    CategoryPromptBuilder $category_prompt_builder,
  ) {
    $this->strategyGenerator = $strategy_generator;
    $this->contentAnalyzer = $content_analyzer;
    $this->aiProvider = $ai_provider;
    $this->promptJsonDecoder = $prompt_json_decoder;
    $this->renderer = $renderer;
    $this->cache = $cache;
    $this->dateFormatter = $date_formatter;
    $this->keyValue = $key_value_factory->get(self::KV_COLLECTION);
    $this->time = $time;
    $this->categoryPromptBuilder = $category_prompt_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_content_strategy.strategy_generator'),
      $container->get('ai_content_strategy.content_analyzer'),
      $container->get('ai.provider'),
      $container->get('ai.prompt_json_decode'),
      $container->get('renderer'),
      $container->get('cache.default'),
      $container->get('date.formatter'),
      $container->get('keyvalue'),
      $container->get('datetime.time'),
      $container->get('ai_content_strategy.category_prompt_builder')
    );
  }

  /**
   * Gets the renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer service.
   */
  protected function renderer() {
    return $this->renderer;
  }

  /**
   * Displays content strategy recommendations.
   *
   * @return array
   *   Render array for the recommendations page.
   */
  public function recommendations() {
    // Get stored data from key-value store.
    $stored_data = $this->keyValue->get(self::KV_KEY);
    $recommendations = [];
    $last_run = NULL;

    if ($stored_data) {
      if (is_array($stored_data) && isset($stored_data['data'],
        $stored_data['timestamp'])) {
        $recommendations = $stored_data['data'];
        $last_run = (int) $stored_data['timestamp'];
      }
      else {
        // Handle legacy format.
        $recommendations = $stored_data;
        $last_run = $this->time->getRequestTime();
      }
    }

    // Load enabled categories and build category metadata.
    $category_storage = $this->entityTypeManager()->getStorage('recommendation_category');
    $category_ids = $category_storage->getQuery()
      ->condition('status', TRUE)
      ->sort('weight')
      ->sort('id')
      ->accessCheck(FALSE)
      ->execute();

    $categories = [];
    if ($category_ids) {
      $category_entities = $category_storage->loadMultiple($category_ids);
      foreach ($category_entities as $category) {
        assert($category instanceof RecommendationCategory);
        $category_id = $category->id();
        $categories[$category_id] = [
          'id' => $category_id,
          'label' => $category->label(),
          'description' => $category->getDescription(),
          'weight' => $category->getWeight(),
          'items' => $recommendations[$category_id] ?? [],
        ];
      }
    }

    return [
      '#theme' => 'ai_content_strategy_recommendations',
      '#categories' => $categories,
      '#last_run' => $last_run ?
      $this->dateFormatter->formatTimeDiffSince($last_run) : NULL,
      '#attached' => [
        'library' => ['ai_content_strategy/content_strategy'],
      ],
    ];
  }

  /**
   * AJAX callback to generate recommendations.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response containing the recommendations.
   */
  public function generateRecommendationsAjax() {
    try {
      // Get recommendations.
      $recommendations = $this->strategyGenerator->generateRecommendations();

      // Store the results with timestamp in key-value store.
      $timestamp = (int) $this->time->getCurrentTime();
      $this->keyValue->set(self::KV_KEY, [
        'data' => $recommendations,
        'timestamp' => $timestamp,
      ]);

      // Create AJAX response.
      $response = new AjaxResponse();

      // Re-enable the generate button.
      $response->addCommand(new HtmlCommand(
        '.generate-recommendations',
        $this->t('Regenerate report')
      ));

      // Update the last run time.
      $response->addCommand(
        new HtmlCommand(
          '.last-run-time',
          $this->t('Last generated: @time ago', [
            '@time' => $this->dateFormatter->formatTimeDiffSince($timestamp),
          ])
        )
      );

      // Remove empty state message if it exists.
      $response->addCommand(
        new RemoveCommand('.empty-recommendations')
      );

      // Create the recommendations wrapper first.
      $wrapper = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['recommendations-wrapper'],
        ],
      ];

      // Load enabled categories dynamically.
      $category_storage = $this->entityTypeManager()->getStorage('recommendation_category');
      $category_ids = $category_storage->getQuery()
        ->condition('status', TRUE)
        ->sort('weight')
        ->sort('id')
        ->accessCheck(FALSE)
        ->execute();

      if ($category_ids) {
        $categories = $category_storage->loadMultiple($category_ids);
        $button_texts = ai_content_strategy_get_button_texts();

        foreach ($categories as $category) {
          $category_id = $category->id();

          $section_build = [
            '#theme' => 'ai_content_strategy_recommendations_items',
            '#items' => $recommendations[$category_id] ?? [],
            '#section' => $category_id,
            '#button_text' => $button_texts,
          ];

          $section_html = $this->renderer->renderRoot($section_build);

          // For empty state, we need to create the section container first.
          $section_container = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['recommendation-section'],
              'data-section' => $category_id,
            ],
            'section_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $category->label(),
            ],
            'section_items' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['recommendation-items']],
              'content' => ['#markup' => $section_html],
            ],
          ];

          // Determine if this category has items or is empty.
          $has_items = !empty($recommendations[$category_id]);
          $button_class = $has_items ? 'button--secondary' : 'button--primary';
          $button_text = $has_items
            ? ($button_texts['add_more'][$category_id] ?? $this->t('Add AI recommendations'))
            : ($button_texts['generate'][$category_id] ?? $this->t('Generate AI recommendations'));

          // Add empty state message for categories with no items.
          if (!$has_items) {
            $section_container['empty_state'] = [
              '#type' => 'container',
              '#attributes' => ['class' => ['empty-category-state']],
              'message' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#value' => $this->t('No recommendations yet. Click below to generate AI-powered content suggestions.'),
              ],
            ];
          }

          // Always include the add-more button.
          $section_container['section_add_more'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['add-more-recommendations-wrapper']],
            'add_link' => [
              '#type' => 'html_tag',
              '#tag' => 'a',
              '#attributes' => [
                'href' => '#',
                'class' => [
                  'add-more-recommendations-link',
                  'button',
                  $button_class,
                ],
                'data-section' => $category_id,
              ],
              '#value' => $button_text,
            ],
          ];

          $wrapper[$category_id] = $section_container;
        }
      }

      // Add the wrapper with all sections.
      $response->addCommand(
        new AppendCommand(
          '.content-strategy-recommendations',
          $this->renderer->renderRoot($wrapper)
        )
      );

      return $response;
    }
    catch (\Exception $e) {
      // Log the full error for administrators.
      Error::logException($this->getLogger('ai_content_strategy'), $e);

      // Create user-friendly error message.
      $error_message = $this->buildUserFriendlyErrorMessage($e);

      $response = new AjaxResponse();

      // Re-enable the button so user can retry.
      $response->addCommand(new HtmlCommand(
        '.generate-recommendations',
        $this->t('Generate recommendations')
      ));

      // Show error message.
      $response->addCommand(
        new MessageCommand(
          $error_message,
          NULL,
          ['type' => 'error']
        )
      );

      // Set HTTP status code - use the exception's code if it's a valid HTTP
      // status, otherwise default to 500.
      $status_code = $this->getHttpStatusFromException($e);
      $response->setStatusCode($status_code);

      return $response;
    }
  }

  /**
   * Gets the front page content as plain text.
   *
   * @return string
   *   The front page content with HTML stripped.
   */
  protected function getFrontPageContent(): string {
    try {
      // Use injected config factory via ControllerBase.
      $config = $this->config('system.site');
      $front_uri = $config->get('page.front') ?: '/node/1';

      // Extract node ID if the front page is a node.
      if (preg_match('/node\/(\d+)/', $front_uri, $matches)) {
        $node = $this->entityTypeManager()->getStorage('node')
          ->load($matches[1]);
        if ($node) {
          // Get the rendered content.
          $view_builder = $this->entityTypeManager()->getViewBuilder('node');
          $build = $view_builder->view($node);
          $html = $this->renderer()->renderInIsolation($build);

          // Convert HTML to plain text.
          $text = strip_tags($html);
          // Normalize whitespace.
          $text = preg_replace('/\s+/', ' ', trim($text));
          // Trim to reasonable length.
          return Unicode::truncate($text, 1000, TRUE, TRUE);
        }
      }
    }
    catch (\Exception $e) {
      // Log error but continue without front page content.
      $this->getLogger('ai_content_strategy')
        ->error('Error fetching front page content: @error', [
          '@error' => $e->getMessage(),
        ]);
    }

    return '';
  }

  /**
   * Generates additional content ideas for a specific section.
   *
   * @param string $section
   *   The section to generate ideas for (content_gaps, authority_topics, etc.).
   * @param string $title
   *   The title of the specific item to generate more ideas for.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response containing the new content ideas.
   */
  public function generateMore(string $section, string $title) {
    try {
      // Get current stored data first.
      $stored_data = $this->keyValue->get(self::KV_KEY);
      if (!$stored_data || !isset($stored_data['data'])) {
        throw new \RuntimeException('No existing recommendations found');
      }
      $recommendations = $stored_data['data'];

      // Get site data for context.
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();
      $front_page_content = $this->getFrontPageContent();

      // Get default provider and model.
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Create a focused prompt for generating more ideas.
      $prompt = <<<EOT
<context>
  <site_info>
    <homepage>
      <title>{$site_structure['homepage']['title']}</title>
      <content>{$front_page_content}</content>
    </homepage>
    <navigation>
{$this->formatMenuItems($site_structure['primary_menu'])}
    </navigation>
    <content_urls>
{$this->formatUrls($sitemap_urls['urls'])}
    </content_urls>
  </site_info>
  <task>
    <section>{$section}</section>
    <topic>{$title}</topic>
    <requirements>
      <requirement>Specific and actionable ideas</requirement>
      <requirement>Relevant to the site's context and purpose</requirement>
      <requirement>Different from previously suggested ideas</requirement>
      <requirement>Based on analyzing the site's structure and content</requirement>
      <requirement>Aligned with the target audience</requirement>
    </requirements>
  </task>
</context>

<instructions>
Based on the above context, generate 5 additional, unique content ideas for
the specified topic.
Return ONLY a JSON array of strings, each being a new content idea.
</instructions>
EOT;

      // Create chat input with proper format.
      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      // Generate ideas.
      $response = $provider->chat($messages, $defaults['model_id'],
        ['content_strategy']);

      // Get the normalized response and try to decode it.
      $message = $response->getNormalized();
      $text = $message->getText();

      // Try to extract and parse JSON array from the text.
      if (preg_match('/\[(?:[^\[\]]|(?R))*\]/', $text, $matches)) {
        try {
          $ideas = Json::decode($matches[0]);
        }
        catch (\Exception $e) {
          throw new \RuntimeException(
            'Failed to parse AI response into valid JSON array'
          );
        }
      }
      else {
        throw new \RuntimeException(
          'Invalid response format from AI provider'
        );
      }

      // After successfully generating and parsing new ideas, update the stored
      // data.
      $updated = FALSE;

      // Find the correct section and item to update.
      if (isset($recommendations[$section])) {
        foreach ($recommendations[$section] as $key => $item) {
          // Try common title fields to match the item.
          $item_title = $item['title'] ?? $item['topic'] ?? $item['content_type'] ?? $item['signal'] ?? NULL;

          if ($item_title === $title) {
            // Append new ideas to existing ones (or initialize if empty).
            $existing_ideas = $recommendations[$section][$key]['content_ideas'] ?? [];
            $recommendations[$section][$key]['content_ideas'] = array_merge(
              $existing_ideas,
              $ideas
            );
            $updated = TRUE;
            break;
          }
        }
      }

      if ($updated) {
        // Update the stored data with timestamp.
        $timestamp = (int) $this->time->getCurrentTime();
        $this->keyValue->set(self::KV_KEY, [
          'data' => $recommendations,
          'timestamp' => $timestamp,
        ]);
      }
      else {
        throw new \RuntimeException('Failed to find matching item to update');
      }

      // Build HTML for new ideas.
      $rows = [];
      foreach ($ideas as $idea) {
        $rows[] = [
          '#type' => 'html_tag',
          '#tag' => 'tr',
          'cell' => [
            '#type' => 'html_tag',
            '#tag' => 'td',
            '#value' => $idea,
          ],
        ];
      }

      // Create AJAX response.
      $response = new AjaxResponse();

      // Build the HTML for the new rows.
      $html = $this->renderer->renderRoot($rows);

      // Update the last run time.
      $response->addCommand(
        new HtmlCommand(
          '.last-run-time',
          $this->t('Last generated: @time ago', [
            '@time' => $this->dateFormatter->formatTimeDiffSince($timestamp),
          ])
        )
      );

      // Add command to append the new rows to the table.
      $response->addCommand(
        new AppendCommand(
          sprintf(
            '.recommendation-item[data-section="%s"][data-title="%s"]' .
            ' .content-ideas-table tbody',
            $section,
            str_replace('"', '\"', $title)
          ),
          $html
        )
      );

      return $response;

    }
    catch (\Exception $e) {
      // Log the full error for administrators.
      Error::logException($this->getLogger('ai_content_strategy'), $e);

      // Create user-friendly error message.
      $error_message = $this->buildUserFriendlyErrorMessage($e);

      $response = new AjaxResponse();
      $response->addCommand(
        new MessageCommand(
          $error_message,
          NULL,
          ['type' => 'error']
        )
      );

      // Set HTTP status code - use the exception's code if it's a valid HTTP
      // status, otherwise default to 500.
      $status_code = $this->getHttpStatusFromException($e);
      $response->setStatusCode($status_code);

      return $response;
    }
  }

  /**
   * Formats menu items for the prompt.
   *
   * @param array $menu_items
   *   Array of menu items to format.
   *
   * @return string
   *   Formatted menu items string.
   */
  protected function formatMenuItems(array $menu_items): string {
    $output = [];
    foreach ($menu_items as $item) {
      $output[] = "- {$item['title']}" .
        (!empty($item['url']) ? " ({$item['url']})" : "");
    }
    return implode("\n", $output);
  }

  /**
   * Formats URLs for the prompt.
   *
   * @param array $urls
   *   Array of URLs to format.
   *
   * @return string
   *   Formatted URLs string.
   */
  protected function formatUrls(array $urls): string {
    $output = [];
    foreach ($urls as $url) {
      $output[] = "- {$url}";
    }
    return implode("\n", $output);
  }

  /**
   * Formats existing recommendations for the prompt.
   *
   * @param array $recommendations
   *   Array of existing recommendations.
   *
   * @return string
   *   Formatted recommendations string.
   */
  protected function formatExistingRecommendations(array $recommendations): string {
    $output = [];
    foreach ($recommendations as $item) {
      if (isset($item['title'])) {
        $output[] = "- {$item['title']}: {$item['description']}";
      }
      elseif (isset($item['topic'])) {
        $output[] = "- {$item['topic']}: {$item['rationale']}";
      }
      elseif (isset($item['content_type'])) {
        $output[] = "- {$item['content_type']}: {$item['description']}";
      }
      elseif (isset($item['signal'])) {
        $output[] = "- {$item['signal']}: {$item['implementation']}";
      }
    }
    return implode("\n", $output);
  }

  /**
   * Generates additional recommendations for a specific section.
   *
   * @param string $section
   *   The section to generate more recommendations for.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response containing the new recommendations.
   */
  public function addMoreRecommendations($section) {
    $response = new AjaxResponse();

    try {
      // Load the category entity.
      $category_storage = $this->entityTypeManager()->getStorage('recommendation_category');
      $category = $category_storage->load($section);

      if (!$category instanceof RecommendationCategory) {
        throw new \InvalidArgumentException('Invalid category specified');
      }

      // Get site data for context.
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();
      $front_page_content = $this->getFrontPageContent();

      // Get existing recommendations.
      $stored_data = $this->keyValue->get(self::KV_KEY);
      $existing_recommendations = '';
      if ($stored_data && isset($stored_data['data'][$section])) {
        $existing_recommendations = $this->formatExistingRecommendations(
          $stored_data['data'][$section]
        );
      }

      // Build prompts dynamically from category instructions.
      $prompts = $this->categoryPromptBuilder->buildAddMorePrompts(
        $category,
        $existing_recommendations
      );

      // Prepare the context variables for token replacement.
      $context = [
        'homepage' => [
          'title' => $site_structure['homepage']['title'],
          'content' => $front_page_content,
        ],
        'navigation' => $this->formatMenuItems($site_structure['primary_menu']),
        'content_urls' => $this->formatUrls($sitemap_urls['urls']),
        'existing_recommendations' => $existing_recommendations,
      ];

      // Get default provider and model.
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Create chat input with proper format.
      $messages = new ChatInput([
        new ChatMessage(
          'system',
          $prompts['system']
        ),
        new ChatMessage(
          'user',
          $this->replaceTokens($prompts['user'], $context)
        ),
      ]);

      // Generate recommendations.
      $chat_response = $provider->chat($messages, $defaults['model_id'],
        ['content_strategy']);
      $message = $chat_response->getNormalized();

      // Use the prompt JSON decoder to parse the response.
      $data = $this->promptJsonDecoder->decode($message);

      if (!isset($data[$section])) {
        throw new \RuntimeException(
          'Invalid response format from AI provider'
        );
      }

      // Update stored recommendations.
      if ($stored_data) {
        // Merge with existing recommendations or initialize if empty.
        $existing = $stored_data['data'][$section] ?? [];
        $stored_data['data'][$section] = array_merge($existing, $data[$section]);
        $stored_data['timestamp'] = $this->time->getCurrentTime();
        $this->keyValue->set(self::KV_KEY, $stored_data);
      }
      else {
        // Initialize storage for first time.
        $this->keyValue->set(self::KV_KEY, [
          'data' => [$section => $data[$section]],
          'timestamp' => $this->time->getCurrentTime(),
        ]);
      }

      // Build the render array for new recommendations.
      $build = [
        '#theme' => 'ai_content_strategy_recommendations_items',
        '#items' => $data[$section],
        '#section' => $section,
        '#button_text' => ai_content_strategy_get_button_texts(),
      ];

      // Render the new recommendations.
      $html = $this->renderer->renderRoot($build);

      // Check if this was an empty category (no existing recommendations).
      $was_empty = empty($existing);

      if ($was_empty) {
        // Remove the empty state container.
        $response->addCommand(
          new RemoveCommand(".recommendation-section[data-section='$section'] .empty-category-state")
        );

        // Create the recommendation-items container and add the content.
        $items_container = [
          '#type' => 'container',
          '#attributes' => ['class' => ['recommendation-items']],
          'content' => ['#markup' => $html],
        ];
        $items_html = $this->renderer->renderRoot($items_container);

        // Insert the new container before the add-more button wrapper.
        $response->addCommand(
          new BeforeCommand(
            ".recommendation-section[data-section='$section'] .add-more-recommendations-wrapper",
            $items_html
          )
        );

        // Update the button to secondary style and change text.
        $button_texts = ai_content_strategy_get_button_texts();
        $response->addCommand(
          new HtmlCommand(
            ".recommendation-section[data-section='$section'] .add-more-recommendations-link",
            $button_texts['add_more'][$section] ?? $this->t('Add more AI recommendations')
          )
        );

        // Update button classes from primary to secondary.
        $response->addCommand(
          new InvokeCommand(
            ".recommendation-section[data-section='$section'] .add-more-recommendations-link",
            'removeClass',
            ['button--primary']
          )
        );
        $response->addCommand(
          new InvokeCommand(
            ".recommendation-section[data-section='$section'] .add-more-recommendations-link",
            'addClass',
            ['button--secondary']
          )
        );
      }
      else {
        // Category already had items, just append to existing container.
        $response->addCommand(
          new AppendCommand(
            ".recommendation-section[data-section='$section'] .recommendation-items",
            $html
          )
        );
      }

      // Update the last run time.
      $response->addCommand(
        new HtmlCommand(
          '.last-run-time',
          $this->t('Last generated: @time ago', [
            '@time' => $this->dateFormatter
              ->formatTimeDiffSince($stored_data['timestamp']),
          ])
        )
      );

    }
    catch (\Exception $e) {
      Error::logException($this->getLogger('ai_content_strategy'), $e);

      $response->addCommand(
        new MessageCommand(
          $this->t(
            'An error occurred while generating additional recommendations: @error',
            [
              '@error' => $e->getMessage(),
            ]),
          NULL,
          ['type' => 'error']
        )
      );

      // Set HTTP status code - use the exception's code if it's a valid HTTP
      // status, otherwise default to 500.
      $status_code = $this->getHttpStatusFromException($e);
      $response->setStatusCode($status_code);
    }

    return $response;
  }

  /**
   * Replaces tokens in a string with their values.
   *
   * @param string $text
   *   The text containing tokens.
   * @param array $context
   *   The context array containing token values.
   *
   * @return string
   *   The text with tokens replaced.
   */
  protected function replaceTokens($text, array $context) {
    $text = strtr($text, [
      '{{ homepage.title }}' => $context['homepage']['title'],
      '{{ homepage.content }}' => $context['homepage']['content'],
      '{{ navigation }}' => $context['navigation'],
      '{{ content_urls }}' => $context['content_urls'],
      '{{ existing_recommendations }}' => $context['existing_recommendations'],
    ]);
    return $text;
  }

  /**
   * Builds a user-friendly error message with actionable guidance.
   *
   * Following Jakob Nielsen's usability heuristics:
   * - Error messages should be expressed in plain language.
   * - Precisely indicate the problem.
   * - Constructively suggest a solution.
   *
   * @param \Exception $exception
   *   The exception that occurred.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A user-friendly error message with next steps.
   */
  protected function buildUserFriendlyErrorMessage(\Exception $exception): TranslatableMarkup {
    $message = $exception->getMessage();

    // Check for common error patterns and provide specific guidance.
    // Pattern 1: No chat provider available.
    if (stripos($message, 'no chat provider') !== FALSE || stripos($message, 'provider') !== FALSE) {
      return $this->t('<strong>AI provider not configured.</strong><br>Please configure an AI chat provider at <a href="@url">AI settings</a> before generating recommendations.', [
        '@url' => '/admin/config/ai/providers',
      ]);
    }

    // Pattern 2: No URLs found in sitemap.
    if (stripos($message, 'no urls') !== FALSE || stripos($message, 'sitemap') !== FALSE) {
      return $this->t('<strong>No content found to analyze.</strong><br>Please ensure your site has published content and a properly configured sitemap. Check your sitemap at <a href="@url">@url</a>.', [
        '@url' => '/sitemap.xml',
      ]);
    }

    // Pattern 3: No enabled categories.
    if (stripos($message, 'no enabled') !== FALSE || stripos($message, 'categor') !== FALSE) {
      return $this->t('<strong>No recommendation categories enabled.</strong><br>Please enable at least one category at <a href="@url">category settings</a>.', [
        '@url' => '/admin/config/ai/content-strategy/categories',
      ]);
    }

    // Pattern 4: JSON parsing errors.
    if (stripos($message, 'json') !== FALSE || stripos($message, 'parse') !== FALSE) {
      return $this->t('<strong>AI returned an invalid response.</strong><br>The AI model may be overloaded or misconfigured. Please try again in a moment. If the problem persists, try a different AI model in <a href="@url">AI settings</a>.', [
        '@url' => '/admin/config/ai/providers',
      ]);
    }

    // Pattern 5: API/Network errors.
    if (stripos($message, 'timeout') !== FALSE || stripos($message, 'connection') !== FALSE || stripos($message, 'network') !== FALSE) {
      return $this->t('<strong>Connection to AI provider failed.</strong><br>This may be a temporary network issue. Please check your internet connection and try again. If using a third-party API, verify your API credentials are correct.');
    }

    // Pattern 6: Rate limiting.
    if (stripos($message, 'rate limit') !== FALSE || stripos($message, 'quota') !== FALSE || stripos($message, 'too many') !== FALSE) {
      return $this->t('<strong>AI service rate limit reached.</strong><br>You have exceeded the API usage limits. Please wait a few minutes before trying again, or check your API plan limits with your provider.');
    }

    // Pattern 7: Authentication errors.
    if (stripos($message, 'auth') !== FALSE || stripos($message, 'api key') !== FALSE || stripos($message, 'credential') !== FALSE) {
      return $this->t('<strong>AI provider authentication failed.</strong><br>Please verify your API credentials are correct at <a href="@url">AI settings</a>.', [
        '@url' => '/admin/config/ai/providers',
      ]);
    }

    // Generic fallback with constructive guidance.
    return $this->t('<strong>Unable to generate recommendations.</strong><br>An unexpected error occurred: @error<br><br><strong>What to try:</strong><ul><li>Refresh the page and try again</li><li>Check the <a href="@logs">error logs</a> for details</li><li>Verify your <a href="@ai">AI provider settings</a></li><li>Ensure your site has published content</li></ul>', [
      '@error' => $message,
      '@logs' => '/admin/reports/dblog',
      '@ai' => '/admin/config/ai/providers',
    ]);
  }

  /**
   * Extracts HTTP status code from exception or returns default.
   *
   * This method attempts to extract a valid HTTP status code from an
   * exception. Many HTTP client libraries and API wrappers throw exceptions
   * with the HTTP status code as the exception code (e.g., 402, 429, 503).
   *
   * @param \Exception $exception
   *   The exception to extract status code from.
   *
   * @return int
   *   Valid HTTP status code (400-599), or 500 if none found.
   */
  protected function getHttpStatusFromException(\Exception $exception): int {
    $code = $exception->getCode();

    // Check if the exception code is a valid HTTP error status (4xx or 5xx).
    if (is_int($code) && $code >= 400 && $code < 600) {
      return $code;
    }

    // Default to 500 Internal Server Error.
    return 500;
  }

}
