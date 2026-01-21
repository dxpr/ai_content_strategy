/**
 * @file
 * Generate behaviors for AI Content Strategy recommendations.
 *
 * Uses Drupal's AJAX framework for proper command processing and behavior
 * attachment.
 */

((Drupal, once) => {
  'use strict';

  const { DOMUtils, createAjaxHandler } = Drupal.aiContentStrategy;

  /**
   * Attaches generate ideas behavior (initial generation).
   *
   * @param {HTMLElement} link - The generate ideas link.
   * @param {number} index - Link index.
   * @param {Object} settings - drupalSettings object.
   */
  function attachGenerateIdeasBehavior(link, index, settings) {
    const { section, uuid } = DOMUtils.getItemData(link);
    if (!section || !uuid) {
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
          url: `admin/reports/ai/content-strategy/generate-more/${section}/${uuid}`,
          loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading,
          successText: buttonText || link.textContent,
          errorText: buttonText || link.textContent,
          onSuccess: (target) => {
            const item = link.closest('.recommendation-item');
            if (item) {
              const actionsDiv = item.querySelector('.recommendation-actions');
              if (actionsDiv) {
                link.remove();

                const moreLink = document.createElement('a');
                moreLink.href = '#';
                moreLink.className = 'generate-more-link';
                moreLink.dataset.section = section;
                moreLink.dataset.uuid = uuid;
                moreLink.textContent = DOMUtils.getButtonText(settings, 'generate_more', section);
                actionsDiv.appendChild(moreLink);

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
    }
    catch (e) {
      // Error handling without console.error.
    }
  }

  /**
   * Attaches generate more behavior.
   *
   * @param {HTMLElement} link - The generate more link.
   * @param {number} index - Link index.
   * @param {Object} settings - drupalSettings object.
   */
  function attachGenerateMoreBehavior(link, index, settings) {
    // Get uuid from link's data attribute or from parent item.
    let section = link.dataset.section;
    let uuid = link.dataset.uuid;

    // Fallback to parent item data if not on link.
    if (!uuid) {
      const itemData = DOMUtils.getItemData(link);
      section = section || itemData.section;
      uuid = itemData.uuid;
    }

    if (!section || !uuid) {
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
          url: `admin/reports/ai/content-strategy/generate-more/${section}/${uuid}`,
          loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading,
          successText: buttonText || link.textContent,
          errorText: buttonText || link.textContent
        }, settings)
      );

      link.addEventListener('click', function(event) {
        event.preventDefault();
        ajaxHandler.execute();
      });
    }
    catch (e) {
      // Error handling without console.error.
    }
  }

  /**
   * Attaches add more recommendations behavior.
   *
   * @param {HTMLElement} link - The add more link.
   * @param {Object} settings - drupalSettings object.
   */
  function attachAddMoreRecommendationsBehavior(link, settings) {
    const section = link.dataset.section;
    if (!section) {
      return;
    }

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
          onSuccess: (target) => {
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
    }
    catch (e) {
      // Error handling without console.error.
    }
  }

  // Export functions to namespace for cross-module access.
  Drupal.aiContentStrategy.attachGenerateIdeasBehavior = attachGenerateIdeasBehavior;
  Drupal.aiContentStrategy.attachGenerateMoreBehavior = attachGenerateMoreBehavior;
  Drupal.aiContentStrategy.attachAddMoreRecommendationsBehavior = attachAddMoreRecommendationsBehavior;

  /**
   * Main generate button behavior.
   */
  Drupal.behaviors.contentStrategyGenerate = {
    attach: function(context, settings) {
      // Handle main generate button.
      once('contentStrategyGenerate', '.generate-recommendations', context).forEach((button) => {
        try {
          DOMUtils.ensureElementId(button, 'content-strategy');
          const ajaxHandler = new Drupal.Ajax(
            button.id,
            button,
            createAjaxHandler({
              element: button,
              url: 'admin/reports/ai/content-strategy/generate',
              loadingText: settings?.aiContentStrategy?.buttonText?.main?.loading,
              successText: button.textContent.trim(),
              errorText: button.textContent.trim(),
              onSuccess: (target) => {
                const wrapper = document.querySelector('.recommendations-wrapper');
                if (wrapper) {
                  wrapper.querySelectorAll('.generate-ideas-link').forEach((link, index) => {
                    attachGenerateIdeasBehavior(link, index, settings);
                  });
                  wrapper.querySelectorAll('.generate-more-link').forEach((link, index) => {
                    attachGenerateMoreBehavior(link, index, settings);
                  });
                  wrapper.querySelectorAll('.add-more-recommendations-link').forEach((link) => {
                    attachAddMoreRecommendationsBehavior(link, settings);
                  });
                }
              }
            }, settings)
          );

          button.addEventListener('click', function(event) {
            event.preventDefault();

            if (button.dataset.hasExisting === 'true') {
              const confirmed = confirm(
                Drupal.t('Regenerate all recommendations?\n\n') +
                Drupal.t('All existing recommendations will be replaced. This cannot be undone.')
              );
              if (!confirmed) {
                return;
              }
            }

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
        }
        catch (e) {
          // Error handling without console.error.
        }
      });

      // Handle generate ideas links.
      once('generateIdeas', '.generate-ideas-link', context).forEach((link, index) => {
        attachGenerateIdeasBehavior(link, index, settings);
      });

      // Handle generate more links.
      once('generateMore', '.generate-more-link', context).forEach((link, index) => {
        attachGenerateMoreBehavior(link, index, settings);
      });

      // Handle add more recommendations links.
      once('addMoreRecs', '.add-more-recommendations-link', context).forEach((link) => {
        attachAddMoreRecommendationsBehavior(link, settings);
      });
    }
  };

})(Drupal, once);
