/**
 * @file
 * CSV export functionality for AI Content Strategy recommendations.
 */

((Drupal, once) => {
  'use strict';

  /**
   * CSV export behavior.
   */
  Drupal.behaviors.contentStrategyExport = {
    attach: function(context, settings) {
      once('contentStrategyExport', '.export-csv-button', context).forEach((button) => {
        button.addEventListener('click', function(event) {
          event.preventDefault();

          const csvData = [];

          // Header row
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
