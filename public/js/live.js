/* =========================================================================
 * live.js — pages that keep themselves up to date.
 *
 * One connection per tab (Server-Sent Events) carries a single fact: which
 * topics changed. The page then asks the server for its own current contents
 * and patches in the difference, node by node — so a clock-in at the site
 * fills in a row in the office, and nothing else on the screen moves. No
 * timers, no page reloads, and nothing fetched until something has actually
 * changed.
 *
 * A page opts in by marking the parts of itself that show data:
 *
 *     <div id="todays-attendance" data-live="attendance">…</div>
 *
 * The id is how the fresh copy is found in the re-rendered page, so it has to
 * be there and has to be stable. data-live is the list of topics that region
 * shows. Extras:
 *
 *     data-live-url="/attendance?tab=today"   where to re-read it from
 *                                             (defaults to this page's URL)
 *     data-live-freeze                        patch around this, never into it
 *
 * Script-driven pages subscribe instead, and update themselves however they
 * already do:
 *
 *     Live.on('kiosk', refreshTheMap);
 *
 * Anything holding the screen — an open dialog, a focused field, a menu the
 * user has just opened — stops the patch until it is done with. The update is
 * not lost; it is re-read and applied the moment the screen is free.
 * ========================================================================= */
(function () {
    'use strict';

    var cfg = window.LiveConfig || {};

    if (!cfg.streamUrl) {
        return;                         // not signed in, or the layout said no
    }

    // ── What this tab knows ─────────────────────────────────────────────────

    var revisions  = Object.assign({}, cfg.revisions || {});
    var subscribed = [];                // {topics: [..], fn: function}
    var source     = null;              // the open EventSource
    var pollTimer  = null;
    var reopen     = null;
    var backoff    = 3000;
    var pollMs     = cfg.pollMs || 8000;
    var stopped    = false;             // signed out: say nothing more
    var streamOff  = cfg.stream === false;
    var inFlight   = {};                // url -> true while it is being re-read
    var waiting    = false;             // a patch held back by the screen
    var hiddenAt   = 0;
    var lastHeard  = Date.now();

    // ── The feed ────────────────────────────────────────────────────────────

    function since() {
        var pairs = [];
        for (var topic in revisions) {
            if (Object.prototype.hasOwnProperty.call(revisions, topic)) {
                pairs.push(topic + ':' + revisions[topic]);
            }
        }
        return pairs.join(',');
    }

    function connect() {
        if (stopped || streamOff || source || document.hidden || !window.EventSource) {
            return;
        }

        var url = cfg.streamUrl + (since() ? '?since=' + encodeURIComponent(since()) : '');

        try {
            source = new EventSource(url, { withCredentials: true });
        } catch (e) {
            startPolling();
            return;
        }

        source.onopen = function () {
            backoff   = 3000;
            lastHeard = Date.now();
            stopPolling();
            connected(true);
        };

        source.addEventListener('hello', function (e) {
            var data = read(e);
            if (data && data.poll_ms) { pollMs = data.poll_ms; }

            // Where things stand as the connection opens — which is not the
            // same as where they stood when this page was rendered. A stream
            // lasts half a minute and then the next one opens; anything that
            // happened in the gap arrives here, and adopting it quietly is how
            // a page could sit on figures that had already moved.
            if (data && data.revisions) { changed(data.revisions); }

            lastHeard = Date.now();
            connected(true);
        });

        source.addEventListener('change', function (e) {
            var data = read(e);
            lastHeard = Date.now();
            if (data && data.topics) { changed(data.topics); }
        });

        source.addEventListener('ping', function () {
            lastHeard = Date.now();
            connected(true);
        });

        // The stream's turn is over, not the connection — open the next one
        // straight away rather than treating it as a drop-out.
        source.addEventListener('bye', function () {
            drop();
            later(250);
        });

        // Every place for a stream is taken. Ask now and then instead, and
        // try for a stream again in a while.
        source.addEventListener('poll', function (e) {
            var data = read(e) || {};
            drop();
            if (data.poll_ms) { pollMs = data.poll_ms; }
            startPolling();
            later((data.retry_in || 60) * 1000);
        });

        source.onerror = function () {
            drop();
            // The browser is offline, the container is restarting, or the
            // connection was cut. Keep asking, more slowly each time, and
            // fall back to the short question so the page still catches up.
            connected(false);
            startPolling();
            later(backoff);
            backoff = Math.min(backoff * 2, 30000);

            // EventSource never says why it failed. Ask straight away, so a
            // tab whose session has run out learns it on this attempt and
            // goes quiet, instead of reconnecting until the next poll.
            ask();
        };
    }

    function drop() {
        if (source) {
            try { source.close(); } catch (e) {}
            source = null;
        }
    }

    function later(ms) {
        clearTimeout(reopen);
        reopen = setTimeout(function () {
            if (!document.hidden) { connect(); }
        }, ms);
    }

    function read(e) {
        try { return JSON.parse(e.data); } catch (err) { return null; }
    }

    // ── Asking, when there is no stream ─────────────────────────────────────

    function startPolling() {
        if (stopped || pollTimer) { return; }
        pollTimer = setInterval(ask, pollMs);
    }

    function stopPolling() {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    function ask() {
        if (stopped || document.hidden) { return; }

        fetch(cfg.revisionsUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Live': '1' }
        }).then(function (res) {
            if (!signedIn(res)) { return null; }
            return res.json();
        }).then(function (data) {
            if (!data) { return; }
            lastHeard = Date.now();
            connected(true);
            if (data.poll_ms) { pollMs = data.poll_ms; }
            if (data.stream === true && streamOff === false && !source) { connect(); }
            changed(data.revisions || {});
        }).catch(function () {
            connected(false);
        });
    }

    // ── What changed ────────────────────────────────────────────────────────

    /** Topics whose revision moved: update what this page shows. */
    function changed(now) {
        var moved = [];

        for (var topic in now) {
            if (Object.prototype.hasOwnProperty.call(now, topic) && revisions[topic] !== now[topic]) {
                revisions[topic] = now[topic];
                moved.push(topic);
            }
        }

        if (moved.length) { act(moved); }
    }

    function act(topics) {
        // The parts of this page that show any of it.
        var urls = {};

        regions().forEach(function (el) {
            if (!watches(el.getAttribute('data-live'), topics)) { return; }

            var url = el.getAttribute('data-live-url') || location.href;
            (urls[url] = urls[url] || []).push(el);
        });

        Object.keys(urls).forEach(function (url) { reread(url, urls[url]); });

        // And the pages that update themselves.
        subscribed.forEach(function (sub) {
            if (watches(sub.topics.join(' '), topics)) {
                try { sub.fn(topics); } catch (e) { /* one subscriber, not the page */ }
            }
        });
    }

    function regions() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-live]'));
    }

    function watches(declared, topics) {
        if (!declared) { return false; }

        var mine = declared.split(/[\s,]+/);

        for (var i = 0; i < topics.length; i++) {
            if (mine.indexOf(topics[i]) !== -1) { return true; }
        }

        return false;
    }

    // ── Re-reading a page and patching in the difference ────────────────────

    function reread(url, els) {
        if (inFlight[url] || stopped) { return; }

        // Nothing is patched while a dialog is open: what it is about could be
        // the very row being replaced. The change is not dropped — the page is
        // re-read once the screen is free, so what lands is current then
        // rather than current now.
        if (held()) { hold(); return; }

        inFlight[url] = true;

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Live': '1', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!signedIn(res)) { return null; }
            return res.text();
        }).then(function (html) {
            delete inFlight[url];
            if (html === null || html === undefined) { return; }
            if (held()) { hold(); return; }

            var fresh = new DOMParser().parseFromString(html, 'text/html');
            var later = false;

            els.forEach(function (el) {
                // A region somebody is typing in is left as it is; the others
                // on the same page are not made to wait for it.
                if (inUse(el)) { later = true; return; }

                var to = el.id ? fresh.getElementById(el.id) : null;
                if (to) { patch(el, to); }
            });

            if (later) { hold(); }
        }).catch(function () {
            delete inFlight[url];
        });
    }

    /**
     * Sign-out, or a session that timed out while the page sat open. Stop
     * talking rather than filling the log with redirects nobody asked for.
     */
    function signedIn(res) {
        if (res.status === 401 || res.status === 403 || res.status === 419 ||
            (res.redirected && /\/login(\?|$)/.test(res.url))) {
            stop();
            return false;
        }

        return res.ok;
    }

    function stop() {
        stopped = true;
        drop();
        stopPolling();
        clearTimeout(reopen);
        connected(true);
    }

    // ── Holding back while the screen is in use ─────────────────────────────

    /**
     * Whether the whole page must wait.
     *
     * A dialog is about something — this row, this advance, this worker — and
     * replacing what it is about underneath it is how a form comes to save the
     * wrong thing. The same for an open row menu, which a patch would shut
     * mid-click. Both are rare and short, so the page simply waits them out.
     */
    function held() {
        return !!document.querySelector(
            'dialog[open], .modal.show, .emp-modal.open, .mod-modal.open, .emp-modal.show, ' +
            '.modal[style*="display: block"], .modal[style*="display:block"], ' +
            '.emp-more.open, .emp-more-menu.open, .mod-menu.open, .dropdown-menu.show, ' +
            // Attendance's mark mode: rows are ticked for deleting, and a
            // patch would untick them. Any page can ask for the same wait by
            // putting .live-busy on an element while it is mid-something.
            '.att-mark-mode, .live-busy'
        );
    }

    /**
     * Whether this one region is in use.
     *
     * Only what is inside it counts. The search box in the top bar is focused
     * the moment a page loads, and it is not in any of these regions: a page
     * that treated that as "somebody is typing" would never update at all.
     */
    function inUse(el) {
        var active = document.activeElement;

        if (active && el.contains(active) &&
            (/^(input|select|textarea)$/i.test(active.tagName) || active.isContentEditable)) {
            return true;
        }

        // Rows somebody has ticked — the people they are about to mark paid,
        // the records they are about to delete. The tick is theirs, not the
        // server's, and a patch would quietly undo it.
        var ticked = el.querySelectorAll('input[type="checkbox"]:checked');

        for (var i = 0; i < ticked.length; i++) {
            if (!ticked[i].hasAttribute('checked')) { return true; }
        }

        var selection = window.getSelection && window.getSelection();

        return !!(selection && !selection.isCollapsed && selection.anchorNode &&
                  el.contains(selection.anchorNode));
    }

    /** Try again shortly; the screen is usually free within a moment. */
    function hold() {
        if (waiting) { return; }
        waiting = true;

        var tries = 0;
        var timer = setInterval(function () {
            if (stopped || ++tries > 600) {          // ten minutes, then let it be
                clearInterval(timer);
                waiting = false;
                return;
            }
            // Only the whole-page holds are waited out here. A single region
            // somebody is typing in is skipped again by the re-read itself,
            // and the rest of the page is not kept waiting for it.
            if (held()) { return; }

            clearInterval(timer);
            waiting = false;
            act(Object.keys(revisions));             // re-read as it stands now
        }, 1000);
    }

    // ── Patching ────────────────────────────────────────────────────────────

    function patch(from, to) {
        var scrolls = [];

        // A list the user has scrolled down keeps its place.
        each(from.querySelectorAll('*'), function (el) {
            if (el.scrollTop > 0 || el.scrollLeft > 0) {
                scrolls.push([el, el.scrollTop, el.scrollLeft]);
            }
        });
        if (from.scrollTop > 0 || from.scrollLeft > 0) {
            scrolls.push([from, from.scrollTop, from.scrollLeft]);
        }

        var touched = [];
        children(from, to, touched);

        scrolls.forEach(function (s) {
            if (s[0].isConnected) { s[0].scrollTop = s[1]; s[0].scrollLeft = s[2]; }
        });

        if (touched.length) {
            flash(touched);
        }

        // Icons and anything else a page draws for itself.
        if (window.lucide && window.lucide.createIcons) {
            try { window.lucide.createIcons(); } catch (e) {}
        }

        from.dispatchEvent(new CustomEvent('live:updated', {
            bubbles: true,
            detail: { region: from, changed: touched.length }
        }));
    }

    function children(from, to, touched) {
        var want = Array.prototype.slice.call(to.childNodes);
        var keyed = {};

        each(from.childNodes, function (node) {
            var k = keyOf(node);
            if (k) { keyed[k] = node; }
        });

        for (var i = 0; i < want.length; i++) {
            var wanted = want[i];
            var here   = from.childNodes[i] || null;
            var key    = keyOf(wanted);
            var match  = null;

            if (key && keyed[key]) {
                match = keyed[key];
                if (match !== here) {
                    from.insertBefore(match, here);
                }
            } else if (here && alike(here, wanted) && !keyOf(here)) {
                match = here;
            }

            if (match) {
                node(match, wanted, touched);
            } else {
                from.insertBefore(wanted.cloneNode(true), here);
                touched.push(from.childNodes[i]);
            }
        }

        while (from.childNodes.length > want.length) {
            from.removeChild(from.childNodes[from.childNodes.length - 1]);
        }
    }

    function node(from, to, touched) {
        if (from.nodeType === 3 || from.nodeType === 8) {        // text, comment
            if (from.nodeValue !== to.nodeValue) {
                from.nodeValue = to.nodeValue;
                if (from.parentNode) { touched.push(from.parentNode); }
            }
            return;
        }

        if (from.nodeType !== 1) { return; }

        if (from.hasAttribute('data-live-freeze')) { return; }

        // A field somebody has typed in keeps what they typed.
        if (/^(input|select|textarea)$/i.test(from.tagName)) {
            attributes(from, to, ['value', 'checked', 'selected']);
            return;
        }

        attributes(from, to, []);
        children(from, to, touched);
    }

    function attributes(from, to, skip) {
        var i, at;

        for (i = 0; i < to.attributes.length; i++) {
            at = to.attributes[i];
            if (skip.indexOf(at.name) !== -1) { continue; }
            if (from.getAttribute(at.name) !== at.value) {
                from.setAttribute(at.name, at.value);
            }
        }

        for (i = from.attributes.length - 1; i >= 0; i--) {
            at = from.attributes[i];
            if (skip.indexOf(at.name) !== -1) { continue; }
            if (!to.hasAttribute(at.name)) {
                from.removeAttribute(at.name);
            }
        }
    }

    /** What makes two nodes the same node between one render and the next. */
    function keyOf(n) {
        if (!n || n.nodeType !== 1) { return null; }
        return n.getAttribute('data-live-key') || n.id || null;
    }

    function alike(a, b) {
        return a.nodeType === b.nodeType &&
               (a.nodeType !== 1 || a.tagName === b.tagName);
    }

    /** A moment's tint on what moved, so a change is noticed, not hunted for. */
    function flash(nodes) {
        var seen = [];

        nodes.forEach(function (n) {
            var el = n && n.nodeType === 1 ? n : (n && n.parentElement);
            var row = el && (el.closest('tr, li, .mod-card, .card, .stat-card') || el);

            if (row && row.nodeType === 1 && seen.indexOf(row) === -1 && seen.length < 40) {
                seen.push(row);
            }
        });

        seen.forEach(function (row) {
            row.classList.remove('live-fresh');
            void row.offsetWidth;                   // let it start again
            row.classList.add('live-fresh');
            setTimeout(function () { row.classList.remove('live-fresh'); }, 1600);
        });
    }

    function each(list, fn) {
        Array.prototype.forEach.call(list, fn);
    }

    // ── Saying when the line is down ────────────────────────────────────────

    var pill = null;

    function connected(ok) {
        if (ok) {
            if (pill) { pill.remove(); pill = null; }
            return;
        }

        // Only worth saying once it has been down long enough to matter.
        if (pill || Date.now() - lastHeard < 12000) { return; }

        pill = document.createElement('div');
        pill.className = 'live-offline';
        pill.setAttribute('role', 'status');
        pill.innerHTML = '<span class="live-offline-dot"></span> Reconnecting — this page may be behind';
        document.body.appendChild(pill);
    }

    // ── Comings and goings ──────────────────────────────────────────────────

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            hiddenAt = Date.now();
            // A tab nobody is looking at gives its place back, so the ones
            // being looked at have one.
            setTimeout(function () {
                if (document.hidden && Date.now() - hiddenAt >= 20000) {
                    drop();
                    stopPolling();
                }
            }, 20000);
            return;
        }

        // Back in view: catch up at once rather than on the next tick.
        ask();
        connect();
    });

    window.addEventListener('online', function () {
        backoff = 3000;
        ask();
        connect();
    });

    window.addEventListener('offline', function () {
        drop();
        connected(false);
    });

    window.addEventListener('pagehide', drop);

    // ── What pages can use ──────────────────────────────────────────────────

    // Whatever subscribed before this file loaded — page scripts run while the
    // page is still being read, and this is opened once it has been.
    var queued = (window.Live && window.Live._queued) || [];

    window.Live = {
        /** Live.on('attendance payroll', fn) — run fn when those change. */
        on: function (topics, fn) {
            subscribed.push({
                topics: String(topics).split(/[\s,]+/).filter(Boolean),
                fn: fn
            });
            return this;
        },

        /** Re-read every live region on this page, now. */
        refresh: function () {
            act(Object.keys(revisions).concat(cfg.topics || []));
        },

        /** Where this tab thinks each topic stands. */
        revisions: function () {
            return Object.assign({}, revisions);
        },

        /** True while a stream is open (as opposed to asking now and then). */
        streaming: function () {
            return !!source;
        }
    };

    queued.forEach(function (sub) { window.Live.on(sub[0], sub[1]); });

    if (streamOff) { startPolling(); } else { connect(); }
})();
