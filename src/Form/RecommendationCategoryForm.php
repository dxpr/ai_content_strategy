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

    // Vertical tabs container.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-basic',
    ];

    // Tab 1: Basic Information.
    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Information'),
      '#group' => 'tabs',
    ];

    $form['basic']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $category->label(),
      '#description' => $this->t('The human-readable name of this category.'),
      '#required' => TRUE,
    ];

    $form['basic']['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $category->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ai_content_strategy\Entity\RecommendationCategory::load',
      ],
      '#disabled' => !$category->isNew(),
      '#description' => $this->t('A unique machine name for this category. Can only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['basic']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $category->getDescription(),
      '#description' => $this->t('A brief description of this category for administrators.'),
      '#rows' => 3,
    ];

    $form['basic']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $category->status(),
      '#description' => $this->t('When enabled, this category will be included in strategy generation.'),
    ];

    $form['basic']['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $category->getWeight(),
      '#description' => $this->t('Categories with lower weights are displayed first.'),
    ];

    // Tab 2: Field Configuration.
    $form['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Field Configuration'),
      '#group' => 'tabs',
    ];

    $field_mapping = $category->getFieldMapping();

    $form['fields']['description'] = [
      '#markup' => '<p>' . $this->t('Define the field names that the AI will use in its response. These determine the structure of the JSON output for this category.') . '</p>',
    ];

    $form['fields']['field_mapping'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field Mapping'),
      '#tree' => TRUE,
    ];

    $form['fields']['field_mapping']['primary_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Primary field name'),
      '#default_value' => $field_mapping['primary_field'] ?? 'title',
      '#description' => $this->t('The main identifier for each recommendation (e.g., "title", "topic", "signal").'),
      '#required' => TRUE,
    ];

    $form['fields']['field_mapping']['secondary_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secondary field name'),
      '#default_value' => $field_mapping['secondary_field'] ?? 'description',
      '#description' => $this->t('Additional details for each recommendation (e.g., "description", "rationale", "implementation").'),
      '#required' => TRUE,
    ];

    $form['fields']['field_mapping']['priority_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority field name'),
      '#default_value' => $field_mapping['priority_field'] ?? 'priority',
      '#description' => $this->t('Field for priority level (typically "priority").'),
      '#required' => TRUE,
    ];

    $form['fields']['field_mapping']['ideas_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content ideas field name'),
      '#default_value' => $field_mapping['ideas_field'] ?? 'content_ideas',
      '#description' => $this->t('Field containing array of content ideas (typically "content_ideas").'),
      '#required' => TRUE,
    ];

    // Tab 3: AI Instructions.
    $form['prompts'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Instructions'),
      '#group' => 'tabs',
    ];

    $form['prompts']['help'] = [
      '#markup' => '<p>' . $this->t('Configure the prompts that guide the AI in generating recommendations for this category. Use tokens like {homepage_title}, {homepage_content}, {primary_menu}, and {urls} for dynamic content.') . '</p>',
    ];

    $form['prompts']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#default_value' => $category->getSystemPrompt(),
      '#description' => $this->t("System-level instructions that set the AI's role and behavior."),
      '#rows' => 5,
    ];

    $form['prompts']['prompt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Main prompt template'),
      '#default_value' => $category->getPromptTemplate(),
      '#description' => $this->t('The main instructions for generating recommendations. This will be included in the overall strategy generation prompt.'),
      '#rows' => 10,
    ];

    $form['prompts']['add_more_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('"Generate more ideas" prompt'),
      '#default_value' => $category->getAddMorePrompt(),
      '#description' => $this->t('Template for generating additional content ideas for existing recommendations.'),
      '#rows' => 8,
    ];

    // Tab 4: Output Schema.
    $form['schema'] = [
      '#type' => 'details',
      '#title' => $this->t('Output Schema'),
      '#group' => 'tabs',
    ];

    $form['schema']['help'] = [
      '#markup' => '<p>' . $this->t("Define the JSON schema that validates the AI's output for this category. This ensures the AI returns data in the expected format.") . '</p>',
    ];

    $schema_json = !empty($category->getSchemaDefinition())
      ? json_encode($category->getSchemaDefinition(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
      : $this->getDefaultSchemaJson();

    $form['schema']['schema_definition'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON Schema definition'),
      '#default_value' => $schema_json,
      '#description' => $this->t("The JSON schema definition for validating this category's output. Must be valid JSON."),
      '#rows' => 20,
      '#attributes' => [
        'style' => 'font-family: monospace;',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate schema JSON.
    $schema_json = $form_state->getValue('schema_definition');
    if (!empty($schema_json)) {
      $decoded = json_decode($schema_json, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $form_state->setErrorByName('schema_definition', $this->t('Invalid JSON in schema definition: @error', [
          '@error' => json_last_error_msg(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\ai_content_strategy\Entity\RecommendationCategory $category */
    $category = $this->entity;

    // Convert schema JSON to array.
    $schema_json = $form_state->getValue('schema_definition');
    if (!empty($schema_json)) {
      $category->set('schema_definition', json_decode($schema_json, TRUE));
    }

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

  /**
   * Gets default schema JSON template.
   *
   * @return string
   *   Default JSON schema.
   */
  protected function getDefaultSchemaJson(): string {
    $default = [
      'type' => 'array',
      'items' => [
        'type' => 'object',
        'required' => ['title', 'description', 'priority'],
        'properties' => [
          'title' => ['type' => 'string'],
          'description' => ['type' => 'string'],
          'priority' => [
            'type' => 'string',
            'enum' => ['high', 'medium', 'low'],
          ],
          'content_ideas' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
          ],
        ],
      ],
    ];

    return json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }

}
