<?php

namespace Drupal\ai_content_strategy\Form;

use Drupal\ai_content_strategy\Service\CategorySchemaBuilder;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for recommendation category add and edit forms.
 */
class RecommendationCategoryForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ai_content_strategy\Entity\RecommendationCategory $category */
    $category = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category name'),
      '#maxlength' => 255,
      '#default_value' => $category->label(),
      '#description' => $this->t('The name of this recommendation category (e.g., "Content Gaps", "Authority Topics").'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $category->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ai_content_strategy\Entity\RecommendationCategory::load',
      ],
      '#disabled' => !$category->isNew(),
      '#description' => $this->t('A unique machine name for this category. Can only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What should the AI analyze?'),
      '#default_value' => $category->getInstructions(),
      '#description' => $this->t('Instructions for the AI on what to analyze and recommend for this category. Be specific about what types of content or opportunities the AI should identify.'),
      '#rows' => 6,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $category->status(),
      '#description' => $this->t('When enabled, this category will be included in strategy generation.'),
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $category->getWeight(),
      '#description' => $this->t('Categories with lower weights are displayed first. Leave at 0 for default ordering.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Admin description'),
      '#default_value' => $category->getDescription(),
      '#description' => $this->t('Optional description for administrators (not used by the AI).'),
      '#rows' => 2,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate instructions are not empty.
    $instructions = trim($form_state->getValue('instructions'));
    if (empty($instructions)) {
      $form_state->setErrorByName('instructions', $this->t('AI instructions cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\ai_content_strategy\Entity\RecommendationCategory $category */
    $category = $this->entity;

    $status = $category->save();

    // Invalidate schema cache.
    $schema_builder = \Drupal::service('ai_content_strategy.category_schema_builder');
    if ($schema_builder instanceof CategorySchemaBuilder) {
      $schema_builder->invalidateCache();
    }

    $message_args = ['%label' => $category->label()];
    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created new recommendation category %label.', $message_args));
    }
    else {
      $this->messenger()->addMessage($this->t('Updated recommendation category %label.', $message_args));
    }

    $form_state->setRedirectUrl($category->toUrl('collection'));

    return $status;
  }

}
