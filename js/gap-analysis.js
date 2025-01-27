((Drupal, once) => {
  'use strict';

  // Add consistent button text constants at the top
  const ButtonText = {
    ANALYZE: Drupal.t('Analyze Content Gap'),
    LOADING: Drupal.t('Analyzing...'),
  };

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
      elementSettings.url = Drupal.url('admin/reports/ai/content-strategy/gap-analysis/analyze');
    }

    return Drupal.ajax(elementSettings);
  }

  Drupal.behaviors.gapAnalysis = {
    attach: function (context, settings) {
      console.log('Attaching gap analysis behavior');
      
      // Handle analyze button
      once('gapAnalysis', '.analyze-content-gap', context).forEach((button) => {
        console.log('Adding click handler to button:', button);
        
        // Set initial button text
        button.textContent = ButtonText.ANALYZE;
        
        // Add AJAX settings
        const elementSettings = {
          url: Drupal.url('admin/reports/ai/content-strategy/gap-analysis/analyze'),
          element: button,
          event: 'click',
          progress: { type: 'throbber' },
          submit: {
            js: true
          },
          beforeSend: function (xhr, settings) {
            // Get URL from input
            const urlInput = document.querySelector('.competitor-url-input');
            if (!urlInput || !urlInput.value) {
              alert(Drupal.t('Please enter a competitor URL'));
              return false;
            }
            
            // Add URL to settings
            settings.url += '?url=' + encodeURIComponent(urlInput.value);
            
            // Update button state
            button.textContent = ButtonText.LOADING;
            button.disabled = true;
            
            return true;
          },
          success: function (response, status) {
            // Reset button state
            button.textContent = ButtonText.ANALYZE;
            button.disabled = false;
            
            // Update results container
            const resultsContainer = document.getElementById('gap-analysis-results');
            if (resultsContainer && response.html) {
              resultsContainer.innerHTML = response.html;
            }
            
            // Handle error
            if (!response.success && response.error) {
              alert(response.error);
            }
          },
          error: function (xhr, status, error) {
            // Reset button state
            button.textContent = ButtonText.ANALYZE;
            button.disabled = false;
            
            // Show error
            alert(Drupal.t('Analysis failed: @error', {'@error': error}));
          }
        };
        
        const instance = createAjaxInstance(button, elementSettings);
      });
    }
  };

})(Drupal, once); 