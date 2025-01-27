((Drupal, once) => {
  'use strict';

  // Add consistent button text constants at the top
  const ButtonText = {
    GENERATE: Drupal.t('Generate Recommendations'),
    REFRESH: Drupal.t('Refresh Recommendations'),
    GENERATE_MORE: Drupal.t('Generate More Ideas'),
    LOADING: Drupal.t('Generating recommendations...')
  };

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
          url: Drupal.url('admin/reports/ai/content-strategy/generate'),
          event: 'click',
          progress: { 
            type: 'throbber',
            message: ButtonText.LOADING
          },
          submit: {
            js: true
          },
          element: button,
          beforeSend: function(xhr, settings) {
            button.textContent = ButtonText.LOADING;
            button.disabled = true;
            return true;
          },
          success: function(response, status) {
            // Process each command
            if (Array.isArray(response)) {
              response.forEach((command) => {
                // Special handling for HTML updates
                if (command.command === 'insert' && command.method === 'html') {
                  const target = document.querySelector(command.selector);
                  if (target) {
                    // Store original content in case we need to restore
                    const originalContent = target.innerHTML;
                    
                    try {
                      target.innerHTML = command.data;
                    } catch (e) {
                      // Restore original content on error
                      target.innerHTML = originalContent;
                    }
                  }
                }
              });
            }
            
            button.disabled = false;
            button.textContent = ButtonText.REFRESH;
            
            // Final structure check
            setTimeout(() => {
              const wrapper = document.querySelector('.recommendations-wrapper');
              const container = document.querySelector('.content-strategy-recommendations');
              
              // Force structure if missing
              if (container && !wrapper) {
                const content = container.innerHTML;
                // Only wrap content that isn't already wrapped
                if (!content.includes('recommendations-wrapper')) {
                  container.innerHTML = `<div class="recommendations-wrapper">${content}</div>`;
                }
              }
            }, 100);
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

      // Handle generate more ideas links
      once('content-ideas', '.generate-more-link', context).forEach((link, index) => {
        // Ensure link has an ID
        if (!link.id) {
          link.id = `generate-more-link-${index}`;
        }
        
        // Set initial link text
        link.textContent = ButtonText.GENERATE_MORE;
        
        const section = link.dataset.section;
        const title = link.dataset.title;
        
        if (!section || !title) {
          console.error('Missing required data attributes:', { section, title });
          return;
        }

        // Create element settings for the AJAX call
        const elementSettings = {
          url: Drupal.url(`admin/reports/ai/content-strategy/generate-more/${section}/${encodeURIComponent(title)}`),
          event: 'click',
          progress: { type: 'throbber' },
          submit: { js: true },
          element: link,
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
                    try {
                      target.insertAdjacentHTML('beforeend', command.data);
                    } catch (e) {
                      console.error('Error appending content:', e);
                    }
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
      });
    }
  };

})(Drupal, once);