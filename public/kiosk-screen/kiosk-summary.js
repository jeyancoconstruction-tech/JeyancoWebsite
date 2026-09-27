// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO KIOSK — SUMMARY TAB (v8)
//
//  Every total the kiosk has, beside MY PAYROLL: who is working, on lunch,
//  done, on overtime, needs review, came late; the AM in / AM out / PM in /
//  PM out counts; and the list the foreman checks before going home.
//
//  The numbers come from the same /today-attendance the ATTENDANCE board
//  reads (its "summary" block is counted by the web), so the kiosk and the
//  office cannot disagree. This tab records nothing.
// ─────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const J   = window.jeyanco || {};
    const t   = J.t || (k => k);
    const esc = J.escapeHtml || (s => String(s ?? ''));
    const $   = id => document.getElementById(id);

    const POLL_MS = 15000;
    let timer = null;

    const active = () => { const el = $('tab-summary'); return !!(el && el.classList.contains('active')); };

    function start() {
        clearInterval(timer);
        render();
        if (J.loadBoard) J.loadBoard();
        timer = setInterval(() => { if (J.loadBoard) J.loadBoard(); }, POLL_MS);
    }

    function stop() {
        clearInterval(timer);
        timer = null;
    }

    window.startSummaryPolling = start;
    window.stopSummaryPolling  = stop;

    // ── Counting, when the web is older and sends no summary ─────────────
    function countFromRecords(rows) {
        const state = r => r.state || (r.working ? (r.ot_running ? 'ot' : 'working') : 'done');
        const n = f => rows.filter(f).length;
        return {
            working: n(r => r.working), on_site: rows.length,
            on_lunch: n(r => ['lunch', 'notback'].includes(state(r))), not_back: n(r => state(r) === 'notback'),
            done: n(r => state(r) === 'done'), overtime: n(r => Number(r.overtime_hours) > 0),
            overtime_hours: rows.reduce((a, r) => a + Number(r.overtime_hours || 0), 0),
            ot_now: n(r => r.ot_running), needs_review: n(r => r.needs_review), late: n(r => Number(r.late_minutes) > 0),
            day: n(r => !r.night), night: n(r => r.night), last_out: null,
            punches: { am_in: n(r => r.am_in), am_out: n(r => r.am_out), pm_in: n(r => r.pm_in), pm_out: n(r => r.pm_out),
                       am_out_auto: n(r => r.am_out_auto), am_in_span: null, pm_in_span: null },
        };
    }

    // ── Drawing ──────────────────────────────────────────────────────────
    function tile(cls, icon, num, label, sub) {
        const tone = num > 0 ? cls : (cls === 'info' || cls === 'ok' || cls === '' ? cls : 'zero');
        return `<div class="stile ${tone}"><span class="n">${num}</span>`
             + `<span class="l"><i class="fas fa-${icon}"></i>${esc(label)}</span><span class="s">${esc(sub || '')}</span></div>`;
    }

    function punch(cls, key, num, sub) {
        return `<div class="pc ${cls}"><div class="pc-k">${key}</div><div class="pc-n">${num}</div><div class="pc-s">${esc(sub || '')}</div></div>`;
    }

    function render() {
        if (!$('sum-tiles')) return;

        const board = J.board ? J.board() : { records: [] };
        const rows  = board.records || [];
        const s     = board.summary || countFromRecords(rows);
        const p     = s.punches || {};
        // v8: the shift running now, not always the day shift.
        const d     = J.currentShift ? J.currentShift() : (J.dayShift ? J.dayShift() : null);
        const isNight = !!(d && d.night);
        const fmt   = hm => (J.fmt12 && J.toMin) ? J.fmt12(J.toMin(hm)) : hm;
        const lateNames = rows.filter(r => Number(r.late_minutes) > 0).map(r => r.name.split(' ')[0]);
        const r = (J.rosterCounts ? J.rosterCounts() : null) || { pending: 0, enrolled: 0, total: 0 };

        $('sum-tiles').innerHTML = [
            tile('ok', 'person-digging', s.working, t('sum.working'), t('sum.ofonsite', { n: s.on_site })),
            tile('info', 'hard-hat', s.on_site, t('sum.onsite'), t('sum.daynight', { d: s.day, n: s.night })),
            tile('warn', isNight ? 'mug-hot' : 'utensils', s.on_lunch, t(isNight ? 'sum.brk' : 'sum.lunch'),
                 s.not_back ? t('sum.notback', { n: s.not_back }) : (d ? fmt(d.am_end) + ' – ' + fmt(d.pm_start) : '')),
            tile('', 'circle-check', s.done, t('sum.done'), s.last_out ? t('sum.lastout', { t: s.last_out }) : t('sum.nobody')),
            tile('warn', 'clock', s.overtime, t('sum.ot'), t('sum.othours', { h: Number(s.overtime_hours || 0).toFixed(2) })),
            tile('live', 'bolt', s.ot_now, t('sum.otnow'), d ? t('sum.since', { t: fmt(d.pm_end) }) : ''),
            tile('bad', 'triangle-exclamation', s.needs_review, t('sum.review'), t('sum.reviewsub')),
            tile('warn', 'hourglass-half', s.late, t(isNight ? 'sum.latenight' : 'sum.late'), lateNames.length ? lateNames.slice(0, 3).join(', ') + (lateNames.length > 3 ? '…' : '') : t('sum.ontime')),
            // The two counters that used to crowd the ENROLL list. They belong
            // with the other totals, and the list there is the longer for it.
            tile('warn', 'fingerprint', r.pending, t('sum.nofp'), t('sum.ofcrew', { n: r.total })),
            tile('ok', 'circle-check', r.enrolled, t('sum.hasfp'), t('sum.ofcrew', { n: r.total })),
        ].join('');

        const span = sp => sp ? sp.first + ' – ' + sp.last : '';
        $('sum-punches').innerHTML =
              punch('in', esc(isNight ? t('n.in1') : t('mon.amin').toUpperCase()), p.am_in || 0, span(p.am_in_span))
            + punch('out', esc(isNight ? t('n.out1') : t('mon.amout').toUpperCase()), p.am_out || 0, p.am_out_auto ? t('sum.auto', { n: p.am_out_auto }) : '')
            + `<div class="pc-gap"><span>${esc(t(isNight ? 'sum.breakgap' : 'sum.lunchgap', { t: d && J.short ? J.short(d.am_end) + '–' + J.short(d.pm_start) : '12' }))}</span></div>`
            + punch('in', esc(isNight ? t('n.in2') : t('mon.pmin').toUpperCase()), p.pm_in || 0, span(p.pm_in_span))
            + punch('out', esc(isNight ? t('n.out2') : t('mon.pmout').toUpperCase()), p.pm_out || 0, s.working ? t('sum.still', { n: s.working }) : '');

        $('sum-note').textContent = d ? t(isNight ? 'sum.notenight' : 'sum.note', {
            a: J.short(d.am_start) + '–' + J.short(d.am_end), b: J.short(d.am_end) + '–' + J.short(d.pm_start),
            c: J.short(d.pm_start) + '–' + J.short(d.pm_end) }) : '';

        if (J.stripHTML && d) $('sum-strip').innerHTML = J.stripHTML(d, false);

        const nightRows = rows.filter(r => r.night);
        const night = $('sum-night');
        if (nightRows.length) {
            const workingN = nightRows.filter(r => r.working).length;
            night.innerHTML = `<span class="shift-tag night">${esc(t('mon.night'))}</span>`
                + `<span><b>${esc(t('sum.night', { n: nightRows.length }))}</b> · `
                + esc(workingN ? t('sum.nightworking', { n: workingN }) : t('sum.nightdone')) + '</span>';
            night.hidden = false;
        } else {
            night.hidden = true;
        }

        renderAttention(rows);

        const at = board.loadedAt instanceof Date ? board.loadedAt.toLocaleTimeString('en-US') : '—';
        const ok = J.boardOk ? J.boardOk() : true;
        $('sum-foot').innerHTML = ok
            ? `<span>${esc(t('sum.updated', { t: at }))}</span><span>${esc(t('sum.same'))}</span>`
            : `<span class="bad">${esc(t('sum.offline'))}</span><span>${esc(t('sum.updated', { t: at }))}</span>`;
    }

    function renderAttention(rows) {
        const items = [];
        const hm = h => { const m = Math.round(Number(h || 0) * 60); return '+' + Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0'); };
        const shiftEnd = r => {
            const sh = r.night ? (J.nightShift && J.nightShift()) : (J.dayShift && J.dayShift());
            return sh ? J.fmt12(J.toMin(sh.pm_end)) : '';
        };

        rows.filter(r => r.ot_running).forEach(r => items.push(
            ['ot', 'bolt', r.name, t('sum.otsince', { t: shiftEnd(r) }), `<span class="attn-v">${hm(r.overtime_hours)}</span>`]));

        rows.filter(r => r.needs_review).forEach(r => {
            const auto = (r.entries || []).find(e => e.auto);
            items.push(['auto', 'robot', r.name,
                t('sum.autoclosed', { session: auto ? auto.session : 'AM', t: auto ? auto.out : '' }),
                `<span class="attn-v muted">${esc(t('sum.reviewb'))}</span>`]);
        });

        rows.filter(r => r.state === 'notback').forEach(r => items.push(
            ['home', 'utensils', r.name, t('sum.notbackd', { t: r.am_out || '' }), `<span class="attn-v muted">${esc(t('sum.notbackb'))}</span>`]));

        rows.filter(r => !r.night && r.state === 'done' && r.am_out && !r.pm_in && !r.am_out_auto).forEach(r => items.push(
            ['home', 'house', r.name, t('sum.leftearly', { t: r.am_out }), `<span class="attn-v muted">${esc(t('sum.halfday'))}</span>`]));

        rows.filter(r => Number(r.late_minutes) > 0).forEach(r => items.push(
            ['late', 'hourglass-half', r.name, t('sum.lated', { t: r.am_in || '', m: r.late_minutes }), `<span class="attn-v muted">+${r.late_minutes}m</span>`]));

        $('sum-attn-count').textContent = items.length;
        $('sum-attn').innerHTML = items.length
            ? items.map(([cls, icon, name, detail, value]) => `<div class="attn-row">
                    <span class="attn-ic ${cls}"><i class="fas fa-${icon}"></i></span>
                    <div><div class="attn-t">${esc(name)}</div><div class="attn-d">${esc(detail)}</div></div>${value}
                </div>`).join('')
            : `<div class="attn-empty"><i class="fas fa-circle-check"></i>${esc(t('sum.clear'))}</div>`;
    }

    window.addEventListener('boarddata', () => { if (active()) render(); });
    window.addEventListener('rostercounts', () => { if (active()) render(); });
    window.addEventListener('sitechanging', () => { if (active()) render(); });
    window.addEventListener('langchange', render);
    window.addEventListener('kiosksettings', render);
    window.addEventListener('DOMContentLoaded', render);
})();
