<?php

namespace Drupal\ai_content_strategy\Form;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for restoring missing built-in recommendation categories.
 */
class RestoreBuiltInCategoriesForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RestoreBuiltInCategoriesForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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
    $missing = $this->getMissingCategories();
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
    $missing = $this->getMissingCategories();
    $storage = $this->entityTypeManager->getStorage('recommendation_category');
    $restored = 0;

    foreach ($missing as $data) {
      $category = $storage->create($data);
      $category->save();
      $restored++;
    }

    if ($restored > 0) {
      $this->messenger()->addMessage($this->t('Restored @count built-in categories.', ['@count' => $restored]));
    }
    else {
      $this->messenger()->addMessage($this->t('No categories needed restoring.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Gets config data for missing built-in categories.
   *
   * @return array
   *   Array of config data arrays, keyed by config name.
   */
  protected function getMissingCategories(): array {
    $config_path = \Drupal::service('extension.list.module')->getPath('ai_content_strategy') . '/config/install';
    $source = new FileStorage($config_path);
    $storage = $this->entityTypeManager->getStorage('recommendation_category');

    $missing = [];
    foreach ($source->listAll('ai_content_strategy.recommendation_category') as $name) {
      $data = $source->read($name);
      if (!empty($data['locked']) && !$storage->load($data['id'])) {
        $missing[$name] = $data;
      }
    }
    return $missing;
  }

}
