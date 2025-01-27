((Drupal, once) => {
  'use strict';

  // Add consistent button text constants at the top
  const ButtonText = {
    GENERATE: Drupal.t('Generate Recommendations'),
    REFRESH: Drupal.t('Refresh Recommendations'),
    GENERATE_MORE: Drupal.t('Generate More Ideas'),
    LOADING: Drupal.t('Generating recommendations...')
  };

  // Helper function to get the title from the parent recommendation item
  function getRecommendationTitle(link) {
    const item = link.closest('.recommendation-item');
    if (!item) return '';
    return item.dataset.title;
  }

  // Helper function to attach generate more links behavior
  function attachGenerateMoreBehavior(link, index) {
    // Ensure link has an ID
    if (!link.id) {
      link.id = `generate-more-link-${index}`;
    }
    
    // Set initial link text
    link.textContent = ButtonText.GENERATE_MORE;
    
    // Get section and title from data attributes
    const item = link.closest('.recommendation-item');
    const section = item.dataset.section;
    const title = item.dataset.title;
    
    if (!section || !title) {
      console.error('Missing required data attributes:', { section, title });
      return;
    }

    // Create element settings for the AJAX call
    const elementSettings = {
      base: link.id,
      element: link,
      url: Drupal.url(`admin/reports/ai/content-strategy/generate-more/${section}/${encodeURIComponent(title)}`),
      event: 'click',
      progress: { type: 'throbber' },
      submit: { js: true },
      beforeSend: function(xhr, settings) {
        link.textContent = ButtonText.LOADING;
        link.disabled = true;
        return true;
      },
      success: function(response, status) {
        if (Array.isArray(response)) {
          response.forEach((command) => {
            if (command.command === 'insert' && command.method === 'append') {
              const target = document.querySelector(command.selector);
              if (target) {
                target.insertAdjacentHTML('beforeend', command.data);
              }
            }
          });
        }
        
        link.disabled = false;
        link.textContent = ButtonText.GENERATE_MORE;
      },
      error: function(xhr, status, error) {
        link.disabled = false;
        link.textContent = ButtonText.GENERATE_MORE;
        alert(Drupal.t('An error occurred while generating more ideas.'));
      }
    };

    try {
      // Create and attach the AJAX behavior
      Drupal.ajax(elementSettings);
    } catch (e) {
      console.error('Error setting up generate more link:', e);
    }
  }

  Drupal.behaviors.contentIdeas = {
    attach: function (context, settings) {
      // Handle generate recommendations button
      once('contentIdeas', '.generate-recommendations', context).forEach((button) => {
        // Ensure button has an ID
        if (!button.id) {
          button.id = 'generate-recommendations-button';
        }
        
        // Set initial button text
        button.textContent = ButtonText.GENERATE;

        // Create element settings for the AJAX call
        const elementSettings = {
          base: button.id,
          element: button,
          url: Drupal.url('admin/reports/ai/content-strategy/generate'),
          event: 'click',
          progress: { 
            type: 'throbber',
            message: ButtonText.LOADING
          },
          submit: {
            js: true
          },
          beforeSend: function(xhr, settings) {
            button.textContent = ButtonText.LOADING;
            button.disabled = true;
            return true;
          },
          success: function(response, status) {
            // Process each command
            if (Array.isArray(response)) {
              response.forEach((command) => {
                // Handle HTML updates
                if (command.command === 'insert' && command.method === 'html') {
                  const target = document.querySelector(command.selector);
                  if (target) {
                    target.innerHTML = command.data;
                    // Re-attach behaviors to new generate more links
                    target.querySelectorAll('.generate-more-link').forEach((link, index) => {
                      attachGenerateMoreBehavior(link, index);
                    });
                  }
                }
              });
            }
            
            button.disabled = false;
            button.textContent = ButtonText.REFRESH;
          },
          error: function(xhr, status, error) {
            button.disabled = false;
            button.textContent = ButtonText.GENERATE;
            alert(Drupal.t('An error occurred while generating recommendations.'));
          }
        };

        try {
          // Create and attach the AJAX behavior
          Drupal.ajax(elementSettings);
        } catch (e) {
          console.error('Error setting up recommendations button:', e);
        }
      });

      // Handle generate more ideas links - use context to ensure we only attach to new elements
      once('content-ideas', '.generate-more-link', context).forEach((link, index) => {
        attachGenerateMoreBehavior(link, index);
      });
    }
  };

})(Drupal, once);