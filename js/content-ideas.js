((Drupal, once) => {
  'use strict';

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
    },

    getButtonText(settings, type, section, itemType = '') {
      try {
        const text = settings?.aiContentStrategy?.buttonText?.[type]?.[section];
        if (!text) return '';
        
        return type === 'generate_more' && section === 'expertise_demonstrations'
          ? Drupal.formatString(text, {'%type': itemType.toLowerCase()})
          : text;
      } catch (e) {
        console.warn('Button text not available yet');
        return '';
      }
    }
  };

  // AJAX handler factory
  function createAjaxHandler({
    element,
    url,
    loadingText = settings?.aiContentStrategy?.buttonText?.main?.loading,
    successText = settings?.aiContentStrategy?.buttonText?.main?.refresh,
    errorText = settings?.aiContentStrategy?.buttonText?.main?.refresh,
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
  function attachGenerateMoreBehavior(link, index, settings) {
    const { section, title } = DOMUtils.getItemData(link);
    if (!section || !title) {
      console.error('Missing required data attributes:', { section, title });
      return;
    }

    const buttonText = DOMUtils.getButtonText(settings, 'generate_more', section, title);
    if (buttonText) {
      link.textContent = buttonText;
    }
    
    try {
      const ajaxHandler = new Drupal.Ajax(
        link.id,
        link,
        createAjaxHandler({
          element: link,
          url: `admin/reports/ai/content-strategy/generate-more/${section}/${encodeURIComponent(title)}`,
          loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading,
          successText: buttonText || link.textContent,
          errorText: buttonText || link.textContent,
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

  // Attach add more recommendations behavior
  function attachAddMoreRecommendationsBehavior(link, settings) {
    const section = link.dataset.section;
    if (!section) {
      console.error('Missing required data attribute: section');
      return;
    }

    // Ensure the link has an ID for Drupal.Ajax
    DOMUtils.ensureElementId(link, 'content-strategy');

    const buttonText = DOMUtils.getButtonText(settings, 'add_more', section);
    if (buttonText) {
      link.textContent = buttonText;
    }
    
    try {
      const ajaxHandler = new Drupal.Ajax(
        link.id,
        link,
        createAjaxHandler({
          element: link,
          url: `admin/reports/ai/content-strategy/add-more/${section}`,
          loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading_more,
          successText: buttonText || link.textContent,
          errorText: buttonText || link.textContent,
          method: 'append',
          onSuccess: (target) => {
            // Find and attach behaviors to any new generate more links
            target.querySelectorAll('.generate-more-link').forEach((newLink, index) => {
              attachGenerateMoreBehavior(newLink, index, settings);
            });
          }
        })
      );

      link.addEventListener('click', function(event) {
        event.preventDefault();
        ajaxHandler.execute();
      });
    } catch (e) {
      console.error('Error setting up add more recommendations link:', e);
    }
  }

  // Main behavior
  Drupal.behaviors.contentIdeas = {
    attach: function (context, settings) {
      // Handle main generate button
      once('contentIdeas', '.generate-recommendations', context).forEach((button) => {
        // Don't override the initial button text from server
        const initialText = button.textContent.trim();
        const hasRecommendations = initialText === settings?.aiContentStrategy?.buttonText?.main?.refresh;

        try {
          const ajaxHandler = new Drupal.Ajax(
            button.id,
            button,
            createAjaxHandler({
              element: button,
              url: 'admin/reports/ai/content-strategy/generate',
              loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading,
              successText: settings?.aiContentStrategy?.buttonText?.main?.refresh,
              errorText: hasRecommendations ? settings?.aiContentStrategy?.buttonText?.main?.refresh : settings?.aiContentStrategy?.buttonText?.main?.generate,
              onSuccess: (target) => {
                // Reattach behaviors to all generate more links
                target.querySelectorAll('.generate-more-link').forEach((link, index) => {
                  attachGenerateMoreBehavior(link, index, settings);
                });
                // Reattach behaviors to all add more recommendations links
                target.querySelectorAll('.add-more-recommendations-link').forEach((link) => {
                  attachAddMoreRecommendationsBehavior(link, settings);
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
        attachGenerateMoreBehavior(link, index, settings);
      });

      // Handle add more recommendations links
      once('content-ideas', '.add-more-recommendations-link', context).forEach((link) => {
        attachAddMoreRecommendationsBehavior(link, settings);
      });
    }
  };

})(Drupal, once);