<?php

namespace Drupal\ai_content_strategy\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai_content_strategy\Entity\RecommendationCategory;

/**
 * Denies access to the delete form for locked (built-in) categories.
 */
class RecommendationCategoryDeleteAccess {

  /**
   * Checks access for the category delete form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\ai_content_strategy\Entity\RecommendationCategory|null $recommendation_category
   *   The category entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, ?RecommendationCategory $recommendation_category = NULL) {
    if ($recommendation_category && $recommendation_category->isLocked()) {
      return AccessResult::forbidden('Built-in categories cannot be deleted.')
        ->addCacheableDependency($recommendation_category);
    }

    return AccessResult::allowedIfHasPermission($account, 'administer ai content strategy')
      ->addCacheableDependency($recommendation_category);
  }

}
