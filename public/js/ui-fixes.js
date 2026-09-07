/* =============================================================================
   JEYANCO CONSTRUCTION · UI BEHAVIOUR FIXES
   -----------------------------------------------------------------------------
   Loaded last, on every page. Everything in here is presentation-level: it fixes
   how existing controls behave. No route, no fetch, no payroll or attendance
   rule, no form field name and no map instance is touched — where a map has to
   re-measure, a plain window `resize` is dispatched and Leaflet does the rest
   through its own trackResize handler.

     1 · Native date fields open their picker when the field itself is clicked
     2 · Scroll lock that cannot shift the layout (modals, mobile sidebar)
     3 · Maps re-measure when their box changes size
     4 · Site Tracker · minimise / maximise
     5 · Clock · small read-only month popover
   ============================================================================= */
(function () {
    'use strict';

    var root = document.documentElement;

    /* ── shared: keep the scroll lock refcounted ────────────────────────────
       Several things lock scrolling (a modal, the mobile sidebar, a maximised
       card) and they overlap. Counting them means the last one to close is the
       one that unlocks, instead of the first one stealing the lock back. */
    var lockCount = 0;
    function lockScroll() {
        lockCount++;
        root.classList.add('ui-scroll-lock');
    }
    function unlockScroll() {
        lockCount = Math.max(0, lockCount - 1);
        if (lockCount === 0) root.classList.remove('ui-scroll-lock');
    }
    window.jeyancoUI = window.jeyancoUI || {};
    window.jeyancoUI.lockScroll = lockScroll;
    window.jeyancoUI.unlockScroll = unlockScroll;

    /* Tell every Leaflet map on the page to re-measure. Leaflet subscribes to
       window resize itself (trackResize, on by default), so this needs no
       reference to any map object and cannot disturb markers, layers or the
       live GPS poll. */
    var nudgeTimer = null;
    function nudgeMaps() {
        if (nudgeTimer) return;
        nudgeTimer = window.setTimeout(function () {
            nudgeTimer = null;
            window.dispatchEvent(new Event('resize'));
        }, 60);
    }
    window.jeyancoUI.nudgeMaps = nudgeMaps;

    /* A deliberate resize of a card, as opposed to the window changing size.
       Leaflet's invalidateSize keeps the centre and the zoom, so a box that
       suddenly grows simply shows much more map — maximising the Site Tracker
       zoomed the view out to the whole region. A map that cares can listen for
       this and put back the ground it was showing; the dashboard does. */
    function announceResize() {
        document.dispatchEvent(new CustomEvent('jeyanco:card-resize'));
        nudgeMaps();
    }


    /* ── 1 · DATE FIELDS OPEN ON CLICK ──────────────────────────────────────
       A native date input only opens its calendar from the small glyph at the
       right edge. Clicking the field — which is what everyone does — did
       nothing, so the control read as dead. showPicker() is the supported way
       to open the browser's own picker; anything older simply keeps the glyph.

       Inputs that Flatpickr has taken over are skipped: it manages its own
       popup and calling showPicker() on top of it would open two calendars. */
    function isPickerManaged(el) {
        return !!(el._flatpickr || el.classList.contains('flatpickr-input') ||
                  el.hasAttribute('data-no-auto-picker'));
    }

    function openNativePicker(el) {
        if (!el || el.disabled || el.readOnly || isPickerManaged(el)) return;
        if (typeof el.showPicker !== 'function') return;
        try {
            el.showPicker();
        } catch (e) {
            /* showPicker() throws without a user gesture, or where the browser
               declines. The glyph still works, so there is nothing to report. */
        }
    }

    var DATE_TYPES = 'input[type="date"], input[type="week"], input[type="month"], ' +
                     'input[type="time"], input[type="datetime-local"]';

    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest(DATE_TYPES) : null;
        if (!el) return;
        openNativePicker(el);
    });

    /* Keyboard parity: Enter or Space on a focused date field opens it too. */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var el = document.activeElement;
        if (!el || !el.matches || !el.matches(DATE_TYPES)) return;
        e.preventDefault();
        openNativePicker(el);
    });

    /* A <label for> already forwards its click to the field, so the delegate
       above covers it — a second handler here would open the picker twice. */


    /* ── 2 · SCROLL LOCK WITHOUT THE SIDEWAYS JUMP ──────────────────────────
       Bootstrap locks <body> and adds padding-right to stand in for the
       scrollbar it just removed — the cause of the page sliding left every time
       a modal opened. ui-fixes.css zeroes that padding and reserves the gutter
       permanently; the lock itself moves up to <html>, which is the element
       that actually scrolls. */
    document.addEventListener('show.bs.modal', lockScroll);
    document.addEventListener('hidden.bs.modal', unlockScroll);
    document.addEventListener('show.bs.offcanvas', lockScroll);
    document.addEventListener('hidden.bs.offcanvas', unlockScroll);

    /* A modal that contains a map opens at zero width; Leaflet needs telling. */
    document.addEventListener('shown.bs.modal', nudgeMaps);


    /* ── 3 · MAPS RE-MEASURE WHEN THEIR BOX CHANGES ─────────────────────────
       The Project Sites hint line rewrites itself as you search, drop a pin and
       save. The map below it is a flex child, so its height moved with the
       message and Leaflet — which only watches the window — kept drawing at the
       old size, leaving torn tiles and a pin in the wrong place on screen.
       ui-fixes.css reserves the hint's height; this catches every other cause
       (a card being resized, a tab revealed, the window's own zoom). */
    if (typeof ResizeObserver === 'function') {
        var mapObserver = new ResizeObserver(function () { nudgeMaps(); });
        var watchMaps = function () {
            document.querySelectorAll('.leaflet-container').forEach(function (el) {
                if (el.dataset.uiObserved) return;
                el.dataset.uiObserved = '1';
                mapObserver.observe(el);
            });
        };
        watchMaps();
        /* Maps are created after this file on some pages, and Leaflet stamps
           .leaflet-container on the node as it initialises — so look again once
           the current task queue has drained, then settle. */
        window.setTimeout(watchMaps, 400);
        window.setTimeout(watchMaps, 1500);
        document.addEventListener('shown.bs.modal', watchMaps);
        document.addEventListener('shown.bs.tab', watchMaps);
    }

    /* Revealing a tab pane gives its contents a size for the first time. */
    document.addEventListener('shown.bs.tab', nudgeMaps);


    /* ── 4 · SITE TRACKER · MINIMISE / MAXIMISE ─────────────────────────────
       Chrome around the existing card. The map, its markers, the place search,
       the site picker, the save request and the ten-second GPS poll all keep
       running untouched — only the box they live in changes size, and Leaflet
       is asked to re-measure afterwards.

       Maximising takes the card out of the grid, so a placeholder of the exact
       height it had holds its slot open: the two cards beside it do not move,
       and closing puts the card back in the same place. */
    (function () {
        var card = document.getElementById('siteTrackerCard');
        if (!card) return;

        var minBtn = document.getElementById('siteTrackerMin');
        var maxBtn = document.getElementById('siteTrackerMax');
        var column = card.parentElement;
        var slot = null;
        var backdrop = null;

        /* The two labels come off the button as data attributes so they stay
           translated — the blade renders them through __(). */
        function setState(btn, active, icon) {
            if (!btn) return;
            var i = btn.querySelector('i');
            if (i) i.className = 'fas ' + icon;
            var label = active ? (btn.dataset.labelOff || btn.title)
                               : (btn.dataset.labelOn || btn.title);
            btn.setAttribute('title', label);
            btn.setAttribute('aria-label', label);
        }

        function isMax() { return card.classList.contains('is-max'); }
        function isMin() { return card.classList.contains('is-min'); }

        function minimise(on) {
            if (on === isMin()) return;
            if (on && isMax()) maximise(false);
            card.classList.toggle('is-min', on);
            setState(minBtn, on, on ? 'fa-window-maximize' : 'fa-window-minimize');
            if (minBtn) minBtn.setAttribute('aria-expanded', on ? 'false' : 'true');
            if (!on) announceResize();
        }

        function maximise(on) {
            if (on === isMax()) return;

            if (on) {
                if (isMin()) minimise(false);

                /* Record the slot BEFORE the card leaves the flow. */
                slot = document.createElement('div');
                slot.className = 'site-tracker-slot is-active';
                slot.style.height = card.offsetHeight + 'px';
                column.insertBefore(slot, card);

                backdrop = document.createElement('div');
                backdrop.className = 'st-backdrop';
                backdrop.addEventListener('click', function () { maximise(false); });
                document.body.appendChild(backdrop);

                card.classList.add('is-max');
                lockScroll();
            } else {
                card.classList.remove('is-max');
                if (backdrop) { backdrop.remove(); backdrop = null; }
                if (slot) { slot.remove(); slot = null; }
                unlockScroll();
            }

            setState(maxBtn, on, on ? 'fa-compress' : 'fa-expand');
            if (maxBtn) maxBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
            announceResize();
        }

        if (minBtn) minBtn.addEventListener('click', function () { minimise(!isMin()); });
        if (maxBtn) maxBtn.addEventListener('click', function () { maximise(!isMax()); });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isMax()) maximise(false);
        });
    })();


    /* ── 5 · CLOCK · SMALL READ-ONLY MONTH POPOVER ──────────────────────────
       The dashboard clock is drawn as a calendar tile but did nothing when
       clicked. It now opens a month view you can page through — a reference,
       not a form control: nothing is submitted and no date is stored, so no
       existing behaviour changes. It is absolutely positioned, so the banner
       beneath it never moves as it opens and closes. */
    (function () {
        var widget = document.getElementById('clockWidget');
        var panel  = document.getElementById('miniCal');
        if (!widget || !panel) return;

        var DOW = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];

        var view = new Date();
        view.setDate(1);

        function sameDay(a, b) {
            return a.getFullYear() === b.getFullYear() &&
                   a.getMonth() === b.getMonth() &&
                   a.getDate() === b.getDate();
        }

        function render() {
            var today = new Date();
            var year = view.getFullYear();
            var month = view.getMonth();
            var first = new Date(year, month, 1);
            var lead = first.getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var daysInPrev = new Date(year, month, 0).getDate();

            var cells = '';
            var i;
            for (i = 0; i < 7; i++) cells += '<div class="mini-cal-dow">' + DOW[i] + '</div>';
            for (i = lead; i > 0; i--) {
                cells += '<div class="mini-cal-day is-muted">' + (daysInPrev - i + 1) + '</div>';
            }
            for (i = 1; i <= daysInMonth; i++) {
                var cls = sameDay(new Date(year, month, i), today) ? ' is-today' : '';
                cells += '<div class="mini-cal-day' + cls + '">' + i + '</div>';
            }
            var tail = (7 - ((lead + daysInMonth) % 7)) % 7;
            for (i = 1; i <= tail; i++) cells += '<div class="mini-cal-day is-muted">' + i + '</div>';

            panel.innerHTML =
                '<div class="mini-cal-head">' +
                    '<button type="button" class="mini-cal-nav" data-step="-1" aria-label="Previous month">' +
                        '<i class="fas fa-chevron-left"></i></button>' +
                    '<span class="mini-cal-title">' + MONTHS[month] + ' ' + year + '</span>' +
                    '<button type="button" class="mini-cal-nav" data-step="1" aria-label="Next month">' +
                        '<i class="fas fa-chevron-right"></i></button>' +
                '</div>' +
                '<div class="mini-cal-grid">' + cells + '</div>' +
                '<div class="mini-cal-foot">' +
                    '<span>' + today.toLocaleDateString(undefined, {
                        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
                    }) + '</span>' +
                    '<button type="button" class="mini-cal-today-btn">Today</button>' +
                '</div>';
        }

        function open() {
            render();
            panel.hidden = false;
            widget.setAttribute('aria-expanded', 'true');
        }
        function close() {
            panel.hidden = true;
            widget.setAttribute('aria-expanded', 'false');
        }
        function toggle() {
            if (panel.hidden) open(); else close();
        }

        widget.addEventListener('click', toggle);
        widget.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            if (e.key === 'Escape') close();
        });

        panel.addEventListener('click', function (e) {
            var nav = e.target.closest('.mini-cal-nav');
            if (nav) {
                view.setMonth(view.getMonth() + parseInt(nav.dataset.step, 10));
                render();
                return;
            }
            if (e.target.closest('.mini-cal-today-btn')) {
                view = new Date();
                view.setDate(1);
                render();
            }
        });

        document.addEventListener('click', function (e) {
            if (panel.hidden) return;
            if (widget.contains(e.target) || panel.contains(e.target)) return;
            close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    })();
})();
