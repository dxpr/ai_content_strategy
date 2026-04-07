<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drush\Attributes as CLI;

/**
 * Drush commands for managing recommendation cards.
 */
class CardCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly RecommendationStorageService $storage,
  ) {
    parent::__construct();
  }

  /**
   * Edits a recommendation card's title and/or description.
   */
  #[CLI\Command(name: 'acs:card:edit', aliases: ['acs-ce', 'acs:c:edit'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Option(name: 'title', description: 'New card title')]
  #[CLI\Option(name: 'description', description: 'New card description')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without saving')]
  #[CLI\Help(description: '[YAML] Edit a recommendation card title and/or description.')]
  #[CLI\Usage(name: 'drush acs:card:edit content_gaps UUID --title="New Title"', description: 'Update card title')]
  #[CLI\Usage(name: 'drush acs:card:edit content_gaps UUID --title="New Title" --dry-run', description: 'Preview edit')]
  public function editCard(
    string $section,
    string $uuid,
    array $options = [
      'title' => '',
      'description' => '',
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    if (empty($options['title']) && empty($options['description'])) {
      return $this->noChanges();
    }

    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: card would be updated.', [
        'dry_run' => TRUE,
        'card' => [
          'uuid' => $uuid,
          'title' => !empty($options['title']) ? $options['title'] : ($card['title'] ?? ''),
          'description' => !empty($options['description']) ? $options['description'] : ($card['description'] ?? ''),
        ],
      ]);
    }

    try {
      if (!empty($options['title'])) {
        $this->storage->updateCardFieldByUuid($section, $uuid, 'title', $options['title']);
      }
      if (!empty($options['description'])) {
        $this->storage->updateCardFieldByUuid($section, $uuid, 'description', $options['description']);
      }

      $updated = $this->storage->getCardByUuid($section, $uuid);

      return $this->success('Card updated.', [
        'card' => [
          'uuid' => $uuid,
          'title' => $updated['title'] ?? '',
          'description' => $updated['description'] ?? '',
        ],
      ]);
    }
    catch (\RuntimeException $e) {
      return $this->error('Failed to update card.', [$e->getMessage()]);
    }
  }

  /**
   * Deletes a recommendation card.
   */
  #[CLI\Command(name: 'acs:card:delete', aliases: ['acs-cd', 'acs:c:delete'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without deleting')]
  #[CLI\Help(description: '[YAML] Delete a recommendation card.')]
  #[CLI\Usage(name: 'drush acs:card:delete content_gaps UUID', description: 'Delete a card')]
  #[CLI\Usage(name: 'drush acs:card:delete content_gaps UUID --dry-run', description: 'Preview deletion')]
  public function deleteCard(string $section, string $uuid, array $options = ['dry-run' => FALSE]): string {
    $this->switchToAdmin();

    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: card would be deleted.', [
        'card' => [
          'uuid' => $uuid,
          'title' => $card['title'] ?? '',
        ],
      ]);
    }

    try {
      $this->storage->deleteCardByUuid($section, $uuid);
      return $this->success('Card deleted.', [
        'deleted' => [
          'uuid' => $uuid,
          'title' => $card['title'] ?? '',
        ],
      ]);
    }
    catch (\RuntimeException $e) {
      return $this->error('Failed to delete card.', [$e->getMessage()]);
    }
  }

}
