(function ($, Drupal) {
  console.log('Report Grievance JS loaded');

  let gmap = null;
  let map = null;
  envSettings.gKey = drupalSettings.globalVariables.mapConfig[0].gKey;
  envSettings.mapDimension = drupalSettings.globalVariables.mapConfig[0].mapDimension;
  envSettings.mapLib = drupalSettings.globalVariables.mapConfig[0].mapLib;
  envSettings.type = drupalSettings.globalVariables.mapConfig[0].type;
  envSettings.mapData = drupalSettings.globalVariables.mapConfig[0].mapData;
  envSettings.gwc = drupalSettings.globalVariables.mapConfig[0].gwc;
  envSettings.offline = drupalSettings.globalVariables.mapConfig[0].offline;
  envSettings.extent1 = Number.parseFloat(drupalSettings.globalVariables.mapConfig[0].extent1);
  envSettings.extent2 = Number.parseFloat(drupalSettings.globalVariables.mapConfig[0].extent2);
  envSettings.extent3 = Number.parseFloat(drupalSettings.globalVariables.mapConfig[0].extent3);
  envSettings.extent4 = Number.parseFloat(drupalSettings.globalVariables.mapConfig[0].extent4);
  envSettings.lat = Number.parseFloat(drupalSettings.globalVariables.mapConfig[0].lat);
  envSettings.lon = Number.parseFloat(drupalSettings.globalVariables.mapConfig[0].lon);

  function createMap() {
    map = tmpl.Map.mapCreation({
      target: "mapDiv",
      callBackFun: function callBackFun(maps, res) {
        gmap = maps;
        setTimeout(() => {
          addSearchBox();
          zoomToExtent(maps);
        }, 2500);
        console.log("Responce..MapObject & Responce Object", maps, res);
      },
    });
    console.log("Map Creation : ", map);
  }

  function addSearchBox() {
    let search = tmpl.Search.addSearchBox({
      map: gmap,
      img_url: "./themes/custom/engage_theme/images/user-location-50.png",
      height: 50,
      width: 25,
      zoom_button: false,
    });
    function result(resule) {
      console.log(result);
      getAddress(resule);
    }
    function getAddress(coord) {
      console.log("coordddd", coord);
      tmpl.Geocode.getGeocode({
        point: [coord.lon, coord.lat],
        callbackFunc: handleGeocode,
      });
    }
    console.log("Add Search..", search);
  }

  /**
   * > The function `mapResize` is called when the window is resized. It calls the `tmpl.Map.resize`
   * function, which is a function that is part of the `tmpl` object
   * @param mapData - The map data object that was returned from the map creation function.
   */
  /**
   * > This function zooms the map to the extent specified in the envSettings object
   * @param mapData - The map object that you want to zoom to the extent of.
   */
  /**
   * It zooms the map to the extent specified in the envSettings object
   * @param mapData - The map object that you want to zoom to the extent of.
   */
  function zoomToExtent(mapData) {
    try {
      tmpl.Zoom.toExtent({
        map: mapData,
        extent: [
          envSettings.extent1,
          envSettings.extent2,
          envSettings.extent3,
          envSettings.extent4,
        ],
      });
    } catch (error) {
      console.error("Error at tmpl.Zoom.toExtent > ", error);
    }
  }

  $(document).on("click", ".get_lat_lang", function () {
    addPoint(gmap);
  });

  /**
   * "Draw a point on the map and then call the getDrawFeatureDetails function when the user is done
   * drawing."
   *
   * The first thing we do is call the draw function from the tmpl.Draw object. This function takes an
   * object as a parameter. The object has three properties: map, type, and callbackFunc. The map
   * property is the mapData object that we passed to the addPoint function. The type property is the
   * type of feature we want to draw. In this case, we want to draw a point. The callbackFunc property is
   * the function that we want to call when the user is done drawing. In this case, we want to call the
   * getDrawFeatureDetails function
   * @param mapData - The map object
   */
  function addPoint(mapData) {
    tmpl.Draw.draw({
      map: mapData,
      type: "Point",
      callbackFunc: getDrawFeatureDetails,
    });
  }

  /**
   * The function is called when a user clicks on the map. It takes the coordinates of the click, and
   * uses the tmpl.Geocode.getGeocode function to get the address of the click
   * @param coord - The coordinates of the point that was clicked on the map.
   * @param feature - The feature that was drawn.
   * @param wktGeom - The geometry of the feature in WKT format.
   * @param value - The value of the feature.
   */
  function getDrawFeatureDetails(coord, feature, wktGeom, value) {
    console.log(coord);
    tmpl.Layer.clearData({
      map: gmap,
      layer: "Incident_Layer",
    });
    tmpl.Overlay.create({
      map: gmap,
      features: [
        {
          id: 1,
          label: "",
          label_color: "#fff",
          img_url: "./themes/custom/engage_theme/images/user-location-50.png",
          lat: coord[1],
          lon: coord[0],
        },
      ],
      layer: "Incident_Layer",
      layerSwitcher: false,
    });
    tmpl.Layer.changeVisibility({
      map: gmap,
      visible: true,
      layer: "Incident_Layer",
    });
    tmpl.Zoom.toXYcustomZoom({
      map: gmap,
      latitude: coord[1],
      longitude: coord[0],
      zoom: 15,
    });
    getAddress(coord);
    function getAddress(coords) {
      console.log("coordddd", coords);
      tmpl.Geocode.getGeocode({
        point: [coords[0], coords[1]],
        callbackFunc: handleGeocode,
      });

      document.querySelector('.lat-input').value = coord[0];
      document.querySelector('.lng-input').value = coord[1];
    }
  }


  /**
   * It takes the address from the geocode API and sets it as the value of the address input field
   * @param data - The data object returned from the geocoder.
   */
  function handleGeocode(data) {
    let grievanceAddress = sessionStorage.setItem(
      "grievanceAddress",
      data.address
    );
    console.log(data);
    console.log(grievanceAddress);
    let appendAddress = document.querySelector("#edit-address");
    appendAddress.value = data.address;
    appendAddress.setAttribute("value", data.address);

  }

  /* Waiting for the page to load before running the code. */
  window.onload = function () {
    createMap();
  };

})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.reportGrievanceValidation = {
    attach: function (context, settings) {
      // Wait until jQuery Validate is loaded
      if (typeof $.fn.validate !== 'function') {
        console.error('jQuery Validate is not loaded!');
        return;
      }

      let $form = $('#report-grievance', context);

      // Prevent double initialization
      if ($form.data('validated')) return;
      $form.data('validated', true);

      // custom file size method
      $.validator.addMethod("filesize", function (value, element, param) {
        if (element.files.length === 0) return true;
        return this.optional(element) || (element.files[0].size <= param);
      }, "File size must be less than 2MB");

      // custom extension method for file
      $.validator.addMethod("extensionFile", function (value, element, param) {
        if (element.files.length === 0) return true;
        let allowed = param.split('|');
        let fileName = element.files[0].name.toLowerCase();
        for (const ext of allowed) {
          if (fileName.endsWith(ext)) {
            return true;
          }
        }
        return false;
      }, "Invalid file type");

      $form.validate({
        rules: {
          grievance_type: { required: true },
          grievance_subtype: { required: true },
          remarks: { required: true, minlength: 10, maxlength: 255 },
          address: { required: true, maxlength: 255 },
          "files[upload_file]": { required: true, extensionFile: "jpg|jpeg|png|pdf|doc|docx|mp4", filesize: 2097152 },
          agree_terms: { required: true }
        },
        messages: {
          grievance_type: "Please select a Category",
          grievance_subtype: "Please select a Sub Category",
          remarks: { required: "Remarks are required", minlength: "At least 5 characters", maxlength: "Max 255 characters" },
          address: { required: "Address is required", maxlength: "Max 255 characters" },
          "files[upload_file]": { required: "Please upload a file", extensionFile: "Invalid file type", filesize: "File must be <= 2MB" },
          agree_terms: "You must agree to the Terms and Conditions"
        },
        errorClass: "text-red-500 text-sm mt-1 block",
        errorPlacement: function (error, element) {
          if (element.attr("type") === "checkbox") {
            error.insertAfter(element.closest('label'));
          } else {
            error.insertAfter(element);
          }
        },
        highlight: function (element) { $(element).addClass("border-red-500"); },
        unhighlight: function (element) { $(element).removeClass("border-red-500"); }
      });
    }
  };
})(jQuery, Drupal);

/**
       * Utility: Reset select with placeholder text
       */
function resetSelect($select, placeholder, disabled = false) {
  $select.empty().append(`<option value="">${placeholder}</option>`);
  $select.prop('disabled', disabled);
}

/**
 * Fetch JSON with proper error handling
 */
async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
  return await response.json();
}

(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.reportGrievanceForm = {
    attach: function (context) {
      const $context = $(context);
      const $typeSelect = $context.find('.grievance-type-select');
      const $subtypeSelect = $context.find('.grievance-subtype-select');

      const typesUrl = drupalSettings?.reportgrievance?.endpoints?.types || '/grievance/types';
      const subtypesUrlTemplate = drupalSettings?.reportgrievance?.endpoints?.subtypes || '/grievance/subtypes/';

      // Prevent multiple attaches
      if (!$typeSelect.length || !$subtypeSelect.length || $typeSelect.data('attached')) return;
      $typeSelect.data('attached', true);


      /**
       * Load grievance types
       */
      async function loadTypes() {
        resetSelect($typeSelect, 'Loading categories...', true);
        resetSelect($subtypeSelect, 'Select Sub Category', true);

        try {
          const data = await fetchJson(typesUrl);
          resetSelect($typeSelect, 'Select a Category', false); // ✅ Matches form

          if (Array.isArray(data)) {
            data.forEach(item => {
              const key = item.key ?? Object.keys(item)[0];
              const value = item.value ?? Object.values(item)[0];
              if (key && value) $typeSelect.append(`<option value="${key}">${value}</option>`);
            });
          } else if (typeof data === 'object') {
            Object.entries(data).forEach(([key, value]) => {
              $typeSelect.append(`<option value="${key}">${value}</option>`);
            });
          } else {
            console.error('Unexpected types response format:', data);
          }
        } catch (err) {
          console.error('Failed to load types:', err);
          resetSelect($typeSelect, 'Failed to load', true);
        }
      }

      /**
       * Load subtypes for a selected category
       */
      async function loadSubtypesFor(typeKey) {
        resetSelect($subtypeSelect, 'Loading subcategories...', true);

        try {
          const data = await fetchJson(subtypesUrlTemplate + encodeURIComponent(typeKey));
          resetSelect($subtypeSelect, 'Select Sub Category', false); // ✅ Matches form

          if (Array.isArray(data)) {
            data.forEach(item => {
              const key = item.key ?? Object.keys(item)[0];
              const value = item.value ?? Object.values(item)[0];
              if (key && value) $subtypeSelect.append(`<option value="${key}">${value}</option>`);
            });
          } else if (typeof data === 'object') {
            Object.entries(data).forEach(([key, value]) => {
              $subtypeSelect.append(`<option value="${key}">${value}</option>`);
            });
          } else {
            console.error('Unexpected subtypes response format:', data);
          }
        } catch (err) {
          console.error('Failed to load subtypes:', err);
          resetSelect($subtypeSelect, 'Failed to load', true);
        }
      }

      // Initialize on page load
      loadTypes();

      // Load subtypes dynamically when type changes
      $typeSelect.on('change', function () {
        const selected = $(this).val();
        if (selected) {
          loadSubtypesFor(selected);
        } else {
          resetSelect($subtypeSelect, 'Select Sub Category', true);
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);