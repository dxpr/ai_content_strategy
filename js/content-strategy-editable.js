/**
 * @file
 * Editable field behaviors for AI Content Strategy recommendations.
 *
 * Uses Drupal's AJAX framework for proper command processing and behavior
 * attachment.
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

        // CSS icon markup (uses mask-image for color control).
        const checkmarkHTML = '<span class="field-save-indicator__checkmark"><span class="cs-icon cs-icon--checkmark" aria-hidden="true"></span></span>';
        const errorHTML = '<span class="field-save-indicator__error"><span class="cs-icon cs-icon--error" aria-hidden="true"></span></span>';

        /**
         * Creates or retrieves the field-specific save indicator.
         *
         * @returns {HTMLElement} The indicator element.
         */
        const getOrCreateIndicator = () => {
          // Check inside field first (for TD elements).
          let indicator = field.querySelector('.field-save-indicator');
          // Also check next sibling (for non-TD elements where indicator is placed after).
          if (!indicator && field.nextElementSibling?.classList.contains('field-save-indicator')) {
            indicator = field.nextElementSibling;
          }
          if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = 'field-save-indicator';
            if (field.tagName === 'TD') {
              field.appendChild(indicator);
            }
            else {
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
         * Saves the field content to the server using Drupal AJAX.
         */
        const saveEdit = () => {
          const fieldName = field.dataset.field;
          const value = field.textContent.trim();
          const ideaIndex = field.dataset.ideaIndex || null;

          showSaving();

          // Ensure element has an ID for Drupal.ajax.
          if (!field.id) {
            field.id = 'editable-' + Date.now();
          }

          // Build submit data.
          const submitData = {
            field: fieldName,
            value: value
          };
          if (ideaIndex !== null) {
            submitData.idea_index = ideaIndex;
          }

          // Use Drupal's AJAX framework.
          const ajaxObject = Drupal.ajax({
            url: Drupal.url('admin/reports/ai/content-strategy/save-card/' + section + '/' + encodeURIComponent(originalTitle)),
            base: field.id,
            element: field,
            submit: submitData,
            progress: { type: 'none' },
            success: function(response, status) {
              showSaved();

              // Update card title reference if title field was edited.
              if (fieldName === 'title') {
                card.dataset.title = value;
              }
            },
            error: function(xhr, status, error) {
              showError();
            }
          });

          ajaxObject.execute();
        };

        // Debounced save on input.
        field.addEventListener('input', () => {
          clearTimeout(saveTimeout);
          saveTimeout = setTimeout(saveEdit, 1000);
        });

        // Save on blur.
        field.addEventListener('blur', () => {
          clearTimeout(saveTimeout);
          saveEdit();
        });

        // Prevent Enter key creating new lines in title.
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
