/**
 * @file
 * Link handlers for AI Content Strategy idea links.
 */

((Drupal, once) => {
  'use strict';

  /**
   * Saves an idea link to the server.
   *
   * @param {string} section - Section identifier.
   * @param {string} title - Card title.
   * @param {string} ideaIndex - Idea index.
   * @param {string} link - The URL to save.
   * @param {HTMLElement} linkArea - The link area element.
   * @param {Object} settings - drupalSettings object.
   * @returns {Promise} Fetch promise.
   */
  function saveIdeaLink(section, title, ideaIndex, link, linkArea, settings) {
    const formData = new FormData();
    formData.append('field', 'link');
    formData.append('value', link);
    formData.append('idea_index', ideaIndex);

    const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/save-card/${section}/${encodeURIComponent(title)}`;

    const translations = settings.aiContentStrategy?.translations || {};

    return fetch(url, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (link) {
        // Use CSS icon class for edit button
        linkArea.innerHTML = `
          <a href="${link}" target="_blank" class="idea-link" data-section="${section}" data-title="${title}" data-idea-index="${ideaIndex}">${link}</a>
          <button type="button" class="idea-link-edit" data-section="${section}" data-title="${title}" data-idea-index="${ideaIndex}" title="${translations.editLink || Drupal.t('Edit link')}">
            <span class="cs-icon cs-icon--edit cs-icon--sm" aria-hidden="true"></span>
          </button>
        `;
        Drupal.attachBehaviors(linkArea);
      } else {
        linkArea.innerHTML = `<button type="button" class="idea-add-link action-link" data-section="${section}" data-title="${title}" data-idea-index="${ideaIndex}">${translations.addLink || Drupal.t('+ Add link')}</button>`;
        Drupal.attachBehaviors(linkArea);
      }
    });
  }

  /**
   * Shows the link input form.
   *
   * @param {HTMLElement} linkArea - The link area element.
   * @param {string} section - Section identifier.
   * @param {string} title - Card title.
   * @param {string} ideaIndex - Idea index.
   * @param {string} currentLink - Current link value.
   * @param {Object} settings - drupalSettings object.
   */
  function showLinkInput(linkArea, section, title, ideaIndex, currentLink, settings) {
    const originalContent = linkArea.innerHTML;
    const translations = settings.aiContentStrategy?.translations || {};

    // Generate link input HTML
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
      saveIdeaLink(section, title, ideaIndex, link, linkArea, settings)
        .catch(error => {
          const messages = new Drupal.Message();
          messages.add(Drupal.t('Error saving link.'), {
            type: 'error',
            id: `content-strategy-error-${Date.now()}`
          });
          linkArea.innerHTML = originalContent;
          Drupal.attachBehaviors(linkArea);
        });
    });

    cancelBtn.addEventListener('click', () => {
      linkArea.innerHTML = originalContent;
      Drupal.attachBehaviors(linkArea);
    });

    // Keyboard shortcuts
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
          const title = button.dataset.title;
          const ideaIndex = button.dataset.ideaIndex;
          const linkArea = button.closest('.idea-link-area');

          showLinkInput(linkArea, section, title, ideaIndex, '', settings);
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
          const title = button.dataset.title;
          const ideaIndex = button.dataset.ideaIndex;
          const linkArea = button.closest('.idea-link-area');
          const currentLink = linkArea.querySelector('.idea-link')?.href || '';

          showLinkInput(linkArea, section, title, ideaIndex, currentLink, settings);
        });
      });
    }
  };

})(Drupal, once);
