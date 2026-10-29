/**
 * @file
 * Shared utilities for AI Content Strategy module.
 */

((Drupal) => {
  'use strict';

  // Create namespace for content strategy utilities.
  Drupal.aiContentStrategy = Drupal.aiContentStrategy || {};

  // Counter keeps generated IDs unique within one synchronous loop.
  let idCounter = 0;

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
        idCounter += 1;
        element.id = `${prefix}-${index || Date.now()}-${idCounter}`;
      }
      return element.id;
    },

    /**
     * Gets section and uuid data from a recommendation item.
     *
     * @param {HTMLElement} element - Element within a recommendation item.
     * @returns {Object} Object with section and uuid properties.
     */
    getItemData(element) {
      const item = element.closest('.recommendation-item');
      if (!item) {
        return {};
      }
      return {
        section: item.dataset.section,
        uuid: item.dataset.uuid
      };
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
    },

    /**
     * Clears progress messages containing specific text.
     */
    clearProgressMessages() {
      document.querySelectorAll('[data-drupal-message-type="status"]').forEach((msg) => {
        if (msg.textContent.includes('Analyzing') || msg.textContent.includes('generating')) {
          msg.remove();
        }
      });
    }
  };

  /**
   * AJAX handler factory for content strategy requests.
   *
   * Creates a Drupal.ajax settings object that uses the Drupal AJAX framework
   * for command processing while providing customized button states.
   *
   * @param {Object} options - Handler options.
   * @param {HTMLElement} options.element - Triggering element.
   * @param {string} options.url - Request URL (relative to base path).
   * @param {string} options.loadingText - Text displayed during loading.
   * @param {string} options.successText - Text displayed on success.
   * @param {string} options.errorText - Text displayed on error.
   * @param {Function} options.onSuccess - Success callback (receives context).
   * @param {Object} drupalSettings - drupalSettings object.
   * @returns {Object} AJAX settings object for Drupal.Ajax constructor.
   */
  Drupal.aiContentStrategy.createAjaxHandler = function({
    element,
    url,
    loadingText,
    successText,
    errorText,
    onSuccess
  }, drupalSettings) {
    const DOMUtils = Drupal.aiContentStrategy.DOMUtils;

    if (!drupalSettings?.aiContentStrategy?.buttonText) {
      throw new Error('Button text settings are not available.');
    }

    if (!loadingText || !successText || !errorText) {
      throw new Error('Required button texts are missing.');
    }

    DOMUtils.ensureElementId(element, 'content-strategy');

    // Store original text for restoration.
    const originalText = element.textContent.trim();

    return {
      base: element.id,
      element: element,
      url: Drupal.url(url),
      // Disable Drupal's default mousedown/keypress listeners; the caller
      // triggers execute() on click.
      event: 'none',
      keypress: false,
      submit: { js: true },
      progress: { type: 'throbber' },

      beforeSend: function(xhr, options) {
        // Keep core's throbber, then apply the loading state.
        Drupal.Ajax.prototype.beforeSend.call(this, xhr, options);
        element.textContent = loadingText;
        element.disabled = true;
        Drupal.announce(loadingText, 'polite');
        return true;
      },

      // Drupal.Ajax calls success(), not complete(). Run core's command
      // processing first, then reset the button and run callbacks.
      success: function(response, status) {
        return Promise.resolve(Drupal.Ajax.prototype.success.call(this, response, status))
          .then(() => {
            DOMUtils.clearProgressMessages();
            element.disabled = false;

            const hasError = Array.isArray(response) && response.some((cmd) => {
              return cmd.command === 'message' && cmd.messageOptions?.type === 'error';
            });

            if (hasError) {
              element.textContent = errorText || originalText;
              return;
            }

            element.textContent = successText || originalText;
            Drupal.announce(Drupal.t('Content loaded successfully'), 'polite');

            if (onSuccess) {
              const mainContainer = document.querySelector('.content-strategy-recommendations');
              onSuccess(mainContainer);
            }
          });
      },

      error: function(xhr, status, error) {
        DOMUtils.clearProgressMessages();
        element.disabled = false;
        element.textContent = errorText || originalText;

        // Show error message.
        const messages = new Drupal.Message();
        try {
          const response = JSON.parse(xhr.responseText);
          if (response[0]?.message) {
            messages.add(response[0].message, {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`,
              announce: response[0].message
            });
          }
          else {
            messages.add(Drupal.t('An error occurred while processing your request.'), {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`
            });
          }
        }
        catch (e) {
          messages.add(Drupal.t('An error occurred while processing your request.'), {
            type: 'error',
            id: `content-strategy-error-${Date.now()}`
          });
        }
      }
    };
  };

})(Drupal);
