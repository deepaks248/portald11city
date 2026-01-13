(function ($, Drupal) {
  'use strict';

  const MAP_TARGET = 'mapDiv';
  const INCIDENT_LAYER = 'Incident_Layer';
  const DEFAULT_ZOOM = 15;
  const MAP_DELAY_MS = 2500;

  let gmap = null;

  initializeEnvSettings();

  /* ---------------- INITIALIZATION ---------------- */

  window.onload = function () {
    createMap();
  };

  $(document).on('click', '.get_lat_lang', function () {
    if (gmap) {
      addPoint(gmap);
    }
  });

  /* ---------------- ENV SETTINGS ---------------- */

  function initializeEnvSettings() {
    const config = drupalSettings.globalVariables.mapConfig?.[0];
    if (!config) {
      console.warn('Map configuration missing');
      return;
    }

    envSettings.gKey = config.gKey;
    envSettings.mapDimension = config.mapDimension;
    envSettings.mapLib = config.mapLib;
    envSettings.type = config.type;
    envSettings.mapData = config.mapData;
    envSettings.gwc = config.gwc;
    envSettings.offline = config.offline;

    envSettings.extent1 = Number.parseFloat(config.extent1);
    envSettings.extent2 = Number.parseFloat(config.extent2);
    envSettings.extent3 = Number.parseFloat(config.extent3);
    envSettings.extent4 = Number.parseFloat(config.extent4);
    envSettings.lat = Number.parseFloat(config.lat);
    envSettings.lon = Number.parseFloat(config.lon);
  }

  /* ---------------- MAP CREATION ---------------- */

  function createMap() {
    tmpl.Map.mapCreation({
      target: MAP_TARGET,
      callBackFun: onMapReady,
    });
  }

  function onMapReady(maps, response) {
    gmap = maps;

    setTimeout(() => {
      addSearchBox();
      zoomToExtent(gmap);
    }, MAP_DELAY_MS);

    console.debug('Map initialized', response);
  }

  /* ---------------- SEARCH ---------------- */

  function addSearchBox() {
    tmpl.Search.addSearchBox({
      map: gmap,
      img_url: './themes/custom/engage_theme/images/user-location-50.png',
      height: 50,
      width: 25,
      zoom_button: false,
    });
  }

  /* ---------------- ZOOM ---------------- */

  function zoomToExtent(mapData) {
    if (!mapData) {
      return;
    }

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
      console.error('Zoom error', error);
    }
  }

  /* ---------------- DRAW POINT ---------------- */

  function addPoint(mapData) {
    tmpl.Draw.draw({
      map: mapData,
      type: 'Point',
      callbackFunc: handlePointDrawn,
    });
  }

  function handlePointDrawn(coord) {
    if (!coord || coord.length < 2) {
      return;
    }

    clearIncidentLayer();
    addIncidentMarker(coord);
    zoomToPoint(coord);
    geocodePoint(coord);
    updateLatLngInputs(coord);
  }

  /* ---------------- MAP HELPERS ---------------- */

  function clearIncidentLayer() {
    tmpl.Layer.clearData({
      map: gmap,
      layer: INCIDENT_LAYER,
    });
  }

  function addIncidentMarker(coord) {
    tmpl.Overlay.create({
      map: gmap,
      features: [{
        id: 1,
        img_url: './themes/custom/engage_theme/images/user-location-50.png',
        lat: coord[1],
        lon: coord[0],
      }],
      layer: INCIDENT_LAYER,
      layerSwitcher: false,
    });

    tmpl.Layer.changeVisibility({
      map: gmap,
      visible: true,
      layer: INCIDENT_LAYER,
    });
  }

  function zoomToPoint(coord) {
    tmpl.Zoom.toXYcustomZoom({
      map: gmap,
      latitude: coord[1],
      longitude: coord[0],
      zoom: DEFAULT_ZOOM,
    });
  }

  /* ---------------- GEOCODING ---------------- */

  function geocodePoint(coord) {
    tmpl.Geocode.getGeocode({
      point: [coord[0], coord[1]],
      callbackFunc: handleGeocode,
    });
  }

  function handleGeocode(data) {
    if (!data || !data.address) {
      return;
    }

    sessionStorage.setItem('grievanceAddress', data.address);

    const addressInput = document.querySelector('#edit-address');
    if (addressInput) {
      addressInput.value = data.address;
      addressInput.setAttribute('value', data.address);
    }
  }

  /* ---------------- FORM HELPERS ---------------- */

  function updateLatLngInputs(coord) {
    const latInput = document.querySelector('.lat-input');
    const lngInput = document.querySelector('.lng-input');

    if (latInput) latInput.value = coord[0];
    if (lngInput) lngInput.value = coord[1];
  }

})(jQuery, Drupal);

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.reportGrievanceValidation = {
    attach(context) {
      if (!isValidatorAvailable()) {
        console.error('jQuery Validate is not loaded!');
        return;
      }

      const $form = $('#report-grievance', context);
      if (!$form.length || $form.data('validated')) {
        return;
      }
      $form.data('validated', true);

      registerCustomValidators();
      initializeValidation($form);
    }
  };

  function isValidatorAvailable() {
    return typeof $.fn.validate === 'function';
  }

  function registerCustomValidators() {
    if ($.validator.methods.filesize) {
      return; // already registered
    }

    $.validator.addMethod(
      'filesize',
      function (_, element, maxSize) {
        return !element.files.length || element.files[0].size <= maxSize;
      },
      'File size must be less than 2MB'
    );

    $.validator.addMethod(
      'extensionFile',
      function (_, element, allowedExt) {
        if (!element.files.length) {
          return true;
        }
        const fileName = element.files[0].name.toLowerCase();
        const extensions = allowedExt.split('|');

        for (const ext of extensions) {
          if (fileName.endsWith(ext)) {
            return true;
          }
        }
        return false;
      },
      'Invalid file type'
    );
  }

  function initializeValidation($form) {
    $form.validate({
      rules: getValidationRules(),
      messages: getValidationMessages(),
      errorClass: 'text-red-500 text-sm mt-1 block',
      errorPlacement: placeError,
      highlight: addErrorHighlight,
      unhighlight: removeErrorHighlight
    });
  }

  function placeError(error, element) {
    if (element.attr('type') === 'checkbox') {
      error.insertAfter(element.closest('label'));
    } else {
      error.insertAfter(element);
    }
  }

  function addErrorHighlight(element) {
    $(element).addClass('border-red-500');
  }

  function removeErrorHighlight(element) {
    $(element).removeClass('border-red-500');
  }

  function getValidationRules() {
    return {
      grievance_type: { required: true },
      grievance_subtype: { required: true },
      remarks: { required: true, minlength: 10, maxlength: 255 },
      address: { required: true, maxlength: 255 },
      'files[upload_file]': {
        required: true,
        extensionFile: 'jpg|jpeg|png|pdf|doc|docx|mp4',
        filesize: 2097152
      },
      agree_terms: { required: true }
    };
  }

  function getValidationMessages() {
    return {
      grievance_type: 'Please select a Category',
      grievance_subtype: 'Please select a Sub Category',
      remarks: {
        required: 'Remarks are required',
        minlength: 'At least 10 characters',
        maxlength: 'Max 255 characters'
      },
      address: {
        required: 'Address is required',
        maxlength: 'Max 255 characters'
      },
      'files[upload_file]': {
        required: 'Please upload a file',
        extensionFile: 'Invalid file type',
        filesize: 'File must be <= 2MB'
      },
      agree_terms: 'You must agree to the Terms and Conditions'
    };
  }

})(jQuery, Drupal);

function resetSelect($select, placeholder, disabled = false) {
  $select.empty().append(`<option value="">${placeholder}</option>`);
  $select.prop('disabled', disabled);
}

async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`${response.status} ${response.statusText}`);
  }
  return response.json();
}

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.reportGrievanceForm = {
    attach(context) {
      const $context = $(context);
      const $typeSelect = $context.find('.grievance-type-select');
      const $subtypeSelect = $context.find('.grievance-subtype-select');

      if (!shouldAttach($typeSelect, $subtypeSelect)) {
        return;
      }

      const endpoints = getEndpoints(drupalSettings);
      attachHandlers($typeSelect, $subtypeSelect, endpoints);
      loadTypes($typeSelect, $subtypeSelect, endpoints.types);
    }
  };

  function shouldAttach($type, $subtype) {
    if (!$type.length || !$subtype.length || $type.data('attached')) {
      return false;
    }
    $type.data('attached', true);
    return true;
  }

  function getEndpoints(settings) {
    return {
      types: settings?.reportgrievance?.endpoints?.types || '/grievance/types',
      subtypes: settings?.reportgrievance?.endpoints?.subtypes || '/grievance/subtypes/'
    };
  }

  function attachHandlers($typeSelect, $subtypeSelect, endpoints) {
    $typeSelect.on('change', function () {
      const selected = $(this).val();
      if (selected) {
        loadSubtypes($subtypeSelect, endpoints.subtypes, selected);
      } else {
        resetSelect($subtypeSelect, 'Select Sub Category', true);
      }
    });
  }

  async function loadTypes($typeSelect, $subtypeSelect, url) {
    resetSelect($typeSelect, 'Loading categories...', true);
    resetSelect($subtypeSelect, 'Select Sub Category', true);

    try {
      const data = await fetchJson(url);
      resetSelect($typeSelect, 'Select a Category', false);
      appendOptions($typeSelect, data);
    } catch (error) {
      console.error('Failed to load types:', error);
      resetSelect($typeSelect, 'Failed to load', true);
    }
  }

  async function loadSubtypes($subtypeSelect, baseUrl, typeKey) {
    resetSelect($subtypeSelect, 'Loading subcategories...', true);

    try {
      const data = await fetchJson(baseUrl + encodeURIComponent(typeKey));
      resetSelect($subtypeSelect, 'Select Sub Category', false);
      appendOptions($subtypeSelect, data);
    } catch (error) {
      console.error('Failed to load subtypes:', error);
      resetSelect($subtypeSelect, 'Failed to load', true);
    }
  }

  function appendOptions($select, data) {
    if (Array.isArray(data)) {
      for (const item of data) {
        const key = item.key ?? Object.keys(item)[0];
        const value = item.value ?? Object.values(item)[0];
        if (key && value) {
          $select.append(`<option value="${key}">${value}</option>`);
        }
      }
      return;
    }

    if (typeof data === 'object' && data !== null) {
      for (const [key, value] of Object.entries(data)) {
        $select.append(`<option value="${key}">${value}</option>`);
      }
      return;
    }

    console.error('Unexpected response format:', data);
  }

})(jQuery, Drupal, drupalSettings);