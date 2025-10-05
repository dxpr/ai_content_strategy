<?php

namespace Drupal\ai_content_strategy\Entity;

use Drupal\Core\Link;
use Drupal\ai_content_strategy\Service\CategorySchemaBuilder;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Recommendation Categories with drag-drop ordering.
 */
class RecommendationCategoryListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recommendation_category_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Category');
    $header['status'] = $this->t('Status');
    $header['field_mapping'] = $this->t('Instructions');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ai_content_strategy\Entity\RecommendationCategory $entity */
    $row['label'] = $entity->label();

    // Status with visual indicator.
    $row['status'] = [
      '#markup' => $entity->status()
        ? '<span style="color: green;">✓ ' . $this->t('Enabled') . '</span>'
        : '<span style="color: #999;">○ ' . $this->t('Disabled') . '</span>',
    ];

    // Instructions summary.
    $instructions = $entity->getInstructions();
    $row['field_mapping'] = [
      '#markup' => !empty($instructions)
        ? '<small>' . $this->t('@text...', ['@text' => substr($instructions, 0, 60)]) . '</small>'
        : '<em>' . $this->t('Not configured') . '</em>',
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Invalidate schema cache when order changes.
    $schema_builder = \Drupal::service('ai_content_strategy.category_schema_builder');
    if ($schema_builder instanceof CategorySchemaBuilder) {
      $schema_builder->invalidateCache();
    }

    $this->messenger()->addMessage($this->t('Category order has been updated.'));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    // Add "View reports" link if user has permission.
    $current_user = \Drupal::currentUser();
    if ($current_user->hasPermission('access ai content strategy')) {
      $reports_url = Url::fromRoute('ai_content_strategy.recommendations');
      if ($reports_url->access()) {
        $link = Link::fromTextAndUrl($this->t('View reports'), $reports_url);
        $link = $link->toRenderable();
        $link['#attributes']['class'][] = 'button';
        $link['#attributes']['class'][] = 'button--small';
        $link['#attributes']['class'][] = 'button--primary';

        $build['actions_top'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-actions']],
          '#weight' => -10,
          'report_link' => $link,
        ];
      }
    }

    // Add helpful description.
    $build['description'] = [
      '#markup' => '<p>' . $this->t('Manage content strategy recommendation categories. Drag categories to reorder them.') . '</p>',
      '#weight' => -9,
    ];

    // Add link to add new category.
    $build['table']['#empty'] = $this->t('No recommendation categories available. <a href="@add-url">Add a category</a>.', [
      '@add-url' => Url::fromRoute('entity.recommendation_category.add_form')->toString(),
    ]);

    return $build;
  }

}
