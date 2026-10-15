/**
 * Jeyanco — Searchable select
 * ------------------------------------------------------------------
 * A <select data-searchable> gets a box to type in. The crew runs to dozens
 * of names, and scrolling a plain dropdown for one of them was slow
 * (Michael, 2026-10-15: File Leave and New Cash Advance).
 *
 * The <select> stays in the form, hidden, and is still what is sent. Picking
 * a name sets its value and fires its `change`, so code already listening to
 * it carries on as before (the cash advance limit). Code that sets its value
 * and fires `change` itself is shown in the box too.
 *
 * Typing matches the start of any word first, then anything containing it,
 * with case and accents ignored, so "nunez" finds Nuñez. Digits match the
 * employee ID as well: an option whose value is 22 answers to "22", "0022" and
 * "#0022", the code the rest of the app shows.
 *
 * The box carries the select's `required`: until a name is picked it is
 * invalid, so the browser will not send the form and says so beside the box.
 * Leaving the box with half a name typed puts back what was picked.
 */
(function () {
    'use strict';

    var DIACRITICS = new RegExp(
        '[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g'
    );

    function norm(s) {
        return (s || '').toLowerCase().normalize('NFD').replace(DIACRITICS, '')
            .replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function pad(id) { return ('0000' + id).slice(-4); }

    function esc(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    var seq = 0;

    function enhance(select) {
        if (select.dataset.searchReady) { return; }
        select.dataset.searchReady = '1';

        var id      = select.id || ('ss' + (++seq));
        var listId  = id + '_list';
        var numeric = /^\d+$/;
        var needed  = select.required;
        var message = select.dataset.searchRequired || 'Choose a name from the list.';

        // The choices, minus the "— Select —" line.
        var items = [];
        Array.prototype.forEach.call(select.options, function (o) {
            if (o.value === '') { return; }
            var code = numeric.test(o.value) ? pad(o.value) : '';
            items.push({
                value: o.value, text: o.text.trim(), code: code ? '#' + code : '',
                key: norm(o.text) + (code ? ' ' + code + ' ' + o.value : '')
            });
        });

        var wrap = document.createElement('div');
        wrap.className = 'ss';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var input = document.createElement('input');
        input.type = 'text';
        input.id = id + '_q';
        input.className = 'form-select ss-input' + (select.classList.contains('is-invalid') ? ' is-invalid' : '');
        input.placeholder = select.dataset.searchPlaceholder || 'Type a name or ID';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', listId);
        wrap.insertBefore(input, select);

        var glass = document.createElement('span');
        glass.className = 'ss-glass';
        glass.setAttribute('aria-hidden', 'true');
        glass.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
        wrap.insertBefore(glass, input);

        var list = document.createElement('div');
        list.className = 'ss-list';
        list.id = listId;
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrap.appendChild(list);

        // The select goes quiet: hidden, out of the tab order, and no longer
        // the one the browser holds back for being empty — the box is.
        select.classList.add('ss-native');
        select.tabIndex = -1;
        select.required = false;
        select.setAttribute('aria-hidden', 'true');

        // Its label now names the box.
        document.querySelectorAll('label[for="' + id + '"]').forEach(function (l) { l.htmlFor = input.id; });

        var shown = [];
        var active = -1;

        function picked() {
            for (var i = 0; i < items.length; i++) {
                if (items[i].value === select.value) { return items[i]; }
            }
            return null;
        }

        function validity() {
            input.setCustomValidity(needed && !select.value ? message : '');
        }

        function show() {
            var p = picked();
            input.value = p ? p.text : '';
            validity();
        }

        function search(q) {
            q = norm(q.replace(/^#/, ''));
            if (!q) { return items.slice(); }
            var starts = [], contains = [];
            items.forEach(function (it) {
                var at = it.key.indexOf(q);
                if (at === 0 || it.key.indexOf(' ' + q) > -1) { starts.push(it); }
                else if (at > -1) { contains.push(it); }
            });
            return starts.concat(contains);
        }

        function render() {
            var html = '';
            if (!shown.length) {
                html = '<div class="ss-none">No match for “' + esc(input.value.trim()) + '”</div>';
            }
            shown.forEach(function (it, i) {
                html += '<div class="ss-opt' + (i === active ? ' is-active' : '') + (it.value === select.value ? ' is-picked' : '') +
                        '" role="option" id="' + listId + '_' + i + '" data-i="' + i + '" aria-selected="' + (it.value === select.value) + '">' +
                        '<span class="ss-name">' + esc(it.text) + '</span>' +
                        (it.code ? '<span class="ss-code">' + esc(it.code) + '</span>' : '') + '</div>';
            });
            list.innerHTML = html;
            if (active > -1) { input.setAttribute('aria-activedescendant', listId + '_' + active); }
            else { input.removeAttribute('aria-activedescendant'); }
        }

        function open(q) {
            shown = search(q);
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            render();
        }

        function close() {
            list.hidden = true;
            active = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function scrollToActive() {
            var a = list.querySelector('.is-active');
            if (a) { a.scrollIntoView({ block: 'nearest' }); }
        }

        function pick(it) {
            var was = select.value;
            select.value = it ? it.value : '';
            close();
            show();
            // Still in the box: the name selected, so typing starts afresh.
            if (document.activeElement === input) { input.select(); }
            input.classList.remove('is-invalid');
            if (select.value !== was) {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // Opens on the whole list, with the one already picked in view.
        function openAll() {
            open('');
            var at = -1;
            shown.forEach(function (it, i) { if (it.value === select.value) { at = i; } });
            active = at;
            render();
            scrollToActive();
        }

        // On the way in the name already there is selected, so typing
        // replaces it rather than adding to it. The click that focused the
        // box would drop that selection as the button comes up; it is kept.
        var keepSelection = false;

        input.addEventListener('focus', function () {
            input.select();
            keepSelection = true;
            openAll();
        });

        input.addEventListener('mouseup', function (e) {
            if (keepSelection) { e.preventDefault(); }
            keepSelection = false;
        });

        // Clicked again after a pick, with the list shut: the same as coming
        // in — the name selected, ready to be typed over, and the list open.
        input.addEventListener('mousedown', function () {
            if (document.activeElement !== input) { return; }
            keepSelection = list.hidden;
            if (list.hidden) { input.select(); openAll(); }
        });

        input.addEventListener('input', function () {
            open(input.value);
            active = shown.length && input.value.trim() ? 0 : -1;
            render();
            list.scrollTop = 0;
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (list.hidden) { openAll(); return; }
                if (!shown.length) { return; }
                active += e.key === 'ArrowDown' ? 1 : -1;
                if (active < 0) { active = shown.length - 1; }
                if (active >= shown.length) { active = 0; }
                render();
                scrollToActive();
            } else if (e.key === 'Enter') {
                // Enter picks; it never sends the form from here.
                if (!list.hidden) {
                    e.preventDefault();
                    if (active > -1) { pick(shown[active]); }
                }
            } else if (e.key === 'Escape') {
                if (!list.hidden) {
                    // Inside a dialog, Escape closes the list, not the dialog.
                    e.preventDefault();
                    e.stopPropagation();
                    close();
                    show();
                }
            } else if (e.key === 'Tab') {
                if (!list.hidden && active > -1 && input.value.trim() !== (picked() || {}).text) {
                    pick(shown[active]);
                }
            }
        });

        input.addEventListener('blur', function () {
            // A click on the list lands after the blur; let it.
            setTimeout(function () {
                if (document.activeElement === input) { return; }
                var typed = norm(input.value);
                if (!typed) {
                    if (select.value) { pick(null); } else { close(); validity(); }
                    return;
                }
                // A name typed out in full counts as picking it.
                var exact = items.filter(function (it) { return norm(it.text) === typed; });
                if (exact.length === 1 && exact[0].value !== select.value) { pick(exact[0]); return; }
                close();
                show();
            }, 150);
        });

        list.addEventListener('mousedown', function (e) {
            // mousedown, not click: the box would blur and close the list first.
            e.preventDefault();
            var row = e.target.closest('.ss-opt');
            if (row) { pick(shown[+row.dataset.i]); }
        });

        // Set from elsewhere — a refused form put back, a reset.
        select.addEventListener('change', show);
        if (select.form) {
            select.form.addEventListener('reset', function () { setTimeout(show, 0); });
        }

        show();
    }

    function all(root) {
        (root || document).querySelectorAll('select[data-searchable]').forEach(enhance);
    }

    window.JeyancoSelectSearch = { enhance: enhance, all: all };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { all(); });
    } else {
        all();
    }
})();
