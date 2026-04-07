<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for all AI Content Strategy Drush commands.
 *
 * Provides admin user switching, YAML output formatting, validation
 * helpers, and result sanitization. Mirrors WebmasterCommandsBase
 * from the drush_webmaster module.
 */
abstract class AcsCommandsBase extends DrushCommands {

  /**
   * Switches to admin user for the duration of the Drush process.
   *
   * Does not call switchBack() — relies on process termination after
   * command execution.
   */
  protected function switchToAdmin(): void {
    // @phpstan-ignore-next-line
    if (!\Drupal::hasContainer()) {
      return;
    }

    try {
      /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
      // @phpstan-ignore-next-line
      $account_switcher = \Drupal::service('account_switcher');
      /** @var \Drupal\user\UserStorageInterface $user_storage */
      // @phpstan-ignore-next-line
      $user_storage = \Drupal::entityTypeManager()->getStorage('user');
      $admin = $user_storage->load(1);

      if ($admin) {
        $account_switcher->switchTo($admin);
      }
    }
    catch (\Exception $e) {
      // Silently fail if services aren't available yet.
    }
  }

  /**
   * Gets the module path.
   */
  protected function getModulePath(): ?string {
    try {
      // @phpstan-ignore-next-line
      $path = \Drupal::service('extension.list.module')->getPath('ai_content_strategy');
      return $path ? DRUPAL_ROOT . '/' . $path : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Gets the Composer project root.
   */
  protected function getProjectRoot(): ?string {
    $dir = defined('DRUPAL_ROOT') ? DRUPAL_ROOT : getcwd();
    for ($i = 0; $i < 5; $i++) {
      if (file_exists($dir . '/composer.json')) {
        return $dir;
      }
      $parent = dirname($dir);
      if ($parent === $dir) {
        break;
      }
      $dir = $parent;
    }
    return NULL;
  }

  /**
   * Outputs data as YAML.
   *
   * @param array $data
   *   The data to output.
   * @param int $inline
   *   Level at which to switch to inline YAML (default 4).
   *
   * @return string
   *   YAML string.
   */
  protected function yaml(array $data, int $inline = 4): string {
    return Yaml::dump($data, $inline, 2);
  }

  /**
   * Returns a success response as YAML.
   *
   * @param string $message
   *   Success message.
   * @param array $data
   *   Additional data to include.
   *
   * @return string
   *   YAML string.
   */
  protected function success(string $message, array $data = []): string {
    return $this->yaml(array_merge([
      'success' => TRUE,
      'message' => $message,
    ], $data));
  }

  /**
   * Returns an error response as YAML.
   *
   * @param string $message
   *   Error message.
   * @param array $errors
   *   Array of error details.
   *
   * @return string
   *   YAML string.
   */
  protected function error(string $message, array $errors = []): string {
    $data = [
      'success' => FALSE,
      'message' => $message,
    ];
    if (!empty($errors)) {
      $data['errors'] = $errors;
    }
    return $this->yaml($data);
  }

  /**
   * Returns "not found" error response.
   *
   * @param string $type
   *   The entity/item type.
   * @param string $id
   *   The identifier that was not found.
   * @param string $listCmd
   *   The list command to suggest.
   *
   * @return string
   *   YAML error response.
   */
  protected function notFound(string $type, string $id, string $listCmd): string {
    return $this->error(
      sprintf('%s "%s" not found.', $type, $id),
      [sprintf('Use %s to see available items.', $listCmd)]
    );
  }

  /**
   * Returns "no changes specified" error response.
   *
   * @return string
   *   YAML error response.
   */
  protected function noChanges(): string {
    return $this->error('No changes specified.', ['Provide at least one option.']);
  }

  /**
   * Returns validation error if errors exist.
   *
   * @param array $errors
   *   Validation errors.
   *
   * @return string|null
   *   YAML error response, or NULL if no errors.
   */
  protected function validationError(array $errors): ?string {
    return empty($errors) ? NULL : $this->error('Validation failed.', $errors);
  }

  /**
   * Returns success response with items list.
   *
   * @param array $items
   *   The items array.
   * @param array $extra
   *   Extra data to include.
   *
   * @return string
   *   YAML success response.
   */
  protected function successList(array $items, array $extra = []): string {
    return $this->yaml(array_merge([
      'success' => TRUE,
      'count' => count($items),
      'items' => $items,
    ], $extra));
  }

}
