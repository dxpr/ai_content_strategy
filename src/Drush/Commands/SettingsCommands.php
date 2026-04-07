<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;

/**
 * Drush commands for managing AI Content Strategy settings.
 */
class SettingsCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Gets current global settings.
   */
  #[CLI\Command(name: 'acs:settings:get', aliases: ['acs-sg', 'acs:s:get'])]
  #[CLI\Help(description: '[YAML] View current global settings (system prompt).')]
  #[CLI\Usage(name: 'drush acs:settings:get', description: 'View settings')]
  public function getSettings(): string {
    $this->switchToAdmin();

    $config = $this->configFactory->get('ai_content_strategy.settings');

    return $this->success('Settings retrieved.', [
      'settings' => [
        'system_prompt' => $config->get('system_prompt') ?? '',
      ],
    ]);
  }

  /**
   * Updates the global system prompt.
   */
  #[CLI\Command(name: 'acs:settings:set', aliases: ['acs-ss', 'acs:s:set'])]
  #[CLI\Option(name: 'system-prompt', description: 'New system prompt text')]
  #[CLI\Option(name: 'dry-run', description: 'Validate without saving')]
  #[CLI\Help(description: '[YAML] Update the global system prompt.')]
  #[CLI\Usage(name: 'drush acs:settings:set --system-prompt="You are an expert content strategist..."', description: 'Update system prompt')]
  #[CLI\Usage(name: 'drush acs:settings:set --system-prompt="$(cat prompt.txt)"', description: 'Load prompt from file')]
  public function setSettings(array $options = ['system-prompt' => '', 'dry-run' => FALSE]): string {
    $this->switchToAdmin();

    if (empty($options['system-prompt'])) {
      return $this->noChanges();
    }

    if ((bool) $options['dry-run']) {
      return $this->success('Dry run: settings would be updated.', [
        'dry_run' => TRUE,
        'system_prompt_length' => strlen($options['system-prompt']),
      ]);
    }

    try {
      $config = $this->configFactory->getEditable('ai_content_strategy.settings');
      $config->set('system_prompt', $options['system-prompt']);
      $config->save();

      return $this->success('Settings updated.', [
        'system_prompt_length' => strlen($options['system-prompt']),
      ]);
    }
    catch (\Exception $e) {
      return $this->error('Failed to update settings.', [$e->getMessage()]);
    }
  }

}
