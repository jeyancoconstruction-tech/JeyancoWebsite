// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO KIOSK — ATTENDANCE TAB (v9)
//
//  Two ways to record, chosen on the web (System Settings → Kiosk):
//
//    BUTTONS    Press TIME IN or TIME OUT, then scan within 12 seconds.
//               A scan without a button records nothing.
//    AUTOMATIC  Just scan. The web decides IN or OUT from the worker's open
//               time in and the shift's clock (12:30 cut-off at lunch).
//
//  Either way the write goes through Flask /attendance (which adds the site)
//  to Laravel's recordClock — the same rules as always: the shift window,
//  AM in / AM out / PM in / PM out, re-time-in, AUTO close, overtime.
//
//  Also here: the board of who is on site (no totals — those are on the
//  SUMMARY tab), the detail modal, and the day strip.
//
//  v9:
//    • The time is the office's (script.js sets it from the web), not the
//      Pi's own clock, which is hours off after a boot without signal.
//    • A scan that lands while the last one is still saving is kept and
//      handled next — it used to be dropped without a word.
//    • A scan no longer waits for the whole board to reload first: the board
//      read in the last 20 seconds is used, and a reload waits 3 s at most.
// ─────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const J    = window.jeyanco || {};
    const t    = J.t || (k => k);
    const esc  = J.escapeHtml || (s => String(s ?? ''));
    const $    = id => document.getElementById(id);

    const SCAN_POLL_MS  = 350;
    const BOARD_POLL_MS = 15000;
    const ARM_SECONDS   = 12;
    const RESULT_MS     = { in: 4500, out: 4500, none: 5500, warn: 6500, rej: 6500 };

    // v9: how old the board may be and still stand in for a fresh one before a
    // scan, and how long a scan waits for a fresh one when it is older.
    const BOARD_FRESH_MS = 20000;
    const BOARD_WAIT_MS  = 3000;
    // v9: a scan kept while another was saving is handled after this pause,
    // so the worker before still sees their own result.
    const PENDING_GAP_MS = 1500;

    let polling   = false;
    let scanTimer = null, boardTimer = null, clockTimer = null;
    let inFlight  = false;     // a /scan request is out
    let busy      = false;     // a record is being written
    let pending   = null;      // v9: a result that arrived while busy
    let consumed  = true;      // this finger was handled; wait for it to lift
    let lastSeq   = null;      // the numbered result handled last (testing.py v4)
    let primed    = false;
    let scanningShown = false;
    let armed     = null;      // { type: 'time_in'|'time_out', until }
    let armTimer  = null;
    let resultTimer = null;
    let online    = null;
    let board     = { records: [] };
    let boardOk   = true;
    let boardAt   = 0;         // v9: when the board was read, by the Pi's own clock

    // ── Settings and the shift day ───────────────────────────────────────
    const settings = () => (J.settings ? J.settings() : { mode: 'buttons', shifts: [] });
    const isAuto   = () => settings().mode === 'auto';

    const FALLBACK_DAY = { name: 'Day', night: false, opens: '06:00', am_start: '08:00', am_end: '12:00',
                           pm_start: '13:00', pm_end: '17:00', cut: '12:30' };

    // v9: the office's time when script.js knows it, the Pi's otherwise.
    const nowDate = () => (J.now ? J.now() : new Date());

    const toMin = hm => { const p = String(hm || '0:0').split(':').map(Number); return (p[0] || 0) * 60 + (p[1] || 0); };
    const nowMin = () => { const d = nowDate(); return d.getHours() * 60 + d.getMinutes(); };

    function fmt12(min) {
        min = ((min % 1440) + 1440) % 1440;
        const h = Math.floor(min / 60), m = min % 60;
        return ((h % 12) || 12) + ':' + String(m).padStart(2, '0') + ' ' + (h >= 12 ? 'PM' : 'AM');
    }
    /** "8", "12", "12:30" — a tick label. */
    function short(hm) {
        const min = toMin(hm), h = Math.floor(min / 60) % 24, m = min % 60;
        return ((h % 12) || 12) + (m ? ':' + String(m).padStart(2, '0') : '');
    }
    /** "13:02:11" → "1:02 PM". */
    function clockLabel(hms) {
        if (!hms) return '';
        const p = String(hms).split(':').map(Number);
        return fmt12((p[0] || 0) * 60 + (p[1] || 0));
    }

    function dayShift()   { return (settings().shifts || []).find(s => !s.night) || FALLBACK_DAY; }
    function nightShift() { return (settings().shifts || []).find(s => s.night) || null; }

    /** The crew the kiosk is most likely serving now. */
    function currentShift() {
        const d = dayShift(), n = nightShift();
        if (!n) return d;
        const now = nowMin(), from = toMin(d.opens), to = toMin(d.pm_end) + 60;
        return (now >= from && now < to) ? d : n;
    }

    /** Minutes into a shift's day, counted from when TIME IN opens (crosses midnight). */
    function rel(sh, hm)  { return (toMin(hm) - toMin(sh.opens) + 1440) % 1440; }
    function relNow(sh)   { return (nowMin() - toMin(sh.opens) + 1440) % 1440; }

    /** v8: outside the day crew's hours (and more than an hour past its end). */
    function offShift(sh) { return !sh.night && relNow(sh) > rel(sh, sh.pm_end) + 60; }

    function halfNames(sh) { return sh.night ? [t('ds.first'), t('ds.second')] : ['AM', 'PM']; }

    /** The day as a strip: AM · lunch · PM · OT, the cut-off, and a "now" line. */
    function stripHTML(sh, withCut) {
        const span = rel(sh, sh.pm_end) + 60;
        const pct  = v => Math.max(0, Math.min(100, v / span * 100));
        const seg  = (cls, a, b, text) =>
            `<span class="ds-seg ${cls}" style="left:${pct(a).toFixed(3)}%;width:${Math.max(0, pct(b) - pct(a)).toFixed(3)}%">${esc(text)}</span>`;
        const [h1, h2] = halfNames(sh);

        let track = seg('work', rel(sh, sh.am_start), rel(sh, sh.am_end), `${h1} ${short(sh.am_start)}–${short(sh.am_end)}`)
                  + seg('lunch', rel(sh, sh.am_end), rel(sh, sh.pm_start), withCut ? '' : short(sh.am_end))
                  + seg('work', rel(sh, sh.pm_start), rel(sh, sh.pm_end), `${h2} ${short(sh.pm_start)}–${short(sh.pm_end)}`)
                  + seg('ot', rel(sh, sh.pm_end), span, 'OT');
        if (withCut) track += `<span class="ds-cut" style="left:${pct(rel(sh, sh.cut)).toFixed(3)}%"></span>`;
        const n = relNow(sh);
        if (n <= span) track += `<span class="ds-now" style="left:${pct(n).toFixed(3)}%"></span>`;

        const ticks = [[0, short(sh.opens), 'first'], [rel(sh, sh.am_start), short(sh.am_start), ''],
                       [rel(sh, sh.am_end), short(sh.am_end), ''], [rel(sh, sh.pm_start), short(sh.pm_start), ''],
                       [rel(sh, sh.pm_end), short(sh.pm_end), '']]
            .map(([v, text, cls]) => `<span class="${cls}" style="left:${pct(v).toFixed(3)}%">${esc(text)}</span>`).join('');

        // v8: past the day shift with no night crew set, say the day is over —
        // not "DAY SHIFT" as if it were still running.
        const title = !sh.night && offShift(sh) ? t('ds.dayover')
                    : (sh.night ? t('ds.night') : t('ds.day')) + ' · '
                    + t(sh.night ? 'ds.break' : 'ds.lunch', { t: short(sh.am_end) });
        const right = withCut ? `${esc(t('ds.cut'))} <b>${esc(fmt12(toMin(sh.cut)))}</b>`
                              : `${esc(t('ds.now'))} <b>${esc(fmt12(nowMin()))}</b>`;

        return `<div class="ds-head"><span>${esc(title)}</span><span>${right}</span></div>`
             + `<div class="ds-track">${track}</div><div class="ds-ticks">${ticks}</div>`;
    }

    // ── The idle screen ──────────────────────────────────────────────────
    const panel = () => $('att-panel');
    const resultShowing = () => !!(panel() && panel().classList.contains('has-result'));

    function sessionNow() {
        const sh = currentShift(), [h1, h2] = halfNames(sh);
        return relNow(sh) < rel(sh, sh.cut) ? h1 : h2;
    }

    function renderIdle() {
        if (!panel() || resultShowing() || busy) return;
        scanningShown = false;
        const auto = isAuto();
        const bar  = $('att-bar'), now = $('att-auto-now');

        if (auto) {
            J.setRing('att-ring', 'att-icon', 'auto');
            $('att-status-text').textContent = t('att.auto.place');
            $('att-hint').textContent = t('att.auto.hint');
            bar.hidden = true;
            renderAutoNow();
            now.hidden = false;
        } else if (armed) {
            J.setRing('att-ring', 'att-icon', armed.type === 'time_in' ? 'armed-in' : 'armed-out');
            $('att-status-text').textContent = t(armed.type === 'time_in' ? 'att.armed.in' : 'att.armed.out');
            now.hidden = true;
            bar.hidden = false;
            bar.classList.toggle('out', armed.type === 'time_out');
            updateArmCountdown();
        } else {
            J.setRing('att-ring', 'att-icon', 'idle');
            $('att-status-text').textContent = t('att.press');
            $('att-hint').textContent = t('att.thenscan');
            bar.hidden = true;
            now.hidden = true;
        }
    }

    function renderAutoNow() {
        const sh = currentShift(), s = sessionNow();
        const closed = !sh.night && relNow(sh) >= rel(sh, sh.pm_end) && relNow(sh) < rel(sh, sh.pm_end) + 60;
        $('an-k').textContent = t('an.now', { t: fmt12(nowMin()) });
        // Once TIME IN has closed, only the way out is left — showing an IN
        // line beside "TIME IN is closed" reads as a contradiction.
        $('an-in').parentElement.hidden = closed;
        $('an-in').textContent  = s + ' IN';
        $('an-in-t').textContent = t('an.notin');
        $('an-out').textContent = s + ' OUT';
        $('an-out-t').textContent = closed ? t('an.closed') : t('an.isin');
    }

    function applyMode() {
        const auto = isAuto();
        const p = panel();
        if (!p) return;
        p.classList.toggle('mode-auto', auto);
        p.classList.toggle('mode-buttons', !auto);

        const chip = $('att-mode-chip');
        chip.className = 'mode-chip' + (auto ? ' auto' : '');
        chip.innerHTML = auto ? `<i class="fas fa-bolt"></i><span>${esc(t('mode.auto'))}</span>`
                              : `<i class="fas fa-hand-pointer"></i><span>${esc(t('mode.buttons'))}</span>`;

        $('att-btns').hidden = auto;
        $('btn-time-in').innerHTML  = `<i class="fas fa-right-to-bracket"></i>${esc(t('btn.in'))}<small>${esc(t('btn.in.sub'))}</small>`;
        $('btn-time-out').innerHTML = `<i class="fas fa-right-from-bracket"></i>${esc(t('btn.out'))}<small>${esc(t('btn.out.sub'))}</small>`;
        $('att-cap').hidden  = auto;
        $('att-cap').textContent = t('att.cap');
        if (auto && armed) disarm(true);

        renderIdle();
        renderStrip();
        renderGroupHeads();
    }

    function renderStrip() {
        const el = $('att-strip');
        if (el) el.innerHTML = stripHTML(currentShift(), isAuto());
    }

    function renderGroupHeads() {
        // v8: the shift running now — a night crew reads 1ST HALF / 2ND HALF.
        const d = currentShift();
        const hm = m => fmt12(toMin(m)).replace(/ [AP]M$/, '');
        const [h1, h2] = d.night ? [t('grp.first'), t('grp.second')] : ['AM', 'PM'];
        $('grp-am').textContent = `${h1} · ${hm(d.am_start)}–${hm(d.am_end)}`;
        $('grp-pm').textContent = `${h2} · ${hm(d.pm_start)}–${hm(d.pm_end)}`;
        const gap = $('grp-gap');
        if (gap) gap.textContent = short(d.am_end);        // the lunch column: "12", or whatever is set
    }

    /**
     * What the header pill says, from the hours set on the web: AM until the
     * shift's lunch cut-off, PM after it, NIGHT for a crew that crosses
     * midnight. Outside the day crew's hours with no night crew set, null —
     * script.js falls back to the clock.
     */
    function pillMode() {
        const sh = currentShift();
        if (sh.night) return 'night';
        if (relNow(sh) > rel(sh, sh.pm_end) + 60) return null;
        return relNow(sh) < rel(sh, sh.cut) ? 'am' : 'pm';
    }

    function setOnline(on) {
        if (online === on) return;
        online = on;
        const dot = $('conn-dot'), lbl = $('conn-label');
        if (dot) dot.classList.toggle('offline', !on);
        if (lbl) lbl.textContent = t(on ? 'conn.online' : 'conn.offline');
    }

    // ── Buttons ──────────────────────────────────────────────────────────
    function arm(type) {
        if (isAuto() || busy) return;
        if (armed && armed.type === type) { disarm(); return; }   // a second press cancels
        armed = { type, until: Date.now() + ARM_SECONDS * 1000 };
        clearResult();
        paintButtons();
        renderIdle();
        clearInterval(armTimer);
        armTimer = setInterval(() => {
            if (!armed) return;
            if (Date.now() >= armed.until) { disarm(); return; }
            updateArmCountdown();
        }, 200);
    }

    function disarm(silent) {
        armed = null;
        clearInterval(armTimer); armTimer = null;
        paintButtons();
        if (!silent) renderIdle();
    }

    function updateArmCountdown() {
        if (!armed) return;
        const left = Math.max(0, armed.until - Date.now());
        $('att-hint').textContent = t('att.within', { session: sessionNow() + ' SESSION', n: Math.ceil(left / 1000) });
        const fill = $('att-bar-fill');
        if (fill) fill.style.width = (left / (ARM_SECONDS * 1000) * 100).toFixed(1) + '%';
    }

    function paintButtons() {
        const bin = $('btn-time-in'), bout = $('btn-time-out');
        if (!bin || !bout) return;
        bin.classList.toggle('on', !!armed && armed.type === 'time_in');
        bout.classList.toggle('on', !!armed && armed.type === 'time_out');
        bin.classList.toggle('dim', !!armed && armed.type !== 'time_in');
        bout.classList.toggle('dim', !!armed && armed.type !== 'time_out');
    }

    function glowButtons() {
        const bin = $('btn-time-in'), bout = $('btn-time-out');
        [bin, bout].forEach(b => b && b.classList.add('glow'));
        setTimeout(() => [bin, bout].forEach(b => b && b.classList.remove('glow')), 3000);
    }

    // ── The scan ─────────────────────────────────────────────────────────
    async function pollScan() {
        if (!polling || inFlight) return;
        inFlight = true;
        try {
            const d = await J.parseJSON(await J.flaskFetch('/scan', {}, 2500));
            setOnline(true);
            handleScan(d || {});
        } catch (e) {
            setOnline(false);
        } finally {
            inFlight = false;
        }
    }

    function handleScan(d) {
        const st     = d.status;
        const hasSeq = d.seq !== undefined && d.seq !== null;

        // The first answer after this tab opens only says where the sensor
        // is: a result already on it was meant for another tab.
        if (!primed) {
            primed = true;
            if (hasSeq) { lastSeq = d.seq; return; }
        }

        // A finger lifted: the next touch is a new scan.
        if (!st || st === 'no_finger') {
            consumed = false;
            if (scanningShown && !busy && !resultShowing()) renderIdle();
            return;
        }

        if (st === 'scanning') {
            if (!busy && !resultShowing()) {
                scanningShown = true;
                J.setRing('att-ring', 'att-icon', 'scanning');
                $('att-status-text').textContent = t('att.scanning');
                $('att-hint').textContent = t('att.hold');
            }
            return;
        }

        // Still writing the last one. v9: a numbered result that lands now is
        // kept and handled once the save is done. It used to wait on the Pi
        // for a later poll — and was lost when the save took longer than the
        // Pi keeps a result (about 2 s): nothing recorded, nothing shown.
        if (busy) {
            if (hasSeq && d.seq !== lastSeq) pending = d;
            return;
        }

        if (hasSeq) {
            // testing.py (v4) numbers every result: each touch is handled
            // exactly once, even when the finger lifts faster than we poll.
            if (d.seq === lastSeq) return;
            lastSeq = d.seq;
        } else if (consumed) {
            return;           // an older service: wait for the finger to lift
        }
        consumed = true;

        if (st === 'not_found') {
            showResult({ kind: 'rej', verb: t('att.notfound'), notes: [['bad', t('att.notenrol')]] });
            return;
        }
        if (st === 'success' && d.employee && d.employee.id) {
            onKnownScan(d.employee);
            return;
        }
        if (d.employee && d.employee.id) {
            // Known, but the web turned the scan away (e.g. not registered yet).
            showResult({ kind: 'rej', verb: t('bdg.rejected'), employee: d.employee, notes: [['bad', d.message || '']] });
            return;
        }
        showResult({ kind: 'rej', verb: t('att.servererr'), notes: [['bad', d.message || t('att.checkconn')]] });
    }

    /** v9: the save is done — handle a scan that was kept meanwhile. */
    function doneSaving() {
        busy = false;
        if (!pending) return;
        const d = pending;
        pending = null;
        setTimeout(() => {
            if (!polling) return;
            if (busy) { pending = pending || d; return; }
            handleScan(d);
        }, PENDING_GAP_MS);
    }

    async function onKnownScan(emp) {
        const auto = isAuto();

        if (!auto && !armed) {
            showResult({ kind: 'none', verb: t('att.pressfirst.t'), employee: emp, notes: [['info', t('att.pressfirst')]] });
            glowButtons();
            return;
        }

        let type = auto ? 'auto' : armed.type;
        if (!auto) disarm(true);          // one press is one record

        busy = true;
        clearResult();
        J.setRing('att-ring', 'att-icon', 'scanning');
        $('att-status-text').textContent = t('att.processing');
        $('att-hint').textContent = t('att.saving');
        $('att-bar').hidden = true;
        $('att-auto-now').hidden = true;

        // v8: one IN and one OUT per session — checked against today's record
        // before anything is sent to the web.
        let verdict = {};
        try { verdict = await sessionGuard(emp, type); } catch (e) { verdict = {}; }
        if (verdict.block) {
            busy = false;
            showResult(Object.assign({ employee: emp }, verdict.block));
            if (verdict.glowOut) glowOut();
            doneSaving();
            return;
        }
        if (verdict.type) type = verdict.type;

        let res = null;
        try {
            res = await record(emp.id, type);
        } catch (e) {
            res = null;
        }
        busy = false;

        if (!res) {
            showResult({ kind: 'rej', verb: t('att.servererr'), employee: emp, notes: [['bad', t('att.checkconn')]] });
            doneSaving();
            return;
        }

        renderRecord(res, emp);
        if (res.success) setTimeout(loadBoard, 300);
        if (res.code === 'mode_buttons' && J.reloadSettings) J.reloadSettings();
        doneSaving();
    }

    // ── v8: one IN, one OUT per session ──────────────────────────────────
    //   AM IN → AM OUT → PM IN → PM OUT, each once.
    //   • AM IN again after AM OUT        → "AM DONE"
    //   • PM IN while AM is still open    → "TIME OUT AM FIRST"
    //   • anything IN after PM OUT        → "DONE FOR TODAY"
    // A night crew is the same with 1ST HALF / 2ND HALF.
    // Read from today's board just before recording. If the board cannot be
    // read, the scan goes through as before — a network hiccup must never
    // stop a worker from timing in; the web's own rules still apply.

    function rowFor(emp) {
        const rows = board.records || [];
        const byId = rows.filter(r => (r.employee_id != null ? r.employee_id : r.id) === emp.id);
        if (byId.length) return byId[0];
        const nm = String(emp.name || '').trim().toLowerCase();
        const byName = rows.filter(r => String(r.name || '').trim().toLowerCase() === nm);
        return byName.length === 1 ? byName[0] : null;
    }

    /** { open, done, inAt, outAt } for one session of a board row. */
    function sessionState(r, sess) {
        const list = (r.entries || []).filter(e => e.session === sess);
        if (list.length) {
            const last = list[list.length - 1];
            return { any: true, open: !last.out, done: !!last.out, inAt: list[0].in || '', outAt: last.out || '' };
        }
        const i = sess === 'AM' ? r.am_in : r.pm_in, o = sess === 'AM' ? r.am_out : r.pm_out;
        return { any: !!i, open: !!i && !o, done: !!i && !!o, inAt: i || '', outAt: o || '' };
    }

    // v9: a DAY worker cannot TIME IN outside the day shift. Before, nothing
    // on the kiosk checked the clock at all — a day worker scanning at night
    // was sent to the web as a normal time in and landed in the PM session,
    // where it counted for 0 hours. TIME IN is open from `opens` to `pm_end`.
    function isNightWorker(emp) {
        if (emp.shift) return !!emp.shift.crosses_midnight;
        return null;                                          // unknown
    }

    function offShiftBlock(emp) {
        const night = isNightWorker(emp);
        if (night === true) return null;                      // the night crew has its own hours
        if (night === null && nightShift()) return null;      // cannot tell — the web decides
        const sh = dayShift();
        // rel() counts from `opens` and wraps at midnight, so anything past
        // pm_end — evening, after midnight, or before `opens` — is outside.
        if (relNow(sh) <= rel(sh, sh.pm_end)) return null;
        return {
            kind: 'rej', verb: t('att.wrongshift.t'),
            notes: [['bad', t('att.wrongshift', {
                shift: (emp.shift && emp.shift.name) || sh.name || 'Day',
                a: fmt12(toMin(sh.opens)), b: fmt12(toMin(sh.pm_end)) })]],
        };
    }

    /** v9: the board is recent enough to stand in for a fresh read. */
    const boardFresh = () => boardOk && boardAt > 0 && (Date.now() - boardAt) < BOARD_FRESH_MS;

    async function sessionGuard(emp, type) {
        const offBlock = offShiftBlock(emp);
        // A pressed TIME IN outside the shift: refuse at once, nothing is sent.
        if (offBlock && type === 'time_in') return { block: offBlock };

        // v9: the board is reloaded every 15 s and straight after every scan
        // here, so it is nearly always fresh already. Only an older one is
        // re-read — and never waited on for more than a few seconds. Before,
        // every scan re-read it first: up to 20 s on a weak signal before the
        // time in was even sent.
        if (!boardFresh()) {
            await Promise.race([
                loadBoard(true).catch(() => {}),
                new Promise(r => setTimeout(r, BOARD_WAIT_MS)),
            ]);
        }
        if (!boardFresh()) return {};
        const r = rowFor(emp);

        // Automatic mode outside the shift: only a way OUT is allowed (someone
        // still inside from the afternoon). With nothing open, it would be a
        // time in — refuse it.
        if (offBlock && type === 'auto') {
            const open = r && (sessionState(r, 'AM').open || sessionState(r, 'PM').open);
            if (!open) return { block: offBlock };
            return { type: 'time_out' };
        }

        if (!r) return {};                                   // nothing yet today

        const night = !!(r.night || (emp.shift && emp.shift.crosses_midnight));
        const sh    = night ? (nightShift() || currentShift()) : dayShift();
        const nowPM = relNow(sh) >= rel(sh, sh.cut);
        const L1    = night ? t('grp.first') : 'AM';
        const L2    = night ? t('grp.second') : 'PM';
        const am = sessionState(r, 'AM'), pm = sessionState(r, 'PM');
        const wantsIn = type === 'time_in' || (type === 'auto' && !am.open && !pm.open);

        if (pm.done && (wantsIn || type === 'time_in')) {
            return { block: { kind: 'none', verb: t('att.daydone.t'), notes: [['info', t('att.daydone')]] } };
        }
        if (am.open && nowPM) {
            if (type === 'time_in') {
                return { glowOut: true, block: { kind: 'warn', verb: t('att.amopen.t', { s: L1 }),
                         notes: [['bad', t('att.amopen', { s: L1, t: am.inAt })]] } };
            }
            // Automatic mode: the open AM is closed first; the PM needs a second scan.
            if (type === 'auto') return { type: 'time_out' };
        }
        if (wantsIn && !nowPM && am.done) {
            return { block: { kind: 'none', verb: t('att.sessdone.t', { s: L1 }),
                     notes: [['info', t('att.sessdone', { s: L1, a: am.inAt, b: am.outAt, n: night ? t('att.secondhalf') : t('att.afternoon') })]] } };
        }
        return {};
    }

    function glowOut() {
        const b = $('btn-time-out');
        if (!b) return;
        b.classList.add('glow');
        setTimeout(() => b.classList.remove('glow'), 4000);
    }

    async function record(employeeId, type) {
        const body = { employee_id: employeeId, type };
        const opts = { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } };

        let res = await J.flaskFetch('/attendance', Object.assign({}, opts, { body: JSON.stringify(body) }), 15000).catch(() => null);
        if (!res || J.isMissingRoute(res)) {
            res = await J.apiFetch('/attendance',
                Object.assign({}, opts, { body: JSON.stringify(Object.assign({}, body, J.siteStamp())) }), 15000);
        }
        return J.parseJSON(res);
    }

    // ── What the web said ────────────────────────────────────────────────
    function renderRecord(r, emp) {
        const who   = r.employee || emp;
        const night = !!(who.shift && who.shift.crosses_midnight);

        if (r.success) {
            const inn   = r.type === 'time_in';
            const sess  = r.session || '';
            const verb  = (night ? '' : sess + ' ') + (inn ? 'IN' : 'OUT');
            const at    = clockLabel(inn ? r.attendance && r.attendance.time_in : r.attendance && r.attendance.time_out)
                       || fmt12(nowMin());
            const label = night ? t('sess.n.' + sess) : t('sess.' + sess);
            const notes = [];

            if (r.auto_closed) notes.push(['warn', t('att.autoclosed', { session: r.auto_closed.session, t: r.auto_closed.at })]);
            if (r.paid_from) notes.push(['good', t('att.paidfrom', { t: r.paid_from })]);
            if (r.ot_hours) notes.push(['warn', t('att.ot', { h: r.ot_hours, a: r.ot_from, b: r.ot_to })]);
            if (!inn && sess === 'AM' && !night) {
                const d = dayShift();
                if (nowMin() < toMin(d.pm_start)) notes.push(['info', t('att.lunch', { a: short(d.am_end), b: short(d.pm_start) })]);
            }

            showResult({
                kind: inn ? 'in' : 'out', verb, verbSub: inn ? t('res.in') : t('res.out'),
                employee: who, time: at, where: esc(label), notes,
            });
            return;
        }

        const map = {
            already_in: ['warn', 'att.already.t'], no_open: ['warn', 'att.noopen.t'], just_timed_in: ['warn', 'att.justin.t'],
            repeat: ['none', 'att.repeat.t'], wrong_shift: ['rej', 'att.wrongshift.t'], not_registered: ['rej', 'att.notreg.t'],
            mode_buttons: ['none', 'att.modebtn.t'], no_gps: ['rej', 'att.gps.t'], outside_location: ['rej', 'att.gps.t'],
            // v10: the worker is assigned to another site, or the kiosk has none.
            wrong_site: ['rej', 'att.site.t'], no_site: ['rej', 'att.nosite.t'], no_site_location: ['rej', 'att.gps.t'],
        };
        const [kind, key] = map[r.code] || ['rej', 'bdg.rejected'];

        let msg = r.message || (r.errors ? Object.values(r.errors).flat().join(' ') : '') || t('att.retry');
        if (r.code === 'already_in')    msg = t('att.already', { t: r.since });
        if (r.code === 'no_open')       msg = t('att.noopen');
        if (r.code === 'wrong_shift')   msg = t('att.wrongshift', { shift: r.shift, a: r.opens, b: r.closes });
        if (r.code === 'just_timed_in') msg = t('att.justin', { t: r.since });
        if (r.code === 'repeat')        msg = t('att.repeat', { what: r.last === 'time_out' ? 'TIME OUT' : 'TIME IN', t: r.since });
        if (r.code === 'mode_buttons')  msg = t('att.modebtn');

        showResult({ kind, verb: t(key), employee: who, notes: [[kind === 'rej' ? 'bad' : 'info', msg]] });
    }

    function showResult(o) {
        const p = panel();
        if (!p) return;
        clearTimeout(resultTimer);

        const icons = { in: 'right-to-bracket', out: 'right-from-bracket', none: 'hand', warn: 'triangle-exclamation', rej: 'circle-xmark' };
        $('att-result').className = 'scan-result ' + o.kind;
        $('sr-verb').innerHTML = `<i class="fas fa-${icons[o.kind] || 'circle-info'}"></i>${esc(o.verb)}`
                               + (o.verbSub ? `<small>· ${esc(o.verbSub)}</small>` : '');

        const who = $('sr-who');
        if (o.employee && o.employee.name) {
            $('sr-name').textContent = o.employee.name;
            const shift = o.employee.shift ? ' · ' + t('shift.of', { shift: o.employee.shift.name }) : '';
            $('sr-sub').textContent = (o.employee.position || t('worker')) + shift;
            who.hidden = false;
        } else {
            who.hidden = true;
        }

        const time = $('sr-time');
        if (o.time) {
            $('sr-time-v').textContent = o.time;
            $('sr-time-s').innerHTML = o.where || '';
            time.hidden = false;
        } else {
            time.hidden = true;
        }

        const noteIcon = { good: 'circle-check', info: 'circle-info', bad: 'circle-xmark', warn: 'triangle-exclamation' };
        // Two notes at most: the card must fit the box it shares with the ring.
        $('sr-notes').innerHTML = (o.notes || []).filter(n => n[1]).slice(0, 2).map(([cls, text]) =>
            `<div class="result-note ${cls === 'warn' ? '' : cls}"><i class="fas fa-${noteIcon[cls] || 'circle-info'}"></i><span>${esc(text)}</span></div>`
        ).join('');

        p.classList.add('has-result');

        const ms = RESULT_MS[o.kind] || 5000;
        const fill = $('sr-bar');
        fill.style.transition = 'none';
        fill.style.width = '100%';
        void fill.offsetWidth;                      // restart the countdown
        fill.style.transition = `width ${ms}ms linear`;
        fill.style.width = '0%';

        resultTimer = setTimeout(clearResult, ms);
    }

    function clearResult() {
        clearTimeout(resultTimer);
        resultTimer = null;
        const p = panel();
        if (p) p.classList.remove('has-result');
        renderIdle();             // never leave "PROCESSING…" behind
    }

    // ── The board ────────────────────────────────────────────────────────
    // v7: every site change starts a new "generation". An answer that was
    // already on its way for the old site is thrown away, not drawn.
    let boardInFlight = null, boardInFlightGen = -1, boardGen = 0;

    function loadBoard(force) {
        if (force === true) boardGen++;
        if (boardInFlight && boardInFlightGen === boardGen) return boardInFlight;
        const gen = boardGen;
        boardInFlightGen = gen;
        const job = (async () => {
            try {
                let res = await J.flaskFetch('/today-attendance', { headers: { 'Accept': 'application/json' } }, 10000).catch(() => null);
                if (!res || J.isMissingRoute(res)) {
                    const qs = new URLSearchParams(J.siteStamp()).toString();
                    res = await J.apiFetch('/today-attendance' + (qs ? '?' + qs : ''), { headers: { 'Accept': 'application/json' } }, 10000);
                }
                const data = await J.parseJSON(res);
                if (gen !== boardGen) return;
                if (!data || data.success === false || !Array.isArray(data.records)) throw new Error(data && data.message || 'bad board');
                board = data;
                board.loadedAt = nowDate();
                boardAt = Date.now();
                boardOk = true;
                renderBoard();
            } catch (e) {
                if (gen !== boardGen) return;
                boardOk = false;
                if (!board.records.length) renderBoardMessage(t('mon.failed'));
            } finally {
                if (boardInFlight === job) boardInFlight = null;
                if (gen === boardGen) window.dispatchEvent(new CustomEvent('boarddata', { detail: { ok: boardOk } }));
            }
        })();
        boardInFlight = job;
        return job;
    }

    function renderBoardMessage(text) {
        $('monitor-body').innerHTML = `<tr class="mon-empty"><td colspan="9"><i class="fas fa-hard-hat"></i>${esc(text)}</td></tr>`;
    }

    function cell(v, auto) {
        if (!v) return '<span class="t-dash">—</span>';
        const m = String(v).match(/^(\d{1,2}:\d{2})\s*([AP]M)?$/i);
        const inner = m ? `${esc(m[1])}<small>${esc((m[2] || '').toUpperCase())}</small>` : esc(v);
        return `<span class="t-val${auto ? ' auto' : ''}">${inner}</span>`
             + (auto ? '<span class="auto-tag">AUTO</span>' : '');   // v8: one IN per session — no more ×2
    }

    function badge(state) {
        switch (state) {
            case 'working': return `<span class="st-badge st-working"><span class="st-pulse"></span>${esc(t('mon.workingb'))}</span>`;
            case 'ot':      return `<span class="st-badge st-ot"><span class="st-pulse"></span>${esc(t('mon.otb'))}</span>`;
            case 'lunch':   return `<span class="st-badge st-lunch">${esc(t('mon.lunchb'))}</span>`;
            case 'notback': return `<span class="st-badge st-notback">${esc(t('mon.notbackb'))}</span>`;
            default:        return `<span class="st-badge st-done">${esc(t('mon.doneb'))}</span>`;
        }
    }

    function stateOf(r) { return r.state || (r.working ? (r.ot_running ? 'ot' : 'working') : 'done'); }

    function renderBoard() {
        const rows = board.records || [];
        if (!rows.length) { renderBoardMessage(t(board.loadedAt ? 'mon.none' : (boardOk ? 'mon.loading' : 'mon.failed'))); return; }

        $('monitor-body').innerHTML = rows.map((r, i) => {
            const tag = r.night ? `<span class="shift-tag night">${esc(t('mon.night'))}</span>`
                                : `<span class="shift-tag day">${esc(t('mon.day'))}</span>`;
            const ot  = Number(r.overtime_hours) > 0
                ? `<span class="ot-badge${r.ot_running ? ' ot-live' : ''}">${r.ot_running ? '<span class="ot-pulse"></span>' : ''}${Number(r.overtime_hours).toFixed(2)}</span>`
                : '<span class="t-dash">—</span>';
            return `<tr class="${r.working ? 'row-working' : ''}" data-i="${i}">
                <td class="col-emp"><div class="mon-name${r.pending ? ' pending' : ''}">${esc(r.name)}${tag}</div>
                    <div class="mon-sub">${esc(r.pending ? t('mon.pending') : (r.position || ''))}</div></td>
                <td>${cell(r.am_in, false)}</td>
                <td>${cell(r.am_out, r.am_out_auto)}</td>
                <td class="c-gap"></td>
                <td>${cell(r.pm_in, false)}</td>
                <td>${cell(r.pm_out, r.pm_out_auto)}</td>
                <td class="t-total c-paid">${Number(r.total_hours || 0).toFixed(2)}</td>
                <td class="c-ot">${ot}</td>
                <td class="c-st">${badge(stateOf(r))} <i class="fas fa-chevron-right mon-chev"></i></td>
            </tr>`;
        }).join('');
    }

    // ── Detail modal ─────────────────────────────────────────────────────
    let overlay = null, modalTimer = null;

    function ensureModal() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'emp-modal-overlay';
        overlay.innerHTML = '<div class="emp-modal" role="dialog" aria-modal="true"></div>';
        if (J.dragScroll) J.dragScroll(overlay.querySelector('.emp-modal'));
        overlay.addEventListener('click', e => { if (e.target === overlay || e.target.closest('.emp-modal-close')) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
        document.body.appendChild(overlay);
        return overlay;
    }

    function openModal(r) {
        const box = ensureModal().querySelector('.emp-modal');
        const state = stateOf(r);
        const working = state === 'working' || state === 'ot';
        const entries = r.entries || [];
        const sessionBox = (sess, title) => {
            const list = entries.filter(e => e.session === sess);
            const body = list.length ? list.map(e => `<div class="emp-ent">
                    <span class="in">${esc(e.in || '—')}</span><span class="ar">→</span>
                    ${e.out ? `<span class="out${e.auto ? ' auto' : ''}">${esc(e.out)}</span>${e.auto ? '<span class="auto-tag">AUTO</span>' : ''}`
                            : `<span class="open">${esc(t('modal.inside'))}</span>`}
                </div>`).join('') : `<div class="emp-ent"><span class="none">${esc(t('modal.none'))}</span></div>`;
            return `<div class="emp-sbox"><div class="emp-sbox-h"><span>${esc(title)}</span></div>${body}</div>`;
        };
        const late = Number(r.late_minutes) > 0 ? '+' + r.late_minutes + ' min' : t('modal.ontime');

        box.innerHTML = `
            <div class="emp-modal-head">
                <div class="emp-modal-avatar"><i class="fas fa-user"></i></div>
                <div class="emp-modal-id">
                    <div class="emp-modal-name">${esc(r.name)}</div>
                    <div class="emp-modal-pos">${esc(r.position || '')}${r.shift ? ' · ' + esc(t('modal.shift', { shift: r.shift.name })) : ''}</div>
                </div>
                <button class="emp-modal-close" type="button" aria-label="Close">&times;</button>
            </div>
            <span class="emp-modal-status ${working ? 'working' : 'done'}">
                ${working ? '<span class="st-pulse"></span>' : ''}${esc(badgeText(state))}${r.since && working ? ' · ' + esc(t('mon.since')) + ' ' + esc(r.since) : ''}
            </span>
            <div class="emp-sessions">
                ${sessionBox('AM', r.night ? t('modal.n1') : t('modal.am'))}
                ${sessionBox('PM', r.night ? t('modal.n2') : t('modal.pm'))}
            </div>
            ${r.needs_review ? `<p class="result-note" style="margin:10px 20px 0"><i class="fas fa-triangle-exclamation"></i><span>${esc(t('modal.auto'))}</span></p>` : ''}
            <div class="emp-modal-totals three">
                <div class="emp-total hours"><div class="emp-total-lbl">${esc(t('modal.paid'))}</div><div class="emp-total-val">${Number(r.total_hours || 0).toFixed(2)}</div></div>
                <div class="emp-total ot"><div class="emp-total-lbl">${esc(t('modal.ot'))}</div><div class="emp-total-val">${Number(r.overtime_hours || 0).toFixed(2)}</div></div>
                <div class="emp-total"><div class="emp-total-lbl">${esc(t('modal.late'))}</div><div class="emp-total-val" style="font-size:1.1rem">${esc(late)}</div></div>
            </div>
            <div class="emp-modal-site"><i class="fas fa-location-dot"></i>${esc(J.activeSite && J.activeSite() ? J.activeSite().name : '')}</div>`;

        requestAnimationFrame(() => overlay.classList.add('open'));
        clearTimeout(modalTimer);
        modalTimer = setTimeout(closeModal, 20000);   // never left open at the gate
    }

    function badgeText(state) {
        return { working: t('mon.workingb'), ot: t('mon.otb'), lunch: t('mon.lunchb'), notback: t('mon.notbackb') }[state] || t('mon.doneb');
    }

    function closeModal() {
        clearTimeout(modalTimer);
        if (overlay) overlay.classList.remove('open');
    }

    // ── Start / stop (called by switchTab in script.js) ──────────────────
    function start() {
        if (polling) return;
        polling  = true;
        consumed = true;      // a finger already on the sensor belongs to another tab
        primed   = false;
        online   = null;
        pending  = null;
        clearInterval(scanTimer);
        scanTimer = setInterval(pollScan, SCAN_POLL_MS);
        clearInterval(boardTimer);
        boardTimer = setInterval(loadBoard, BOARD_POLL_MS);
        loadBoard();
        applyMode();
    }

    function stop() {
        polling = false;
        pending = null;
        clearInterval(scanTimer); scanTimer = null;
        clearInterval(boardTimer); boardTimer = null;
        if (armed) disarm(true);
        clearResult();
        closeModal();
    }

    window.startAttendancePolling = start;
    window.stopAttendancePolling  = stop;

    // Shared with kiosk-summary.js.
    Object.assign(J, {
        loadBoard, board: () => board, boardOk: () => boardOk,
        stripHTML, dayShift, nightShift, currentShift, fmt12, toMin, short, pillMode, offShift,
    });

    window.addEventListener('DOMContentLoaded', () => {
        $('btn-time-in').addEventListener('click', () => arm('time_in'));
        $('btn-time-out').addEventListener('click', () => arm('time_out'));
        $('monitor-body').addEventListener('click', e => {
            const tr = e.target.closest('tr[data-i]');
            if (tr && board.records[+tr.dataset.i]) openModal(board.records[+tr.dataset.i]);
        });

        // The strip's "now" line and the automatic hint move with the clock.
        clockTimer = setInterval(() => {
            renderStrip();
            renderGroupHeads();
            if (window.jeyanco && typeof window.updateSessionPill === 'function') window.updateSessionPill();
            if (isAuto() && !resultShowing() && !busy) renderAutoNow();
        }, 30000);

        applyMode();
    });

    // New hours or a new mode from the web: redraw at once, and re-read the
    // board so LUNCH / NOT BACK follow the new lunch. A result on screen is
    // left alone — renderIdle waits for it to clear.
    window.addEventListener('kiosksettings', () => {
        applyMode();
        if (polling) loadBoard();
    });
    // v7: a new site was tapped. Empty the board at once, and read the new
    // site's board the moment the Pi has it — not on the next 15 s refresh.
    window.addEventListener('sitechanging', () => {
        boardGen++;
        board = { records: [] };
        boardOk = true;
        boardAt = 0;
        closeModal();
        renderBoardMessage(t('mon.loading'));
        const wrap = document.querySelector('.monitor-table-wrap');
        if (wrap) wrap.scrollTop = 0;
        window.dispatchEvent(new CustomEvent('boarddata', { detail: { ok: true } }));
    });
    window.addEventListener('sitechange', () => {
        loadBoard(true);
        if (polling) {                      // restart the 15 s clock from now
            clearInterval(boardTimer);
            boardTimer = setInterval(loadBoard, BOARD_POLL_MS);
        }
    });

    window.addEventListener('langchange', () => {
        applyMode();
        renderBoard();
        const lbl = $('conn-label');
        if (lbl) lbl.textContent = t(online === false ? 'conn.offline' : online ? 'conn.online' : 'conn.checking');
    });
})();
