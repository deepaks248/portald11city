(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('remove-profile-button');
        if (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('ajax-trigger-hidden')?.click();
            });
        }
    });
})();


async function fileUpload(fileData, inputName) {
    console.log(fileData, "```````````");
    console.log(inputName, "`````````````````");

    const file = fileData.files[0];
    const errorMsg = document.querySelector(".error-msg");
    const fileName = file.name;
    console.log("fileName", fileName);

    // Disallow multiple extensions
    if (fileName.split(".").length > 2) {
        alert("Multiple file extensions not allowed");
        return;
    }

    const extMatch = fileName.match(/\.[a-zA-Z0-9]+$/); // Match file extension (starting with a dot)
    const extFile = extMatch ? extMatch[0].slice(1).toLowerCase() : ""; // Remove the dot and convert to lowercase

    let fileTypeVal = "", fileTypeType = "", fileTypeValid = false;

    if (["jpg", "jpeg", "png"].includes(extFile)) {
        fileTypeVal = 2;
        fileTypeType = "image";
        fileTypeValid = true;
    } else if (["pdf", "doc", "docx", "mp4"].includes(extFile)) {
        fileTypeVal = 4;
        fileTypeType = "file";
        fileTypeValid = true;
    } else {
        errorMsg?.classList.remove("hidden");
        return Promise.reject("Invalid file type");
    }

    if (!fileTypeValid) {
        errorMsg?.classList.remove("hidden");
        return Promise.reject("Invalid file type");
    }

    const formData = new FormData();
    const filename = "portal_" + Date.now() + "." + extFile;
    const url = drupalSettings.globalVariables.webportalUrl + "fileupload";

    formData.append("uploadedfile1", file, filename);
    formData.append("success_action_status", "200");
    formData.append('userPic', inputName);

    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open("POST", url, true);
        request.withCredentials = false;

        request.onreadystatechange = function () {
            if (request.readyState !== 4) return;

            if (request.status === 200) {
                let successorFailure = document.querySelector(".successOrFailure");
                 successorFailure.classList.remove("hidden");
                let responseObject;
                try {
                    responseObject = JSON.parse(request.responseText);
                    console.log("Response", responseObject);
                } catch (e) {
                    console.error("Invalid JSON from server:", request.responseText);
                    errorMsg?.classList.remove("hidden");
                    return reject("Upload failed: Invalid server response");
                }

                // Assign values

                if (inputName === "profilePic") {
                    const img = document.querySelector('.profilePicSrc');
                    if (img) {
                        img.setAttribute('src', responseObject.profilePic);
                            document.querySelector(".update-img").src =
                        "themes/custom/engage_theme/images/Profile/change-success.png";
                    document.querySelector(".update-msg").innerHTML =
                        '<p class="update-msg text-[#00AB26]">Profile Added successfully</p>';
                    setTimeout(() => {
                        successorFailure.classList.add("hidden");
                    }, 5000)


                    }
                } else {
                    // Set the file name
                      document.querySelector(".update-img").src =
                        "themes/custom/engage_theme/images/Profile/failure.svg";
                    document.querySelector(".update-msg").innerHTML =
                        '<p class="update-msg text-[#de001b] translateLabel" label-alias="la_family_member_not_deleted_successfully">Profile not added successfully</p>';
                    setTimeout(() => {
                        successorFailure.classList.add("hidden");
                    }, 5000);
                    document.getElementById(inputName + "_name").value = responseObject.fileName;

                    // Set values (not attributes)
                    document.getElementById(inputName + "_id").value = responseObject.fileTypeId;
                    document.getElementById(inputName + "_type").value = responseObject.fileTypeVal;
                }
            } else {
                console.warn("Upload failed response:", request.responseText);
                errorMsg?.classList.remove("hidden");
                reject("Upload failed with status " + request.status);
            }
        };

        request.send(formData);
    });

}

console.log("CONSO", window.drupalSettings);
console.log("Popup flag: ", window.drupalSettings.profile_form?.show_success_popup);
if (window.drupalSettings.profile_form?.show_success_popup) {
    document.getElementById('feedback_profile')?.classList.remove('hidden');
}

// Clicking on Delete Icon to enable the Delete Icon for all Family Members
document.querySelector(".showDeleteIcons").addEventListener("click", (el) => {
    el.stopPropagation();
    el.preventDefault();
    console.log("showDeleteIcons");
    document.querySelector(".swap-off").classList.toggle("hidden");
    document.querySelector(".swap-on").classList.toggle("hidden");
    document.querySelectorAll(".deleteRedIcon").forEach((element) => {
        element.classList.toggle("hidden");
    });
});


document.addEventListener('DOMContentLoaded', function () {
    const modalTriggerButtons = document.querySelectorAll('[data-modal-toggle]');
    const hiddenInput = document.getElementById('delete-nid');
    let successorFailure = document.querySelector(".successOrFailure");
    modalTriggerButtons.forEach(button => {
        button.addEventListener('click', function () {
            const nid = this.getAttribute('data-nid');
            hiddenInput.value = nid;
        });
    });

    document.querySelector('.confirm-delete-btn').addEventListener('click', function () {
        const selectedNid = hiddenInput.value;
        console.log('Deleting member with nid:', selectedNid);
        fetch(drupalSettings.globalVariables.webportalUrl + "postData", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                endPoint: `family-members/delete-family-member/${selectedNid}`,
                service: "tiotcitizenapp",
                type: 2,
            }),
        })
            .then((response) => response.json())
            .then((response) => {
                successorFailure.classList.remove("hidden");
                console.log("Controll", response);
                if (response.status === true) {
                     document.querySelector(`[disable-nid="${selectedNid}"]`).classList.add('hidden');
                    console.log("eurwrtewtwutytyutyuryurtyeyrer", response);
                    document.querySelector(".update-img").src =
                        "themes/custom/engage_theme/images/Profile/change-success.png";
                    document.querySelector(".update-msg").innerHTML =
                        '<p class="update-msg text-[#00AB26]">Family member deleted successfully</p>';
                    setTimeout(() => {
                        successorFailure.classList.add("hidden");
                    }, 5000)
                }
                else {
                    document.querySelector(".update-img").src =
                        "themes/custom/engage_theme/images/Profile/change-success.png";
                    document.querySelector(".update-msg").innerHTML =
                        '<p class="update-msg text-[#de001b] translateLabel" label-alias="la_family_member_not_deleted_successfully">Family member not deleted successfully</p>';
                    setTimeout(() => {
                        successorFailure.classList.add("hidden");
                    }, 5000);
                }
            }
            );

    });
});

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (event) {
        const target = event.target.closest('.confirm-logout-btn');
        if (target) {
            event.preventDefault();
            if (typeof drupalSettings.globalVariables.webportalUrl !== 'undefined') {
                window.location.href = drupalSettings.globalVariables.webportalUrl + '/logout';
            } else {
                console.error('siteURL is not defined');
            }
        }
    });
});



  const deleteButton = document.querySelector('.confirm-account-btn');
  const deleteInput = document.querySelector('#delete');

  if (deleteButton && deleteInput) {
    deleteButton.addEventListener('click', function (event) {
      const inputValue = deleteInput.value.trim();

      if (inputValue !== 'DELETE') {
        event.preventDefault();

      }

      deleteUser();
    });
  }

  function deleteUser() {
    const payload = {
      endPoint: "deleteUserAccount",
      payload: {
        tenantCode: drupalSettings.globalVariables.ceptenantCode
      },
      service: "tiotweb",
      type: "delyUser"
    };

    fetch(drupalSettings.globalVariables.webportalUrl + "postData", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
      console.log("Delete response:", data);
      if (data.status === true) {
        const loading = document.querySelector(".loading");
        if (loading) loading.classList.remove("hidden");

        window.location.href = drupalSettings.globalVariables.webportalUrl + "/logout";
      } else {
        document.querySelector('.deleteModalDiv')?.classList.add('hidden');
        document.querySelector('#deleteAccount')?.classList.remove('hidden');
      }
    })
    .catch(error => {
      console.error("Delete request failed:", error);
    });
  };

(function (Drupal, drupalSettings) {
  Drupal.behaviors.profilePictureRemove = {
    attach: function (context, settings) {
      console.log("📌 profilePictureRemove behavior attached");

      // Main "Remove" button (opens modal)
      once('remove-profile-picture', '#remove-profile-picture', context).forEach(function (button) {
        console.log("✅ Found remove-profile-picture button:", button);
        button.addEventListener('click', function (e) {
          e.preventDefault();
          console.log("🖱️ Remove button clicked");
          document.querySelector('#remove-profile-picture-modal')?.classList.remove('hidden');
        });
      });

      // Modal "Confirm Remove" button (#remove-btn)
      once('remove-btn', '#remove-btn', context).forEach(function (button) {
        console.log("✅ Found confirm remove button (#remove-btn):", button);
        button.addEventListener('click', function (e) {
          e.preventDefault();
          console.log("🖱️ Confirm Remove button clicked");
          document.querySelector('#global-spinner').classList.remove('hidden');
          removePP();
        });
      });

      function removePP() {
        console.log("🚀 removePP() triggered");

        const payload = {
          endPoint: "detailsUpdate",
          payload: {
            tenantCode: drupalSettings.globalVariables.ceptenantCode
          },
          service: "cityapp",
          type: "2"
        };


        fetch(drupalSettings.globalVariables.webportalUrl + "detailsUpdate", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(payload)
        })
        .then(response => {
          return response.json();
        })
        .then(data => {
          if (data.status === true) {
            console.log("🎉 Profile picture removed successfully");
            document.querySelector('.profilePicSrc').setAttribute('src','/themes/custom/engage_theme/images/Profile/profile_pic.png');
            document.querySelector('#global-spinner').classList.add('hidden');
            window.location.reload();
          } else {
            console.warn("⚠️ Removal failed:", data);
            document.querySelector('#global-spinner').classList.add('hidden');
          }
        })
        .catch(error => {
          console.error("❌ Delete request failed:", error);
          document.querySelector('#global-spinner').classList.add('hidden');
        });
      }
    }
  };
})(Drupal, drupalSettings);

(function ($, Drupal) {
  Drupal.behaviors.profileValidation = {
    attach: function (context, settings) {
      // Ensure jQuery Validate is loaded
      if ($.validator === 'undefined') {
        console.error('jQuery Validate is not loaded!');
        return;
      }

      let $form = $('#profile-form', context);
      if (!$form.length) return;

      // Prevent double initialization
      if ($form.data('validated')) return;
      $form.data('validated', true);
      console.log("Form", $form);

      // Initialize validation
      $form.validate({
        rules: {
          first_name: { required: true, minlength: 2, maxlength: 50 },
          last_name: { required: true, minlength: 2, maxlength: 50 },
          dob: { required: true, date: true },
          gender: { required: true },
          mobile: { required: true, digits: true, minlength: 10, maxlength: 10 },
          email: { required: true, email: true },
          address: { required: true, minlength: 5, maxlength: 50 }
        },
        messages: {
          first_name: { required: "First name is required", minlength: "Must be at least 2 characters", maxlength: "Max 50 characters" },
          last_name: { required: "Last name is required", minlength: "Must be at least 2 characters", maxlength: "Max 50 characters" },
          dob: { required: "Date of birth is required", date: "Invalid date" },
          gender: { required: "Please select gender" },
          mobile: { required: "Mobile number is required", digits: "Only digits allowed", minlength: "Must be 10 digits", maxlength: "Must be 10 digits" },
          email: { required: "Email is required", email: "Enter a valid email address" },
          address: { required: "Address is required", minlength: "At least 5 characters", maxlength: "Max 50 characters" }
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
      if (Drupal.Ajax !== 'undefined') {
        Drupal.Ajax.prototype.beforeSubmit = function (form_values, element_settings, options) {
          // Only validate if form has class 'cv-validate-before-ajax' or validateAll is set
          let validateAll = 1; // or set dynamically if needed
          console.log("wjknwejkw",validateAll);
          if (typeof this.$form !== 'undefined' &&
              (validateAll === 1 || $(this.$form).hasClass('cv-validate-before-ajax')) &&
              $(this.element).attr("formnovalidate") === undefined) {

            $(this.$form).removeClass('ajax-submit-prevented');

            // Trigger jQuery validation
            $(this.$form).validate();
            if (!($(this.$form).valid())) {
              this.ajaxing = false;
              $(this.$form).addClass('ajax-submit-prevented');
              console.log(this.$form);
              return false;
            }
          }
        };
      }

    }
  };
})(jQuery, Drupal);