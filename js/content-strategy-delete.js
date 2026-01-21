/**
 * @file
 * Delete handlers for AI Content Strategy recommendations.
 *
 * Uses Drupal's AJAX framework for proper command processing and behavior
 * attachment.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Delete card behavior.
   */
  Drupal.behaviors.contentStrategyDeleteCard = {
    attach: function(context, settings) {
      once('contentStrategyDeleteCard', '.delete-card-link', context).forEach((link) => {
        link.addEventListener('click', function(event) {
          event.preventDefault();

          const section = link.dataset.section;
          const title = link.dataset.title;

          const card = link.closest('.recommendation-item');
          const ideasTable = card.querySelector('.content-ideas-table tbody');
          const ideasCount = ideasTable ? ideasTable.querySelectorAll('tr').length : 0;

          // Build contextual confirmation message.
          let confirmMessage = Drupal.t('Delete "@title"?', {'@title': title});
          if (ideasCount > 0) {
            confirmMessage += '\n\n' + Drupal.t('This will permanently delete @count content idea(s).', {'@count': ideasCount});
          }
          else {
            confirmMessage += '\n\n' + Drupal.t('This recommendation has no content ideas yet.');
          }
          confirmMessage += '\n\n' + Drupal.t('This action cannot be undone.');

          if (!confirm(confirmMessage)) {
            return;
          }

          // Disable link during request.
          link.style.opacity = '0.5';
          link.style.pointerEvents = 'none';

          // Ensure element has an ID for Drupal.ajax.
          if (!link.id) {
            link.id = 'delete-card-' + Date.now();
          }

          // Use Drupal's AJAX framework - automatically processes commands
          // and attaches behaviors.
          const ajaxObject = Drupal.ajax({
            url: Drupal.url('admin/reports/ai/content-strategy/delete-card/' + section + '/' + encodeURIComponent(title)),
            base: link.id,
            element: link,
            progress: { type: 'none' },
            error: function(xhr, status, error) {
              const messages = new Drupal.Message();
              messages.add(Drupal.t('An error occurred while deleting.'), {
                type: 'error',
                id: 'content-strategy-error-' + Date.now()
              });
              link.style.opacity = '1';
              link.style.pointerEvents = 'auto';
            }
          });

          ajaxObject.execute();
        });
      });
    }
  };

  /**
   * Delete idea behavior.
   */
  Drupal.behaviors.contentStrategyDeleteIdea = {
    attach: function(context, settings) {
      once('contentStrategyDeleteIdea', '.delete-idea-link', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();

          const section = button.dataset.section;
          const title = button.dataset.title;
          const ideaIndex = button.dataset.ideaIndex;

          const row = button.closest('tr');
          const ideaCell = row.querySelector('.editable-field');
          const ideaText = ideaCell ? ideaCell.textContent.trim() : '';

          // Build contextual confirmation message.
          let confirmMessage = Drupal.t('Delete this content idea?');
          if (ideaText) {
            const truncatedText = ideaText.length > 60 ? ideaText.substring(0, 60) + '...' : ideaText;
            confirmMessage += '\n\n"' + truncatedText + '"';
          }
          confirmMessage += '\n\n' + Drupal.t('This action cannot be undone.');

          if (!confirm(confirmMessage)) {
            return;
          }

          // Disable button during request.
          button.style.opacity = '0.5';
          button.style.pointerEvents = 'none';

          // Ensure element has an ID for Drupal.ajax.
          if (!button.id) {
            button.id = 'delete-idea-' + Date.now();
          }

          // Use Drupal's AJAX framework.
          const ajaxObject = Drupal.ajax({
            url: Drupal.url('admin/reports/ai/content-strategy/delete-idea/' + section + '/' + encodeURIComponent(title) + '/' + ideaIndex),
            base: button.id,
            element: button,
            progress: { type: 'none' },
            error: function(xhr, status, error) {
              const messages = new Drupal.Message();
              messages.add(Drupal.t('An error occurred while deleting the content idea.'), {
                type: 'error',
                id: 'content-strategy-error-' + Date.now()
              });
              button.style.opacity = '1';
              button.style.pointerEvents = 'auto';
            }
          });

          ajaxObject.execute();
        });
      });
    }
  };

})(Drupal, once);
