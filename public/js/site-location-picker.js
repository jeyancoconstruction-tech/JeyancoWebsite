/**
 * Jeyanco — Site Location Picker
 * ------------------------------------------------------------------
 * Turns a text input + a map container into a Google Maps location
 * picker: Places autocomplete search, a draggable / click-to-place
 * pin, and reverse geocoding that fills hidden address + lat/lng
 * fields.
 *
 * Degrades gracefully: with no API key (or if Maps fails to load) the
 * search box becomes a plain address text field and the map is hidden,
 * so the "Add Site" form still works.
 *
 *   const picker = JeyancoSiteMap.init({
 *       apiKey,                       // config('services.google_maps.key')
 *       searchInput, mapEl,           // elements
 *       addressField, latField, lngField,   // hidden inputs
 *       defaultCenter: { lat, lng },  // optional
 *   });
 *   // When the map starts hidden (modal / collapsed panel), call this
 *   // once it becomes visible so tiles render correctly:
 *   JeyancoSiteMap.refresh(picker);
 */
window.JeyancoSiteMap = (function () {
    'use strict';

    var loadPromise = null;
    var failed      = false;

    // Manila as a sensible default center for PH construction sites.
    var DEFAULT_CENTER = { lat: 14.5995, lng: 120.9842 };

    // Ensure the Places autocomplete dropdown sits above Bootstrap modals.
    function injectPacFix() {
        if (document.getElementById('jeyanco-pac-fix')) return;
        var s = document.createElement('style');
        s.id = 'jeyanco-pac-fix';
        s.textContent = '.pac-container{z-index:20000 !important;}';
        document.head.appendChild(s);
    }

    function loadMaps(apiKey) {
        if (window.google && window.google.maps && window.google.maps.places) {
            return Promise.resolve();
        }
        if (loadPromise) return loadPromise;

        loadPromise = new Promise(function (resolve, reject) {
            if (!apiKey) { reject(new Error('no-key')); return; }

            window.__jeyancoMapsReady = function () { resolve(); };
            // Google calls this global on invalid-key / billing errors.
            window.gm_authFailure = function () { failed = true; reject(new Error('auth')); };

            var s = document.createElement('script');
            s.src = 'https://maps.googleapis.com/maps/api/js?key=' +
                    encodeURIComponent(apiKey) +
                    '&libraries=places&callback=__jeyancoMapsReady';
            s.async = true;
            s.defer = true;
            s.onerror = function () { reject(new Error('load-failed')); };
            document.head.appendChild(s);
        });
        return loadPromise;
    }

    function setCoords(o, latLng, address) {
        if (o.latField) o.latField.value = latLng.lat().toFixed(7);
        if (o.lngField) o.lngField.value = latLng.lng().toFixed(7);
        if (address != null && o.addressField) o.addressField.value = address;
        if (address != null && o.searchInput)  o.searchInput.value  = address;
    }

    // No map: keep the search box as a manual address field.
    function enableFallback(o) {
        if (o.mapEl) o.mapEl.style.display = 'none';
        if (o.searchInput && o.addressField) {
            o.searchInput.addEventListener('input', function () {
                o.addressField.value = this.value;
            });
        }
    }

    function setupInstance(o) {
        var center = o.defaultCenter || DEFAULT_CENTER;
        if (o.latField && o.lngField && o.latField.value && o.lngField.value) {
            center = { lat: parseFloat(o.latField.value), lng: parseFloat(o.lngField.value) };
        }

        var map = new google.maps.Map(o.mapEl, {
            center: center,
            zoom: 13,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });
        var marker   = new google.maps.Marker({ map: map, position: center, draggable: true });
        var geocoder = new google.maps.Geocoder();

        function reverseGeocode(latLng) {
            geocoder.geocode({ location: latLng }, function (results, status) {
                // Always keep the coordinates; fill the address when available.
                setCoords(o, latLng, (status === 'OK' && results[0]) ? results[0].formatted_address : null);
            });
        }

        if (o.searchInput) {
            var ac = new google.maps.places.Autocomplete(o.searchInput, {
                fields: ['geometry', 'formatted_address', 'name'],
            });
            ac.addListener('place_changed', function () {
                var place = ac.getPlace();
                if (!place.geometry) return;
                var loc = place.geometry.location;
                map.setCenter(loc);
                map.setZoom(16);
                marker.setPosition(loc);
                setCoords(o, loc, place.formatted_address || place.name || o.searchInput.value);
            });
            // Stop Enter (selecting a suggestion) from submitting the form.
            o.searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') e.preventDefault();
            });
        }

        marker.addListener('dragend', function () { reverseGeocode(marker.getPosition()); });
        map.addListener('click', function (e) {
            marker.setPosition(e.latLng);
            reverseGeocode(e.latLng);
        });

        o._map = map;
        o._marker = marker;
    }

    function init(o) {
        injectPacFix();
        if (failed || !o.apiKey) { enableFallback(o); return o; }

        loadMaps(o.apiKey)
            .then(function () { setupInstance(o); })
            .catch(function () { enableFallback(o); });
        return o;
    }

    // Re-render a map that was created while hidden (modal / collapsed panel).
    function refresh(o) {
        if (o && o._map && window.google && window.google.maps) {
            google.maps.event.trigger(o._map, 'resize');
            o._map.setCenter(o._marker.getPosition());
        }
    }

    // Clear the picker's fields back to empty (after a successful save).
    function reset(o) {
        if (!o) return;
        if (o.searchInput)  o.searchInput.value  = '';
        if (o.addressField) o.addressField.value = '';
        if (o.latField)     o.latField.value     = '';
        if (o.lngField)     o.lngField.value     = '';
    }

    return { init: init, refresh: refresh, reset: reset };
})();
