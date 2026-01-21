/**
 * @file
 * Shared utilities for AI Content Strategy module.
 */

((Drupal) => {
  'use strict';

  // Create namespace for content strategy utilities.
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
      if (!item) {
        return {};
      }
      return {
        section: item.dataset.section,
        title: item.dataset.title
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
      submit: { js: true },
      progress: { type: 'throbber' },

      beforeSend: function(xhr, settings) {
        element.textContent = loadingText;
        element.disabled = true;
        Drupal.announce(loadingText, 'polite');
        return true;
      },

      // Let Drupal handle success - commands are processed automatically.
      // We use complete to reset button state and run callbacks.
      complete: function(response, status) {
        DOMUtils.clearProgressMessages();
        element.disabled = false;

        // Check if there was an error in the response.
        let hasError = false;
        if (response && response.responseJSON) {
          const data = response.responseJSON;
          if (Array.isArray(data)) {
            data.forEach((cmd) => {
              if (cmd.command === 'message' && cmd.messageOptions?.type === 'error') {
                hasError = true;
              }
            });
          }
        }

        if (hasError || status === 'error') {
          element.textContent = errorText || originalText;
        }
        else {
          element.textContent = successText || originalText;
          Drupal.announce(Drupal.t('Content loaded successfully'), 'polite');

          // Run success callback if provided.
          if (onSuccess) {
            const mainContainer = document.querySelector('.content-strategy-recommendations');
            onSuccess(mainContainer);
          }
        }
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
