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

    getButtonText(settings, type, section) {
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

      return buttonTexts[type][section];
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
        Drupal.announce(loadingText, 'polite');
        return true;
      },
      success: function(response, status) {
        let hasError = false;

        if (Array.isArray(response)) {
          // Process all commands first
          response.forEach((command) => {
            if (command.command === 'insert') {
              const target = document.querySelector(command.selector);

              // Special handling for last-run-time
              if (command.selector === '.last-run-time') {
                let lastRunTime = target;
                if (!lastRunTime) {
                  lastRunTime = document.createElement('div');
                  lastRunTime.className = 'last-run-time';
                  const actionsContainer = document.querySelector('.content-strategy-actions');
                  if (actionsContainer) {
                    actionsContainer.appendChild(lastRunTime);
                  }
                }
                if (lastRunTime) {
                  DOMUtils.safeInsertHTML(lastRunTime, command.data, command.method);
                }
              } else if (target) {
                // If this is the main recommendations wrapper, remove the old one first
                if (command.selector === '.content-strategy-recommendations' && command.method === 'append') {
                  const oldWrapper = target.querySelector('.recommendations-wrapper');
                  if (oldWrapper) {
                    oldWrapper.remove();
                  }
                }
                DOMUtils.safeInsertHTML(target, command.data, command.method);
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

              // Track if this is an error message
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

          // After all commands are processed, call onSuccess if provided and no errors
          if (onSuccess && !hasError) {
            const mainContainer = document.querySelector('.content-strategy-recommendations');
            onSuccess(mainContainer);
          }
        }

        element.disabled = false;

        // Update button text and announcement based on whether there was an error
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
  }

  // Attach generate ideas behavior (initial generation for items without content_ideas)
  function attachGenerateIdeasBehavior(link, index, settings) {
    const { section, title } = DOMUtils.getItemData(link);
    if (!section || !title) {
      return;
    }

    const buttonText = DOMUtils.getButtonText(settings, 'generate', section);
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
          method: 'append',
          onSuccess: (target) => {
            // After generating initial ideas, replace the button with "generate more" link
            const item = link.closest('.recommendation-item');
            if (item) {
              const actionsDiv = item.querySelector('.recommendation-actions');
              if (actionsDiv) {
                // Remove the generate button
                link.remove();

                // Create the "generate more" link
                const moreLink = document.createElement('a');
                moreLink.href = '#';
                moreLink.className = 'generate-more-link';
                moreLink.dataset.section = section;
                moreLink.dataset.title = title;
                moreLink.textContent = DOMUtils.getButtonText(settings, 'generate_more', section);
                actionsDiv.appendChild(moreLink);

                // Attach behavior to the new link
                attachGenerateMoreBehavior(moreLink, 0, settings);
              }
            }
          }
        }, settings)
      );

      link.addEventListener('click', function(event) {
        event.preventDefault();
        ajaxHandler.execute();
      });
    } catch (e) {
      // Error handling without console.error
    }
  }

  // Attach generate more behavior
  function attachGenerateMoreBehavior(link, index, settings) {
    const { section, title } = DOMUtils.getItemData(link);
    if (!section || !title) {
      return;
    }

    const buttonText = DOMUtils.getButtonText(settings, 'generate_more', section);
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
      // Error handling without console.error
    }
  }

  // Attach add more recommendations behavior
  function attachAddMoreRecommendationsBehavior(link, settings) {
    const section = link.dataset.section;
    if (!section) {
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
      // Error handling without console.error
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
                // Find the recommendations wrapper that was just added
                const wrapper = document.querySelector('.recommendations-wrapper');
                if (wrapper) {
                  // Reattach behaviors to all generate ideas links
                  wrapper.querySelectorAll('.generate-ideas-link').forEach((link, index) => {
                    attachGenerateIdeasBehavior(link, index, settings);
                  });
                  // Reattach behaviors to all generate more links
                  wrapper.querySelectorAll('.generate-more-link').forEach((link, index) => {
                    attachGenerateMoreBehavior(link, index, settings);
                  });
                  // Reattach behaviors to all add more recommendations links
                  wrapper.querySelectorAll('.add-more-recommendations-link').forEach((link) => {
                    attachAddMoreRecommendationsBehavior(link, settings);
                  });
                }
              }
            }, settings)
          );

          button.addEventListener('click', function(event) {
            event.preventDefault();
            ajaxHandler.execute();
          });
        } catch (e) {
          // Error handling without console.error
        }
      });

      // Handle generate ideas links (initial generation)
      once('generate-ideas', '.generate-ideas-link', context).forEach((link, index) => {
        attachGenerateIdeasBehavior(link, index, settings);
      });

      // Handle generate more links
      once('content-ideas', '.generate-more-link', context).forEach((link, index) => {
        attachGenerateMoreBehavior(link, index, settings);
      });

      // Handle add more recommendations links
      once('content-ideas', '.add-more-recommendations-link', context).forEach((link) => {
        attachAddMoreRecommendationsBehavior(link, settings);
      });

      // Handle delete card links
      once('delete-card', '.delete-card-link', context).forEach((link) => {
        link.addEventListener('click', function(event) {
          event.preventDefault();

          const section = link.dataset.section;
          const title = link.dataset.title;

          // Confirm deletion
          if (!confirm(Drupal.t('Are you sure you want to delete this recommendation? This cannot be undone.'))) {
            return;
          }

          // Disable link during request
          link.style.opacity = '0.5';
          link.style.pointerEvents = 'none';

          // Make AJAX request
          const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/delete-card/${section}/${encodeURIComponent(title)}`;

          fetch(url, {
            method: 'GET',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(response => response.json())
          .then(commands => {
            // Process AJAX commands
            if (Array.isArray(commands)) {
              commands.forEach((command) => {
                if (command.command === 'remove') {
                  const target = document.querySelector(command.selector);
                  if (target) {
                    target.remove();
                  }
                }
                else if (command.command === 'message') {
                  const messages = new Drupal.Message();
                  if (command.clearPrevious) {
                    messages.clear();
                  }
                  messages.add(command.message, {
                    type: command.messageOptions?.type || 'status',
                    id: `content-strategy-message-${Date.now()}`
                  });
                }
                else if (command.command === 'insert') {
                  const target = document.querySelector(command.selector);
                  if (target) {
                    if (command.method === 'before') {
                      target.insertAdjacentHTML('beforebegin', command.data);
                    }
                  }
                }
              });

              // Reattach behaviors to new elements
              Drupal.attachBehaviors(document.querySelector('.content-strategy-recommendations'));
            }
          })
          .catch(error => {
            const messages = new Drupal.Message();
            messages.add(Drupal.t('An error occurred while deleting.'), {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`
            });

            // Re-enable link
            link.style.opacity = '1';
            link.style.pointerEvents = 'auto';
          });
        });
      });

      // Handle editable fields
      once('edit-card', '.editable-field', context).forEach((field) => {
        let saveTimeout;
        const card = field.closest('.recommendation-item');
        const section = card.dataset.section;
        const originalTitle = card.dataset.title;
        const saveIndicator = card.querySelector('.save-indicator');

        // Save function with debouncing
        const saveEdit = () => {
          const fieldName = field.dataset.field;
          const value = field.textContent.trim();
          const ideaIndex = field.dataset.ideaIndex || null;

          // Show "Saving..." indicator
          if (saveIndicator) {
            saveIndicator.textContent = Drupal.t('Saving...');
            saveIndicator.style.display = 'inline';
          }

          // Prepare data
          const formData = new FormData();
          formData.append('field', fieldName);
          formData.append('value', value);
          if (ideaIndex !== null) {
            formData.append('idea_index', ideaIndex);
          }

          // Make AJAX request
          const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/save-card/${section}/${encodeURIComponent(originalTitle)}`;

          fetch(url, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            // Show "Saved" indicator briefly
            if (saveIndicator) {
              saveIndicator.textContent = Drupal.t('Saved');
              setTimeout(() => {
                saveIndicator.style.display = 'none';
              }, 2000);
            }

            // If title changed, update data-title attribute
            if (fieldName === 'title') {
              card.dataset.title = value;
            }
          })
          .catch(error => {
            if (saveIndicator) {
              saveIndicator.textContent = Drupal.t('Error saving');
              saveIndicator.style.color = 'red';
              setTimeout(() => {
                saveIndicator.style.display = 'none';
                saveIndicator.style.color = '';
              }, 3000);
            }
          });
        };

        // Listen for input events (typing)
        field.addEventListener('input', () => {
          // Clear existing timeout
          clearTimeout(saveTimeout);

          // Set new timeout - save 1 second after typing stops
          saveTimeout = setTimeout(saveEdit, 1000);
        });

        // Also save on blur (when clicking away)
        field.addEventListener('blur', () => {
          clearTimeout(saveTimeout);
          saveEdit();
        });

        // Prevent Enter key from creating new lines in title/description
        if (field.dataset.field === 'title') {
          field.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              field.blur(); // Exit edit mode
            }
          });
        }
      });
    }
  };

})(Drupal, once);