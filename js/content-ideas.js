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
      if (!settings?.aiContentStrategy?.buttonText) {
        throw new Error('Button text settings are not available. Make sure aiContentStrategy.buttonText is properly initialized in drupalSettings.');
      }

      const buttonTexts = settings.aiContentStrategy.buttonText;
      if (!buttonTexts[type]) {
        throw new Error(`Button text type "${type}" is not defined in settings.`);
      }
      if (!buttonTexts[type][section] && type !== 'main') {
        throw new Error(`Button text for section "${section}" is not defined in type "${type}".`);
      }

      const text = type === 'main' ? buttonTexts[type][section] : buttonTexts[type][section];
      if (!text) {
        throw new Error(`Button text is empty for type "${type}" and section "${section}".`);
      }

      // Get the appropriate type label based on section
      const typeLabels = {
        content_gaps: 'content gap',
        authority_topics: 'authority topic',
        expertise_demonstrations: itemType.toLowerCase(),
        trust_signals: 'trust signal',
      };

      // For 'add_more' type, use plural forms
      const typeLabelPlurals = {
        content_gaps: 'Content Opportunities',
        authority_topics: 'Authority Topics',
        expertise_demonstrations: 'Expertise',
        trust_signals: 'Trust-Building Elements',
      };

      const label = type === 'add_more' ? typeLabelPlurals[section] : typeLabels[section];
      return Drupal.formatString(text, {
        '%type': label,
        '%types': label,
      });
    }
  };

  // AJAX handler factory
  function createAjaxHandler({
    element,
    url,
    loadingText,
    successText,
    errorText,
    onSuccess,
    method = 'html'
  }, drupalSettings) {
    if (!drupalSettings?.aiContentStrategy?.buttonText) {
      throw new Error('Button text settings are not available. Make sure aiContentStrategy.buttonText is properly initialized in drupalSettings.');
    }

    if (!loadingText || !successText || !errorText) {
      throw new Error('Required button texts are missing. Make sure all text parameters are provided.');
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

    return ajaxSettings;
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
      DOMUtils.ensureElementId(link, 'content-strategy');
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
        }, settings)
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
        }, settings)
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
          DOMUtils.ensureElementId(button, 'content-strategy');
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
            }, settings)
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