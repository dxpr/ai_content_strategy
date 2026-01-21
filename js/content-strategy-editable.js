/**
 * @file
 * Editable field behaviors for AI Content Strategy recommendations.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Editable fields behavior with auto-save and visual feedback.
   */
  Drupal.behaviors.contentStrategyEditable = {
    attach: function(context, settings) {
      once('contentStrategyEditable', '.editable-field', context).forEach((field) => {
        let saveTimeout;
        const card = field.closest('.recommendation-item');
        const section = card.dataset.section;
        const originalTitle = card.dataset.title;

        // CSS icon markup (uses mask-image for color control)
        const checkmarkHTML = '<span class="field-save-indicator__checkmark"><span class="cs-icon cs-icon--checkmark" aria-hidden="true"></span></span>';
        const errorHTML = '<span class="field-save-indicator__error"><span class="cs-icon cs-icon--error" aria-hidden="true"></span></span>';

        /**
         * Creates or retrieves the field-specific save indicator.
         *
         * @returns {HTMLElement} The indicator element.
         */
        const getOrCreateIndicator = () => {
          // Check inside field first (for TD elements)
          let indicator = field.querySelector('.field-save-indicator');
          // Also check next sibling (for non-TD elements where indicator is placed after)
          if (!indicator && field.nextElementSibling?.classList.contains('field-save-indicator')) {
            indicator = field.nextElementSibling;
          }
          if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = 'field-save-indicator';
            if (field.tagName === 'TD') {
              field.appendChild(indicator);
            } else {
              field.insertAdjacentElement('afterend', indicator);
            }
          }
          return indicator;
        };

        /**
         * Shows saving state with Drupal's throbber spinner.
         */
        const showSaving = () => {
          field.classList.add('editable-field--saving');
          field.classList.remove('editable-field--saved');
          const indicator = getOrCreateIndicator();
          indicator.innerHTML = Drupal.theme.ajaxProgressThrobber();
        };

        /**
         * Shows saved state with checkmark and green flash.
         */
        const showSaved = () => {
          field.classList.remove('editable-field--saving');
          field.classList.add('editable-field--saved');
          const indicator = getOrCreateIndicator();
          indicator.innerHTML = checkmarkHTML;

          setTimeout(() => {
            field.classList.remove('editable-field--saved');
            indicator.remove();
          }, 2000);
        };

        /**
         * Shows error state.
         */
        const showError = () => {
          field.classList.remove('editable-field--saving');
          const indicator = getOrCreateIndicator();
          indicator.innerHTML = errorHTML;

          setTimeout(() => {
            indicator.remove();
          }, 3000);
        };

        /**
         * Saves the field content to the server.
         */
        const saveEdit = () => {
          const fieldName = field.dataset.field;
          const value = field.textContent.trim();
          const ideaIndex = field.dataset.ideaIndex || null;

          showSaving();

          const formData = new FormData();
          formData.append('field', fieldName);
          formData.append('value', value);
          if (ideaIndex !== null) {
            formData.append('idea_index', ideaIndex);
          }

          const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/save-card/${section}/${encodeURIComponent(originalTitle)}`;

          fetch(url, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            showSaved();

            if (fieldName === 'title') {
              card.dataset.title = value;
            }
          })
          .catch(error => {
            showError();
          });
        };

        // Debounced save on input
        field.addEventListener('input', () => {
          clearTimeout(saveTimeout);
          saveTimeout = setTimeout(saveEdit, 1000);
        });

        // Save on blur
        field.addEventListener('blur', () => {
          clearTimeout(saveTimeout);
          saveEdit();
        });

        // Prevent Enter key creating new lines in title
        if (field.dataset.field === 'title') {
          field.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              field.blur();
            }
          });
        }
      });
    }
  };

})(Drupal, once);
