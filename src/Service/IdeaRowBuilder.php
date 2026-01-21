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
   * @param string $card_uuid
   *   The card UUID.
   * @param array|string $idea
   *   The idea data (string or array with text/implemented/link/uuid).
   *
   * @return array
   *   A render array for the idea row.
   */
  public function buildRow(string $section, string $card_uuid, $idea): array {
    // Normalize idea to array format.
    if (is_string($idea)) {
      $idea = [
        'text' => $idea,
        'implemented' => FALSE,
        'link' => '',
        'uuid' => '',
      ];
    }

    $idea_text = $idea['text'] ?? $idea;
    $idea_implemented = $idea['implemented'] ?? FALSE;
    $idea_link = $idea['link'] ?? '';
    $idea_uuid = $idea['uuid'] ?? '';

    return [
      '#theme' => 'ai_content_strategy_idea_row',
      '#section' => $section,
      '#uuid' => $card_uuid,
      '#idea_uuid' => $idea_uuid,
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
   * @param string $card_uuid
   *   The card UUID.
   * @param array $ideas
   *   Array of ideas (strings or idea objects with uuid).
   *
   * @return array
   *   Array of render arrays.
   */
  public function buildRows(string $section, string $card_uuid, array $ideas): array {
    $rows = [];
    foreach ($ideas as $idea) {
      $rows[] = $this->buildRow($section, $card_uuid, $idea);
    }
    return $rows;
  }

  /**
   * Renders idea rows to HTML string.
   *
   * @param string $section
   *   The section identifier.
   * @param string $card_uuid
   *   The card UUID.
   * @param array $ideas
   *   Array of ideas (must have uuid property).
   *
   * @return string
   *   The rendered HTML.
   */
  public function renderRows(string $section, string $card_uuid, array $ideas): string {
    $rows = $this->buildRows($section, $card_uuid, $ideas);
    return (string) $this->renderer->renderRoot($rows);
  }

  /**
   * Builds a render array for the link area.
   *
   * @param string $section
   *   The section identifier.
   * @param string $card_uuid
   *   The card UUID.
   * @param string $idea_uuid
   *   The idea UUID.
   * @param string $link
   *   The link URL (empty if no link).
   * @param bool $implemented
   *   Whether the idea is implemented.
   *
   * @return array
   *   A render array for the link area.
   */
  public function buildLinkArea(string $section, string $card_uuid, string $idea_uuid, string $link, bool $implemented = TRUE): array {
    if ($link) {
      return [
        '#theme' => 'ai_content_strategy_link_display',
        '#link' => $link,
        '#section' => $section,
        '#uuid' => $card_uuid,
        '#idea_uuid' => $idea_uuid,
      ];
    }
    else {
      return [
        '#theme' => 'ai_content_strategy_link_add_button',
        '#section' => $section,
        '#uuid' => $card_uuid,
        '#idea_uuid' => $idea_uuid,
      ];
    }
  }

  /**
   * Renders link area to HTML string.
   *
   * @param string $section
   *   The section identifier.
   * @param string $card_uuid
   *   The card UUID.
   * @param string $idea_uuid
   *   The idea UUID.
   * @param string $link
   *   The link URL.
   * @param bool $implemented
   *   Whether the idea is implemented.
   *
   * @return string
   *   The rendered HTML.
   */
  public function renderLinkArea(string $section, string $card_uuid, string $idea_uuid, string $link, bool $implemented = TRUE): string {
    $build = $this->buildLinkArea($section, $card_uuid, $idea_uuid, $link, $implemented);
    return (string) $this->renderer->renderRoot($build);
  }

}
