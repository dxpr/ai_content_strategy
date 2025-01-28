<?php

namespace Drupal\ai_content_strategy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_content_strategy\Service\StrategyGenerator;
use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Ajax\InsertCommand;

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
   */
  public function __construct(
    StrategyGenerator $strategy_generator,
    ContentAnalyzer $content_analyzer,
    AiProviderPluginManager $ai_provider,
    PromptJsonDecoderInterface $prompt_json_decoder,
    RendererInterface $renderer,
    CacheBackendInterface $cache,
    DateFormatterInterface $date_formatter,
    KeyValueFactoryInterface $key_value_factory
  ) {
    $this->strategyGenerator = $strategy_generator;
    $this->contentAnalyzer = $content_analyzer;
    $this->aiProvider = $ai_provider;
    $this->promptJsonDecoder = $prompt_json_decoder;
    $this->renderer = $renderer;
    $this->cache = $cache;
    $this->dateFormatter = $date_formatter;
    $this->keyValue = $key_value_factory->get(self::KV_COLLECTION);
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
      $container->get('keyvalue')
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
    // Get stored data from key-value store
    $stored_data = $this->keyValue->get(self::KV_KEY);
    $recommendations = [];
    $last_run = NULL;
    
    if ($stored_data) {
      if (is_array($stored_data) && isset($stored_data['data'], $stored_data['timestamp'])) {
        $recommendations = $stored_data['data'];
        $last_run = (int) $stored_data['timestamp'];
      }
      else {
        // Handle legacy format
        $recommendations = $stored_data;
        $last_run = \Drupal::time()->getRequestTime();
      }
    }

    return [
      '#theme' => 'ai_content_strategy_recommendations',
      '#content_gaps' => $recommendations['content_gaps'] ?? [],
      '#authority_topics' => $recommendations['authority_topics'] ?? [],
      '#expertise_demonstrations' => $recommendations['expertise_demonstrations'] ?? [],
      '#trust_signals' => $recommendations['trust_signals'] ?? [],
      '#last_run' => $last_run ? $this->dateFormatter->formatTimeDiffSince($last_run) : NULL,
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
      // Get recommendations
      $recommendations = $this->strategyGenerator->generateRecommendations();
      
      // Store the results with timestamp in key-value store
      $timestamp = (int) \Drupal::time()->getCurrentTime();
      $this->keyValue->set(self::KV_KEY, [
        'data' => $recommendations,
        'timestamp' => $timestamp,
      ]);
      
      // Build the response HTML
      $build = [
        '#theme' => 'ai_content_strategy_recommendations',
        '#content_gaps' => $recommendations['content_gaps'] ?? [],
        '#authority_topics' => $recommendations['authority_topics'] ?? [],
        '#expertise_demonstrations' => $recommendations['expertise_demonstrations'] ?? [],
        '#trust_signals' => $recommendations['trust_signals'] ?? [],
        '#last_run' => $this->dateFormatter->formatTimeDiffSince($timestamp),
      ];
      
      // Create AJAX response
      $response = new AjaxResponse();

      // Update the button text to "Refresh recommendations"
      $response->addCommand(
        new HtmlCommand(
          '.generate-recommendations',
          $this->t('Refresh recommendations')
        )
      );
      
      // Update the last run time
      $response->addCommand(
        new HtmlCommand(
          '.last-run-time',
          $this->t('Last generated: @time ago', [
            '@time' => $this->dateFormatter->formatTimeDiffSince($timestamp),
          ])
        )
      );

      // Remove empty state message if it exists
      $response->addCommand(
        new RemoveCommand('.empty-recommendations')
      );

      // Create the recommendations wrapper first
      $wrapper = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['recommendations-wrapper'],
        ],
      ];

      // Build the sections HTML
      $sections = [
        'content_gaps',
        'authority_topics',
        'expertise_demonstrations',
        'trust_signals'
      ];

      foreach ($sections as $section) {
        $section_build = [
          '#theme' => 'ai_content_strategy_recommendations_items',
          '#items' => $recommendations[$section] ?? [],
          '#section' => $section,
          '#section_config' => [
            'title' => $this->getSectionTitle($section),
            'item_key' => $this->getSectionItemKey($section),
            'description_key' => $this->getSectionDescriptionKey($section),
          ],
          '#button_text' => ai_content_strategy_get_button_texts(),
        ];

        $section_html = $this->renderer->render($section_build);

        // For empty state, we need to create the section container first
        $section_container = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['recommendation-section'],
            'data-section' => $section,
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->getSectionTitle($section),
          ],
          'items' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['recommendation-items']],
            'content' => ['#markup' => $section_html],
          ],
        ];

        if (!empty($recommendations[$section])) {
          $section_container['add_more'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['add-more-recommendations-wrapper']],
            'link' => [
              '#type' => 'html_tag',
              '#tag' => 'a',
              '#attributes' => [
                'href' => '#',
                'class' => ['add-more-recommendations-link', 'button', 'button--secondary'],
                'data-section' => $section,
              ],
              '#value' => ai_content_strategy_get_button_texts()['add_more'][$section],
            ],
          ];
        }

        $wrapper[$section] = $section_container;
      }

      // Add the wrapper with all sections
      $response->addCommand(
        new AppendCommand(
          '.content-strategy-recommendations',
          $this->renderer->render($wrapper)
        )
      );

      return $response;
    }
    catch (\Exception $e) {
      $response = new AjaxResponse();
      $response->addCommand(
        new MessageCommand(
          $this->t('Error: @message', ['@message' => $e->getMessage()]),
          NULL,
          ['type' => 'error']
        )
      );
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
      // Use injected config factory via ControllerBase
      $config = $this->config('system.site');
      $front_uri = $config->get('page.front') ?: '/node/1';
      
      // Extract node ID if the front page is a node
      if (preg_match('/node\/(\d+)/', $front_uri, $matches)) {
        $node = $this->entityTypeManager()->getStorage('node')->load($matches[1]);
        if ($node) {
          // Get the rendered content
          $view_builder = $this->entityTypeManager()->getViewBuilder('node');
          $build = $view_builder->view($node);
          $html = $this->renderer()->renderPlain($build);
          
          // Convert HTML to plain text
          $text = strip_tags($html);
          // Normalize whitespace
          $text = preg_replace('/\s+/', ' ', $text);
          // Trim to reasonable length
          return substr(trim($text), 0, 1000) . (strlen($text) > 1000 ? '...' : '');
        }
      }
    }
    catch (\Exception $e) {
      // Log error but continue without front page content
      $this->getLogger('ai_content_strategy')->error('Error fetching front page content: @error', [
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
      // Get current stored data first
      $stored_data = $this->keyValue->get(self::KV_KEY);
      if (!$stored_data || !isset($stored_data['data'])) {
        throw new \RuntimeException('No existing recommendations found');
      }
      $recommendations = $stored_data['data'];

      // Get site data for context
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();
      $front_page_content = $this->getFrontPageContent();

      // Get default provider and model
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Create a focused prompt for generating more ideas
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
Based on the above context, generate 5 additional, unique content ideas for the specified topic.
Return ONLY a JSON array of strings, each being a new content idea.
</instructions>
EOT;

      // Create chat input with proper format
      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      // Generate ideas
      $response = $provider->chat($messages, $defaults['model_id'], ['content_strategy']);
      
      // Get the normalized response and try to decode it
      $message = $response->getNormalized();
      $text = $message->getText();
      
      // Try to extract and parse JSON array from the text
      if (preg_match('/\[(?:[^\[\]]|(?R))*\]/', $text, $matches)) {
        $ideas = json_decode($matches[0], TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new \RuntimeException('Failed to parse AI response into valid JSON array');
        }
      } else {
        throw new \RuntimeException('Invalid response format from AI provider');
      }

      // After successfully generating and parsing new ideas, update the stored data
      $updated = FALSE;
      
      // Find the correct section and item to update
      if (isset($recommendations[$section])) {
        foreach ($recommendations[$section] as $key => $item) {
          $match = FALSE;
          switch ($section) {
            case 'content_gaps':
              $match = ($item['title'] === $title);
              break;
            case 'authority_topics':
              $match = ($item['topic'] === $title);
              break;
            case 'expertise_demonstrations':
              $match = ($item['content_type'] === $title);
              break;
            case 'trust_signals':
              $match = ($item['signal'] === $title);
              break;
          }
          
          if ($match) {
            // Append new ideas to existing ones
            $recommendations[$section][$key]['content_ideas'] = array_merge(
              $recommendations[$section][$key]['content_ideas'],
              $ideas
            );
            $updated = TRUE;
            break;
          }
        }
      }

      if ($updated) {
        // Update the stored data with timestamp
        $timestamp = (int) \Drupal::time()->getCurrentTime();
        $this->keyValue->set(self::KV_KEY, [
          'data' => $recommendations,
          'timestamp' => $timestamp,
        ]);
      } else {
        throw new \RuntimeException('Failed to find matching item to update');
      }

      // Build HTML for new ideas
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

      // Create AJAX response
      $response = new AjaxResponse();
      
      // Build the HTML for the new rows
      $html = $this->renderer->renderRoot($rows);

      // Update the last run time
      $response->addCommand(
        new HtmlCommand(
          '.last-run-time',
          $this->t('Last generated: @time ago', [
            '@time' => $this->dateFormatter->formatTimeDiffSince($timestamp),
          ])
        )
      );
      
      // Add command to append the new rows to the table
      $response->addCommand(
        new AppendCommand(
          sprintf('.recommendation-item[data-section="%s"][data-title="%s"] .content-ideas-table tbody',
            $section,
            str_replace('"', '\"', $title)
          ),
          $html
        )
      );

      return $response;

    } catch (\Exception $e) {
      watchdog_exception('ai_content_strategy', $e);
      
      $response = new AjaxResponse();
      $response->addCommand(
        new MessageCommand(
          $this->t('An error occurred while generating more ideas: @error', ['@error' => $e->getMessage()]),
          null,
          ['type' => 'error']
        )
      );
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
      $output[] = "- {$item['title']}" . (!empty($item['url']) ? " ({$item['url']})" : "");
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
      } elseif (isset($item['topic'])) {
        $output[] = "- {$item['topic']}: {$item['rationale']}";
      } elseif (isset($item['content_type'])) {
        $output[] = "- {$item['content_type']}: {$item['description']}";
      } elseif (isset($item['signal'])) {
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
      // Get site data for context
      $site_structure = $this->contentAnalyzer->getSiteStructure();
      $sitemap_urls = $this->contentAnalyzer->getSitemapUrls();
      $front_page_content = $this->getFrontPageContent();

      // Get the prompt configuration
      $config = $this->config('ai_content_strategy.prompts');
      $prompts = $config->get('add_more_recommendations');
      
      if (!isset($prompts[$section])) {
        throw new \InvalidArgumentException('Invalid section specified');
      }

      // Get existing recommendations
      $stored_data = $this->keyValue->get(self::KV_KEY);
      $existing_recommendations = '';
      if ($stored_data && isset($stored_data['data'][$section])) {
        $existing_recommendations = $this->formatExistingRecommendations($stored_data['data'][$section]);
      }

      // Prepare the context variables
      $context = [
        'homepage' => [
          'title' => $site_structure['homepage']['title'],
          'content' => $front_page_content,
        ],
        'navigation' => $this->formatMenuItems($site_structure['primary_menu']),
        'content_urls' => $this->formatUrls($sitemap_urls['urls']),
        'existing_recommendations' => $existing_recommendations,
      ];

      // Get default provider and model
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $provider = $this->aiProvider->createInstance($defaults['provider_id']);

      // Create chat input with proper format
      $messages = new ChatInput([
        new ChatMessage('system', $this->replaceTokens($prompts[$section]['system'], $context)),
        new ChatMessage('user', $this->replaceTokens($prompts[$section]['user'], $context)),
      ]);

      // Generate recommendations
      $chat_response = $provider->chat($messages, $defaults['model_id'], ['content_strategy']);
      $message = $chat_response->getNormalized();
      
      // Use the prompt JSON decoder to parse the response
      $data = $this->promptJsonDecoder->decode($message);
      
      if (!isset($data[$section])) {
        throw new \RuntimeException('Invalid response format from AI provider');
      }

      // Update stored recommendations
      if ($stored_data && isset($stored_data['data'][$section])) {
        $stored_data['data'][$section] = array_merge(
          $stored_data['data'][$section],
          $data[$section]
        );
        $stored_data['timestamp'] = \Drupal::time()->getCurrentTime();
        $this->keyValue->set(self::KV_KEY, $stored_data);
      }

      // Build the render array for new recommendations
      $build = [
        '#theme' => 'ai_content_strategy_recommendations_items',
        '#items' => $data[$section],
        '#section' => $section,
        '#section_config' => [
          'title' => $this->getSectionTitle($section),
          'item_key' => $this->getSectionItemKey($section),
          'description_key' => $this->getSectionDescriptionKey($section),
        ],
        '#button_text' => $this->config('ai_content_strategy.settings')->get('button_text')['main'],
      ];

      // Render the new recommendations
      $html = $this->renderer->render($build);

      // Update the recommendations section
      $response->addCommand(
        new AppendCommand(
          ".recommendation-section[data-section='$section'] .recommendation-items",
          $html
        )
      );

      // Update the last run time
      $response->addCommand(
        new HtmlCommand(
          '.last-run-time',
          $this->t('Last generated: @time ago', [
            '@time' => $this->dateFormatter->formatTimeDiffSince($stored_data['timestamp']),
          ])
        )
      );

    } catch (\Exception $e) {
      watchdog_exception('ai_content_strategy', $e);
      
      $response->addCommand(
        new MessageCommand(
          $this->t('An error occurred while generating additional recommendations: @error', [
            '@error' => $e->getMessage()
          ]),
          null,
          ['type' => 'error']
        )
      );
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
   * Gets the section title.
   *
   * @param string $section
   *   The section identifier.
   *
   * @return string
   *   The human-readable section title.
   */
  protected function getSectionTitle($section) {
    $titles = [
      'content_gaps' => $this->t('Content Gap'),
      'authority_topics' => $this->t('Authority Topic'),
      'expertise_demonstrations' => $this->t('Expertise Demonstration'),
      'trust_signals' => $this->t('Trust Signal'),
    ];
    return $titles[$section] ?? $section;
  }

  /**
   * Gets the section item key.
   *
   * @param string $section
   *   The section identifier.
   *
   * @return string
   *   The key used to identify items in this section.
   */
  protected function getSectionItemKey($section) {
    $keys = [
      'content_gaps' => 'title',
      'authority_topics' => 'topic',
      'expertise_demonstrations' => 'content_type',
      'trust_signals' => 'signal',
    ];
    return $keys[$section] ?? $section;
  }

  /**
   * Gets the section description key.
   *
   * @param string $section
   *   The section identifier.
   *
   * @return string
   *   The key used for descriptions in this section.
   */
  protected function getSectionDescriptionKey($section) {
    $keys = [
      'content_gaps' => 'description',
      'authority_topics' => 'rationale',
      'expertise_demonstrations' => 'description',
      'trust_signals' => 'implementation',
    ];
    return $keys[$section] ?? $section;
  }

} 