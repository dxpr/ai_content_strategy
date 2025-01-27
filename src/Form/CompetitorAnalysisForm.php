<?php

namespace Drupal\ai_content_strategy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for competitor analysis.
 */
class CompetitorAnalysisForm extends FormBase {

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
   * Constructs a CompetitorAnalysisForm object.
   */
  public function __construct(
    CacheBackendInterface $cache,
    DateFormatterInterface $date_formatter
  ) {
    $this->cache = $cache;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'competitor_analysis_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'competitor-analysis-form';
    
    $form['competitor_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Competitor Website URL'),
      '#description' => $this->t('Enter the full URL of the competitor website to analyze (e.g., https://example.com)'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'https://',
      ],
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['competitor-analysis-actions']],
    ];

    $form['actions']['analyze'] = [
      '#type' => 'button',
      '#value' => $this->t('Analyze Competitor'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'analyze-competitor'],
      ],
      '#attached' => [
        'library' => [
          'ai_content_strategy/competitor_analysis',
        ],
      ],
    ];

    // Container for analysis results
    $form['results'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['competitor-analysis-results']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form submission is handled via AJAX
  }

} 