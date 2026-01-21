<?php

namespace Drupal\ai_content_strategy\Service;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for building standardized AJAX responses.
 *
 * Provides factory methods for common AJAX response patterns used
 * throughout the AI Content Strategy module.
 */
class AjaxResponseBuilder {

  use StringTranslationTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs an AjaxResponseBuilder.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Creates a new AJAX response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A new AJAX response object.
   */
  public function create(): AjaxResponse {
    return new AjaxResponse();
  }

  /**
   * Creates a success response with a message.
   *
   * @param string $message
   *   The success message.
   * @param \Drupal\Core\Ajax\AjaxResponse|null $response
   *   Optional existing response to add to.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response with message command.
   */
  public function createSuccessResponse(string $message, ?AjaxResponse $response = NULL): AjaxResponse {
    $response = $response ?? new AjaxResponse();
    $response->addCommand(new MessageCommand($message, NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Creates an error response with a message.
   *
   * @param string $message
   *   The error message.
   * @param int $status_code
   *   The HTTP status code.
   * @param \Drupal\Core\Ajax\AjaxResponse|null $response
   *   Optional existing response to add to.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response with error message.
   */
  public function createErrorResponse(string $message, int $status_code = 500, ?AjaxResponse $response = NULL): AjaxResponse {
    $response = $response ?? new AjaxResponse();
    $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error']));
    $response->setStatusCode($status_code);
    return $response;
  }

  /**
   * Adds an append command to insert HTML.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to add to.
   * @param string $selector
   *   The CSS selector.
   * @param string $html
   *   The HTML to append.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response with append command.
   */
  public function addAppendCommand(AjaxResponse $response, string $selector, string $html): AjaxResponse {
    $response->addCommand(new AppendCommand($selector, $html));
    return $response;
  }

  /**
   * Adds an HTML replace command.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to add to.
   * @param string $selector
   *   The CSS selector.
   * @param string $html
   *   The HTML to replace with.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response with HTML command.
   */
  public function addHtmlCommand(AjaxResponse $response, string $selector, string $html): AjaxResponse {
    $response->addCommand(new HtmlCommand($selector, $html));
    return $response;
  }

  /**
   * Adds a remove command.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to add to.
   * @param string $selector
   *   The CSS selector of element to remove.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response with remove command.
   */
  public function addRemoveCommand(AjaxResponse $response, string $selector): AjaxResponse {
    $response->addCommand(new RemoveCommand($selector));
    return $response;
  }

  /**
   * Adds an invoke command to call a jQuery method.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to add to.
   * @param string $selector
   *   The CSS selector.
   * @param string $method
   *   The jQuery method to call.
   * @param array $arguments
   *   Arguments to pass to the method.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response with invoke command.
   */
  public function addInvokeCommand(AjaxResponse $response, string $selector, string $method, array $arguments = []): AjaxResponse {
    $response->addCommand(new InvokeCommand($selector, $method, $arguments));
    return $response;
  }

  /**
   * Adds a command to update the timestamp display.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to add to.
   * @param int $timestamp
   *   The timestamp to display.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response with timestamp update command.
   */
  public function addTimestampCommand(AjaxResponse $response, int $timestamp): AjaxResponse {
    $html = '<strong>' . $this->t('Last generated:') . '</strong> ' .
      $this->dateFormatter->formatTimeDiffSince($timestamp);

    $response->addCommand(new HtmlCommand('.status-item--timestamp', $html));
    return $response;
  }

  /**
   * Adds a message command.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to add to.
   * @param string $message
   *   The message text.
   * @param string $type
   *   The message type (status, warning, error).
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response with message command.
   */
  public function addMessageCommand(AjaxResponse $response, string $message, string $type = 'status'): AjaxResponse {
    $response->addCommand(new MessageCommand($message, NULL, ['type' => $type]));
    return $response;
  }

  /**
   * Builds the selector for a recommendation item.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   *
   * @return string
   *   The CSS selector.
   */
  public function buildCardSelector(string $section, string $title): string {
    $escaped_title = str_replace(["'", '"'], ["\\'", '\\"'], $title);
    return ".recommendation-item[data-section='{$section}'][data-title='{$escaped_title}']";
  }

  /**
   * Builds the selector for an idea row.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   * @param int $idea_index
   *   The idea index.
   *
   * @return string
   *   The CSS selector.
   */
  public function buildIdeaRowSelector(string $section, string $title, int $idea_index): string {
    return $this->buildCardSelector($section, $title) . " tr[data-idea-index='{$idea_index}']";
  }

  /**
   * Builds the selector for a card's table body.
   *
   * @param string $section
   *   The section identifier.
   * @param string $title
   *   The card title.
   *
   * @return string
   *   The CSS selector.
   */
  public function buildTableBodySelector(string $section, string $title): string {
    return $this->buildCardSelector($section, $title) . ' .content-ideas-table tbody';
  }

}
