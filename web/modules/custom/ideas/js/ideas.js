(function (Drupal, drupalSettings) {
  Drupal.behaviors.ideasPopup = {
    attach: function (context, settings) {
      if (settings.ideas && settings.ideas.submissionSuccess && !context.querySelector('.ideas-popup-modal')) {
        // Prevent re-showing
        settings.ideas.submissionSuccess = false;

        console.log("Hello world");

        // Create modal wrapper
        const overlay = document.createElement('div');
        overlay.className = 'ideas-popup-modal fixed top-0 left-0 w-full h-full flex items-center justify-center bg-black bg-opacity-50 z-50';

        // Create modal content
        overlay.innerHTML = `
          <div class="bg-white rounded-lg shadow-lg p-6 text-center max-w-md">
            <h2 class="text-xl font-bold mb-4">Thank you!</h2>
            <p class="mb-4">Your idea has been submitted successfully.</p>
            <button onclick="window.location.reload()" class="mt-2 bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded" id="popup-close">Close</button>
          </div>
        `;

        document.body.appendChild(overlay);

        // Close handler
        const closeBtn = overlay.querySelector('#popup-close');
        if (closeBtn) {
          closeBtn.addEventListener('click', function () {
            overlay.classList.add('fade-out');
            setTimeout(() => {
              overlay.remove();
            }, 300);
          });
        }
      }
    }
  };
})(Drupal, drupalSettings);

