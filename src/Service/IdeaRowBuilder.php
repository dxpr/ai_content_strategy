<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for building idea row render arrays and HTML.
 *
 * Centralizes the creation of idea row markup, ensuring consistency
 * between initial page load and AJAX-generated content.
 */
class IdeaRowBuilder {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an IdeaRowBuilder.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Builds a render array for a single idea row.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param int $idea_index
   *   The idea index.
   * @param array|string $idea
   *   The idea data (string or array with text/implemented/link).
   *
   * @return array
   *   A render array for the idea row.
   */
  public function buildRow(string $section, string $title, int $idea_index, $idea): array {
    // Normalize idea to array format.
    if (is_string($idea)) {
      $idea = [
        'text' => $idea,
        'implemented' => FALSE,
        'link' => '',
      ];
    }

    $idea_text = $idea['text'] ?? $idea;
    $idea_implemented = $idea['implemented'] ?? FALSE;
    $idea_link = $idea['link'] ?? '';

    return [
      '#theme' => 'ai_content_strategy_idea_row',
      '#section' => $section,
      '#title' => $title,
      '#idea_index' => $idea_index,
      '#idea_text' => $idea_text,
      '#idea_implemented' => $idea_implemented,
      '#idea_link' => $idea_link,
    ];
  }

  /**
   * Builds render arrays for multiple idea rows.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param array $ideas
   *   Array of ideas (strings or idea objects).
   * @param int $start_index
   *   The starting index for the rows.
   *
   * @return array
   *   Array of render arrays.
   */
  public function buildRows(string $section, string $title, array $ideas, int $start_index = 0): array {
    $rows = [];
    foreach ($ideas as $index => $idea) {
      $rows[] = $this->buildRow($section, $title, $start_index + $index, $idea);
    }
    return $rows;
  }

  /**
   * Renders idea rows to HTML string.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param array $ideas
   *   Array of ideas.
   * @param int $start_index
   *   The starting index for the rows.
   *
   * @return string
   *   The rendered HTML.
   */
  public function renderRows(string $section, string $title, array $ideas, int $start_index = 0): string {
    $rows = $this->buildRows($section, $title, $ideas, $start_index);
    $build = ['#theme' => 'container', '#children' => $rows];
    return (string) $this->renderer->renderRoot($rows);
  }

  /**
   * Builds a render array for the link area.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param int $idea_index
   *   The idea index.
   * @param string $link
   *   The link URL (empty if no link).
   * @param bool $implemented
   *   Whether the idea is implemented.
   *
   * @return array
   *   A render array for the link area.
   */
  public function buildLinkArea(string $section, string $title, int $idea_index, string $link, bool $implemented = TRUE): array {
    if ($link) {
      return [
        '#theme' => 'ai_content_strategy_link_display',
        '#link' => $link,
        '#section' => $section,
        '#title' => $title,
        '#idea_index' => $idea_index,
      ];
    }
    else {
      return [
        '#theme' => 'ai_content_strategy_link_add_button',
        '#section' => $section,
        '#title' => $title,
        '#idea_index' => $idea_index,
      ];
    }
  }

  /**
   * Renders link area to HTML string.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param int $idea_index
   *   The idea index.
   * @param string $link
   *   The link URL.
   * @param bool $implemented
   *   Whether the idea is implemented.
   *
   * @return string
   *   The rendered HTML.
   */
  public function renderLinkArea(string $section, string $title, int $idea_index, string $link, bool $implemented = TRUE): string {
    $build = $this->buildLinkArea($section, $title, $idea_index, $link, $implemented);
    return (string) $this->renderer->renderRoot($build);
  }

}
