<?php

namespace Drupal\ai_content_strategy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure AI Content Strategy settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_content_strategy_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ai_content_strategy.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ai_content_strategy.settings');

    $form['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#description' => $this->t('Global system-level instructions that set the AI\'s role and behavior when generating recommendations. This applies to all categories.'),
      '#default_value' => $config->get('system_prompt') ?? $this->getDefaultSystemPrompt(),
      '#rows' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ai_content_strategy.settings')
      ->set('system_prompt', $form_state->getValue('system_prompt'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Gets the default system prompt.
   *
   * @return string
   *   The default system prompt.
   */
  protected function getDefaultSystemPrompt(): string {
    return 'You are an AI content strategist analyzing a website\'s content structure. Based on the provided site information, generate recommendations that are:
- Directly inferred from the site\'s actual content and structure
- Specific to the site\'s domain and purpose (never generic)
- Actionable and practical to implement
- Focused on improving user value and engagement';
  }

}
