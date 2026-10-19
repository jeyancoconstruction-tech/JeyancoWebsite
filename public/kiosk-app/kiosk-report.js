// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO KIOSK — REPORT (v14, RSC 2026-10-19)
//
//  1. The worker scans a finger — the report carries their name, and only
//     they see their earlier reports and the office's answers.
//  2. Taps one of the ready-made requests.
//  3. SEND — or ADD A NOTE first: the keys appear only then. "Something
//     else" opens the keys straight away, since it needs words.
//  It reaches the office's Complaints page (Leave & Advances), with an alert.
// ─────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const J   = window.jeyanco || {};
    const t   = J.t || (k => k);
    const esc = J.escapeHtml || (s => String(s ?? ''));
    const $   = id => document.getElementById(id);

    const SESSION_SECONDS = 150;
    const MAX_CHARS       = 300;
    const KEYS = ['1234567890', 'QWERTYUIOP', 'ASDFGHJKL', 'ZXCVBNM'];

    // The ready-made requests: [id, topic, icon]. The words are rq.<id> in
    // script.js; what reaches the office is always the English line, so the
    // Complaints page reads the same whichever language the worker used.
    const PRESETS = [
        ['missing',   'attendance', 'calendar-xmark'],
        ['hours',     'attendance', 'clock'],
        ['pay',       'pay',        'peso-sign'],
        ['vale',      'pay',        'hand-holding-dollar'],
        ['unsafe',    'safety',     'helmet-safety'],
        ['tool',      'equipment',  'screwdriver-wrench'],
        ['people',    'treatment',  'people-arrows'],
        ['call',      'other',      'phone'],
        ['own',       'other',      'keyboard'],
    ];
    const enLine = id => (window.jeyanco && window.jeyanco.tEn ? window.jeyanco.tEn('rq.' + id) : t('rq.' + id));

    let session = null;     // { token, employee, history, left }
    let picked  = null;     // a PRESETS row
    let message = '', keysOpen = false;
    let timer = null, secsLeft = 0, poll = null, busy = false, sentTimer = null;
    let lastSeq = null, primed = false, needLift = false;
    let statusKey = 'rep.unlock';

    function status(key, ring) {
        statusKey = key;
        $('rep-status').textContent = t(key);
        if (ring) J.setRing('rep-ring', 'rep-icon', ring);
    }

    // ── The scan ─────────────────────────────────────────────────────────
    async function watch() {
        if (session || busy) return;
        let d;
        try {
            d = await J.parseJSON(await J.flaskFetch('/scan', {}, 3000));
            if (J.setStatus) J.setStatus('sensor', true);
        } catch (e) {
            if (J.setStatus) J.setStatus('sensor', false);
            status('dtr.nosensor', 'error');
            return;
        }
        const hasSeq = d.seq !== undefined && d.seq !== null;
        if (!primed) { primed = true; if (d.status && d.status !== 'no_finger') needLift = true; if (hasSeq) lastSeq = d.seq; }
        if (!d.status || d.status === 'no_finger') {
            needLift = false;
            if (statusKey !== 'rep.unlock' && !busy) status('rep.unlock', 'idle');
            return;
        }
        if (needLift) return;
        if (d.status === 'scanning') { status('dtr.scanning', 'scanning'); return; }
        if (hasSeq) { if (d.seq === lastSeq) return; lastSeq = d.seq; } else { needLift = true; }

        if (d.status === 'not_found') { status('dtr.unknown', 'error'); return; }
        if (!d.employee || !d.employee.id || !d.scan_token) {
            $('rep-status').textContent = d.message || t('dtr.noweb');
            J.setRing('rep-ring', 'rep-icon', 'error');
            return;
        }
        open(d.scan_token);
    }

    async function call(method, body, qs) {
        const opts = { method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' } };
        if (body) opts.body = JSON.stringify(body);
        const path = '/complaints' + (qs ? '?' + qs : '');
        let res = await J.flaskFetch(path, opts, 15000).catch(() => null);
        if (!res || J.isMissingRoute(res)) {
            if (body) opts.body = JSON.stringify(Object.assign({}, body, J.siteStamp()));
            res = await J.apiFetch(path, opts, 15000);
        }
        return J.parseJSON(res);
    }

    async function open(token) {
        busy = true;
        status('dtr.loading', 'scanning');
        try {
            const data = await call('GET', null, new URLSearchParams({ scan_token: token }).toString());
            if (!data.success) {
                J.setRing('rep-ring', 'rep-icon', 'error');
                $('rep-status').textContent = data.code === 'stale_scan' ? t('dtr.stale') : (data.message || t('dtr.noweb'));
                return;
            }
            session = { token, employee: data.employee, history: data.complaints || [], left: data.left_today };
            picked = null; message = ''; keysOpen = false;
            $('rep-scan').hidden = true;
            $('rep-who').hidden = false;
            $('rep-who-name').textContent = data.employee.name;
            $('rep-who-sub').textContent = data.employee.position || '';
            $('rep-hist-label').hidden = false;
            $('rep-history').hidden = false;
            $('rep-actions').hidden = false;
            $('rep-locked').hidden = true;
            $('rep-sent').hidden = true;
            renderHistory();
            showPresets();
            resetTimer();
        } catch (e) {
            J.setRing('rep-ring', 'rep-icon', 'error');
            $('rep-status').textContent = t('dtr.noweb');
        } finally {
            busy = false;
        }
    }

    // ── Step 1 · the ready-made requests ─────────────────────────────────
    function showPresets() {
        picked = null; message = ''; keysOpen = false;
        $('rep-confirm').hidden = true;
        $('rep-form').hidden = false;
        if (session && session.left === 0) {
            $('rep-presets').innerHTML = `<div class="roster-empty"><i class="fas fa-circle-info"></i>${esc(t('rep.limit'))}</div>`;
            return;
        }
        $('rep-presets').innerHTML = PRESETS.map(([id, topic, icon]) =>
            `<button type="button" class="preset-btn${topic === 'safety' ? ' safety' : ''}${id === 'own' ? ' own' : ''}" data-preset="${id}">
                <i class="fas fa-${icon}"></i><span>${esc(t('rq.' + id))}</span><small>${esc(t('cat.' + topic))}</small></button>`).join('');
    }

    // ── Step 2 · check, note, send ───────────────────────────────────────
    function pick(id) {
        picked = PRESETS.find(p => p[0] === id) || null;
        if (!picked) return;
        message = '';
        keysOpen = picked[0] === 'own';          // "something else" needs words
        $('rep-form').hidden = true;
        $('rep-confirm').hidden = false;
        $('rep-pick').innerHTML = `<i class="fas fa-${picked[2]}"></i><div><b>${esc(t('rq.' + picked[0]))}</b><small>${esc(t('cat.' + picked[1]))}</small></div>`;
        renderKeys();
        renderMsg();
    }

    function renderHistory() {
        const box = $('rep-history');
        if (!session) return;
        const list = session.history || [];
        box.innerHTML = list.length ? list.map(c => `
            <div class="hist">
                <div class="hist-top"><span>#${esc(c.id)} · ${esc(c.when)}</span>
                    <span class="st ${esc(c.status)}">${esc(t(c.status === 'open' ? 'rep.open' : 'rep.resolved'))}</span></div>
                <div class="hist-msg">${esc(c.message)}</div>
                ${c.response ? `<div class="hist-ans"><i class="fas fa-reply"></i> ${esc(t('rep.answer'))}: ${esc(c.response)}</div>` : ''}
            </div>`).join('') : `<div class="roster-empty">${esc(t('rep.nohist'))}</div>`;
        if (J.dragScroll) J.dragScroll(box);
    }

    function renderKeys() {
        const kbd = $('rep-kbd');
        if (kbd.dataset.drawn) return;
        const rows = KEYS.map((row, i) => {
            let keys = row.split('').map(k => `<button type="button" data-k="${k}">${k}</button>`).join('');
            if (i === 3) keys += `<button type="button" class="wide" data-k="BACK" aria-label="Backspace"><i class="fas fa-delete-left"></i></button>`;
            return `<div class="kbd-row">${keys}</div>`;
        });
        rows.push(`<div class="kbd-row">
            <button type="button" data-k=",">,</button><button type="button" data-k=".">.</button>
            <button type="button" class="space" data-k=" ">SPACE</button>
            <button type="button" data-k="?">?</button>
            <button type="button" class="wide" data-k="CLEAR">CLEAR</button></div>`);
        kbd.innerHTML = rows.join('');
        kbd.dataset.drawn = '1';
    }

    function canSend() {
        if (!session || !picked || busy || session.left === 0) return false;
        return picked[0] !== 'own' || message.trim().length >= 3;
    }

    function renderMsg() {
        const own = picked && picked[0] === 'own';
        $('rep-msg').hidden = !keysOpen && !message;
        $('rep-kbd').hidden = !keysOpen;
        $('rep-msg').innerHTML = message
            ? `${esc(message)}${keysOpen ? '<span class="caret"></span>' : ''}`
            : `<span class="ph">${esc(t(own ? 'rep.typeown' : 'rep.typenote'))}</span><span class="caret"></span>`;
        $('rep-msg').classList.toggle('focus', keysOpen);
        $('rep-confirm').classList.toggle('keys', keysOpen);
        $('rep-msg').scrollTop = $('rep-msg').scrollHeight;
        const note = $('rep-note');
        note.hidden = own;
        note.querySelector('span').textContent = t(keysOpen ? 'rep.hidenote' : 'rep.addnote');
        $('rep-send').disabled = !canSend();
        $('rep-count').textContent = keysOpen || message ? `${message.length}/${MAX_CHARS}` : '';
    }

    function type(k) {
        if (!session) return;
        resetTimer();
        if (k === 'BACK') message = message.slice(0, -1);
        else if (k === 'CLEAR') message = '';
        else if (message.length < MAX_CHARS) {
            const startOfSentence = !message.trim() || /[.?!]\s*$/.test(message);
            message += /[A-Z]/.test(k) && !startOfSentence ? k.toLowerCase() : k;
        }
        renderMsg();
    }

    async function send() {
        if (!canSend()) return;
        busy = true;
        $('rep-send').disabled = true;
        $('rep-send').querySelector('span').textContent = t('rep.sending');
        const note = message.trim();
        const text = picked[0] === 'own' ? note : enLine(picked[0]) + (note ? ' — ' + note : '');
        try {
            const data = await call('POST', { scan_token: session.token, category: picked[1], message: text });
            if (!data.success) {
                const msg = data.code === 'stale_scan' ? t('dtr.stale') : data.code === 'limit' ? t('rep.limit')
                          : (data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : t('att.retry')));
                $('rep-count').textContent = t('rep.failed', { msg });
                if (data.code === 'stale_scan') setTimeout(() => close(), 2500);
                return;
            }
            session.history = data.complaints || session.history;
            session.left = data.left_today;
            renderHistory();
            $('rep-confirm').hidden = true;
            $('rep-sent').hidden = false;
            $('rep-sent-t').textContent = t('rep.sent.t');
            $('rep-sent-d').textContent = t('rep.sent.d', { id: data.id });
            clearTimeout(sentTimer);
            sentTimer = setTimeout(() => close(), 8000);
        } catch (e) {
            $('rep-count').textContent = t('rep.failed', { msg: t('att.checkconn') });
        } finally {
            busy = false;
            const s = $('rep-send').querySelector('span'); if (s) s.textContent = t('rep.send');
            if (session && !$('rep-confirm').hidden) renderMsg();
        }
    }

    // ── Privacy ──────────────────────────────────────────────────────────
    function drawTimer() {
        const m = Math.floor(secsLeft / 60), s = secsLeft % 60;
        $('rep-timer').textContent = session ? t('rep.timer', { t: m + ':' + String(s).padStart(2, '0') }) : '';
    }
    function resetTimer() {
        secsLeft = SESSION_SECONDS;
        clearInterval(timer);
        timer = setInterval(() => { secsLeft--; drawTimer(); if (secsLeft <= 0) close(); }, 1000);
        drawTimer();
    }

    function close() {
        session = null; picked = null; message = ''; keysOpen = false;
        clearInterval(timer); timer = null; clearTimeout(sentTimer);
        ['rep-who', 'rep-hist-label', 'rep-history', 'rep-actions', 'rep-form', 'rep-confirm', 'rep-sent'].forEach(id => { $(id).hidden = true; });
        $('rep-history').innerHTML = '';
        $('rep-scan').hidden = false;
        $('rep-locked').hidden = false;
        status('rep.unlock', 'idle');
        drawTimer();
    }

    // ── Tab wiring ───────────────────────────────────────────────────────
    function start() { primed = false; needLift = false; clearInterval(poll); poll = setInterval(watch, 700); }
    function stop()  { clearInterval(poll); poll = null; if (session) close(); }

    window.addEventListener('tabchange', e => {
        if (e.detail.tab === 'report') start();
        else if (e.detail.was === 'report') stop();
    });
    J.holds = J.holds || {};
    J.holds.report = () => !!session;
    // For Device Monitoring: who is writing, and how far — never the words.
    J.reportView = () => (session ? { who: session.employee.name,
        step: !$('rep-sent').hidden ? 'sent' : (picked ? 'writing' : 'topic') } : null);

    window.addEventListener('DOMContentLoaded', () => {
        status('rep.unlock', 'idle');
        $('rep-lock').addEventListener('click', close);
        $('rep-send').addEventListener('click', send);
        $('rep-back').addEventListener('click', () => { resetTimer(); showPresets(); });
        $('rep-note').addEventListener('click', () => { keysOpen = !keysOpen; resetTimer(); renderMsg(); });
        $('rep-presets').addEventListener('click', e => {
            const b = e.target.closest('[data-preset]');
            if (b && session) { resetTimer(); pick(b.dataset.preset); }
        });
        $('rep-kbd').addEventListener('click', e => {
            const b = e.target.closest('[data-k]');
            if (b) type(b.dataset.k);
        });
    });
    window.addEventListener('langchange', () => {
        if (session) {
            renderHistory();
            if (!$('rep-form').hidden) showPresets();
            if (!$('rep-confirm').hidden && picked) { const keep = message, open_ = keysOpen; pick(picked[0]); message = keep; keysOpen = open_; renderMsg(); }
            drawTimer();
        } else status(statusKey);
    });
})();
