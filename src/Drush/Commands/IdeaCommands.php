<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drush\Attributes as CLI;

/**
 * Drush commands for managing content ideas within recommendation cards.
 */
class IdeaCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly RecommendationStorageService $storage,
  ) {
    parent::__construct();
  }

  /**
   * Edits an idea's text.
   */
  #[CLI\Command(name: 'acs:idea:edit', aliases: ['acs-ie', 'acs:i:edit'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Argument(name: 'idea_uuid', description: 'Idea UUID')]
  #[CLI\Option(name: 'text', description: 'New idea text')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without saving')]
  #[CLI\Help(description: '[YAML] Edit a content idea text.')]
  #[CLI\Usage(name: 'drush acs:idea:edit content_gaps CARD-UUID IDEA-UUID --text="New idea"', description: 'Update idea text')]
  public function editIdea(
    string $section,
    string $uuid,
    string $idea_uuid,
    array $options = ['text' => '', 'dry-run' => FALSE],
  ): string {
    $this->switchToAdmin();

    if (empty($options['text'])) {
      return $this->noChanges();
    }

    // Verify card and idea exist.
    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    $idea_index = $this->storage->findIdeaIndexByUuid($section, $uuid, $idea_uuid);
    if ($idea_index === NULL) {
      return $this->notFound('Idea', $idea_uuid, 'acs:report:card');
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: idea would be updated.', [
        'dry_run' => TRUE,
        'idea' => ['uuid' => $idea_uuid, 'text' => $options['text']],
      ]);
    }

    try {
      $this->storage->updateIdeaFieldByUuid($section, $uuid, $idea_uuid, 'text', $options['text']);
      return $this->success('Idea updated.', [
        'idea' => [
          'uuid' => $idea_uuid,
          'text' => $options['text'],
        ],
      ]);
    }
    catch (\RuntimeException $e) {
      return $this->error('Failed to update idea.', [$e->getMessage()]);
    }
  }

  /**
   * Toggles idea implementation status.
   */
  #[CLI\Command(name: 'acs:idea:implement', aliases: ['acs-ii', 'acs:i:implement'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Argument(name: 'idea_uuid', description: 'Idea UUID')]
  #[CLI\Option(name: 'link', description: 'URL for the implemented content')]
  #[CLI\Option(name: 'undo', description: 'Mark idea as not implemented')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without saving')]
  #[CLI\Help(description: '[YAML] Mark idea as implemented, optionally with a URL.')]
  #[CLI\Usage(name: 'drush acs:idea:implement section CARD IDEA --link=https://example.com/page', description: 'Mark as implemented with link')]
  #[CLI\Usage(name: 'drush acs:idea:implement section CARD IDEA --undo', description: 'Undo implementation')]
  public function implementIdea(
    string $section,
    string $uuid,
    string $idea_uuid,
    array $options = [
      'link' => '',
      'undo' => FALSE,
      'dry-run' => FALSE,
    ],
  ): string {
    $this->switchToAdmin();

    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    $idea_index = $this->storage->findIdeaIndexByUuid($section, $uuid, $idea_uuid);
    if ($idea_index === NULL) {
      return $this->notFound('Idea', $idea_uuid, 'acs:report:card');
    }

    if ((bool) $options['dry-run']) {
      $action = (bool) $options['undo'] ? 'marked as not implemented' : 'marked as implemented';
      return $this->success(sprintf('Dry run: idea would be %s.', $action), [
        'dry_run' => TRUE,
        'uuid' => $idea_uuid,
      ]);
    }

    try {
      if ((bool) $options['undo']) {
        $this->storage->updateIdeaFieldByUuid($section, $uuid, $idea_uuid, 'implemented', FALSE);
        $this->storage->updateIdeaFieldByUuid($section, $uuid, $idea_uuid, 'link', '');
        return $this->success('Idea marked as not implemented.', ['uuid' => $idea_uuid]);
      }

      $this->storage->updateIdeaFieldByUuid($section, $uuid, $idea_uuid, 'implemented', TRUE);
      if (!empty($options['link'])) {
        $this->storage->updateIdeaFieldByUuid($section, $uuid, $idea_uuid, 'link', $options['link']);
      }

      return $this->success('Idea marked as implemented.', [
        'uuid' => $idea_uuid,
        'link' => $options['link'] ?: NULL,
      ]);
    }
    catch (\RuntimeException $e) {
      return $this->error('Failed to update idea.', [$e->getMessage()]);
    }
  }

  /**
   * Deletes a single content idea.
   */
  #[CLI\Command(name: 'acs:idea:delete', aliases: ['acs-id', 'acs:i:delete'])]
  #[CLI\Argument(name: 'section', description: 'Category machine name')]
  #[CLI\Argument(name: 'uuid', description: 'Card UUID')]
  #[CLI\Argument(name: 'idea_uuid', description: 'Idea UUID')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without deleting')]
  #[CLI\Help(description: '[YAML] Delete a single content idea.')]
  #[CLI\Usage(name: 'drush acs:idea:delete section CARD IDEA', description: 'Delete an idea')]
  public function deleteIdea(string $section, string $uuid, string $idea_uuid, array $options = ['dry-run' => FALSE]): string {
    $this->switchToAdmin();

    $card = $this->storage->getCardByUuid($section, $uuid);
    if (!$card) {
      return $this->notFound('Card', $uuid, 'acs:report');
    }

    $idea_index = $this->storage->findIdeaIndexByUuid($section, $uuid, $idea_uuid);
    if ($idea_index === NULL) {
      return $this->notFound('Idea', $idea_uuid, 'acs:report:card');
    }

    // Get idea text for confirmation.
    $idea_text = '';
    if (isset($card['content_ideas'][$idea_index])) {
      $idea = $card['content_ideas'][$idea_index];
      $idea_text = is_string($idea) ? $idea : ($idea['text'] ?? '');
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: idea would be deleted.', [
        'idea' => ['uuid' => $idea_uuid, 'text' => $idea_text],
      ]);
    }

    try {
      $this->storage->deleteIdeaByUuid($section, $uuid, $idea_uuid);
      return $this->success('Idea deleted.', [
        'deleted' => ['uuid' => $idea_uuid, 'text' => $idea_text],
      ]);
    }
    catch (\RuntimeException $e) {
      return $this->error('Failed to delete idea.', [$e->getMessage()]);
    }
  }

}
