(function (Drupal, once) {
  'use strict';

  /**
   * Button text constants for consistent microcopy.
   */
  const ButtonText = {
    GENERATE: Drupal.t('Generate Recommendations'),
    ANALYZE: Drupal.t('Analyze Content Gap'),
    GENERATE_MORE: Drupal.t('Generate More Ideas'),
    LOADING: Drupal.t('Analyzing content...')
  };

  /**
   * Handles button state during AJAX operations.
   */
  function handleButtonState(button, isLoading) {
    if (isLoading) {
      // Store original text and disable button
      button.setAttribute('data-original-text', button.textContent || button.value);
      button.disabled = true;
      button.classList.add('is-loading');
      if (button.tagName.toLowerCase() === 'input') {
        button.value = ButtonText.LOADING;
      } else {
        button.textContent = ButtonText.LOADING;
      }
    } else {
      // Restore original text and enable button
      const originalText = button.getAttribute('data-original-text');
      button.disabled = false;
      button.classList.remove('is-loading');
      if (originalText) {
        if (button.tagName.toLowerCase() === 'input') {
          button.value = originalText;
        } else {
          button.textContent = originalText;
        }
      }
    }
  }

  /**
   * Validates and manages the gap analysis button state.
   */
  function handleGapAnalysisButtonState(button, input) {
    if (!button || !input) return;
    
    // Disable button if URL is empty or only whitespace
    const isEmpty = !input.value.trim();
    button.disabled = isEmpty;
    
    // Also update button classes for visual feedback
    if (isEmpty) {
      button.classList.add('is-disabled');
    } else {
      button.classList.remove('is-disabled');
    }
  }

  /**
   * Creates a Drupal.Ajax instance with consistent settings.
   */
  function createAjaxInstance(element, settings = {}) {
    const defaultSettings = {
      url: null,
      event: 'click',
      progress: { type: 'throbber' },
      submit: { js: true },
      timeout: 120000 // 2 minutes timeout
    };

    const elementSettings = {...defaultSettings, ...settings};
    
    if (!elementSettings.url) {
      elementSettings.url = element.form ? element.form.getAttribute('action') : element.getAttribute('data-url');
    }

    const ajaxInstance = Drupal.ajax({
      ...elementSettings,
      element: element
    });

    // Store original callbacks
    const originalSuccess = ajaxInstance.success;
    const originalError = ajaxInstance.error;

    // Override success callback
    ajaxInstance.success = function(response, status) {
      handleButtonState(element, false);
      if (originalSuccess) {
        return originalSuccess.call(this, response, status);
      }
    };

    // Override error callback
    ajaxInstance.error = function(xmlhttprequest, uri, customMessage) {
      handleButtonState(element, false);
      
      let errorMessage = '';
      const statusCode = xmlhttprequest.status;
      const responseText = xmlhttprequest.responseText;

      if (statusCode === 502 || statusCode === 504) {
        errorMessage = Drupal.t('The request timed out. Please try again.');
      } else if (responseText) {
        errorMessage = Drupal.t('An error occurred: @text', {'@text': responseText});
      } else {
        errorMessage = customMessage;
      }

      Drupal.message('error', errorMessage);

      if (originalError) {
        originalError.call(this, xmlhttprequest, uri, customMessage);
      }
    };

    return ajaxInstance;
  }

  /**
   * Attaches AJAX behavior to a button.
   */
  function attachAjaxBehavior(element, settings = {}) {
    // Create Ajax instance
    const ajaxInstance = createAjaxInstance(element, settings);

    // Handle button state on click
    element.addEventListener('click', function(event) {
      handleButtonState(this, true);
    });

    return ajaxInstance;
  }

  Drupal.behaviors.contentStrategy = {
    attach: function (context, settings) {
      once('contentStrategy', '.generate-recommendations', context).forEach(function (button) {
        // Set initial state
        button.disabled = false;

        button.addEventListener('click', function (e) {
          e.preventDefault();
          
          // Set loading state
          button.disabled = true;

          // Make AJAX request
          fetch('/admin/reports/ai/content-strategy/generate', {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
            },
          })
          .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.text();
          })
          .then(html => {
            const container = document.querySelector('.content-strategy-recommendations');
            if (container) {
              container.innerHTML = html;
              Drupal.attachBehaviors(container);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Drupal.message('error', Drupal.t('An error occurred while generating recommendations.'));
          })
          .finally(() => {
            button.disabled = false;
          });
        });
      });

      // Handle gap analysis button and URL input
      once('gapAnalysis', '.analyze-competitor', context).forEach(function (button) {
        // Set initial text
        button.textContent = ButtonText.ANALYZE;
        
        // Find the competitor URL input field
        const form = button.closest('form');
        if (!form) return;
        
        const urlInput = form.querySelector('input[name="competitor_url"]');
        if (!urlInput) return;

        // Set initial button state
        handleGapAnalysisButtonState(button, urlInput);

        // Listen for input changes
        urlInput.addEventListener('input', function() {
          handleGapAnalysisButtonState(button, this);
        });

        // Also listen for change event to catch paste operations
        urlInput.addEventListener('change', function() {
          handleGapAnalysisButtonState(button, this);
        });

        // Handle form submission
        button.addEventListener('click', function (e) {
          e.preventDefault();
          
          if (!this.form) return;
          
          // Double check URL is not empty before proceeding
          const input = this.form.querySelector('input[name="competitor_url"]');
          if (!input || !input.value.trim()) {
            return;
          }

          // Set loading state
          handleButtonState(this, true);

          // Get form data
          const formData = new FormData(this.form);
          
          // Make AJAX request
          fetch(this.form.action, {
            method: 'POST',
            body: formData,
          })
          .then(response => {
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
          })
          .then(html => {
            const container = document.querySelector('.gap-analysis-results');
            if (container) {
              container.innerHTML = html;
              Drupal.attachBehaviors(container);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Drupal.message('error', Drupal.t('An error occurred while analyzing the competitor. Please try again.'));
          })
          .finally(() => {
            // Reset button state
            handleButtonState(this, false);
          });
        });
      });
    }
  };

})(Drupal, once); 