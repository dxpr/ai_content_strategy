/**
 * @file
 * Implemented checkbox behavior for AI Content Strategy recommendations.
 *
 * Uses Drupal's AJAX framework for proper command processing and behavior
 * attachment.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Implemented checkbox toggle behavior.
   */
  Drupal.behaviors.contentStrategyCheckbox = {
    attach: function(context, settings) {
      once('contentStrategyCheckbox', '.idea-implemented-checkbox', context).forEach((checkbox) => {
        checkbox.addEventListener('change', function(event) {
          const section = checkbox.dataset.section;
          const title = checkbox.dataset.title;
          const ideaIndex = checkbox.dataset.ideaIndex;
          const isImplemented = checkbox.checked;

          // Optimistic UI update - apply changes immediately.
          const row = checkbox.closest('tr');
          if (isImplemented) {
            row.classList.add('idea-implemented');
          }
          else {
            row.classList.remove('idea-implemented');
          }

          // Show/hide link area based on implemented status.
          const linkArea = row.querySelector('.idea-link-area');
          if (linkArea) {
            linkArea.style.display = isImplemented ? '' : 'none';
          }

          // Disable checkbox during request.
          checkbox.disabled = true;

          // Ensure element has an ID for Drupal.ajax.
          if (!checkbox.id) {
            checkbox.id = 'checkbox-' + Date.now();
          }

          // Use Drupal's AJAX framework.
          const ajaxObject = Drupal.ajax({
            url: Drupal.url('admin/reports/ai/content-strategy/save-card/' + section + '/' + encodeURIComponent(title)),
            base: checkbox.id,
            element: checkbox,
            submit: {
              field: 'implemented',
              value: isImplemented ? '1' : '0',
              idea_index: ideaIndex
            },
            progress: { type: 'none' },
            success: function(response, status) {
              // Re-enable checkbox.
              checkbox.disabled = false;
            },
            error: function(xhr, status, error) {
              // Revert on error.
              checkbox.checked = !isImplemented;
              checkbox.disabled = false;

              if (!isImplemented) {
                row.classList.add('idea-implemented');
              }
              else {
                row.classList.remove('idea-implemented');
              }
              if (linkArea) {
                linkArea.style.display = !isImplemented ? '' : 'none';
              }

              const messages = new Drupal.Message();
              messages.add(Drupal.t('Error saving implementation status.'), {
                type: 'error',
                id: 'content-strategy-error-' + Date.now()
              });
            }
          });

          ajaxObject.execute();
        });
      });
    }
  };

})(Drupal, once);
