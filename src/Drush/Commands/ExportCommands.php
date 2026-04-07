<?php

declare(strict_types=1);

namespace Drupal\ai_content_strategy\Drush\Commands;

use Drupal\ai_content_strategy\Entity\RecommendationCategory;
use Drupal\ai_content_strategy\Service\RecommendationStorageService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;

/**
 * Drush commands for exporting AI Content Strategy data.
 */
class ExportCommands extends AcsCommandsBase {

  public function __construct(
    protected readonly RecommendationStorageService $storage,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Exports recommendations.
   */
  #[CLI\Command(name: 'acs:export', aliases: ['acs-e'])]
  #[CLI\Option(name: 'format', description: 'Output format: yaml (default), json, csv')]
  #[CLI\Option(name: 'category', description: 'Filter by category ID')]
  #[CLI\Option(name: 'file', description: 'Write output to file instead of stdout')]
  #[CLI\Help(description: 'Export recommendations in yaml, json, or csv format. Without --file, outputs raw content (no YAML envelope) for piping. With --file, returns YAML success envelope.')]
  #[CLI\Usage(name: 'drush acs:export', description: 'Export as YAML to stdout')]
  #[CLI\Usage(name: 'drush acs:export --format=json', description: 'Export as JSON')]
  #[CLI\Usage(name: 'drush acs:export --format=csv --file=export.csv', description: 'Export as CSV to file')]
  #[CLI\Usage(name: 'drush acs:export --format=json | jq .', description: 'Pipe JSON to jq')]
  public function export(array $options = ['format' => 'yaml', 'category' => '', 'file' => '']): string {
    $this->switchToAdmin();

    $stored = $this->storage->getStoredData();
    if (!$stored || empty($stored['data'])) {
      return $this->error('No recommendations to export.', ['Use acs:generate to create recommendations first.']);
    }

    $recommendations = $stored['data'];

    // Load categories for labels.
    $category_storage = $this->entityTypeManager->getStorage('recommendation_category');
    /** @var \Drupal\ai_content_strategy\Entity\RecommendationCategory[] $categories */
    $categories = $category_storage->loadByProperties(['status' => TRUE]);
    uasort($categories, static fn(RecommendationCategory $a, RecommendationCategory $b): int => $a->getWeight() <=> $b->getWeight());

    // Filter by category if specified.
    if (!empty($options['category'])) {
      $filtered = [];
      if (isset($recommendations[$options['category']])) {
        $filtered[$options['category']] = $recommendations[$options['category']];
      }
      $recommendations = $filtered;
    }

    if (empty($recommendations)) {
      return $this->error('No recommendations match the specified filters.');
    }

    $format = $options['format'] ?: 'yaml';
    $output = match ($format) {
      'json' => $this->exportJson($recommendations, $categories, $stored),
      'csv' => $this->exportCsv($recommendations, $categories),
      default => $this->exportYaml($recommendations, $categories, $stored),
    };

    if (!empty($options['file'])) {
      $dir = dirname($options['file']);
      if (!is_dir($dir) || !is_writable($dir)) {
        return $this->error(
          sprintf('Cannot write to "%s".', $options['file']),
          ['Directory does not exist or is not writable.']
        );
      }
      $result = file_put_contents($options['file'], $output);
      if ($result === FALSE) {
        return $this->error(
          sprintf('Failed to write to "%s".', $options['file'])
        );
      }
      return $this->success(sprintf('Exported to %s.', $options['file']), [
        'format' => $format,
        'file' => $options['file'],
      ]);
    }

    return $output;
  }

  /**
   * Exports as YAML.
   */
  protected function exportYaml(array $recommendations, array $categories, array $stored): string {
    $data = $this->buildExportData($recommendations, $categories, $stored);
    return $this->yaml($data, 6);
  }

  /**
   * Exports as JSON.
   */
  protected function exportJson(array $recommendations, array $categories, array $stored): string {
    $data = $this->buildExportData($recommendations, $categories, $stored);
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  }

  /**
   * Exports as CSV.
   */
  protected function exportCsv(array $recommendations, array $categories): string {
    $rows = [];
    $rows[] = implode(',', ['Category', 'Title', 'Description', 'Priority', 'Idea', 'Implemented', 'Link']);

    foreach ($recommendations as $cat_id => $cards) {
      $cat_label = isset($categories[$cat_id]) ? $categories[$cat_id]->label() : $cat_id;

      foreach ($cards as $card) {
        $title = $card['title'] ?? '';
        $description = $card['description'] ?? '';
        $priority = $card['priority'] ?? 'medium';

        if (empty($card['content_ideas'])) {
          $rows[] = $this->csvRow([$cat_label, $title, $description, $priority, '', '', '']);
          continue;
        }

        foreach ($card['content_ideas'] as $idea) {
          $idea_text = is_string($idea) ? $idea : ($idea['text'] ?? '');
          $implemented = is_array($idea) && !empty($idea['implemented']) ? 'Yes' : 'No';
          $link = is_array($idea) ? ($idea['link'] ?? '') : '';
          $rows[] = $this->csvRow([$cat_label, $title, $description, $priority, $idea_text, $implemented, $link]);
        }
      }
    }

    return implode("\n", $rows);
  }

  /**
   * Builds export data structure.
   */
  protected function buildExportData(array $recommendations, array $categories, array $stored): array {
    $data = [
      'generated_at' => isset($stored['timestamp']) ? date('c', $stored['timestamp']) : NULL,
      'pages_analyzed' => $stored['pages_analyzed'] ?? NULL,
      'categories' => [],
    ];

    foreach ($recommendations as $cat_id => $cards) {
      $cat_label = isset($categories[$cat_id]) ? $categories[$cat_id]->label() : $cat_id;
      $data['categories'][$cat_id] = [
        'label' => $cat_label,
        'cards' => $cards,
      ];
    }

    return $data;
  }

  /**
   * Formats a CSV row with proper escaping.
   */
  protected function csvRow(array $fields): string {
    return implode(',', array_map(function ($field) {
      $field = (string) $field;
      // Replace embedded newlines to prevent CSV row breaks (RFC 4180).
      $field = str_replace(["\r\n", "\r", "\n"], ' ', $field);
      $field = str_replace('"', '""', $field);
      return '"' . $field . '"';
    }, $fields));
  }

}
