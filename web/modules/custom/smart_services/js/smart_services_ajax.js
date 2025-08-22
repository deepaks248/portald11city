(function ($, Drupal) {
  Drupal.behaviors.smartServicesAjax = {
    attach: function (context, settings) {
      $('.ajax-smart-service', context).not('.processed-smart-service').each(function () {
        $(this)
          .addClass('processed-smart-service')
          .on('click', function (e) {
            e.preventDefault();
            const tid = $(this).data('tid');
            const wrapper = $('#smart-services-wrapper');

            wrapper.addClass('opacity-50 pointer-events-none');

            $.ajax({
              url: '/services/' + tid,
              type: 'GET',
              dataType: 'html',
              success: function (data) {
                const newContent = $(data).find('#smart-services-wrapper');
                wrapper.replaceWith(newContent);

                // ✅ This is crucial: re-attach behaviors to new content.
                Drupal.attachBehaviors(document.getElementById('smart-services-wrapper'));
              },
              error: function () {
                alert('Failed to load Smart Service. Please try again.');
              },
              complete: function () {
                wrapper.removeClass('opacity-50 pointer-events-none');
              }
            });
          });
      });
    }
  };
})(jQuery, Drupal);
