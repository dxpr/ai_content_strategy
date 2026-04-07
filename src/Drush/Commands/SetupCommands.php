<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drush\Attributes as CLI;

/**
 * Drush command for AI coding assistant setup.
 */
final class SetupCommands extends AcsCommandsBase {

  /**
   * Installs AI skill files to the project root.
   */
  #[CLI\Command(name: 'acs:setup-ai', aliases: ['acs-sa'])]
  #[CLI\Help(description: '[YAML] Installs AI Content Strategy skill files so coding assistants can discover acs:* commands.')]
  #[CLI\Option(name: 'host', description: 'Target: claude, agents, or all (default: all)')]
  #[CLI\Option(name: 'check', description: 'Check if installed files are up to date (no changes made)')]
  #[CLI\Usage(name: 'drush acs:setup-ai', description: 'Install for all AI tools')]
  #[CLI\Usage(name: 'drush acs:setup-ai --check', description: 'Check if skill files are up to date')]
  #[CLI\Usage(name: 'drush acs-sa --host=claude', description: 'Install for Claude Code only')]
  #[CLI\Usage(name: 'drush acs-sa --host=agents', description: 'Install for Codex/Gemini/Copilot/Cursor')]
  public function setupAi(
    array $options = [
      'host' => 'all',
      'check' => FALSE,
    ],
  ): string {
    $modulePath = $this->getModulePath();
    $projectRoot = $this->getProjectRoot();
    $host = $options['host'] ?? 'all';

    if ($modulePath === NULL) {
      return $this->error('Could not determine ai_content_strategy module path.');
    }
    if ($projectRoot === NULL) {
      return $this->error('Could not determine project root (no composer.json found).');
    }
    if (!in_array($host, ['claude', 'agents', 'all'])) {
      return $this->error('Invalid --host value. Use: claude, agents, or all.');
    }

    if ($options['check']) {
      return $this->checkSkillFiles($modulePath, $projectRoot, $host);
    }

    $results = [];
    $installClaude = in_array($host, ['claude', 'all']);
    $installAgents = in_array($host, ['agents', 'all']);

    if ($installClaude) {
      $results = array_merge($results, $this->installFile(
        $modulePath,
        $projectRoot,
        '.claude/skills/acs/SKILL.md',
      ));
    }

    if ($installAgents) {
      $results = array_merge($results, $this->installFile(
        $modulePath,
        $projectRoot,
        '.agents/skills/acs/SKILL.md',
      ));
      $results = array_merge($results, $this->installFile(
        $modulePath,
        $projectRoot,
        '.agents/skills/acs/agents/openai.yaml',
      ));
    }

    $supportedTools = [];
    if ($installClaude) {
      $supportedTools[] = 'Claude Code: .claude/skills/acs/SKILL.md';
    }
    if ($installAgents) {
      $supportedTools[] = 'Codex / Gemini / Copilot / Cursor: .agents/skills/acs/SKILL.md';
    }

    return $this->yaml([
      'success' => TRUE,
      'message' => 'AI Content Strategy skill files installed.',
      'actions' => $results,
      'supported_tools' => $supportedTools,
    ]);
  }

  /**
   * Checks if installed skill files match the module source.
   */
  protected function checkSkillFiles(string $modulePath, string $projectRoot, string $host): string {
    $files = [];
    if (in_array($host, ['claude', 'all'])) {
      $files[] = '.claude/skills/acs/SKILL.md';
    }
    if (in_array($host, ['agents', 'all'])) {
      $files[] = '.agents/skills/acs/SKILL.md';
      $files[] = '.agents/skills/acs/agents/openai.yaml';
    }

    $results = [];
    $outdated = FALSE;
    foreach ($files as $relativePath) {
      $source = $modulePath . '/' . $relativePath;
      $dest = $projectRoot . '/' . $relativePath;

      if (!file_exists($dest)) {
        $results[] = sprintf('%s — NOT INSTALLED', $relativePath);
        $outdated = TRUE;
      }
      elseif (!file_exists($source)) {
        $results[] = sprintf('%s — source missing', $relativePath);
      }
      elseif (md5_file($source) !== md5_file($dest)) {
        $results[] = sprintf('%s — OUTDATED', $relativePath);
        $outdated = TRUE;
      }
      else {
        $results[] = sprintf('%s — up to date', $relativePath);
      }
    }

    if ($outdated) {
      return $this->yaml([
        'success' => FALSE,
        'message' => 'Skill files are outdated. Run drush acs:setup-ai to update.',
        'files' => $results,
      ]);
    }

    return $this->yaml([
      'success' => TRUE,
      'message' => 'All skill files are up to date.',
      'files' => $results,
    ]);
  }

  /**
   * Copies a single file from module to project root.
   */
  protected function installFile(string $modulePath, string $projectRoot, string $relativePath): array {
    $results = [];
    $source = $modulePath . '/' . $relativePath;
    $dest = $projectRoot . '/' . $relativePath;

    if (!file_exists($source)) {
      return [sprintf('Source not found: %s', $relativePath)];
    }

    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
      mkdir($destDir, 0755, TRUE);
    }

    $action = file_exists($dest) ? 'updated' : 'installed';
    if (!copy($source, $dest)) {
      return [sprintf('Failed to copy %s', $relativePath)];
    }
    $results[] = sprintf('%s %s at %s', basename($relativePath), $action, $relativePath);

    return $results;
  }

}
