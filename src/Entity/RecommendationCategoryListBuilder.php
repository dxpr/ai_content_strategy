<?php

namespace Drupal\ai_content_strategy\Entity;

use Drupal\Core\Link;
use Drupal\ai_content_strategy\Service\CategorySchemaBuilder;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Recommendation Categories with drag-drop ordering.
 */
class RecommendationCategoryListBuilder extends DraggableListBuilder {

  /**
   * The category schema builder service.
   *
   * @var \Drupal\ai_content_strategy\Service\CategorySchemaBuilder
   */
  protected $categorySchemaBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new RecommendationCategoryListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\ai_content_strategy\Service\CategorySchemaBuilder $category_schema_builder
   *   The category schema builder service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, CategorySchemaBuilder $category_schema_builder, AccountProxyInterface $current_user) {
    parent::__construct($entity_type, $storage);
    $this->categorySchemaBuilder = $category_schema_builder;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('ai_content_strategy.category_schema_builder'),
      $container->get('current_user')
    );
  }

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
    $this->categorySchemaBuilder->invalidateCache();

    $this->messenger()->addMessage($this->t('Category order has been updated.'));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    // Add "View reports" link if user has permission.
    if ($this->currentUser->hasPermission('access ai content strategy')) {
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
