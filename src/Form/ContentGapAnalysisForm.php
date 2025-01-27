<?php

namespace Drupal\ai_content_strategy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_content_strategy\Service\ContentAnalyzer;
use Drupal\ai_content_strategy\Service\GapAnalyzer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for content gap analysis.
 */
class ContentGapAnalysisForm extends FormBase {

  /**
   * The content analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\ContentAnalyzer
   */
  protected $contentAnalyzer;

  /**
   * The gap analyzer service.
   *
   * @var \Drupal\ai_content_strategy\Service\GapAnalyzer
   */
  protected $gapAnalyzer;

  /**
   * Constructs a ContentGapAnalysisForm object.
   *
   * @param \Drupal\ai_content_strategy\Service\ContentAnalyzer $content_analyzer
   *   The content analyzer service.
   * @param \Drupal\ai_content_strategy\Service\GapAnalyzer $gap_analyzer
   *   The gap analyzer service.
   */
  public function __construct(
    ContentAnalyzer $content_analyzer,
    GapAnalyzer $gap_analyzer
  ) {
    $this->contentAnalyzer = $content_analyzer;
    $this->gapAnalyzer = $gap_analyzer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_content_strategy.content_analyzer'),
      $container->get('ai_content_strategy.gap_analyzer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_content_strategy_gap_analysis_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['competitor_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Competitor Website URL'),
      '#description' => $this->t('Enter the URL of the competitor website to analyze (e.g., https://example.com)'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Analyze Content Gap'),
    ];

    // Display results if available
    $results = $form_state->get('analysis_results');
    if ($results) {
      $form['results'] = [
        '#type' => 'details',
        '#title' => $this->t('Analysis Results'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];

      foreach ($results['gaps'] as $priority => $gaps) {
        $form['results'][$priority] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Priority: @priority', ['@priority' => ucfirst($priority)]),
        ];

        $form['results'][$priority]['items'] = [
          '#theme' => 'item_list',
          '#items' => $gaps,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $competitor_url = $form_state->getValue('competitor_url');
    
    try {
      $results = $this->gapAnalyzer->analyzeContentGap($competitor_url);
      $form_state->set('analysis_results', $results);
      $form_state->setRebuild();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error analyzing content gap: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

} 