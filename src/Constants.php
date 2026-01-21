<?php

namespace Drupal\ai_content_strategy;

/**
 * Defines constants for the AI Content Strategy module.
 *
 * Centralizes magic strings to improve maintainability and reduce
 * duplication across the codebase.
 */
final class Constants {

  /**
   * CSS selectors used in AJAX responses.
   */
  const SELECTOR_RECOMMENDATIONS = '.content-strategy-recommendations';
  const SELECTOR_GENERATE_BUTTON = '.generate-recommendations';
  const SELECTOR_ACTIONS = '.content-strategy-actions';
  const SELECTOR_STATUS = '.content-strategy-status';
  const SELECTOR_WRAPPER = '.recommendations-wrapper';
  const SELECTOR_EMPTY_STATE = '.empty-recommendations';
  const SELECTOR_TIMESTAMP = '.status-item--timestamp';

  /**
   * Field names for editable content.
   */
  const FIELD_TITLE = 'title';
  const FIELD_DESCRIPTION = 'description';
  const FIELD_CONTENT_IDEAS = 'content_ideas';
  const FIELD_IMPLEMENTED = 'implemented';
  const FIELD_LINK = 'link';

  /**
   * CSS class names.
   */
  const CLASS_RECOMMENDATION_ITEM = 'recommendation-item';
  const CLASS_RECOMMENDATION_ITEMS = 'recommendation-items';
  const CLASS_RECOMMENDATION_SECTION = 'recommendation-section';
  const CLASS_EMPTY_CATEGORY = 'empty-category-state';
  const CLASS_ADD_MORE_WRAPPER = 'add-more-recommendations-wrapper';
  const CLASS_ADD_MORE_LINK = 'add-more-recommendations-link';

  /**
   * Data attributes.
   */
  const DATA_SECTION = 'data-section';
  const DATA_TITLE = 'data-title';
  const DATA_IDEA_INDEX = 'data-idea-index';
  const DATA_HAS_EXISTING = 'data-has-existing';

  /**
   * Key-value storage keys.
   */
  const KV_COLLECTION = 'ai_content_strategy.recommendations';
  const KV_KEY = 'recommendations';

  /**
   * Icon names.
   */
  const ICON_TRASH = 'trash';
  const ICON_EDIT = 'edit';
  const ICON_CHECKMARK = 'checkmark';
  const ICON_ERROR = 'error';

  /**
   * Priority levels.
   */
  const PRIORITY_HIGH = 'high';
  const PRIORITY_MEDIUM = 'medium';
  const PRIORITY_LOW = 'low';

  /**
   * Private constructor to prevent instantiation.
   */
  private function __construct() {}

}
