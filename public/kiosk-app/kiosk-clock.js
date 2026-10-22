// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO KIOSK — ATTENDANCE TAB (v15, 2026-10-22)
//
//  The crew list is the screen, a worker icon on every row; under it one
//  line of words — no scanner on screen until a finger is on the sensor.
//
//    1. A worker puts a finger on the sensor.
//    2. A card opens with their name and where they stand today, and two
//       big buttons: TIME IN / TIME OUT (the likely one is outlined). It
//       closes by itself after a few seconds if nothing is pressed.
//    3. The press is recorded with the scan token the web gave that finger
//       read, at the server's own time, and the card shows what was saved.
//
//  AUTOMATIC mode (System Settings → Kiosk) skips step 2: the scan alone
//  records, and the card shows the answer.
//
//  The rules are unchanged: one IN and one OUT per session, the shift's
//  hours, the site, AUTO close — checked here first and by the web always.
// ─────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const J   = window.jeyanco || {};
    const t   = J.t || (k => k);
    const esc = J.escapeHtml || (s => String(s ?? ''));
    const $   = id => document.getElementById(id);

    const SCAN_POLL_MS   = 350;
    const BOARD_POLL_MS  = 15000;
    const CHOOSE_SECONDS = 12;
    const RESULT_MS      = { in: 4500, out: 4500, none: 5500, warn: 6500, rej: 6500 };
    const BOARD_FRESH_MS = 20000;
    const BOARD_WAIT_MS  = 3000;
    const PENDING_GAP_MS = 1200;

    let polling   = false;
    let scanTimer = null, boardTimer = null;
    let inFlight  = false;
    let busy      = false;      // a record is being written
    let pending   = null;       // a scan that landed while busy
    let consumed  = true;
    let lastSeq   = null;
    let primed    = false;
    let scanningShown = false;
    let pop       = null;       // { mode: 'choose'|'busy'|'result', emp, token }
    let popTimer  = null, barTimer = null;
    let board     = { records: [] };
    let boardOk   = true;
    let boardAt   = 0;
    let justId    = null, justUntil = 0;

    // ── Settings and the shift day ───────────────────────────────────────
    const settings = () => (J.settings ? J.settings() : { mode: 'buttons', shifts: [] });
    const isAuto   = () => settings().mode === 'auto';

    const FALLBACK_DAY = { name: 'Day', night: false, opens: '06:00', am_start: '08:00', am_end: '12:00',
                           pm_start: '13:00', pm_end: '17:00', cut: '12:30' };

    const nowDate = () => (J.now ? J.now() : new Date());
    const synced  = () => (J.clockSynced ? J.clockSynced() : true);
    const toMin   = hm => { const p = String(hm || '0:0').split(':').map(Number); return (p[0] || 0) * 60 + (p[1] || 0); };
    const nowMin  = () => { const d = nowDate(); return d.getHours() * 60 + d.getMinutes(); };

    function fmt12(min) {
        min = ((min % 1440) + 1440) % 1440;
        const h = Math.floor(min / 60), m = min % 60;
        return ((h % 12) || 12) + ':' + String(m).padStart(2, '0') + ' ' + (h >= 12 ? 'PM' : 'AM');
    }
    function short(hm, ap) {
        const min = toMin(hm), h = Math.floor(min / 60) % 24, m = min % 60;
        return ((h % 12) || 12) + (m ? ':' + String(m).padStart(2, '0') : '') + (ap ? (h >= 12 ? ' PM' : ' AM') : '');
    }
    function clockLabel(hms) {
        if (!hms) return '';
        const p = String(hms).split(':').map(Number);
        return fmt12((p[0] || 0) * 60 + (p[1] || 0));
    }

    function dayShift()   { return (settings().shifts || []).find(s => !s.night) || FALLBACK_DAY; }
    function nightShift() { return (settings().shifts || []).find(s => s.night) || null; }
    function currentShift() {
        const d = dayShift(), n = nightShift();
        if (!n) return d;
        const now = nowMin(), from = toMin(d.opens), to = toMin(d.pm_end) + 60;
        return (now >= from && now < to) ? d : n;
    }
    function rel(sh, hm) { return (toMin(hm) - toMin(sh.opens) + 1440) % 1440; }
    function relNow(sh)  { return (nowMin() - toMin(sh.opens) + 1440) % 1440; }

    function sessionLabel(sh) {
        sh = sh || currentShift();
        const first = relNow(sh) < rel(sh, sh.cut);
        if (!sh.night) return t(first ? 'sess.AM' : 'sess.PM');
        const [a, b] = first ? [sh.am_start, sh.am_end] : [sh.pm_start, sh.pm_end];
        return short(a, true) + ' – ' + short(b, true);
    }

    /** The header pill: AM / PM / NIGHT from the web's hours; null outside them. */
    function pillMode() {
        const sh = currentShift();
        if (sh.night) return 'night';
        if (relNow(sh) > rel(sh, sh.pm_end) + 60) return null;
        return relNow(sh) < rel(sh, sh.cut) ? 'am' : 'pm';
    }

    // ── The line under the list ──────────────────────────────────────────
    // Words only (v15): what to do, or why the kiosk cannot take a scan.
    function renderIdle() {
        if (!$('att-panel')) return;
        scanningShown = false;
        $('att-panel').classList.toggle('down', !synced() || !!(J.sensorDown && J.sensorDown()));
        if (!synced()) {
            J.setRing('att-ring', 'att-icon', 'muted');
            $('att-status-text').textContent = t('att.waitclock');
            $('att-hint').textContent = t('att.waitclock.d');
        } else if (J.sensorDown && J.sensorDown()) {
            J.setRing('att-ring', 'att-icon', 'error');
            $('att-status-text').textContent = t('st.sensor.off').toUpperCase();
            $('att-hint').textContent = t('enr.nosvc');
        } else {
            J.setRing('att-ring', 'att-icon', isAuto() ? 'auto' : 'idle');
            $('att-status-text').textContent = t('att.place');
            $('att-hint').textContent = t(isAuto() ? 'att.place.auto' : 'att.place.d');
        }
    }

    function applyMode() {
        const auto = isAuto();
        const chip = $('att-mode-chip');
        if (chip) {
            chip.className = 'chip' + (auto ? ' auto' : '');
            chip.innerHTML = auto ? `<i class="fas fa-bolt"></i>${esc(t('mode.auto'))}`
                                  : `<i class="fas fa-hand-pointer"></i>${esc(t('mode.buttons'))}`;
        }
        renderIdle();
        renderHeads();
    }

    /** A night crew reads 1st In … 2nd Out: its first half is PM, its second AM. */
    function renderHeads() {
        const night = !!currentShift().night;
        const heads = { 'mon.amin': 'n.in1', 'mon.amout': 'n.out1', 'mon.pmin': 'n.in2', 'mon.pmout': 'n.out2' };
        Object.entries(heads).forEach(([dayKey, nightKey]) => {
            const th = document.querySelector(`th[data-i18n="${dayKey}"], th[data-i18n="${nightKey}"]`);
            if (!th) return;
            th.dataset.i18n = night ? nightKey : dayKey;
            th.textContent  = t(th.dataset.i18n);
        });
    }

    function setOnline(on) {
        const was = J.sensorDown ? J.sensorDown() : false;
        if (J.setStatus) J.setStatus('sensor', on);
        if (was === on && !pop) renderIdle();          // offline ↔ online: redraw the bar
    }

    // ── The card ─────────────────────────────────────────────────────────
    function setWho(emp) {
        const who = $('sr-who');
        if (emp && emp.name) {
            $('sr-name').textContent = emp.name;
            const shift = emp.shift ? ' · ' + t('shift.of', { shift: emp.shift.name }) : '';
            $('sr-sub').textContent = (emp.position || t('worker')) + shift;
            who.hidden = false;
        } else {
            who.hidden = true;
        }
    }

    function openPop(mode, kind) {
        const box = $('scan-pop');
        box.className = 'scan-pop is-' + mode;
        $('att-result').className = 'scan-result ' + (kind || '');
        box.hidden = false;
    }

    function closePop() {
        clearTimeout(popTimer); popTimer = null;
        clearInterval(barTimer); barTimer = null;
        pop = null;
        $('scan-pop').hidden = true;
        renderIdle();
    }

    /** The bar along the bottom of the card, running out over `ms`. */
    function runBar(ms) {
        const fill = $('sr-bar');
        fill.style.transition = 'none';
        fill.style.width = '100%';
        void fill.offsetWidth;
        fill.style.transition = `width ${ms}ms linear`;
        fill.style.width = '0%';
    }

    /** Where a worker stands today, from the board: [line, suggested type]. */
    function standing(emp, suggested) {
        const r = rowFor(emp);
        const sh = emp.shift && emp.shift.crosses_midnight ? (nightShift() || currentShift()) : dayShift();
        if (r) {
            const am = sessionState(r, 'AM'), pm = sessionState(r, 'PM');
            if (am.open || pm.open) {
                const s = am.open ? (r.night ? t('grp.first') : 'AM') : (r.night ? t('grp.second') : 'PM');
                return [t('pop.isin', { t: (am.open ? am.inAt : pm.inAt), s }), 'time_out'];
            }
            if (pm.done) return [t('pop.done'), null];
            if (am.done) return [t('pop.amdone', { t: am.outAt }), 'time_in'];
        }
        if (suggested === 'time_out') return [t('pop.isin', { t: '—', s: '' }), 'time_out'];
        return [t('pop.notin', { s: sessionLabel(sh) }), 'time_in'];
    }

    function paintChoice(suggest) {
        const sub = esc(sessionLabel());
        const bin = $('btn-time-in'), bout = $('btn-time-out');
        bin.innerHTML  = `<i class="fas fa-right-to-bracket"></i>${esc(t('btn.in'))}<small>${sub}</small>`;
        bout.innerHTML = `<i class="fas fa-right-from-bracket"></i>${esc(t('btn.out'))}<small>${sub}</small>`;
        bin.classList.toggle('suggest', suggest === 'time_in');
        bout.classList.toggle('suggest', suggest === 'time_out');
        bin.classList.toggle('dim', suggest === 'time_out');
        bout.classList.toggle('dim', suggest === 'time_in');
    }

    function openChoose(emp, token, suggested) {
        const [line, suggest] = standing(emp, suggested);
        pop = { mode: 'choose', emp, token };
        setWho(emp);
        $('sr-time').hidden = true;
        $('sp-state').innerHTML = esc(line);
        paintChoice(suggest);
        openPop('choose', 'none');
        clearTimeout(popTimer); clearInterval(barTimer);
        runBar(CHOOSE_SECONDS * 1000);
        popTimer = setTimeout(closePop, CHOOSE_SECONDS * 1000);
    }

    /**
     * The answer, in the card: o = { kind, verb, verbSub, employee, time,
     * where, notes }. Shared with the Device Monitoring copy of this screen.
     */
    function showResult(o) {
        clearTimeout(popTimer); clearInterval(barTimer);
        pop = { mode: 'result', emp: o.employee || null };
        // Plain marks: the card draws the round badge around them.
        const icons = { in: 'right-to-bracket', out: 'right-from-bracket', none: 'info', warn: 'exclamation', rej: 'xmark' };
        $('sr-verb').innerHTML = `<i class="fas fa-${icons[o.kind] || 'info'}"></i><span>${esc(o.verb)}</span>`
                               + (o.verbSub ? `<small>· ${esc(o.verbSub)}</small>` : '');
        setWho(o.employee);
        const time = $('sr-time');
        if (o.time) { $('sr-time-v').textContent = o.time; $('sr-time-s').textContent = o.where || ''; time.hidden = false; }
        else time.hidden = true;

        const noteIcon = { good: 'circle-check', info: 'circle-info', bad: 'circle-xmark', warn: 'triangle-exclamation' };
        $('sr-notes').innerHTML = (o.notes || []).filter(n => n[1]).slice(0, 2).map(([cls, text]) =>
            `<div class="result-note ${cls === 'warn' ? '' : cls}"><i class="fas fa-${noteIcon[cls] || 'circle-info'}"></i><span>${esc(text)}</span></div>`
        ).join('');

        openPop('result', o.kind);
        const ms = RESULT_MS[o.kind] || 5000;
        runBar(ms);
        popTimer = setTimeout(closePop, ms);
    }

    function showBusy(emp) {
        clearTimeout(popTimer); clearInterval(barTimer);
        pop = { mode: 'busy', emp };
        setWho(emp);
        $('sp-state').innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${esc(t('att.saving'))}`;
        openPop('busy', 'none');
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

        // The first answer after this tab opens only says where the sensor is.
        if (!primed) {
            primed = true;
            if (hasSeq) { lastSeq = d.seq; return; }
        }

        if (!st || st === 'no_finger') {
            consumed = false;
            if (scanningShown && !pop) renderIdle();
            return;
        }

        if (st === 'scanning') {
            if (!pop) {
                scanningShown = true;
                J.setRing('att-ring', 'att-icon', 'scanning');
                $('att-status-text').textContent = t('att.scanning');
                $('att-hint').textContent = t('att.hold');
            }
            return;
        }

        // Still saving the last one: keep this result for straight after.
        if (busy) {
            if (hasSeq && d.seq !== lastSeq) pending = d;
            return;
        }

        if (hasSeq) {
            if (d.seq === lastSeq) return;
            lastSeq = d.seq;
        } else if (consumed) {
            return;
        }
        consumed = true;

        if (st === 'not_found') {
            showResult({ kind: 'rej', verb: t('att.notfound'), notes: [['bad', t('att.notenrol')]] });
            return;
        }
        if (st === 'success' && d.employee && d.employee.id) {
            onKnownScan(d.employee, d.scan_token || null, d.type || null);
            return;
        }
        if (d.employee && d.employee.id) {
            // Known, but the web turned the scan away (another site, no GPS…).
            const map = { wrong_site: 'att.site.t', no_site: 'att.nosite.t', not_registered: 'att.notreg.t',
                          no_gps: 'att.gps.t', outside_location: 'att.gps.t', no_site_location: 'att.gps.t',
                          device_key: 'att.servererr' };
            showResult({ kind: 'rej', verb: t(map[d.code] || 'bdg.rejected'), employee: d.employee, notes: [['bad', d.message || '']] });
            return;
        }
        showResult({ kind: 'rej', verb: t('att.servererr'), notes: [['bad', d.message || t('att.checkconn')]] });
    }

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

    function onKnownScan(emp, token, suggested) {
        if (!synced()) { renderIdle(); return; }

        // The same worker again while their card is open: a fresh token, a
        // fresh countdown — not a second card.
        if (pop && pop.mode === 'choose' && pop.emp && pop.emp.id === emp.id) {
            pop.token = token || pop.token;
            clearTimeout(popTimer); runBar(CHOOSE_SECONDS * 1000);
            popTimer = setTimeout(closePop, CHOOSE_SECONDS * 1000);
            return;
        }

        if (isAuto()) { commit(emp, 'auto', token); return; }
        openChoose(emp, token, suggested);
    }

    /** TIME IN / TIME OUT pressed on the card (or Automatic): record it. */
    async function commit(emp, type, token) {
        if (busy) return;
        busy = true;
        showBusy(emp);

        let verdict = {};
        try { verdict = await sessionGuard(emp, type); } catch (e) { verdict = {}; }
        if (verdict.block) {
            busy = false;
            showResult(Object.assign({ employee: emp }, verdict.block));
            doneSaving();
            return;
        }
        if (verdict.type) type = verdict.type;

        let res = null;
        try { res = await record(emp.id, type, token); } catch (e) { res = null; }
        busy = false;

        if (!res) {
            showResult({ kind: 'rej', verb: t('att.servererr'), employee: emp, notes: [['bad', t('att.checkconn')]] });
            doneSaving();
            return;
        }

        renderRecord(res, emp);
        if (res.success) {
            justId = emp.id; justUntil = Date.now() + 6000;
            setTimeout(loadBoard, 300);
        }
        if (res.code === 'mode_buttons' && J.reloadSettings) J.reloadSettings();
        doneSaving();
    }

    function choose(type) {
        if (!pop || pop.mode !== 'choose') return;
        const { emp, token } = pop;
        clearTimeout(popTimer);
        commit(emp, type, token);
    }

    // ── One IN, one OUT per session — read from today's board ────────────
    function rowFor(emp) {
        const rows = board.records || [];
        const byId = rows.filter(r => (r.employee_id != null ? r.employee_id : r.id) === emp.id);
        if (byId.length) return byId[0];
        // Device Monitoring knows the worker by name only.
        const nm = String(emp.name || '').trim().toLowerCase();
        const byName = emp.id === -1 && nm ? rows.filter(r => String(r.name || '').trim().toLowerCase() === nm) : [];
        return byName.length === 1 ? byName[0] : null;
    }

    function sessionState(r, sess) {
        const list = (r.entries || []).filter(e => e.session === sess);
        if (list.length) {
            const last = list[list.length - 1];
            return { open: !last.out, done: !!last.out, inAt: list[0].in || '', outAt: last.out || '' };
        }
        const i = sess === 'AM' ? r.am_in : r.pm_in, o = sess === 'AM' ? r.am_out : r.pm_out;
        return { open: !!i && !o, done: !!i && !!o, inAt: i || '', outAt: o || '' };
    }

    function offShiftBlock(emp) {
        const night = emp.shift ? !!emp.shift.crosses_midnight : null;
        if (night === true) return null;
        if (night === null && nightShift()) return null;
        const sh = dayShift();
        if (relNow(sh) <= rel(sh, sh.pm_end)) return null;
        return {
            kind: 'rej', verb: t('att.wrongshift.t'),
            notes: [['bad', t('att.wrongshift', { shift: (emp.shift && emp.shift.name) || sh.name || 'Day',
                a: fmt12(toMin(sh.opens)), b: fmt12(toMin(sh.pm_end)) })]],
        };
    }

    const boardFresh = () => boardOk && boardAt > 0 && (Date.now() - boardAt) < BOARD_FRESH_MS;

    async function sessionGuard(emp, type) {
        const offBlock = offShiftBlock(emp);
        if (offBlock && type === 'time_in') return { block: offBlock };

        if (!boardFresh()) {
            await Promise.race([loadBoard(true).catch(() => {}), new Promise(r => setTimeout(r, BOARD_WAIT_MS))]);
        }
        if (!boardFresh()) return {};       // the web's own rules still apply
        const r = rowFor(emp);

        if (offBlock && type === 'auto') {
            const open = r && (sessionState(r, 'AM').open || sessionState(r, 'PM').open);
            return open ? { type: 'time_out' } : { block: offBlock };
        }
        if (!r) return {};

        const night = !!(r.night || (emp.shift && emp.shift.crosses_midnight));
        const sh    = night ? (nightShift() || currentShift()) : dayShift();
        const nowPM = relNow(sh) >= rel(sh, sh.cut);
        const L1    = night ? t('grp.first') : 'AM';
        const am = sessionState(r, 'AM'), pm = sessionState(r, 'PM');
        const wantsIn = type === 'time_in' || (type === 'auto' && !am.open && !pm.open);

        if (pm.done && wantsIn) {
            return { block: { kind: 'none', verb: t('att.daydone.t'), notes: [['info', t('att.daydone')]] } };
        }
        if (am.open && nowPM) {
            if (type === 'time_in') {
                return { block: { kind: 'warn', verb: t('att.amopen.t', { s: L1 }),
                         notes: [['bad', t('att.amopen', { s: L1, t: am.inAt })]] } };
            }
            if (type === 'auto') return { type: 'time_out' };
        }
        if (wantsIn && !nowPM && am.done) {
            return { block: { kind: 'none', verb: t('att.sessdone.t', { s: L1 }),
                     notes: [['info', t('att.sessdone', { s: L1, a: am.inAt, b: am.outAt, n: night ? t('att.secondhalf') : t('att.afternoon') })]] } };
        }
        return {};
    }

    async function record(employeeId, type, token) {
        const body = { employee_id: employeeId, type };
        if (token) body.scan_token = token;
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
            // The server's time, as it was written — never the kiosk's.
            const at    = clockLabel(inn ? r.attendance && r.attendance.time_in : r.attendance && r.attendance.time_out);
            const label = night ? t('sess.n.' + sess) : t('sess.' + sess);
            const notes = [];

            if (r.auto_closed) notes.push(['warn', t('att.autoclosed', { session: r.auto_closed.session, t: r.auto_closed.at })]);
            if (r.paid_from) notes.push(['good', t('att.paidfrom', { t: r.paid_from })]);
            if (r.ot_hours) notes.push(['warn', t('att.ot', { h: r.ot_hours, a: r.ot_from, b: r.ot_to })]);
            if (!inn && sess === 'AM' && !night) {
                const d = dayShift();
                if (nowMin() < toMin(d.pm_start)) notes.push(['info', t('att.lunch', { a: short(d.am_end), b: short(d.pm_start) })]);
            }

            showResult({ kind: inn ? 'in' : 'out', verb, verbSub: inn ? t('res.in') : t('res.out'),
                         employee: who, time: at, where: label, notes });
            return;
        }

        const map = {
            already_in: ['warn', 'att.already.t'], no_open: ['warn', 'att.noopen.t'], just_timed_in: ['warn', 'att.justin.t'],
            just_timed_out: ['warn', 'att.justout.t'], session_done: ['none', 'att.sessdone.t'],
            wrong_shift: ['rej', 'att.wrongshift.t'], not_registered: ['rej', 'att.notreg.t'],
            mode_buttons: ['none', 'att.modebtn.t'], no_gps: ['rej', 'att.gps.t'], outside_location: ['rej', 'att.gps.t'],
            wrong_site: ['rej', 'att.site.t'], no_site: ['rej', 'att.nosite.t'], no_site_location: ['rej', 'att.gps.t'],
            stale_scan: ['warn', 'att.stale.t'],
        };
        const [kind, key] = map[r.code] || ['rej', 'bdg.rejected'];

        let msg = r.message || (r.errors ? Object.values(r.errors).flat().join(' ') : '') || t('att.retry');
        if (r.code === 'already_in')     msg = t('att.already', { t: r.since });
        if (r.code === 'no_open')        msg = t('att.noopen');
        if (r.code === 'wrong_shift')    msg = t('att.wrongshift', { shift: r.shift, a: r.opens, b: r.closes });
        if (r.code === 'just_timed_in')  msg = t('att.justin', { t: r.since });
        if (r.code === 'just_timed_out') msg = t('att.justout', { t: r.since });
        if (r.code === 'mode_buttons')   msg = t('att.modebtn');
        if (r.code === 'stale_scan')     msg = t('att.stale');

        const verb = r.code === 'session_done' ? t(key, { s: r.session_label || r.session || '' }) : t(key);
        showResult({ kind, verb, employee: who, notes: [[kind === 'rej' ? 'bad' : 'info', msg]] });
    }

    // ── The board ────────────────────────────────────────────────────────
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
                board.loaded = true;
                boardAt = Date.now();
                boardOk = true;
                renderBoard();
            } catch (e) {
                if (gen !== boardGen) return;
                boardOk = false;
                if (!board.records.length) renderBoardMessage(t('mon.failed'));
            } finally {
                if (boardInFlight === job) boardInFlight = null;
            }
        })();
        boardInFlight = job;
        return job;
    }

    function renderBoardMessage(text) {
        $('monitor-body').innerHTML = `<tr class="mon-empty"><td colspan="6"><i class="fas fa-hard-hat"></i>${esc(text)}</td></tr>`;
        $('board-count').innerHTML = '';
    }

    // The chips above the list: everybody, or only those working / at lunch / not back.
    let boardFilter = 'all';
    const FILTERS = { working: s => s === 'working' || s === 'ot', lunch: s => s === 'lunch', notback: s => s === 'notback' };

    function cell(v, auto) {
        if (!v) return '<span class="t-dash">—</span>';
        const m = String(v).match(/^(\d{1,2}:\d{2})\s*([AP]M)?$/i);
        const inner = m ? `${esc(m[1])}<small>${esc((m[2] || '').toUpperCase())}</small>` : esc(v);
        return `<span class="t-val${auto ? ' auto' : ''}">${inner}</span>` + (auto ? '<span class="auto-tag">AUTO</span>' : '');
    }

    function badge(state) {
        switch (state) {
            case 'working': return `<span class="st-badge st-working"><span class="st-pulse"></span>${esc(t('mon.workingb'))}</span>`;
            case 'ot':      return `<span class="st-badge st-ot"><span class="st-pulse"></span>${esc(t('mon.otb'))}</span>`;
            case 'lunch':   return `<span class="st-badge st-lunch">${esc(t('mon.lunchb'))}</span>`;
            case 'notback': return `<span class="st-badge st-notback">${esc(t('mon.notbackb'))}</span>`;
            default:        return `<span class="st-badge">${esc(t('mon.doneb'))}</span>`;
        }
    }
    const stateOf = r => r.state || (r.working ? (r.ot_running ? 'ot' : 'working') : 'done');

    function renderBoard() {
        const rows = board.records || [];
        if (!rows.length) { renderBoardMessage(t(board.loaded ? 'mon.none' : (boardOk ? 'mon.loading' : 'mon.failed'))); return; }
        const just = Date.now() < justUntil ? justId : null;

        // The one who just scanned goes to the top, for as long as the blue sweep lasts.
        const idOf = r => (r.employee_id != null ? r.employee_id : r.id);
        const ordered = just === null ? rows : rows.filter(r => idOf(r) === just).concat(rows.filter(r => idOf(r) !== just));
        const keep = FILTERS[boardFilter];
        const shown = keep ? ordered.filter(r => keep(stateOf(r))) : ordered;

        $('monitor-body').innerHTML = shown.length ? shown.map(r => {
            const id = idOf(r);
            const cls = [r.working ? 'row-working' : '', just !== null && id === just ? 'just' : ''].join(' ').trim();
            return `<tr class="${cls}">
                <td><div class="mon-who"><span class="w-av"><span class="wk"></span></span><div>
                    <div class="mon-name">${esc(r.name)}</div>
                    <div class="mon-sub">${esc(r.pending ? t('mon.pending') : (r.position || ''))}</div></div></div></td>
                <td>${cell(r.am_in, false)}</td>
                <td>${cell(r.am_out, r.am_out_auto)}</td>
                <td>${cell(r.pm_in, false)}</td>
                <td>${cell(r.pm_out, r.pm_out_auto)}</td>
                <td>${badge(stateOf(r))}</td>
            </tr>`;
        }).join('') : `<tr class="mon-empty"><td colspan="6"><i class="fas fa-hard-hat"></i>${esc(t('mon.f.none'))}</td></tr>`;

        const chip = (key, label) => `<button type="button" class="chip${boardFilter === key ? ' on' : ''}" data-filter="${key}">${esc(label)}</button>`;
        $('board-count').innerHTML = chip('all', t('mon.f.all', { n: rows.length }))
            + chip('working', t('mon.f.working')) + chip('lunch', t('mon.f.lunch')) + chip('notback', t('mon.f.notback'));
    }

    // ── Start / stop (switchTab in script.js) ────────────────────────────
    function start() {
        if (polling) return;
        polling  = true;
        consumed = true;
        primed   = false;
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
        if (pop && pop.mode !== 'busy') closePop();
    }

    window.startAttendancePolling = start;
    window.stopAttendancePolling  = stop;

    Object.assign(J, { loadBoard, dayShift, nightShift, currentShift, fmt12, toMin, short, pillMode,
        // For script.js (idle reload) and Device Monitoring.
        popOpen: () => !!pop || busy,
        popView: () => (pop ? { who: pop.emp ? pop.emp.name : null, step: pop.mode } : null),
        showResult,
        // Device Monitoring draws the same card for a worker it is told about.
        mirrorChoose: (who) => {
            if (!who) { if (pop && pop.mode === 'choose') closePop(); return; }
            if (pop && pop.mode !== 'choose') return;
            openChoose({ id: -1, name: who }, null, null);
        },
    });

    window.addEventListener('DOMContentLoaded', () => {
        $('btn-time-in').addEventListener('click', () => choose('time_in'));
        $('btn-time-out').addEventListener('click', () => choose('time_out'));
        $('sp-cancel').addEventListener('click', closePop);
        $('board-count').addEventListener('click', e => {
            const b = e.target.closest('[data-filter]');
            if (!b) return;
            boardFilter = b.dataset.filter;
            renderBoard();
            const wrap = $('board-wrap'); if (wrap) wrap.scrollTop = 0;
        });
        // A tap on the dark around the card closes a choice (never a save).
        $('scan-pop').addEventListener('click', e => { if (e.target === $('scan-pop') && pop && pop.mode !== 'busy') closePop(); });

        setInterval(() => {
            renderHeads();
            if (typeof window.updateSessionPill === 'function') window.updateSessionPill();
        }, 30000);
        applyMode();
    });

    window.addEventListener('clocksynced', () => { if (!pop) renderIdle(); });
    window.addEventListener('kiosksettings', () => { applyMode(); if (polling) loadBoard(); });
    window.addEventListener('sitechanging', () => {
        boardGen++;
        board = { records: [] };
        boardOk = true;
        boardAt = 0;
        renderBoardMessage(t('mon.loading'));
        const wrap = $('board-wrap'); if (wrap) wrap.scrollTop = 0;
    });
    window.addEventListener('sitechange', () => {
        loadBoard(true);
        if (polling) { clearInterval(boardTimer); boardTimer = setInterval(loadBoard, BOARD_POLL_MS); }
    });
    window.addEventListener('langchange', () => { applyMode(); renderBoard(); });
})();
