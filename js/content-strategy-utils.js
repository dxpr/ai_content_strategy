/**
 * @file
 * Shared utilities for AI Content Strategy module.
 */

((Drupal) => {
  'use strict';

  // Create namespace for content strategy utilities
  Drupal.aiContentStrategy = Drupal.aiContentStrategy || {};

  /**
   * DOM utility functions.
   */
  Drupal.aiContentStrategy.DOMUtils = {
    /**
     * Ensures an element has an ID, generating one if needed.
     *
     * @param {HTMLElement} element - The element to check.
     * @param {string} prefix - Prefix for generated ID.
     * @param {string|number} index - Optional index for uniqueness.
     * @returns {string} The element's ID.
     */
    ensureElementId(element, prefix, index = '') {
      if (!element.id) {
        element.id = `${prefix}-${index || Date.now()}`;
      }
      return element.id;
    },

    /**
     * Gets section and title data from a recommendation item.
     *
     * @param {HTMLElement} element - Element within a recommendation item.
     * @returns {Object} Object with section and title properties.
     */
    getItemData(element) {
      const item = element.closest('.recommendation-item');
      if (!item) return {};
      return {
        section: item.dataset.section,
        title: item.dataset.title
      };
    },

    /**
     * Safely inserts HTML into a target element.
     *
     * @param {HTMLElement} target - Target element.
     * @param {string} html - HTML to insert.
     * @param {string} method - 'html' to replace, 'append' to add.
     * @returns {boolean} Success status.
     */
    safeInsertHTML(target, html, method = 'html') {
      if (!target) return false;
      if (method === 'html') {
        target.innerHTML = html;
      } else if (method === 'append') {
        target.insertAdjacentHTML('beforeend', html);
      }
      return true;
    },

    /**
     * Gets translated button text from drupalSettings.
     *
     * @param {Object} settings - drupalSettings object.
     * @param {string} type - Button text type.
     * @param {string} section - Section identifier.
     * @returns {string} Translated button text.
     */
    getButtonText(settings, type, section) {
      if (!settings?.aiContentStrategy?.buttonText) {
        throw new Error('Button text settings are not available.');
      }

      const buttonTexts = settings.aiContentStrategy.buttonText;
      if (!buttonTexts[type]) {
        throw new Error(`Button text type "${type}" is not defined.`);
      }
      if (!buttonTexts[type][section] && type !== 'main') {
        throw new Error(`Button text for section "${section}" not defined.`);
      }

      return buttonTexts[type][section];
    }
  };

  /**
   * AJAX handler factory for content strategy requests.
   *
   * @param {Object} options - Handler options.
   * @param {HTMLElement} options.element - Triggering element.
   * @param {string} options.url - Request URL.
   * @param {string} options.loadingText - Text during loading.
   * @param {string} options.successText - Text on success.
   * @param {string} options.errorText - Text on error.
   * @param {Function} options.onSuccess - Success callback.
   * @param {string} options.method - Insert method ('html' or 'append').
   * @param {Object} drupalSettings - drupalSettings object.
   * @returns {Object} AJAX settings object.
   */
  Drupal.aiContentStrategy.createAjaxHandler = function({
    element,
    url,
    loadingText,
    successText,
    errorText,
    onSuccess,
    method = 'html'
  }, drupalSettings) {
    const DOMUtils = Drupal.aiContentStrategy.DOMUtils;

    if (!drupalSettings?.aiContentStrategy?.buttonText) {
      throw new Error('Button text settings are not available.');
    }

    if (!loadingText || !successText || !errorText) {
      throw new Error('Required button texts are missing.');
    }

    DOMUtils.ensureElementId(element, 'content-strategy');

    const ajaxSettings = {
      base: element.id,
      element: element,
      url: drupalSettings.path.baseUrl + url,
      submit: { js: true },
      progress: { type: 'throbber' },
      beforeSend: function(xhr, settings) {
        element.textContent = loadingText;
        element.disabled = true;
        Drupal.announce(loadingText, 'polite');
        return true;
      },
      success: function(response, status) {
        let hasError = false;
        let contentInserted = false;

        // Clear progress messages (supports multiple instances)
        document.querySelectorAll('[data-drupal-message-type="status"]').forEach((msg) => {
          if (msg.textContent.includes('Analyzing') || msg.textContent.includes('generating')) {
            msg.remove();
          }
        });

        if (Array.isArray(response)) {
          response.forEach((command) => {
            if (command.command === 'insert') {
              const target = document.querySelector(command.selector);
              if (target) {
                if (command.selector === '.content-strategy-recommendations' && command.method === 'append') {
                  const oldWrapper = target.querySelector('.recommendations-wrapper');
                  if (oldWrapper) {
                    oldWrapper.remove();
                  }
                }
                DOMUtils.safeInsertHTML(target, command.data, command.method);
                contentInserted = true;
              }
            }
            else if (command.command === 'message') {
              const messages = new Drupal.Message();
              if (command.clearPrevious) {
                messages.clear();
              }
              messages.add(command.message, {
                type: command.messageOptions?.type || 'status',
                id: `content-strategy-message-${Date.now()}`,
                announce: command.message
              });
              if (command.messageOptions?.type === 'error') {
                hasError = true;
              }
            }
            else if (command.command === 'remove') {
              const target = document.querySelector(command.selector);
              if (target) {
                target.remove();
              }
            }
          });

          // Attach Drupal behaviors to newly inserted content
          if (contentInserted && !hasError) {
            const mainContainer = document.querySelector('.content-strategy-recommendations');
            if (mainContainer) {
              Drupal.attachBehaviors(mainContainer, drupalSettings);
            }
          }

          if (onSuccess && !hasError) {
            const mainContainer = document.querySelector('.content-strategy-recommendations');
            onSuccess(mainContainer);
          }
        }

        element.disabled = false;

        if (hasError) {
          element.textContent = errorText;
        } else {
          element.textContent = successText;
          Drupal.announce(Drupal.t('Content loaded successfully'), 'polite');
        }
      },
      error: function(xhr, status, error) {
        element.disabled = false;
        element.textContent = errorText;

        document.querySelectorAll('[data-drupal-message-type="status"]').forEach((msg) => {
          if (msg.textContent.includes('Analyzing') || msg.textContent.includes('generating')) {
            msg.remove();
          }
        });

        try {
          const response = JSON.parse(xhr.responseText);
          const messages = new Drupal.Message();

          if (response[0]?.message) {
            messages.add(response[0].message, {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`,
              announce: response[0].message
            });
          } else {
            messages.add(Drupal.t('An error occurred while processing your request.'), {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`
            });
          }
        } catch (e) {
          const messages = new Drupal.Message();
          messages.add(Drupal.t('An error occurred while processing your request.'), {
            type: 'error',
            id: `content-strategy-error-${Date.now()}`
          });
        }
      }
    };

    return ajaxSettings;
  };

})(Drupal);
