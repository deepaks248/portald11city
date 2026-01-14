(function ($, Drupal, drupalSettings) {
  'use strict';

  /* ---------------- CONSTANTS ---------------- */

  const MAP_TARGET = 'mapDiv';
  const INCIDENT_LAYER = 'Incident_Layer';
  const DEFAULT_ZOOM = 15;
  const MAP_DELAY_MS = 2500;
  const MARKER_ICON = './themes/custom/engage_theme/images/user-location-50.png';

  let gmap = null;

  /* ---------------- BOOTSTRAP ---------------- */

  initializeEnvSettings();
  window.onload = createMap;

  $(document).on('click', '.get_lat_lang', () => {
    if (gmap) {
      addPoint(gmap);
    }
  });

  /* ---------------- ENV SETTINGS ---------------- */

  function initializeEnvSettings() {
    const config = drupalSettings.globalVariables?.mapConfig?.[0];
    if (!config) {
      console.warn('Map configuration missing');
      return;
    }

    applyEnvSettings(config);
  }

  function applyEnvSettings(config) {
    Object.assign(envSettings, {
      gKey: config.gKey,
      mapDimension: config.mapDimension,
      mapLib: config.mapLib,
      type: config.type,
      mapData: config.mapData,
      gwc: config.gwc,
      offline: config.offline,
      extent1: Number.parseFloat(config.extent1),
      extent2: Number.parseFloat(config.extent2),
      extent3: Number.parseFloat(config.extent3),
      extent4: Number.parseFloat(config.extent4),
      lat: Number.parseFloat(config.lat),
      lon: Number.parseFloat(config.lon),
    });
  }

  /* ---------------- MAP CREATION ---------------- */

  function createMap() {
    tmpl.Map.mapCreation({
      target: MAP_TARGET,
      callBackFun: onMapReady,
    });
  }

  function onMapReady(mapInstance, response) {
    gmap = mapInstance;

    setTimeout(initializeMapUI, MAP_DELAY_MS);
    console.debug('Map initialized', response);
  }

  function initializeMapUI() {
    addSearchBox();
    zoomToExtent(gmap);
  }

  /* ---------------- SEARCH ---------------- */

  function addSearchBox() {
    tmpl.Search.addSearchBox({
      map: gmap,
      img_url: MARKER_ICON,
      height: 50,
      width: 25,
      zoom_button: false,
    });
  }

  /* ---------------- ZOOM ---------------- */

  function zoomToExtent(map) {
    if (!map) return;

    safeExecute(() =>
      tmpl.Zoom.toExtent({
        map,
        extent: getExtent(),
      })
    );
  }

  function zoomToPoint(coord) {
    tmpl.Zoom.toXYcustomZoom({
      map: gmap,
      latitude: coord[1],
      longitude: coord[0],
      zoom: DEFAULT_ZOOM,
    });
  }

  function getExtent() {
    return [
      envSettings.extent1,
      envSettings.extent2,
      envSettings.extent3,
      envSettings.extent4,
    ];
  }

  /* ---------------- DRAW POINT ---------------- */

  function addPoint(map) {
    tmpl.Draw.draw({
      map,
      type: 'Point',
      callbackFunc: handlePointDrawn,
    });
  }

  function handlePointDrawn(coord) {
    if (!isValidCoord(coord)) return;

    resetIncidentLayer();
    renderIncident(coord);
    zoomToPoint(coord);
    geocodePoint(coord);
    updateLatLngInputs(coord);
  }

  function isValidCoord(coord) {
    return Array.isArray(coord) && coord.length >= 2;
  }

  /* ---------------- INCIDENT MARKER ---------------- */

  function resetIncidentLayer() {
    tmpl.Layer.clearData({
      map: gmap,
      layer: INCIDENT_LAYER,
    });
  }

  function renderIncident(coord) {
    tmpl.Overlay.create({
      map: gmap,
      features: [createMarker(coord)],
      layer: INCIDENT_LAYER,
      layerSwitcher: false,
    });

    tmpl.Layer.changeVisibility({
      map: gmap,
      visible: true,
      layer: INCIDENT_LAYER,
    });
  }

  function createMarker(coord) {
    return {
      id: 1,
      img_url: MARKER_ICON,
      lat: coord[1],
      lon: coord[0],
    };
  }

  /* ---------------- GEOCODING ---------------- */

  function geocodePoint(coord) {
    tmpl.Geocode.getGeocode({
      point: coord,
      callbackFunc: handleGeocode,
    });
  }

  function handleGeocode(data) {
    const address = data?.address;
    if (!address) return;

    sessionStorage.setItem('grievanceAddress', address);
    setInputValue('#edit-address', address);
  }

  /* ---------------- FORM HELPERS ---------------- */

  function updateLatLngInputs(coord) {
    setInputValue('.lat-input', coord[0]);
    setInputValue('.lng-input', coord[1]);
  }

  function setInputValue(selector, value) {
    const input = document.querySelector(selector);
    if (input) {
      input.value = value;
      input.setAttribute('value', value);
    }
  }

  /* ---------------- UTIL ---------------- */

  function safeExecute(fn) {
    try {
      fn();
    } catch (error) {
      console.error('Map operation failed', error);
    }
  }

})(jQuery, Drupal, drupalSettings);


(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.reportGrievanceValidation = {
    attach(context) {
      if (!canAttach(context)) {
        return;
      }

      const $form = $('#report-grievance', context);
      markAttached($form);

      ensureValidatorsRegistered();
      initializeValidation($form);
    }
  };

  /* ---------------- GUARDS ---------------- */

  function canAttach(context) {
    if (!isValidatorAvailable()) {
      console.error('jQuery Validate is not loaded!');
      return false;
    }

    const $form = $('#report-grievance', context);
    return $form.length && !$form.data('validated');
  }

  function markAttached($form) {
    $form.data('validated', true);
  }

  function isValidatorAvailable() {
    return typeof $.fn.validate === 'function';
  }

  /* ---------------- VALIDATORS ---------------- */

  function ensureValidatorsRegistered() {
    if ($.validator.methods.filesize) {
      return;
    }

    registerFileSizeValidator();
    registerExtensionValidator();
  }

  function registerFileSizeValidator() {
    $.validator.addMethod(
      'filesize',
      (_, element, maxSize) =>
        !element.files.length || element.files[0].size <= maxSize,
      'File size must be less than 2MB'
    );
  }

  function registerExtensionValidator() {
    $.validator.addMethod(
      'extensionFile',
      (_, element, allowedExt) =>
        !element.files.length ||
        isAllowedExtension(element.files[0].name, allowedExt),
      'Invalid file type'
    );
  }

  function isAllowedExtension(fileName, allowedExt) {
    const lower = fileName.toLowerCase();
    return allowedExt
      .split('|')
      .some(ext => lower.endsWith(ext));
  }

  /* ---------------- FORM INIT ---------------- */

  function initializeValidation($form) {
    $form.validate({
      rules: getValidationRules(),
      messages: getValidationMessages(),
      errorClass: 'text-red-500 text-sm mt-1 block',
      errorPlacement: placeError,
      highlight: toggleErrorHighlight(true),
      unhighlight: toggleErrorHighlight(false)
    });
  }

  /* ---------------- UI HELPERS ---------------- */

  function placeError(error, element) {
    const target = isCheckbox(element)
      ? element.closest('label')
      : element;
    error.insertAfter(target);
  }

  function isCheckbox(element) {
    return element.attr('type') === 'checkbox';
  }

  function toggleErrorHighlight(add) {
    return element =>
      $(element).toggleClass('border-red-500', add);
  }

  /* ---------------- RULES ---------------- */

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

/* ---------------- SHARED HELPERS ---------------- */

function resetSelect($select, placeholder, disabled = false) {
  $select
    .empty()
    .append(`<option value="">${placeholder}</option>`)
    .prop('disabled', disabled);
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
      const $typeSelect = $('.grievance-type-select', context);
      const $subtypeSelect = $('.grievance-subtype-select', context);

      if (!canAttach($typeSelect, $subtypeSelect)) {
        return;
      }

      const endpoints = resolveEndpoints(drupalSettings);

      bindTypeChange($typeSelect, $subtypeSelect, endpoints.subtypes);
      loadTypes($typeSelect, $subtypeSelect, endpoints.types);
    }
  };

  /* ---------------- GUARD ---------------- */

  function canAttach($type, $subtype) {
    if (!$type.length || !$subtype.length || $type.data('attached')) {
      return false;
    }
    $type.data('attached', true);
    return true;
  }

  /* ---------------- CONFIG ---------------- */

  function resolveEndpoints(settings) {
    const cfg = settings?.reportgrievance?.endpoints || {};
    return {
      types: cfg.types || '/grievance/types',
      subtypes: cfg.subtypes || '/grievance/subtypes/'
    };
  }

  /* ---------------- EVENTS ---------------- */

  function bindTypeChange($type, $subtype, subtypesUrl) {
    $type.on('change', () => {
      const value = $type.val();
      value
        ? loadSubtypes($subtype, subtypesUrl, value)
        : resetSelect($subtype, 'Select Sub Category', true);
    });
  }

  /* ---------------- LOADERS ---------------- */

  function loadTypes($type, $subtype, url) {
    resetSelect($type, 'Loading categories...', true);
    resetSelect($subtype, 'Select Sub Category', true);
    loadOptions(url, $type, 'Select a Category');
  }

  function loadSubtypes($subtype, baseUrl, key) {
    resetSelect($subtype, 'Loading subcategories...', true);
    loadOptions(baseUrl + encodeURIComponent(key), $subtype, 'Select Sub Category');
  }

  /* ---------------- SHARED ASYNC ---------------- */

  async function loadOptions(url, $select, placeholder) {
    try {
      const data = await fetchJson(url);
      resetSelect($select, placeholder, false);
      appendOptions($select, data);
    } catch (error) {
      console.error('Failed to load options:', error);
      resetSelect($select, 'Failed to load', true);
    }
  }

  /* ---------------- OPTIONS ---------------- */

  function appendOptions($select, data) {
    if (Array.isArray(data)) {
      for (const item of data) {
        addOption($select, item);
      }
      return;
    }

    if (isObject(data)) {
      for (const [key, value] of Object.entries(data)) {
        $select.append(`<option value="${key}">${value}</option>`);
      }
      return;
    }

    console.error('Unexpected response format:', data);
  }

  function addOption($select, item) {
    const key = item?.key ?? Object.keys(item || {})[0];
    const value = item?.value ?? Object.values(item || {})[0];

    if (key && value) {
      $select.append(`<option value="${key}">${value}</option>`);
    }
  }

  function isObject(value) {
    return typeof value === 'object' && value !== null;
  }

})(jQuery, Drupal, drupalSettings);