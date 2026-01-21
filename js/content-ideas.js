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

        // Clear all progress messages (supports multiple instances)
        document.querySelectorAll('[data-drupal-message-type="status"]').forEach((msg) => {
          if (msg.textContent.includes('Analyzing') || msg.textContent.includes('generating')) {
            msg.remove();
          }
        });

        if (Array.isArray(response)) {
          // Process all commands first
          response.forEach((command) => {
            if (command.command === 'insert') {
              const target = document.querySelector(command.selector);
              if (target) {
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

        // Clear all progress messages (supports multiple instances)
        document.querySelectorAll('[data-drupal-message-type="status"]').forEach((msg) => {
          if (msg.textContent.includes('Analyzing') || msg.textContent.includes('generating')) {
            msg.remove();
          }
        });

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
        try {
          DOMUtils.ensureElementId(button, 'content-strategy');
          const ajaxHandler = new Drupal.Ajax(
            button.id,
            button,
            createAjaxHandler({
              element: button,
              url: 'admin/reports/ai/content-strategy/generate',
              loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading,
              successText: button.textContent.trim(), // Keep whatever backend set
              errorText: button.textContent.trim(), // Keep whatever backend set
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

            // If there are existing recommendations, show confirmation dialog
            if (button.dataset.hasExisting === 'true') {
              const confirmed = confirm(
                Drupal.t('Regenerate all recommendations?\n\n') +
                Drupal.t('All existing recommendations will be replaced. This cannot be undone.')
              );

              if (!confirmed) {
                return;
              }
            }

            // Show detailed loading message
            const messages = new Drupal.Message();
            messages.add(
              Drupal.t('Analyzing your site and generating recommendations... This may take a minute.'),
              {
                type: 'status',
                id: `generation-progress-${Date.now()}`,
                announce: Drupal.t('Generating recommendations, please wait')
              }
            );

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

          // Get card context for better confirmation message
          const card = link.closest('.recommendation-item');
          const ideasTable = card.querySelector('.content-ideas-table tbody');
          const ideasCount = ideasTable ? ideasTable.querySelectorAll('tr').length : 0;

          // Build contextual confirmation message
          let confirmMessage = Drupal.t('Delete "@title"?', {'@title': title});
          if (ideasCount > 0) {
            confirmMessage += '\n\n' + Drupal.t('This will permanently delete @count content idea(s).', {'@count': ideasCount});
          } else {
            confirmMessage += '\n\n' + Drupal.t('This recommendation has no content ideas yet.');
          }
          confirmMessage += '\n\n' + Drupal.t('This action cannot be undone.');

          // Confirm deletion
          if (!confirm(confirmMessage)) {
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

        // Get icons from drupalSettings templates
        const templates = settings.aiContentStrategy?.templates || {};
        const icons = templates.icons || {};
        const checkmarkHTML = icons.checkmark ? `<span class="field-save-indicator__checkmark">${icons.checkmark}</span>` : '';
        const errorHTML = icons.error ? `<span class="field-save-indicator__error">${icons.error}</span>` : '';

        // Create or get field-specific save indicator
        const getOrCreateIndicator = () => {
          let indicator = field.querySelector('.field-save-indicator');
          if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = 'field-save-indicator';
            // For table cells, append inside; for others, append after
            if (field.tagName === 'TD') {
              field.appendChild(indicator);
            } else {
              field.insertAdjacentElement('afterend', indicator);
            }
          }
          return indicator;
        };

        // Show spinner indicator using Drupal's theme function
        const showSaving = () => {
          field.classList.add('editable-field--saving');
          field.classList.remove('editable-field--saved');
          const indicator = getOrCreateIndicator();
          // Use Drupal.theme.ajaxProgressThrobber() for theme-compatible spinner
          indicator.innerHTML = Drupal.theme.ajaxProgressThrobber();
        };

        // Show checkmark indicator with green flash
        const showSaved = () => {
          field.classList.remove('editable-field--saving');
          field.classList.add('editable-field--saved');
          const indicator = getOrCreateIndicator();
          indicator.innerHTML = checkmarkHTML;

          // Remove indicator and class after animation
          setTimeout(() => {
            field.classList.remove('editable-field--saved');
            indicator.remove();
          }, 2000);
        };

        // Show error state
        const showError = () => {
          field.classList.remove('editable-field--saving');
          const indicator = getOrCreateIndicator();
          indicator.innerHTML = errorHTML;

          setTimeout(() => {
            indicator.remove();
          }, 3000);
        };

        // Save function with debouncing
        const saveEdit = () => {
          const fieldName = field.dataset.field;
          const value = field.textContent.trim();
          const ideaIndex = field.dataset.ideaIndex || null;

          // Show saving state
          showSaving();

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
            // Show saved state with checkmark
            showSaved();

            // If title changed, update data-title attribute
            if (fieldName === 'title') {
              card.dataset.title = value;
            }
          })
          .catch(error => {
            showError();
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

      // Handle delete idea links (individual content ideas)
      once('delete-idea', '.delete-idea-link', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();

          const section = button.dataset.section;
          const title = button.dataset.title;
          const ideaIndex = button.dataset.ideaIndex;

          // Get the idea text for confirmation
          const row = button.closest('tr');
          const ideaCell = row.querySelector('.editable-field');
          const ideaText = ideaCell ? ideaCell.textContent.trim() : '';

          // Build contextual confirmation message
          let confirmMessage = Drupal.t('Delete this content idea?');
          if (ideaText) {
            const truncatedText = ideaText.length > 60 ? ideaText.substring(0, 60) + '...' : ideaText;
            confirmMessage += '\n\n"' + truncatedText + '"';
          }
          confirmMessage += '\n\n' + Drupal.t('This action cannot be undone.');

          // Confirm deletion
          if (!confirm(confirmMessage)) {
            return;
          }

          // Disable button during request
          button.style.opacity = '0.5';
          button.style.pointerEvents = 'none';

          // Make AJAX request
          const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/delete-idea/${section}/${encodeURIComponent(title)}/${ideaIndex}`;

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
              });

              // Reattach behaviors to the card
              const card = document.querySelector(`.recommendation-item[data-section='${section}'][data-title='${title}']`);
              if (card) {
                Drupal.attachBehaviors(card);
              }
            }
          })
          .catch(error => {
            const messages = new Drupal.Message();
            messages.add(Drupal.t('An error occurred while deleting the content idea.'), {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`
            });

            // Re-enable button
            button.style.opacity = '1';
            button.style.pointerEvents = 'auto';
          });
        });
      });

      // Handle implemented checkbox toggle
      once('implemented-checkbox', '.idea-implemented-checkbox', context).forEach((checkbox) => {
        checkbox.addEventListener('change', function(event) {
          const section = checkbox.dataset.section;
          const title = checkbox.dataset.title;
          const ideaIndex = checkbox.dataset.ideaIndex;
          const isImplemented = checkbox.checked;

          // Update row styling immediately
          const row = checkbox.closest('tr');
          if (isImplemented) {
            row.classList.add('idea-implemented');
          } else {
            row.classList.remove('idea-implemented');
          }

          // Show/hide link area based on implemented status
          const linkArea = row.querySelector('.idea-link-area');
          if (linkArea) {
            linkArea.style.display = isImplemented ? '' : 'none';
          }

          // Prepare data
          const formData = new FormData();
          formData.append('field', 'implemented');
          formData.append('value', isImplemented ? '1' : '0');
          formData.append('idea_index', ideaIndex);

          // Make AJAX request to save
          const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/save-card/${section}/${encodeURIComponent(title)}`;

          fetch(url, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            // Success - no need for visible feedback, checkbox state is already updated
          })
          .catch(error => {
            // Revert checkbox state on error
            checkbox.checked = !isImplemented;
            if (!isImplemented) {
              row.classList.add('idea-implemented');
            } else {
              row.classList.remove('idea-implemented');
            }
            // Revert link area visibility
            if (linkArea) {
              linkArea.style.display = !isImplemented ? '' : 'none';
            }

            const messages = new Drupal.Message();
            messages.add(Drupal.t('Error saving implementation status.'), {
              type: 'error',
              id: `content-strategy-error-${Date.now()}`
            });
          });
        });
      });

      // Helper function to save idea link
      const saveIdeaLink = (section, title, ideaIndex, link, linkArea) => {
        const formData = new FormData();
        formData.append('field', 'link');
        formData.append('value', link);
        formData.append('idea_index', ideaIndex);

        const url = `${settings.path.baseUrl}admin/reports/ai/content-strategy/save-card/${section}/${encodeURIComponent(title)}`;

        // Get templates and translations from drupalSettings
        const templates = settings.aiContentStrategy?.templates || {};
        const icons = templates.icons || {};
        const translations = settings.aiContentStrategy?.translations || {};

        return fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          // Update link area with saved link
          if (link) {
            const editIcon = icons.edit || '';
            linkArea.innerHTML = `
              <a href="${link}" target="_blank" class="idea-link" data-section="${section}" data-title="${title}" data-idea-index="${ideaIndex}">${link}</a>
              <button type="button" class="idea-link-edit" data-section="${section}" data-title="${title}" data-idea-index="${ideaIndex}" title="${translations.editLink || Drupal.t('Edit link')}">
                ${editIcon}
              </button>
            `;
            // Reattach behaviors to new elements
            Drupal.attachBehaviors(linkArea);
          } else {
            linkArea.innerHTML = `<button type="button" class="idea-add-link action-link" data-section="${section}" data-title="${title}" data-idea-index="${ideaIndex}">${translations.addLink || Drupal.t('+ Add link')}</button>`;
            Drupal.attachBehaviors(linkArea);
          }
        });
      };

      // Helper function to show link input
      const showLinkInput = (linkArea, section, title, ideaIndex, currentLink = '') => {
        const originalContent = linkArea.innerHTML;

        // Get template from drupalSettings or use fallback
        const templates = settings.aiContentStrategy?.templates || {};
        const translations = settings.aiContentStrategy?.translations || {};
        let linkInputHTML = templates.linkInput || '';

        // If we have a template, replace the placeholder value with current link
        if (linkInputHTML && currentLink) {
          // Create a temporary container to manipulate the HTML
          const temp = document.createElement('div');
          temp.innerHTML = linkInputHTML;
          const input = temp.querySelector('.idea-link-input');
          if (input) {
            input.value = currentLink;
          }
          linkInputHTML = temp.innerHTML;
        } else if (!linkInputHTML) {
          // Fallback if template not available
          linkInputHTML = `
            <div class="idea-link-input-wrapper">
              <input type="url" class="idea-link-input form-url" placeholder="${translations.enterUrl || Drupal.t('Enter URL...')}" value="${currentLink}">
              <button type="button" class="button button--small button--primary idea-link-save">${translations.save || Drupal.t('Save')}</button>
              <button type="button" class="button button--small idea-link-cancel">${translations.cancel || Drupal.t('Cancel')}</button>
            </div>
          `;
        }

        linkArea.innerHTML = linkInputHTML;

        const input = linkArea.querySelector('.idea-link-input');
        const saveBtn = linkArea.querySelector('.idea-link-save');
        const cancelBtn = linkArea.querySelector('.idea-link-cancel');

        // Set the current link value (in case template didn't have it)
        if (input && currentLink) {
          input.value = currentLink;
        }

        input.focus();

        saveBtn.addEventListener('click', () => {
          const link = input.value.trim();
          saveIdeaLink(section, title, ideaIndex, link, linkArea)
            .catch(error => {
              const messages = new Drupal.Message();
              messages.add(Drupal.t('Error saving link.'), {
                type: 'error',
                id: `content-strategy-error-${Date.now()}`
              });
              linkArea.innerHTML = originalContent;
              Drupal.attachBehaviors(linkArea);
            });
        });

        cancelBtn.addEventListener('click', () => {
          linkArea.innerHTML = originalContent;
          Drupal.attachBehaviors(linkArea);
        });

        // Save on Enter key
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            saveBtn.click();
          } else if (e.key === 'Escape') {
            cancelBtn.click();
          }
        });
      };

      // Handle "Add link" button click
      once('add-link', '.idea-add-link', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();
          const section = button.dataset.section;
          const title = button.dataset.title;
          const ideaIndex = button.dataset.ideaIndex;
          const linkArea = button.closest('.idea-link-area');

          showLinkInput(linkArea, section, title, ideaIndex);
        });
      });

      // Handle "Edit link" button click
      once('edit-link', '.idea-link-edit', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();
          const section = button.dataset.section;
          const title = button.dataset.title;
          const ideaIndex = button.dataset.ideaIndex;
          const linkArea = button.closest('.idea-link-area');
          const currentLink = linkArea.querySelector('.idea-link')?.href || '';

          showLinkInput(linkArea, section, title, ideaIndex, currentLink);
        });
      });

      // Handle CSV export button
      once('export-csv', '.export-csv-button', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();

          // Collect all recommendation data
          const csvData = [];

          // Add header row - Content Idea first, Recommendation Title last
          csvData.push(['Content Idea', 'Implemented', 'Link', 'Category', 'Priority', 'Recommendation Title']);

          // Loop through all recommendation cards
          document.querySelectorAll('.recommendation-item').forEach((card) => {
            const section = card.dataset.section || '';
            const title = card.querySelector('h4')?.textContent?.trim() || '';
            const priority = card.querySelector('.priority-badge')?.textContent?.trim() || '';

            // Get category name from section heading
            const sectionElement = card.closest('.recommendation-section');
            const categoryName = sectionElement?.querySelector('h3')?.textContent?.trim() || section;

            // Get all content ideas
            const ideas = card.querySelectorAll('.content-ideas-table tbody tr');

            if (ideas.length > 0) {
              // One row per idea
              ideas.forEach((ideaRow) => {
                const idea = ideaRow.querySelector('.editable-field')?.textContent?.trim() || '';
                const isImplemented = ideaRow.classList.contains('idea-implemented') ? 'Yes' : 'No';
                const link = ideaRow.querySelector('.idea-link')?.href || '';
                csvData.push([
                  idea,
                  isImplemented,
                  link,
                  categoryName,
                  priority,
                  title
                ]);
              });
            } else {
              // Card without ideas
              csvData.push([
                '',
                '',
                '',
                categoryName,
                priority,
                title
              ]);
            }
          });

          // Convert to CSV string
          const csvContent = csvData.map(row =>
            row.map(cell => {
              // Escape quotes and wrap in quotes if contains comma, newline, or quote
              const cellStr = String(cell).replace(/"/g, '""');
              if (cellStr.includes(',') || cellStr.includes('\n') || cellStr.includes('"')) {
                return `"${cellStr}"`;
              }
              return cellStr;
            }).join(',')
          ).join('\n');

          // Create blob and trigger download
          const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
          const link = document.createElement('a');
          const url = URL.createObjectURL(blob);
          const timestamp = new Date().toISOString().split('T')[0];

          link.setAttribute('href', url);
          link.setAttribute('download', `content-strategy-${timestamp}.csv`);
          link.style.visibility = 'hidden';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);

          // Show success message
          const messages = new Drupal.Message();
          messages.add(Drupal.t('CSV file downloaded successfully.'), {
            type: 'status',
            id: `csv-export-success-${Date.now()}`
          });
        });
      });
    }
  };

})(Drupal, once);