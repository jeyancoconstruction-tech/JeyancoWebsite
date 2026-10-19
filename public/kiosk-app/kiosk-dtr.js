// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO KIOSK — MY DTR (v13, RSC 2026-10-18: the kiosk displays the
//  attendance and the DTR)
//
//  A worker scans a finger and sees their own Daily Time Record — this week
//  or last — the same rows and totals as Payroll Reports › Employees DTR.
//  Nothing is recorded here. A scan made on this tab never clocks anyone in.
//
//  The DTR is opened with the scan token the web gave that finger read
//  (through the Pi), never with a slot number — only the worker whose finger
//  is on the sensor can open it. It closes by itself after a short while.
// ─────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const J   = window.jeyanco || {};
    const t   = J.t || (k => k);
    const esc = J.escapeHtml || (s => String(s ?? ''));
    const $   = id => document.getElementById(id);

    const SESSION_SECONDS = 90;

    let session  = null;     // { token, employee, week, data }
    let timer    = null, secsLeft = 0;
    let poll     = null;
    let busy     = false;
    let lastSeq  = null, primed = false, needLift = false;
    let statusKey = 'dtr.unlock';

    function status(key, ring) {
        statusKey = key;
        $('dtr-status').textContent = t(key);
        if (ring) J.setRing('dtr-ring', 'dtr-icon', ring);
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
        if (!primed) {                       // a finger already down belongs to another tab
            primed = true;
            if (d.status && d.status !== 'no_finger') needLift = true;
            if (hasSeq) lastSeq = d.seq;
        }
        if (!d.status || d.status === 'no_finger') {
            needLift = false;
            if (statusKey !== 'dtr.unlock' && !busy) status('dtr.unlock', 'idle');
            return;
        }
        if (needLift) return;
        if (d.status === 'scanning') { status('dtr.scanning', 'scanning'); return; }
        if (hasSeq) { if (d.seq === lastSeq) return; lastSeq = d.seq; } else { needLift = true; }

        if (d.status === 'not_found') { status('dtr.unknown', 'error'); return; }
        if (!d.employee || !d.employee.id || !d.scan_token) {
            $('dtr-status').textContent = d.message || t('dtr.noweb');
            J.setRing('dtr-ring', 'dtr-icon', 'error');
            return;
        }
        open(d.scan_token, d.employee, 0);
    }

    async function fetchDtr(token, week) {
        const qs = new URLSearchParams({ scan_token: token, week: String(week) }).toString();
        let res = await J.flaskFetch('/my-dtr?' + qs, { headers: { 'Accept': 'application/json' } }, 20000).catch(() => null);
        if (!res || J.isMissingRoute(res)) res = await J.apiFetch('/my-dtr?' + qs, { headers: { 'Accept': 'application/json' } }, 20000);
        return J.parseJSON(res);
    }

    async function open(token, employee, week) {
        busy = true;
        status('dtr.loading', 'scanning');
        try {
            const data = await fetchDtr(token, week);
            if (!data.success) {
                if (data.code === 'stale_scan') { close(null); $('dtr-status').textContent = t('dtr.stale'); }
                else if (!session) { J.setRing('dtr-ring', 'dtr-icon', 'error'); $('dtr-status').textContent = data.message || t('dtr.failed'); }
                return;
            }
            session = { token, employee, week, data };
            J.setRing('dtr-ring', 'dtr-icon', 'success');
            $('dtr-status').textContent = (data.employee.name || '').toUpperCase();
            $('dtr-scan').hidden = true;          // the name card takes the ring's place
            $('dtr-who').hidden = false;
            $('dtr-who-name').textContent = data.employee.name;
            $('dtr-who-sub').textContent = [data.employee.position, data.employee.site].filter(Boolean).join(' · ');
            $('dtr-actions').hidden = false;
            document.querySelectorAll('#dtr-actions .seg button').forEach(b => b.classList.toggle('active', +b.dataset.week === week));
            render();
            resetTimer();
        } catch (e) {
            if (!session) {
                J.setRing('dtr-ring', 'dtr-icon', 'error');
                $('dtr-status').textContent = t('dtr.noweb');
            }
        } finally {
            busy = false;
        }
    }

    // ── The record ───────────────────────────────────────────────────────
    function slot(d, name) {
        if ((d.miss || []).includes(name)) return `<td class="miss">${esc(t('dtr.noscan'))}</td>`;
        return `<td>${d[name] ? esc(d[name]) : (d.future || d.remark === '' ? '' : '<span class="t-dash">—</span>')}</td>`;
    }

    function render() {
        const card = $('dtr-card');
        if (!session || !card) return;
        const d = session.data, tot = d.totals || {};

        const rows = (d.days || []).map(day => `
            <tr class="${day.today ? 'today' : ''}${day.future ? ' future' : ''}">
                <td class="d">${esc(day.label)}<span>${esc(day.day)}</span></td>
                ${slot(day, 'in1')}${slot(day, 'out1')}${slot(day, 'in2')}${slot(day, 'out2')}
                <td>${day.worked ? esc(day.worked) : ''}</td>
                <td class="${day.late ? 'late' : ''}">${day.late ? esc(day.late) : ''}</td>
                <td class="${day.ot ? 'ot' : ''}">${day.ot ? esc(day.ot) : ''}</td>
                <td>${day.remark ? `<span class="rem ${esc(day.tone)}">${esc(t(day.remark))}</span>` : ''}</td>
            </tr>`).join('');

        const extra = [];
        if (tot.absent)  extra.push(t('dtr.absent', { n: tot.absent }));
        if (tot.missing) extra.push(t('dtr.missing', { n: tot.missing }));

        card.innerHTML = `
            <div class="dtr-head">
                <div>
                    <div class="dtr-name">${esc(d.employee.name)}</div>
                    <div class="dtr-sub">${esc(d.period.hours || '')}</div>
                </div>
                <div class="dtr-period"><span>${esc(t('dtr.period'))}</span><b>${esc(d.period.label)}</b></div>
            </div>
            <div class="dtr-wrap" id="dtr-wrap">
                <table class="dtr-table">
                    <thead><tr>
                        <th>${esc(t('dtr.date'))}</th>
                        <th>${esc(t('dtr.in'))}</th><th>${esc(t('dtr.out'))}</th>
                        <th>${esc(t('dtr.in'))}</th><th>${esc(t('dtr.out'))}</th>
                        <th>${esc(t('dtr.hours'))}</th><th>${esc(t('dtr.late'))}</th><th>OT</th>
                        <th>${esc(t('dtr.remark'))}</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div class="dtr-totals">
                <div class="tot"><span>${esc(t('dtr.days'))}</span><b>${esc(tot.days)}</b></div>
                <div class="tot"><span>${esc(t('dtr.worked'))}</span><b>${esc(tot.worked || '—')}</b></div>
                <div class="tot ot"><span>${esc(t('dtr.ot'))}</span><b>${esc(tot.ot || '—')}</b></div>
                <div class="tot ${extra.length ? 'bad' : 'late'}"><span>${esc(extra.length ? extra.join(' · ') : t('dtr.late'))}</span><b>${esc(extra.length ? (tot.absent + tot.missing) : (tot.late || '—'))}</b></div>
            </div>`;
        card.hidden = false;
        $('dtr-locked').hidden = true;
        if (J.dragScroll) J.dragScroll($('dtr-wrap'));
    }

    // ── Privacy: it closes by itself ─────────────────────────────────────
    function drawTimer() {
        const m = Math.floor(secsLeft / 60), s = secsLeft % 60;
        $('dtr-timer').textContent = t('dtr.timer', { t: m + ':' + String(s).padStart(2, '0') });
    }
    function resetTimer() {
        secsLeft = SESSION_SECONDS;
        clearInterval(timer);
        timer = setInterval(() => { secsLeft--; drawTimer(); if (secsLeft <= 0) close('dtr.closed'); }, 1000);
        drawTimer();
    }

    function close(reasonKey) {
        session = null;
        clearInterval(timer); timer = null;
        $('dtr-card').hidden = true; $('dtr-card').innerHTML = '';
        $('dtr-locked').hidden = false;
        $('dtr-scan').hidden = false;
        $('dtr-who').hidden = true;
        $('dtr-actions').hidden = true;
        status('dtr.unlock', 'idle');
        const p = $('dtr-locked').querySelector('p');
        if (p) p.textContent = reasonKey ? t(reasonKey) + ' ' + t('dtr.scanleft') : t('dtr.scanleft');
    }

    // ── Tab wiring ───────────────────────────────────────────────────────
    function start() { primed = false; needLift = false; clearInterval(poll); poll = setInterval(watch, 700); }
    function stop()  { clearInterval(poll); poll = null; if (session) close(null); }

    window.addEventListener('tabchange', e => {
        if (e.detail.tab === 'dtr') start();
        else if (e.detail.was === 'dtr') stop();
    });
    J.holds = J.holds || {};
    J.holds.dtr = () => !!session;
    // For Device Monitoring: whose DTR is open — the name, never the record.
    J.dtrView = () => (session ? { who: session.data.employee.name, week: session.week } : null);

    window.addEventListener('DOMContentLoaded', () => {
        status('dtr.unlock', 'idle');
        $('dtr-lock').addEventListener('click', () => close(null));
        document.querySelectorAll('#dtr-actions .seg button').forEach(b => b.addEventListener('click', () => {
            if (!session || busy) return;
            const week = +b.dataset.week;
            if (week === session.week) return;
            // The open week stays on screen until the other one has arrived.
            open(session.token, session.employee, week);
        }));
    });
    window.addEventListener('langchange', () => {
        if (session) { render(); drawTimer(); } else { status(statusKey); }
    });
})();
