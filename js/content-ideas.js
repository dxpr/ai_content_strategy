((Drupal, once) => {
  'use strict';

  // Button text constants
  const ButtonText = {
    GENERATE: Drupal.t('Generate Recommendations'),
    REFRESH: Drupal.t('Refresh Recommendations'),
    GENERATE_MORE: Drupal.t('Generate More Ideas'),
    LOADING: Drupal.t('Generating recommendations...')
  };

  // DOM utility functions
  const DOMUtils = {
    ensureElementId(element, prefix, index = '') {
      if (!element.id) {
        element.id = `${prefix}-${index || Date.now()}`;
      }
      return element.id;
    },

    getItemData(element) {
      const item = element.closest('.recommendation-item');
      if (!item) return {};
      return {
        section: item.dataset.section,
        title: item.dataset.title
      };
    },

    safeInsertHTML(target, html, method = 'html') {
      if (!target) return false;
      if (method === 'html') {
        target.innerHTML = html;
      } else if (method === 'append') {
        target.insertAdjacentHTML('beforeend', html);
      }
      return true;
    }
  };

  // AJAX handler factory
  function createAjaxHandler({
    element,
    url,
    loadingText = ButtonText.LOADING,
    successText,
    errorText,
    onSuccess,
    method = 'html'
  }) {
    DOMUtils.ensureElementId(element, 'content-strategy');

    const settings = {
      base: element.id,
      element: element,
      url: Drupal.url(url),
      submit: { js: true },
      progress: { type: 'throbber' },
      beforeSend: function(xhr, settings) {
        element.textContent = loadingText;
        element.disabled = true;
        return true;
      },
      success: function(response, status) {
        if (Array.isArray(response)) {
          response.forEach((command) => {
            if (command.command === 'insert' && command.method === method) {
              const target = document.querySelector(command.selector);
              DOMUtils.safeInsertHTML(target, command.data, method);
              if (onSuccess) {
                onSuccess(target);
              }
            }
            // Handle message commands using Drupal.Message API
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
            }
          });
        }
        element.disabled = false;
        element.textContent = successText;
      },
      error: function(xhr, status, error) {
        element.disabled = false;
        element.textContent = errorText;
        
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
          console.error('Error parsing response:', e);
          const messages = new Drupal.Message();
          messages.add(Drupal.t('An error occurred while processing your request.'), {
            type: 'error',
            id: `content-strategy-error-${Date.now()}`
          });
        }
      }
    };

    return settings;
  }

  // Attach generate more behavior
  function attachGenerateMoreBehavior(link, index) {
    const { section, title } = DOMUtils.getItemData(link);
    if (!section || !title) {
      console.error('Missing required data attributes:', { section, title });
      return;
    }

    link.textContent = ButtonText.GENERATE_MORE;
    
    try {
      const ajaxHandler = new Drupal.Ajax(
        link.id,
        link,
        createAjaxHandler({
          element: link,
          url: `admin/reports/ai/content-strategy/generate-more/${section}/${encodeURIComponent(title)}`,
          loadingText: ButtonText.LOADING,
          successText: ButtonText.GENERATE_MORE,
          errorText: ButtonText.GENERATE_MORE,
          method: 'append'
        })
      );

      link.addEventListener('click', function(event) {
        event.preventDefault();
        ajaxHandler.execute();
      });
    } catch (e) {
      console.error('Error setting up generate more link:', e);
    }
  }

  // Main behavior
  Drupal.behaviors.contentIdeas = {
    attach: function (context, settings) {
      // Handle main generate button
      once('contentIdeas', '.generate-recommendations', context).forEach((button) => {
        button.textContent = ButtonText.GENERATE;

        try {
          const ajaxHandler = new Drupal.Ajax(
            button.id,
            button,
            createAjaxHandler({
              element: button,
              url: 'admin/reports/ai/content-strategy/generate',
              loadingText: ButtonText.LOADING,
              successText: ButtonText.REFRESH,
              errorText: ButtonText.GENERATE,
              onSuccess: (target) => {
                target.querySelectorAll('.generate-more-link').forEach((link, index) => {
                  attachGenerateMoreBehavior(link, index);
                });
              }
            })
          );

          button.addEventListener('click', function(event) {
            event.preventDefault();
            ajaxHandler.execute();
          });
        } catch (e) {
          console.error('Error setting up main button:', e);
        }
      });

      // Handle generate more links
      once('content-ideas', '.generate-more-link', context).forEach((link, index) => {
        attachGenerateMoreBehavior(link, index);
      });
    }
  };

})(Drupal, once);