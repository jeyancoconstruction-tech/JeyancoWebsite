// ─────────────────────────────────────────────────────────────────────────
//  The kiosk's own screen, inside the web — for System Settings → Kiosks.
//
//  The kiosk's own files (public/kiosk-app — the same ones the device runs
//  from /kiosk), unchanged. They talk to two places: the Pi (Flask — the sensor, the window,
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
            // MY DTR and REPORT open only with a finger on the real sensor.
            if (path === '/my-dtr' || path === '/complaints') return reply(VIEW_ONLY);
            return reply({}, 404);
        }

        if (path === '/settings')         return read('settings' + query);
        if (path === '/sites')            return read('sites');
        if (path === '/today-attendance') return read('today-attendance');
        if (path === '/roster')           return read('roster');
        if (path === '/my-dtr' || path === '/complaints') return reply(VIEW_ONLY);
        return reply({}, 404);
    };

    // ── A scan at the kiosk, drawn in the kiosk's own result card ─────────
    const $ = id => document.getElementById(id);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const t = (k, v) => (window.jeyanco && window.jeyanco.t ? window.jeyanco.t(k, v) : k);
    // The kiosk's own words for each answer (script.js, v13).
    const REASON = {
        already_in: 'att.already.t', no_open: 'att.noopen.t', just_timed_in: 'att.justin.t', just_timed_out: 'att.justout.t',
        session_done: 'att.sessdone.t', wrong_shift: 'att.wrongshift.t', not_registered: 'att.notreg.t',
        mode_buttons: 'att.modebtn.t', no_gps: 'att.gps.t', outside_location: 'att.gps.t',
        wrong_site: 'att.site.t', no_site: 'att.nosite.t', no_site_location: 'att.gps.t',
        stale_scan: 'att.stale.t',
    };
    const SHOW_MS = 4500;

    /** A scan the kiosk sent, drawn in the kiosk's own card (kiosk-clock.js showResult). */
    function show(e) {
        const J = window.jeyanco;
        if (!J || !J.showResult || e.kind === 'scan') return;    // the recorded result follows at once
        const kind = e.kind === 'unknown' ? 'rej' : e.kind;
        const inout = kind === 'in' || kind === 'out';
        const verb = inout
            ? (e.session ? e.session + ' ' : '') + (kind === 'in' ? 'IN' : 'OUT')
            : e.kind === 'unknown' ? t('att.notfound') : t(REASON[e.code] || 'bdg.rejected', { s: e.session || 'SESSION' });
        const note = e.kind === 'unknown' ? t('att.notenrol') : inout ? (e.auto ? t('mode.auto') : '') : (e.message || '');

        J.showResult({
            kind, verb,
            verbSub: kind === 'in' ? t('res.in') : kind === 'out' ? t('res.out') : '',
            employee: e.name ? { name: e.name, position: e.position || t('worker') } : null,
            time: inout ? String(e.time || '').replace(/:\d{2}(\s*[AP]M)$/i, '$1') : '',
            where: e.session ? t('sess.' + e.session) : '',
            notes: note ? [[kind === 'rej' ? 'bad' : kind === 'warn' ? 'warn' : 'info', note]] : [],
        });

        // A recorded scan changed the board: read it now, not on the next 15 s.
        if (inout) {
            window.dispatchEvent(new CustomEvent('sitechange', { detail: { monitor: true } }));
            if (typeof window.loadRoster === 'function') window.loadRoster();
        }
    }
    // ── The kiosk's tab, followed live ────────────────────────────────────
    // The page holding this screen posts {type: 'kiosk-view', view} whenever
    // the kiosk reports a tap: the tab, who is using it, the button pressed.
    // This screen moves with it. A worker's DTR and the words of a complaint
    // stay on the kiosk — here they are only named.
    let liveBar = null;
    const STEP = { topic: 'choosing a topic', writing: 'writing a report', sent: 'report sent' };
    const TABN = { attendance: 'tab.attendance', dtr: 'tab.dtr', report: 'tab.report', register: 'tab.enroll' };

    function paintLive(v) {
        if (!liveBar) {
            liveBar = document.createElement('div');
            liveBar.className = 'mon-live';
            liveBar.style.cssText = 'position:fixed;left:8px;bottom:6px;z-index:50;font:800 11px Inter,sans-serif;letter-spacing:.06em;'
                + 'color:#d1fae5;background:rgba(6,78,59,.92);border:1px solid #10b981;border-radius:5px;padding:4px 9px;pointer-events:none';
            document.body.appendChild(liveBar);
        }
        const bits = ['● LIVE', t(TABN[v.tab] || 'tab.attendance')];
        if (v.who) bits.push(v.who);
        if (v.tab === 'dtr' && v.who) bits.push(v.week ? 'last week' : 'this week');
        if (v.tab === 'report' && v.step) bits.push(STEP[v.step] || v.step);
        if (v.tab === 'attendance' && v.who) bits.push(v.step === 'choose' ? 'choosing TIME IN / OUT' : v.step === 'busy' ? 'saving' : '');
        liveBar.textContent = bits.filter(Boolean).join(' · ');
    }

    function nameCard(whoId, nameId, subId, hideId, name, sub) {
        const card = $(whoId);
        if (!card) return;
        card.hidden = !name;
        if ($(hideId)) $(hideId).hidden = !!name;
        if (name) { $(nameId).textContent = name; $(subId).textContent = sub; }
    }

    function follow(v) {
        const J = window.jeyanco;
        if (!J || !J.switchTab) return;
        v = v && v.tab ? v : { tab: 'attendance' };

        if (J.currentTab() !== v.tab) J.switchTab(v.tab);
        // Light or dark, and the tab dock up or tucked away — as on the kiosk.
        if (J.setTheme) J.setTheme(v.theme === 'light' ? 'light' : 'dark', true);
        if (J.setDock) J.setDock(v.dock !== 0);
        // A finger on the kiosk's sensor: the same card, with the same name.
        if (v.tab === 'attendance' && J.mirrorChoose) J.mirrorChoose(v.step === 'choose' ? v.who : null);

        // MY DTR: who has it open — the record itself is not shown here.
        nameCard('dtr-who', 'dtr-who-name', 'dtr-who-sub', 'dtr-scan', v.tab === 'dtr' ? v.who : null,
                 'DTR open on the kiosk · ' + (v.week ? 'last week' : 'this week'));
        const dl = $('dtr-locked') && $('dtr-locked').querySelector('p');
        if (dl) dl.textContent = v.tab === 'dtr' && v.who ? v.who + ' is looking at their own DTR on the kiosk. It stays private to them.' : t('dtr.scanleft');

        // REPORT: who is writing, and how far.
        nameCard('rep-who', 'rep-who-name', 'rep-who-sub', 'rep-scan', v.tab === 'report' ? v.who : null,
                 STEP[v.step] || 'on the REPORT tab');
        const rl = $('rep-locked') && $('rep-locked').querySelector('p');
        if (rl) rl.textContent = v.tab === 'report' && v.who ? v.who + ' is ' + (STEP[v.step] || 'on the REPORT tab') + '. It arrives on the Complaints page when sent.' : t('rep.scanleft');

        // ENROLL: whose fingers are being taken, and the step on the sensor.
        if (v.tab === 'register') {
            const who = $('enroll-who');
            if (who && v.who) { who.classList.add('has-pick'); who.innerHTML = '<div class="who-name">' + esc(v.who) + '</div><div class="who-sub">Enrolling on the kiosk</div>'; }
            if (v.step && $('reg-status-text')) $('reg-status-text').textContent = v.step;
        }
        paintLive(v);
    }

    // The monitor never leaves the tab the kiosk is on by itself.
    window.addEventListener('DOMContentLoaded', () => setTimeout(() => {
        const J = window.jeyanco;
        if (J) J.holds = Object.assign(J.holds || {}, { dtr: () => true, report: () => true, register: () => true });
    }, 0));

    window.addEventListener('message', ev => {
        if (ev.origin !== location.origin || !ev.data) return;
        if (ev.data.type === 'kiosk-event') show(ev.data.event);
        if (ev.data.type === 'kiosk-view') follow(ev.data.view);
    });
})();
