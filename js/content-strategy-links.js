/**
 * @file
 * Link handlers for AI Content Strategy idea links.
 *
 * Uses Drupal's AJAX framework for proper command processing and behavior
 * attachment.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Shows the link input form.
   *
   * @param {HTMLElement} linkArea - The link area element.
   * @param {string} section - Section identifier.
   * @param {string} uuid - Card UUID.
   * @param {string} ideaUuid - Idea UUID.
   * @param {string} currentLink - Current link value.
   * @param {Object} settings - drupalSettings object.
   */
  function showLinkInput(linkArea, section, uuid, ideaUuid, currentLink, settings) {
    const originalContent = linkArea.innerHTML;
    const translations = settings.aiContentStrategy?.translations || {};

    // Generate link input HTML (this stays client-side as it's temporary UI).
    const linkInputHTML = `
      <div class="idea-link-input-wrapper">
        <input type="url" class="idea-link-input form-url" placeholder="${translations.enterUrl || Drupal.t('Enter URL...')}" value="${currentLink}">
        <button type="button" class="button button--small button--primary idea-link-save">${translations.save || Drupal.t('Save')}</button>
        <button type="button" class="button button--small idea-link-cancel">${translations.cancel || Drupal.t('Cancel')}</button>
      </div>
    `;

    linkArea.innerHTML = linkInputHTML;

    const input = linkArea.querySelector('.idea-link-input');
    const saveBtn = linkArea.querySelector('.idea-link-save');
    const cancelBtn = linkArea.querySelector('.idea-link-cancel');

    input.focus();

    saveBtn.addEventListener('click', () => {
      const link = input.value.trim();

      // Disable buttons during request.
      saveBtn.disabled = true;
      cancelBtn.disabled = true;
      saveBtn.textContent = Drupal.t('Saving...');

      // Ensure we have an ID for Drupal.ajax.
      if (!saveBtn.id) {
        saveBtn.id = 'save-link-' + Date.now();
      }

      // Use Drupal's AJAX framework.
      const ajaxObject = Drupal.ajax({
        url: Drupal.url('admin/reports/ai/content-strategy/save-card/' + section + '/' + uuid),
        base: saveBtn.id,
        element: saveBtn,
        submit: {
          field: 'link',
          value: link,
          idea_uuid: ideaUuid
        },
        progress: { type: 'none' },
        error: function(xhr, status, error) {
          const messages = new Drupal.Message();
          messages.add(Drupal.t('Error saving link.'), {
            type: 'error',
            id: 'content-strategy-error-' + Date.now()
          });
          linkArea.innerHTML = originalContent;
          Drupal.attachBehaviors(linkArea);
        }
      });

      // Override the success method to attach behaviors after commands run.
      // Drupal's AJAX success handler processes commands, then calls this.
      const originalSuccess = ajaxObject.success;
      ajaxObject.success = function(response, status) {
        // Call original success to process AJAX commands (including HtmlCommand).
        originalSuccess.call(this, response, status);
        // Now attach behaviors to the updated linkArea.
        // Use setTimeout to ensure DOM is updated after HtmlCommand.
        setTimeout(() => {
          Drupal.attachBehaviors(linkArea);
        }, 0);
      };

      ajaxObject.execute();
    });

    cancelBtn.addEventListener('click', () => {
      linkArea.innerHTML = originalContent;
      Drupal.attachBehaviors(linkArea);
    });

    // Keyboard shortcuts.
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        saveBtn.click();
      } else if (e.key === 'Escape') {
        cancelBtn.click();
      }
    });
  }

  /**
   * Add link button behavior.
   */
  Drupal.behaviors.contentStrategyAddLink = {
    attach: function(context, settings) {
      once('contentStrategyAddLink', '.idea-add-link', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();
          const section = button.dataset.section;
          const uuid = button.dataset.uuid;
          const ideaUuid = button.dataset.ideaUuid;
          const linkArea = button.closest('.idea-link-area');

          showLinkInput(linkArea, section, uuid, ideaUuid, '', settings);
        });
      });
    }
  };

  /**
   * Edit link button behavior.
   */
  Drupal.behaviors.contentStrategyEditLink = {
    attach: function(context, settings) {
      once('contentStrategyEditLink', '.idea-link-edit', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();
          const section = button.dataset.section;
          const uuid = button.dataset.uuid;
          const ideaUuid = button.dataset.ideaUuid;
          const linkArea = button.closest('.idea-link-area');
          const currentLink = linkArea.querySelector('.idea-link')?.href || '';

          showLinkInput(linkArea, section, uuid, ideaUuid, currentLink, settings);
        });
      });
    }
  };

})(Drupal, once);
