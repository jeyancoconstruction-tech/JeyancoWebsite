/**
 * Jeyanco — Address Picker
 * ------------------------------------------------------------------
 * Turns the Province / City / Barangay inputs on the employee profile
 * form into cascading comboboxes backed by PSGC, the published
 * Philippine Standard Geographic Code.
 *
 * Province is typed against all 84 entries; picking one narrows City /
 * Municipality to that province, and picking a city narrows Barangay to
 * that city. House No. / Street stays a plain field — no list exists at
 * that level and none should be invented.
 *
 * The inputs stay TEXT inputs, never <select>. A worker can live at an
 * address PSGC does not spell the way they do, and every profile saved
 * before this existed holds free text. So a typed value that matches
 * nothing is kept as-is; the suggestions only ever offer, never enforce.
 *
 *   JeyancoAddress.init({
 *       base:     '/psgc',      // where build-psgc.php wrote its tables
 *       province: el, city: el, barangay: el,
 *   });
 */
window.JeyancoAddress = (function () {
    'use strict';

    var MAX_SUGGESTIONS = 60;

    /** The combining-mark block, U+0300-U+036F, assembled from char codes
     *  instead of written into the pattern. Those characters are invisible in
     *  source and survive only as long as everything down the line agrees this
     *  file is UTF-8; built this way the regex is plain ASCII and cannot rot. */
    var DIACRITICS = new RegExp(
        '[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g'
    );

    /** Fold case, accents and punctuation away so "Sto. Nino" answers to
     *  "sto nino" — the way a payroll clerk actually types it. */
    function norm(s) {
        return (s || '')
            .toLowerCase()
            .normalize('NFD').replace(DIACRITICS, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    /** Prefix matches first, then anything containing the query. Typing
     *  "camarines" should list Camarines Norte and Camarines Sur above a
     *  place that merely mentions the word further along. */
    function search(items, query) {
        var q = norm(query);
        if (!q) return items.slice(0, MAX_SUGGESTIONS);
        var starts = [], contains = [];
        for (var i = 0; i < items.length; i++) {
            var it  = items[i];
            var hay = it._k || (it._k = norm(it.n) + ' ' + norm(it.a || ''));
            var at  = hay.indexOf(q);
            if (at === 0 || hay.indexOf(' ' + q) > -1) starts.push(it);
            else if (at > -1) contains.push(it);
        }
        return starts.concat(contains).slice(0, MAX_SUGGESTIONS);
    }

    var cache = {};
    function loadJson(url) {
        if (!cache[url]) {
            cache[url] = fetch(url, { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .catch(function () { return null; });   // offline: inputs stay free text
        }
        return cache[url];
    }

    function escapeHtml(s) {
        return s.replace(/[&<>]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
        });
    }

    // -- One combobox ---------------------------------------------------------
    function Combo(input, onPick) {
        var self = this;
        this.input   = input;
        this.onPick  = onPick;
        this.items   = [];
        this.shown   = [];
        this.index   = -1;
        this.enabled = false;

        var list = document.createElement('div');
        list.className = 'ap-list';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        (input.parentNode || document.body).appendChild(list);
        this.list = list;

        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');

        input.addEventListener('input', function () { self.index = -1; self.render(); });
        input.addEventListener('focus', function () { self.render(); });
        input.addEventListener('blur', function () {
            setTimeout(function () { self.close(); }, 120);
        });
        input.addEventListener('keydown', function (e) { self.key(e); });

        list.addEventListener('mousedown', function (e) {
            // mousedown, not click: the input's blur would close the list first.
            var row = e.target.closest('.ap-opt');
            if (!row) return;
            e.preventDefault();
            self.pick(self.shown[+row.dataset.i]);
        });
    }

    Combo.prototype.setItems = function (items) {
        this.items   = items || [];
        this.enabled = this.items.length > 0;
        this.input.classList.toggle('ap-live', this.enabled);
        if (document.activeElement === this.input) this.render();
        else this.close();
    };

    Combo.prototype.render = function () {
        if (!this.enabled) return this.close();
        this.shown = search(this.items, this.input.value);
        if (!this.shown.length) return this.close();

        var html = '';
        for (var i = 0; i < this.shown.length; i++) {
            html += '<div class="ap-opt' + (i === this.index ? ' is-active' : '') +
                    '" role="option" data-i="' + i + '">' +
                    escapeHtml(this.shown[i].n) + '</div>';
        }
        this.list.innerHTML = html;
        this.list.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    };

    Combo.prototype.close = function () {
        this.list.hidden = true;
        this.index = -1;
        this.input.setAttribute('aria-expanded', 'false');
    };

    Combo.prototype.pick = function (item) {
        if (!item) return;
        this.input.value = item.n;
        this.close();
        this.onPick(item);
    };

    Combo.prototype.key = function (e) {
        if (e.key === 'Escape') return this.close();
        if (e.key === 'Enter' && !this.list.hidden && this.index > -1) {
            e.preventDefault();
            return this.pick(this.shown[this.index]);
        }
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        e.preventDefault();
        if (this.list.hidden) this.render();
        if (!this.shown.length) return;
        this.index += (e.key === 'ArrowDown' ? 1 : -1);
        if (this.index < 0) this.index = this.shown.length - 1;
        if (this.index >= this.shown.length) this.index = 0;
        this.render();
        var active = this.list.querySelector('.is-active');
        if (active) active.scrollIntoView({ block: 'nearest' });
    };

    /** Resolve typed text back to a PSGC entry — this is how an already-saved
     *  profile re-enters the cascade when the form is reopened. */
    function match(items, text) {
        var q = norm(text);
        if (!q) return null;
        for (var i = 0; i < items.length; i++) {
            if (norm(items[i].n) === q) return items[i];
        }
        return null;
    }

    function init(cfg) {
        if (!cfg || !cfg.province || !cfg.city || !cfg.barangay) return null;
        var base = (cfg.base || '/psgc').replace(/\/$/, '');

        var brgyByCity = {};        // { cityCode: ["Barangay", ...] } for the loaded province
        var cityCombo, brgyCombo;

        function namesOf(list) {
            return (list || []).map(function (n) { return { n: n }; });
        }

        var provCombo = new Combo(cfg.province, function (prov) {
            // A different province invalidates whatever sat below it.
            cfg.city.value = '';
            cfg.barangay.value = '';
            cityCombo.setItems([]);
            brgyCombo.setItems([]);
            loadProvince(prov.c);
        });

        cityCombo = new Combo(cfg.city, function (city) {
            cfg.barangay.value = '';
            brgyCombo.setItems(namesOf(brgyByCity[city.c]));
        });

        brgyCombo = new Combo(cfg.barangay, function () {});

        function loadProvince(code, thenCityText) {
            return Promise.all([
                loadJson(base + '/cities/' + code + '.json'),
                loadJson(base + '/barangays/' + code + '.json')
            ]).then(function (res) {
                var cities = res[0] || [];
                brgyByCity = res[1] || {};
                cityCombo.setItems(cities);
                if (thenCityText) {
                    var city = match(cities, thenCityText);
                    if (city) brgyCombo.setItems(namesOf(brgyByCity[city.c]));
                }
            });
        }

        loadJson(base + '/provinces.json').then(function (provinces) {
            if (!provinces) return;                 // offline — inputs stay plain text
            provCombo.setItems(provinces);
            // Re-enter the cascade for a profile that already has an address.
            var prov = match(provinces, cfg.province.value);
            if (prov) loadProvince(prov.c, cfg.city.value);
        });

        return { province: provCombo, city: cityCombo, barangay: brgyCombo };
    }

    return { init: init };
})();
