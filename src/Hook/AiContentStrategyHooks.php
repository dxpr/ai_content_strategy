<?php

namespace Drupal\ai_content_strategy\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for ai_content_strategy.
 */
class AiContentStrategyHooks {
  use StringTranslationTrait;

  /**
   * Constructs the hook service.
   */
  public function __construct(
    protected DateFormatterInterface $dateFormatter,
    protected AccountProxyInterface $currentUser,
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'ai_content_strategy_recommendations' => [
        'variables' => [
          'categories' => [],
          'button_text' => [],
          'last_run' => NULL,
          'ai_configured' => TRUE,
        ],
      ],
      'ai_content_strategy_recommendations_items' => [
        'variables' => [
          'items' => [],
          'section' => NULL,
          'button_text' => [],
        ],
      ],
      'ai_content_strategy_icon' => [
        'variables' => [
          'name' => NULL,
          'class' => NULL,
          'size' => NULL,
        ],
        'template' => 'components/icon',
      ],
      'ai_content_strategy_link_display' => [
        'variables' => [
          'link' => NULL,
          'section' => NULL,
          'uuid' => NULL,
          'idea_uuid' => NULL,
        ],
        'template' => 'components/link-display',
      ],
      'ai_content_strategy_link_add_button' => [
        'variables' => [
          'section' => NULL,
          'uuid' => NULL,
          'idea_uuid' => NULL,
        ],
        'template' => 'components/link-add-button',
      ],
      'ai_content_strategy_link_input' => [
        'variables' => [
          'current_link' => '',
        ],
        'template' => 'components/link-input',
      ],
      'ai_content_strategy_idea_row' => [
        'variables' => [
          'section' => NULL,
          'uuid' => NULL,
          'idea_uuid' => NULL,
          'idea_text' => NULL,
          'idea_implemented' => FALSE,
          'idea_link' => '',
        ],
        'template' => 'components/idea-row',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_ai_content_strategy_recommendations')]
  public function preprocessAiContentStrategyRecommendations(&$variables) {
    // Get translated button texts.
    $variables['button_text'] = ai_content_strategy_get_button_texts();
    // Get last run time from state if available.
    $last_run = $this->state->get('ai_content_strategy.last_run');
    if ($last_run) {
      $variables['last_run'] = $this->dateFormatter->formatTimeDiffSince($last_run);
    }
    // Add "Configure settings" link if user has permission.
    $current_user = $this->currentUser;
    if ($current_user->hasPermission('administer ai content strategy')) {
      $settings_url = Url::fromRoute('entity.recommendation_category.collection');
      if ($settings_url->access()) {
        $link = Link::fromTextAndUrl($this->t('Configure categories'), $settings_url);
        $link = $link->toRenderable();
        $link['#attributes']['class'][] = 'button';
        $link['#attributes']['class'][] = 'button--small';
        $link['#attributes']['class'][] = 'button--primary';
        $variables['configure_link'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'form-actions',
              'configure-actions',
            ],
          ],
          '#weight' => -10,
          'link' => $link,
        ];
      }
    }
    // Add JavaScript settings (icons now use CSS mask-image).
    $variables['#attached']['drupalSettings']['aiContentStrategy'] = [
      'buttonText' => $variables['button_text'],
      'translations' => [
        'addLink' => $this->t('+ Add link'),
        'editLink' => $this->t('Edit link'),
        'save' => $this->t('Save'),
        'cancel' => $this->t('Cancel'),
        'enterUrl' => $this->t('Enter URL...'),
      ],
    ];
  }

}
