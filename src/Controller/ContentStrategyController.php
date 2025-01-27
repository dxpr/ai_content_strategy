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

/**
 * Controller for content strategy functionality.
 */
class ContentStrategyController extends ControllerBase {

  /**
   * Cache ID for content strategy recommendations.
   */
  const CACHE_ID = 'ai_content_strategy.recommendations';

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
   */
  public function __construct(
    StrategyGenerator $strategy_generator,
    ContentAnalyzer $content_analyzer,
    AiProviderPluginManager $ai_provider,
    PromptJsonDecoderInterface $prompt_json_decoder,
    RendererInterface $renderer,
    CacheBackendInterface $cache,
    DateFormatterInterface $date_formatter
  ) {
    $this->strategyGenerator = $strategy_generator;
    $this->contentAnalyzer = $content_analyzer;
    $this->aiProvider = $ai_provider;
    $this->promptJsonDecoder = $prompt_json_decoder;
    $this->renderer = $renderer;
    $this->cache = $cache;
    $this->dateFormatter = $date_formatter;
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
      $container->get('date.formatter')
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
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['content-strategy-recommendations']],
    ];
    
    // Add description
    $build['description'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['content-strategy-description']],
      'content' => [
        '#markup' => $this->t('AI-powered content strategy recommendations based on your site structure and EEAT principles.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
    ];

    // Check for cached data
    $cached = $this->cache->get(self::CACHE_ID);
    $recommendations = [];
    $last_run = NULL;
    
    if ($cached) {
      $cache_data = $cached->data;
      if (is_array($cache_data) && isset($cache_data['data'], $cache_data['timestamp'])) {
        $recommendations = $cache_data['data'];
        $last_run = (int) $cache_data['timestamp'];
      }
      else {
        // Handle legacy cache format
        $recommendations = $cache_data;
        $last_run = (int) $cached->created;
      }
    }

    // Add generate/refresh button
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['content-strategy-actions']],
      'generate' => [
        '#type' => 'button',
        '#value' => !empty($recommendations) ? $this->t('Refresh Recommendations') : $this->t('Generate Recommendations'),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'generate-recommendations'],
        ],
        '#attached' => [
          'library' => [
            'ai_content_strategy/recommendations',
          ],
        ],
      ],
    ];

    if ($last_run) {
      $build['actions']['last_run'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['last-run-time']],
        '#value' => $this->t('Last generated: @time ago', [
          '@time' => $this->dateFormatter->formatTimeDiffSince($last_run),
        ]),
      ];
    }

    // Display recommendations if available
    if (!empty($recommendations)) {
      $build['recommendations'] = [
        '#theme' => 'ai_content_strategy_recommendations',
        '#content_gaps' => $recommendations['content_gaps'] ?? [],
        '#authority_topics' => $recommendations['authority_topics'] ?? [],
        '#expertise_demonstrations' => $recommendations['expertise_demonstrations'] ?? [],
        '#trust_signals' => $recommendations['trust_signals'] ?? [],
      ];
    }
    else {
      $build['empty'] = [
        '#type' => 'markup',
        '#markup' => '<div class="empty-recommendations">' . $this->t('Click the button above to generate content recommendations.') . '</div>',
      ];
    }

    return $build;
  }

  /**
   * AJAX callback to generate recommendations.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response containing the recommendations.
   */
  public function generateRecommendationsAjax() {
    try {
      // Debug container for raw messages
      $debug = [];
      
      // Get recommendations and capture debug info
      try {
        $recommendations = $this->strategyGenerator->generateRecommendations();
        $debug[] = "Raw recommendations array:\n" . print_r($recommendations, TRUE);
      }
      catch (\Exception $e) {
        $debug[] = "Error generating recommendations:\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
        throw $e;
      }
      
      // Cache the results with timestamp
      $timestamp = (int) \Drupal::time()->getCurrentTime();
      $this->cache->set(self::CACHE_ID, [
        'data' => $recommendations,
        'timestamp' => $timestamp,
      ], CacheBackendInterface::CACHE_PERMANENT);
      
      // Build the response HTML
      $build = [
        '#theme' => 'ai_content_strategy_recommendations',
        '#content_gaps' => $recommendations['content_gaps'] ?? [],
        '#authority_topics' => $recommendations['authority_topics'] ?? [],
        '#expertise_demonstrations' => $recommendations['expertise_demonstrations'] ?? [],
        '#trust_signals' => $recommendations['trust_signals'] ?? [],
      ];
      
      try {
        $html = $this->renderer->render($build);
        $debug[] = "Rendered HTML structure:\n" . htmlspecialchars($html);
      }
      catch (\Exception $e) {
        $debug[] = "Error rendering template:\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
        throw $e;
      }

      // Wrap debug messages in pre tag
      $debug_output = '<pre class="debug-output">' . implode("\n\n", $debug) . '</pre>';

      return new JsonResponse([
        'success' => TRUE,
        'html' => $debug_output . $html,
        'last_run' => $this->t('Last generated: @time ago', [
          '@time' => $this->dateFormatter->formatTimeDiffSince($timestamp),
        ])->render(),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
        'debug' => '<pre class="debug-output">' . $e->getMessage() . "\n" . $e->getTraceAsString() . '</pre>',
      ]);
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
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response containing the new content ideas.
   */
  public function generateMore(string $section, string $title) {
    try {
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

Example response format:
[
  "First specific content idea based on site context",
  "Second specific content idea based on site context",
  "Third specific content idea based on site context",
  "Fourth specific content idea based on site context",
  "Fifth specific content idea based on site context"
]
</instructions>
EOT;

      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      // Get response
      $response = $provider->chat($messages, $defaults['model_id'], ['content_strategy'])->getNormalized();
      
      // Decode JSON response
      $decoded = $this->promptJsonDecoder->decode($response);
      
      if (is_array($decoded)) {
        return new JsonResponse([
          'success' => TRUE,
          'ideas' => $decoded,
        ]);
      }
      
      // If decoding failed, try to extract JSON array from the response text
      $text = $response->getText();
      if (preg_match('/\[(?:[^\[\]]|(?R))*\]/', $text, $matches)) {
        $json = json_decode($matches[0], TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
          return new JsonResponse([
            'success' => TRUE,
            'ideas' => $json,
          ]);
        }
      }
      
      throw new \RuntimeException('Failed to parse AI response into valid JSON');
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ]);
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

} 