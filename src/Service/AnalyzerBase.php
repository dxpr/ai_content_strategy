<?php

namespace Drupal\ai_content_strategy\Service;

/**
 * Base class for analyzer services.
 */
abstract class AnalyzerBase {

  /**
   * Formats URLs for AI prompt.
   *
   * @param array $urls
   *   Array of URLs to format.
   *
   * @return string
   *   Formatted URLs string.
   */
  protected function formatUrls(array $urls): string {
    return implode("\n", array_map(function ($url) {
      return "- $url";
    }, $urls));
  }

  /**
   * Formats menu items for AI prompt.
   *
   * @param array $menu_items
   *   Array of menu items.
   *
   * @return string
   *   Formatted menu items string.
   */
  protected function formatMenuItems(array $menu_items): string {
    $output = [];
    foreach ($menu_items as $item) {
      $output[] = "- {$item['title']} ({$item['url']})";
    }
    return implode("\n", $output);
  }

} 