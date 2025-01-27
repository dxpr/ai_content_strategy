<?php

namespace Drupal\ai_content_strategy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_content_strategy\Service\CompetitorAnalyzer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Controller for competitor analysis functionality.
 */
class CompetitorAnalysisController extends ControllerBase {

  /**
   * Cache ID prefix for competitor analysis.
   */
  const CACHE_ID_PREFIX = 'ai_content_strategy.competitor_analysis.';

  /**
   * The competitor analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\CompetitorAnalyzer
   */
  protected $competitorAnalyzer;

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
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a CompetitorAnalysisController object.
   */
  public function __construct(
    CompetitorAnalyzer $competitor_analyzer,
    RendererInterface $renderer,
    CacheBackendInterface $cache,
    DateFormatterInterface $date_formatter,
    FormBuilderInterface $form_builder
  ) {
    $this->competitorAnalyzer = $competitor_analyzer;
    $this->renderer = $renderer;
    $this->cache = $cache;
    $this->dateFormatter = $date_formatter;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_content_strategy.competitor_analyzer'),
      $container->get('renderer'),
      $container->get('cache.default'),
      $container->get('date.formatter'),
      $container->get('form_builder')
    );
  }

  /**
   * Displays the competitor analysis form and results.
   */
  public function analyze() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['competitor-analysis']],
    ];
    
    // Add description
    $build['description'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['competitor-analysis-description']],
      'content' => [
        '#markup' => $this->t('AI-powered competitor analysis based on website structure comparison.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
    ];

    // Add the competitor URL form
    $build['form'] = $this->formBuilder->getForm('Drupal\ai_content_strategy\Form\CompetitorAnalysisForm');

    return $build;
  }

  /**
   * AJAX callback to generate competitor analysis.
   */
  public function generateAnalysisAjax(Request $request) {
    try {
      // Get competitor URL from request
      $competitor_url = $request->query->get('url');
      if (!$competitor_url) {
        throw new \RuntimeException('No competitor URL provided');
      }

      // Debug container for raw messages
      $debug = [];
      
      // Generate analysis and capture debug info
      try {
        $analysis = $this->competitorAnalyzer->generateAnalysis($competitor_url);
        $debug[] = "Raw analysis array:\n" . print_r($analysis, TRUE);
      }
      catch (\Exception $e) {
        $debug[] = "Error generating analysis:\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
        throw $e;
      }
      
      // Cache the results with timestamp
      $timestamp = (int) \Drupal::time()->getCurrentTime();
      $cache_id = self::CACHE_ID_PREFIX . md5($competitor_url);
      $this->cache->set($cache_id, [
        'data' => $analysis,
        'timestamp' => $timestamp,
        'url' => $competitor_url,
      ], CacheBackendInterface::CACHE_PERMANENT);
      
      // Build the response HTML
      $build = [
        '#theme' => 'ai_content_strategy_competitor_analysis',
        '#competitor_url' => $competitor_url,
        '#content_gaps' => $analysis['content_gaps'] ?? [],
        '#competitive_advantages' => $analysis['competitive_advantages'] ?? [],
        '#improvement_opportunities' => $analysis['improvement_opportunities'] ?? [],
        '#structural_insights' => $analysis['structural_insights'] ?? [],
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
        'last_run' => $this->t('Last analyzed: @time ago', [
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

} 