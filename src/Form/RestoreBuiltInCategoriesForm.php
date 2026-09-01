<?php

namespace Drupal\ai_content_strategy\Form;

use Drupal\ai_content_strategy\Service\BuiltInCategoryManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for restoring missing built-in recommendation categories.
 */
class RestoreBuiltInCategoriesForm extends ConfirmFormBase {

  /**
   * The built-in category manager.
   *
   * @var \Drupal\ai_content_strategy\Service\BuiltInCategoryManager
   */
  protected $builtInCategoryManager;

  /**
   * Constructs a RestoreBuiltInCategoriesForm.
   *
   * @param \Drupal\ai_content_strategy\Service\BuiltInCategoryManager $built_in_category_manager
   *   The built-in category manager.
   */
  public function __construct(BuiltInCategoryManager $built_in_category_manager) {
    $this->builtInCategoryManager = $built_in_category_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_content_strategy.built_in_category_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_content_strategy_restore_built_in_categories';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Restore missing built-in categories?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $missing = $this->builtInCategoryManager->getMissing();
    if (empty($missing)) {
      return $this->t('All built-in categories are already present.');
    }

    $labels = array_map(fn($data) => $data['label'], $missing);
    return $this->t('The following built-in categories will be restored with their default settings: @list', [
      '@list' => implode(', ', $labels),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.recommendation_category.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $restored = $this->builtInCategoryManager->restoreMissing();

    if ($restored > 0) {
      $this->messenger()->addMessage($this->t('Restored @count built-in categories.', ['@count' => $restored]));
    }
    else {
      $this->messenger()->addMessage($this->t('No categories needed restoring.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
