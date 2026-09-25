// ── Phones and tablets ─────────────────────────────────────────────────────
// What mobile.css cannot do alone. Nothing here runs on a screen wider than
// a phone (767px), so a tablet, a laptop or a desktop page is exactly what it
// was.
//
// 1. On a phone a list is drawn as cards (mobile.css §4, §5), and each line
//    of a card is named after its column. CSS can only read a name off the
//    cell itself, so the column's heading is copied onto every cell as
//    data-mlabel. Rows arrive later too — a live refresh, a filter fetched in
//    place, the next page — so it is done again whenever the page changes.
//
// 2. A row of tabs that scrolls sideways (mobile.css §3) opens with the
//    selected tab in view, not scrolled off past the edge.
(function () {
    'use strict';

    var phone  = window.matchMedia('(max-width: 767.98px)');
    var TABLES = '.mod-table, .rmx-table, .atm-table, #roleList > .sx-table';
    var TABS   = '.mod-tabs, .settings-tabs';

    // The heading over each column, a colspan counting once per column it
    // covers. The last header row is the one that names single columns;
    // Attendance has a row above it that groups them into sessions.
    function headings(table) {
        var head = table.tHead;
        if (!head || !head.rows.length) { return null; }
        var row = head.rows[head.rows.length - 1];
        var names = [];
        for (var i = 0; i < row.cells.length; i++) {
            var text = row.cells[i].textContent.replace(/\s+/g, ' ').trim();
            for (var s = 0; s < (row.cells[i].colSpan || 1); s++) { names.push(text); }
        }
        return names;
    }

    function label(table) {
        var names = headings(table);
        if (!names) { return; }
        var groups = Array.prototype.slice.call(table.tBodies);
        if (table.tFoot) { groups.push(table.tFoot); }
        groups.forEach(function (group) {
            for (var r = 0; r < group.rows.length; r++) {
                var col = 0, cells = group.rows[r].cells;
                for (var c = 0; c < cells.length; c++) {
                    var name = names[col] || '';
                    if (cells[c].getAttribute('data-mlabel') !== name) {
                        cells[c].setAttribute('data-mlabel', name);
                    }
                    col += cells[c].colSpan || 1;
                }
            }
        });
    }

    function labelAll() {
        Array.prototype.forEach.call(document.querySelectorAll(TABLES), label);
    }

    function showOpenTab() {
        Array.prototype.forEach.call(document.querySelectorAll(TABS), function (strip) {
            if (strip.scrollWidth <= strip.clientWidth) { return; }
            var on = strip.querySelector('.active');
            if (!on) { return; }
            var left = on.offsetLeft - strip.offsetLeft;
            strip.scrollLeft = Math.max(0, left - (strip.clientWidth - on.offsetWidth) / 2);
        });
    }

    var queued = false;
    function soon() {
        if (queued) { return; }
        queued = true;
        requestAnimationFrame(function () { queued = false; labelAll(); });
    }

    var watcher = null;
    function start() {
        if (!phone.matches) {
            if (watcher) { watcher.disconnect(); watcher = null; }
            return;
        }
        labelAll();
        showOpenTab();
        if (!watcher && window.MutationObserver) {
            var main = document.querySelector('.main-content') || document.body;
            watcher = new MutationObserver(soon);
            watcher.observe(main, { childList: true, subtree: true });
        }
    }

    // A live refresh sets a region's attributes back to the server's, which
    // has no data-mlabel; put them back before the frame is painted.
    document.addEventListener('live:updated', function () { if (phone.matches) { labelAll(); } });
    document.addEventListener('shown.bs.tab', function () { if (phone.matches) { showOpenTab(); } });

    if (phone.addEventListener) { phone.addEventListener('change', start); }
    else if (phone.addListener) { phone.addListener(start); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
