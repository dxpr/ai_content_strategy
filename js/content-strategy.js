// /**
//  * @file
//  * Behaviors for the content strategy module.
//  */
// (function (Drupal, once) {
//   'use strict';

//   /**
//    * Behavior for content strategy functionality.
//    *
//    * @type {Drupal~behavior}
//    */
//   Drupal.behaviors.contentStrategy = {
//     attach: function (context, settings) {
//       // Handle recommendation generation
//       once('contentStrategy', '.generate-recommendations', context).forEach(function (button) {
//         button.addEventListener('click', function (e) {
//           e.preventDefault();
          
//           // Add loading state
//           button.classList.add('is-loading');
//           button.disabled = true;

//           // Make AJAX request
//           fetch(Drupal.url('admin/reports/ai/content-strategy/generate'), {
//             method: 'GET',
//             headers: {
//               'Content-Type': 'application/json',
//             },
//           })
//           .then(response => response.json())
//           .then(data => {
//             if (data.success) {
//               // Update the content
//               const container = document.querySelector('.content-strategy-recommendations');
//               container.innerHTML = data.html;
              
//               // Show success message
//               Drupal.message('status', Drupal.t('Recommendations generated successfully.'));
//             } else {
//               throw new Error(data.error || Drupal.t('An error occurred while generating recommendations.'));
//             }
//           })
//           .catch(error => {
//             // Show error message
//             Drupal.message('error', error.message);
//           })
//           .finally(() => {
//             // Remove loading state
//             button.classList.remove('is-loading');
//             button.disabled = false;
//           });
//         });
//       });
//     }
//   };
// })(Drupal, once); 