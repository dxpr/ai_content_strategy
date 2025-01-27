((Drupal, once) => {
  'use strict';

  Drupal.behaviors.contentIdeas = {
    attach: function (context) {
      console.log('Attaching content ideas behavior');
      
      // Handle generate recommendations button
      const buttons = once('generate-recommendations', '.generate-recommendations', context);
      console.log('Found generate buttons:', buttons.length);
      
      buttons.forEach((button) => {
        console.log('Adding click handler to button:', button);
        
        button.addEventListener('click', async (e) => {
          e.preventDefault();
          console.log('Button clicked');
          
          // Find container
          const container = button.closest('.content-strategy-recommendations');
          console.log('Looking for container:', container);
          
          if (!container) {
            console.error('Could not find recommendations container');
            console.log('Button parent structure:', button.parentElement);
            return;
          }
          
          try {
            // Show loading state
            button.disabled = true;
            button.classList.add('is-loading');
            button.value = Drupal.t('Generating recommendations...');
            button.setAttribute('value', Drupal.t('Generating recommendations...'));
            
            console.log('Fetching recommendations...');
            const url = Drupal.url('admin/reports/ai/content-strategy/generate');
            console.log('Request URL:', url);
            
            // Make the fetch request
            const response = await fetch(url, {
              method: 'GET',
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
            });

            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Received response:', data);

            if (!data.success) {
              throw new Error(data.error || 'Unknown error occurred');
            }

            // Remove existing content
            const existingContent = container.querySelectorAll('.recommendations, .empty-recommendations');
            console.log('Found existing content:', existingContent?.length);
            existingContent?.forEach(el => el.remove());
            
            // Create a temporary container to parse the HTML
            const temp = document.createElement('div');
            temp.innerHTML = data.html;
            
            // Extract and append recommendation sections
            const recommendations = temp.querySelectorAll('.recommendation-section');
            console.log('Found recommendation sections:', recommendations?.length);
            
            if (recommendations?.length) {
              const wrapper = document.createElement('div');
              wrapper.className = 'recommendations';
              recommendations.forEach(section => {
                wrapper.appendChild(section.cloneNode(true));
              });
              container.appendChild(wrapper);
              console.log('Added recommendations to container');
            }
            
            // Update last run time
            const lastRunEl = container.querySelector('.last-run-time');
            if (lastRunEl) {
              lastRunEl.remove();
            }
            const actionsEl = container.querySelector('.content-strategy-actions');
            if (actionsEl) {
              const newLastRun = document.createElement('div');
              newLastRun.className = 'last-run-time';
              newLastRun.innerHTML = data.last_run;
              actionsEl.appendChild(newLastRun);
              console.log('Updated last run time');
            }
            
            // Update button text
            button.value = Drupal.t('Refresh Recommendations');
            button.setAttribute('value', Drupal.t('Refresh Recommendations'));

            // Reattach behaviors for new content
            console.log('Reattaching behaviors');
            Drupal.attachBehaviors(container);
          }
          catch (error) {
            console.error('AJAX Error:', error);
            console.log('Error details:', {
              message: error.message,
              stack: error.stack
            });
            alert(Drupal.t('An error occurred while generating recommendations: @error', {
              '@error': error.message
            }));
          }
          finally {
            // Remove loading state
            button.disabled = false;
            button.classList.remove('is-loading');
          }
        });
      });

      // Handle generate more ideas links
      once('content-ideas', '.generate-more-link', context).forEach((link) => {
        link.addEventListener('click', async (e) => {
          e.preventDefault();
          
          const item = link.closest('.recommendation-item');
          if (!item) return;
          
          const table = item.querySelector('.content-ideas-table tbody');
          if (!table) return;
          
          const section = link.dataset.section;
          const title = link.dataset.title;
          
          if (!section || !title) {
            console.error('Missing required data attributes');
            return;
          }
          
          try {
            // Show loading state
            link.classList.add('is-loading');
            link.disabled = true;
            
            // Make the fetch request
            const response = await fetch(Drupal.url(`admin/reports/ai/content-strategy/generate-more/${section}/${encodeURIComponent(title)}`), {
              method: 'GET',
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
            });

            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (!data.success) {
              throw new Error(data.error || 'Failed to generate more ideas');
            }

            if (!Array.isArray(data.ideas)) {
              throw new Error('Invalid response format');
            }

            // Add new rows to the table
            data.ideas.forEach(idea => {
              const row = document.createElement('tr');
              const cell = document.createElement('td');
              cell.textContent = idea;
              row.appendChild(cell);
              table.appendChild(row);
            });
          }
          catch (error) {
            console.error('AJAX Error:', error);
            alert(Drupal.t('An error occurred while generating more ideas: @error', {
              '@error': error.message
            }));
          }
          finally {
            // Remove loading state
            link.classList.remove('is-loading');
            link.disabled = false;
          }
        });
      });
    }
  };

})(Drupal, once); 