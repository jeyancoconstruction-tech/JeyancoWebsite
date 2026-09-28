// ─────────────────────────────────────────────────────────────────────────
//  The kiosk's own screen, inside the web — for System Settings → Kiosks.
//
//  script.js, kiosk-clock.js and kiosk-summary.js are the kiosk's v8 files,
//  unchanged. They talk to two places: the Pi (Flask — the sensor, the window,
//  the chosen site) and the web API. Loaded before them, this file answers
//  both, so the screen draws exactly what the device draws:
//
//    • every read (sites, settings, today's board, the roster) comes from this
//      system, for the kiosk being watched — the same data the device reads;
//    • the sensor never has a finger on it, so the monitor never scans;
//    • nothing that writes is passed on: a time in, an enrolment, a fingerprint
//      are refused as "view only". Choosing a site is answered yes and kept on
//      this screen only — the device keeps its own.
//
//  The page that holds this screen posts each scan the kiosk sends as it
//  happens ({type: 'kiosk-event'}); it is drawn in the kiosk's own result
//  card, and the board is read again at once.
// ─────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const CFG  = window.KIOSK_MONITOR || {};
    const PI   = 'https://kiosk-monitor.invalid/pi';
    const API  = 'https://kiosk-monitor.invalid/api';
    window.JEYANCO_FLASK_BASE = PI;
    window.JEYANCO_API_BASE   = API;

    const realFetch = window.fetch.bind(window);
    const reply = (body, status) => Promise.resolve(new Response(JSON.stringify(body), {
        status: status || 200, headers: { 'Content-Type': 'application/json' },
    }));
    const VIEW_ONLY = { success: false, code: 'view_only', message: 'View only — this is the monitor, not the kiosk. Nothing was recorded.' };
    const read = what => realFetch(CFG.api + '/' + what, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });

    window.fetch = function (input, init) {
        const url = typeof input === 'string' ? input : (input && input.url) || '';
        const side = url.startsWith(PI) ? 'pi' : url.startsWith(API) ? 'api' : null;
        if (!side) return realFetch(input, init);

        const rest   = url.slice((side === 'pi' ? PI : API).length);
        const path   = rest.split('?')[0];
        const query  = rest.includes('?') ? rest.slice(rest.indexOf('?')) : '';
        const method = ((init && init.method) || 'GET').toUpperCase();

        if (method !== 'GET') {
            // The window buttons: "no window service here", so the page's own
            // full screen is used, as on a kiosk without one.
            if (path.startsWith('/window/')) return reply({}, 404);
            // The site: yes, on this screen only.
            if (path === '/set-site' || path === '/set-active-site' || path === '/active-site') return reply({ success: true });
            return reply(VIEW_ONLY);
        }

        if (side === 'pi') {
            if (path === '/scan')          return reply({ status: 'no_finger' });
            if (path === '/ping')          return reply({ status: 'ok', sensor: 'monitor' });
            if (path === '/enroll/status') return reply({ status: 'idle' });
            if (path === '/sites' || path === '/active-site') return read('sites');
            if (path === '/today-attendance') return read('today-attendance');
            if (path === '/roster')        return read('roster');
            return reply({}, 404);
        }

        if (path === '/settings')         return read('settings' + query);
        if (path === '/sites')            return read('sites');
        if (path === '/today-attendance') return read('today-attendance');
        if (path === '/roster')           return read('roster');
        return reply({}, 404);
    };

    // ── A scan at the kiosk, drawn in the kiosk's own result card ─────────
    const $ = id => document.getElementById(id);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const t = (k, v) => (window.jeyanco && window.jeyanco.t ? window.jeyanco.t(k, v) : k);
    const REASON = {
        already_in: 'att.already.t', no_open: 'att.noopen.t', just_timed_in: 'att.justin.t', just_timed_out: 'att.repeat.t',
        session_done: 'att.repeat.t', wrong_shift: 'att.wrongshift.t', not_registered: 'att.notreg.t',
        mode_buttons: 'att.modebtn.t', no_gps: 'att.gps.t', outside_location: 'att.gps.t',
        wrong_site: 'att.site.t', no_site: 'att.nosite.t',
    };
    const ICON = { in: 'right-to-bracket', out: 'right-from-bracket', warn: 'triangle-exclamation', rej: 'circle-xmark' };
    const SHOW_MS = 4500;
    let timer = null;

    function show(e) {
        const panel = $('att-panel');
        if (!panel || e.kind === 'scan') return;       // the recorded result follows at once
        const kind = e.kind === 'unknown' ? 'rej' : e.kind;
        const verb = kind === 'in' || kind === 'out'
            ? (e.session ? e.session + ' ' : '') + (kind === 'in' ? 'IN' : 'OUT')
            : e.kind === 'unknown' ? t('att.notfound') : t(REASON[e.code] || 'bdg.rejected');

        $('att-result').className = 'scan-result ' + kind;
        $('sr-verb').innerHTML = `<i class="fas fa-${ICON[kind] || 'circle-info'}"></i>${esc(verb)}`
            + (kind === 'in' ? `<small>· ${esc(t('res.in'))}</small>` : kind === 'out' ? `<small>· ${esc(t('res.out'))}</small>` : '');

        if (e.name) {
            $('sr-name').textContent = e.name;
            $('sr-sub').textContent = e.position || t('worker');
            $('sr-who').hidden = false;
        } else {
            $('sr-who').hidden = true;
        }
        const at = String(e.time || '').replace(/:\d{2}(\s*[AP]M)$/i, '$1');
        $('sr-time-v').textContent = at;
        $('sr-time-s').textContent = e.session ? t('sess.' + e.session) : '';
        $('sr-time').hidden = !at || !(kind === 'in' || kind === 'out');

        const note = e.kind === 'unknown' ? t('att.notenrol') : (kind === 'in' || kind === 'out') ? (e.auto ? t('mode.auto') : '') : (e.message || '');
        $('sr-notes').innerHTML = note ? `<div class="result-note ${kind === 'rej' ? 'bad' : kind === 'warn' ? '' : 'info'}"><span>${esc(note)}</span></div>` : '';

        panel.classList.add('has-result');
        const bar = $('sr-bar');
        bar.style.transition = 'none'; bar.style.width = '100%'; void bar.offsetWidth;
        bar.style.transition = `width ${SHOW_MS}ms linear`; bar.style.width = '0%';

        clearTimeout(timer);
        timer = setTimeout(() => panel.classList.remove('has-result'), SHOW_MS);

        // A recorded scan changed the board: read it now, not on the next 15 s.
        if (kind === 'in' || kind === 'out') {
            window.dispatchEvent(new CustomEvent('sitechange', { detail: { monitor: true } }));
            if (typeof window.loadRoster === 'function') window.loadRoster();
        }
    }

    window.addEventListener('message', ev => {
        if (ev.origin !== location.origin || !ev.data || ev.data.type !== 'kiosk-event') return;
        show(ev.data.event);
    });
})();
