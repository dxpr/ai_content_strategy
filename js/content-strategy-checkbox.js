/**
 * @file
 * Implemented checkbox behavior for AI Content Strategy recommendations.
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

          // Update row styling immediately
          const row = checkbox.closest('tr');
          if (isImplemented) {
            row.classList.add('idea-implemented');
          } else {
            row.classList.remove('idea-implemented');
          }

          // Show/hide link area based on implemented status
          const linkArea = row.querySelector('.idea-link-area');
          if (linkArea) {
            linkArea.style.display = isImplemented ? '' : 'none';
          }

          // Prepare data
          const formData = new FormData();
          formData.append('field', 'implemented');
          formData.append('value', isImplemented ? '1' : '0');
          formData.append('idea_index', ideaIndex);

          const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/save-card/${section}/${encodeURIComponent(title)}`;

          fetch(url, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            // Success - checkbox state already updated
          })
          .catch(error => {
            // Revert on error
            checkbox.checked = !isImplemented;
            if (!isImplemented) {
              row.classList.add('idea-implemented');
            } else {
              row.classList.remove('idea-implemented');
            }
            if (linkArea) {
              linkArea.style.display = !isImplemented ? '' : 'none';
            }

            const messages = new Drupal.Message();
            messages.add(Drupal.t('Error saving implementation status.'), {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`
            });
          });
        });
      });
    }
  };

})(Drupal, once);
