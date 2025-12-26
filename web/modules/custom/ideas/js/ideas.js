(function (Drupal, drupalSettings) {
  Drupal.behaviors.ideasPopup = {
    attach: function (context, settings) {
      if (settings.ideas && settings.ideas.submissionSuccess && !context.querySelector('.ideas-popup-modal')) {
        // Prevent re-showing
        settings.ideas.submissionSuccess = false;

        // Create modal wrapper
        const overlay = document.createElement('div');
        overlay.className = 'ideas-popup-modal fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50';

        // Create modal content
        overlay.innerHTML = `
          <div class="bg-white rounded-lg shadow-lg p-6 text-center text-center p-10 flex flex-col justify-center items-center">
            <img src="themes/custom/engage_theme/images/Profile/success.png" alt="Success Popup">
            <p class="font-bold text-3xl font-['nevis'] mb-10 flex justify-center mb-[20px]">Your idea has been submitted successfully.</p>
            <button onclick="window.location.href='./ideas'" class="mt-2 bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded" id="popup-close">Close</button>
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


(function ($, Drupal) {
  Drupal.behaviors.ideasFormValidation = {
    attach: function (context, settings) {
      // Ensure jQuery Validate is loaded
      if (typeof $.validator === 'undefined') {
        console.error('jQuery Validate is not loaded!');
        return;
      }

      var $form = $('#ideas-form', context);

      // Prevent double initialization
      if ($form.data('validated')) return;
      $form.data('validated', true);

      // Custom file size method
      $.validator.addMethod("filesize", function (value, element, param) {
        if (element.files.length === 0) return true;
        return this.optional(element) || (element.files[0].size <= param);
      }, "File size must be less than 2MB");

      // Custom file extension method
      $.validator.addMethod("extensionFile", function (value, element, param) {
        if (element.files.length === 0) return true;
        var allowed = param.split('|');
        var fileName = element.files[0].name.toLowerCase();
        for (var i = 0; i < allowed.length; i++) {
          if (fileName.endsWith(allowed[i])) return true;
        }
        return false;
      }, "Invalid file type");

      // Initialize validation
      $form.validate({
        rules: {
          first_name: { required: true, minlength: 2, maxlength: 50 },
          author: { required: true },
          category_idea: { required: true },
          idea_content: { required: true, minlength: 5 },
          "files[upload_file]": { required: true, extensionFile: "jpg|jpeg|png|pdf", filesize: 2097152 },
          terms: { required: true }
        },
        messages: {
          first_name: { required: "Title is required", minlength: "Title must be at least 2 characters", maxlength: "Max 50 characters" },
          author: { required: "Author is required" },
          category_idea: { required: "Please select a category" },
          idea_content: { required: "Idea content is required", minlength: "Idea content must be at least 5 characters" },
          "files[upload_file]": { required: "Please upload a file", extensionFile: "Invalid file type", filesize: "File must be <= 2MB" },
          terms: "You must agree to the Terms and Conditions"
        },
        errorClass: "text-red-500 text-sm mt-1 block",
        errorPlacement: function (error, element) {
          if (element.attr("type") === "checkbox") {
            error.insertAfter(element.closest('div'));
          } else {
            error.insertAfter(element);
          }
        },
        highlight: function (element) { $(element).addClass("border-red-500"); },
        unhighlight: function (element) { $(element).removeClass("border-red-500"); }
      });

      // Override Drupal AJAX beforeSubmit
      if (typeof Drupal.Ajax !== 'undefined') {
        Drupal.Ajax.prototype.beforeSubmit = function (form_values, element_settings, options) {
          // Only validate if form has class 'cv-validate-before-ajax' or validateAll is set
          var validateAll = 1; // or set dynamically if needed
          if (typeof this.$form !== 'undefined' &&
              (validateAll === 1 || $(this.$form).hasClass('cv-validate-before-ajax')) &&
              $(this.element).attr("formnovalidate") === undefined) {

            $(this.$form).removeClass('ajax-submit-prevented');

            // Trigger jQuery validation
            $(this.$form).validate();
            if (!($(this.$form).valid())) {
              this.ajaxing = false;
              $(this.$form).addClass('ajax-submit-prevented');
              return false;
            }
          }
        };
      }

    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.ideasFilePreUpload = {
    attach: function (context, settings) {
      const $fileInput = $('#edit-upload-file', context);
      const $hiddenField = $('#uploaded_file_url', context);

      // Prevent multiple bindings
      if ($fileInput.data('ideas-file-uploaded')) return;
      $fileInput.data('ideas-file-uploaded', true);

      const $status = $('<div id="upload-status" class="text-sm text-gray-500 mt-1 hidden">Uploading...</div>');
      $fileInput.after($status);

      $fileInput.on('change', function (e) {
        const file = this.files[0];
        if (!file) return;

        $status.text('Uploading...').removeClass('hidden');

        const formData = new FormData();

        formData.append('files[upload_file]', file);

        $.ajax({
          url: '/ideas/upload-file',
          type: 'POST',
          data: formData,
          contentType: false,
          processData: false,
          success: function (response) {
            if (response.fileUrl) {
              $hiddenField.val(response.fileUrl);
              $status.text('File uploaded successfully');
              $hiddenField.attr('data-uploaded', 'true');
            } else {
              $status.text('Upload failed');
              $hiddenField.val('');
              $hiddenField.attr('data-uploaded', 'false');
            }
          },
          error: function () {
            $status.text('Upload failed');
            $hiddenField.val('');
            $hiddenField.attr('data-uploaded', 'false');
          }
        });
      });
    }
  };
})(jQuery, Drupal);