// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO ATTENDANCE KIOSK — v8
//
//  Talks to two things:
//    • Flask on this Pi (http://127.0.0.1:5000) — the fingerprint sensor,
//      the chosen site, and the window controls.
//    • Laravel on Railway — the payroll system of record.
//
//  This file: language, sites, tabs, the clock, the window buttons, the
//  settings the web sends (Buttons or Automatic), the idle return, and the
//  ENROLL tab. ATTENDANCE lives in kiosk-clock.js, SUMMARY in
//  kiosk-summary.js, MY PAYROLL in kiosk-ai.js.
// ─────────────────────────────────────────────────────────────────────────

// ── CONFIG ───────────────────────────────────────────────────────────────
const apiBase   = window.JEYANCO_API_BASE   || "https://jeyancowebsite-production.up.railway.app/api/kiosk";
const flaskBase = window.JEYANCO_FLASK_BASE || "http://127.0.0.1:5000";

// How often the kiosk asks the web "has anything changed?" — the mode and the
// hours of every shift. While nothing has, the answer is a few bytes, so
// asking often costs next to nothing and a change shows within seconds.
const SETTINGS_REFRESH_MS = 5 * 1000;

// ── LANGUAGE ─────────────────────────────────────────────────────────────
// English by default; Tagalog one tap away. Only wording changes — names,
// money and times are the same either way.
const I18N = {
    en: {
        'site.here': 'YOU ARE AT', 'site.loading': 'Loading sites…', 'site.none': 'No sites found',
        'site.pick': 'Tap the site where you are working',
        'site.saved': 'Saved to web', 'site.saving': 'Saving…', 'site.notsaved': 'Not saved to web',
        'site.retry': 'The web does not know about "{site}" yet. Saved on the kiosk — retrying every 30 s.',
        'tab.attendance': 'ATTENDANCE', 'tab.enroll': 'ENROLL FINGERPRINT', 'tab.payroll': 'MY PAYROLL', 'tab.summary': 'SUMMARY',

        'win.fullscreen': 'Full screen', 'win.minimize': 'Hold to minimize',
        'win.holding': 'Keep holding…', 'win.nomin': 'Minimize needs the kiosk service on the Pi',
        'pill.am': 'AM SESSION', 'pill.pm': 'PM SESSION', 'pill.night': 'NIGHT SHIFT',

        'mode.buttons': 'BUTTONS', 'mode.auto': 'AUTOMATIC',
        'att.scan': 'BIOMETRIC SCAN',
        'att.press': 'PRESS TIME IN OR TIME OUT', 'att.thenscan': 'Then place your finger on the sensor',
        'att.cap': 'Press first, then scan your finger', 'att.scannow': 'SCAN NOW',
        'att.armed.in': 'TIME IN — SCAN YOUR FINGER', 'att.armed.out': 'TIME OUT — SCAN YOUR FINGER',
        'att.within': '{session} · within {n} seconds',
        'att.auto.place': 'PLACE YOUR FINGER', 'att.auto.hint': 'No button needed — the kiosk knows if it is IN or OUT.',
        'an.now': 'A SCAN RIGHT NOW · {t}', 'an.notin': 'if you are not timed in yet', 'an.isin': 'if you are already timed in',
        'an.closed': 'TIME IN is closed — this can only be a time out',
        'att.scanning': 'SCANNING…', 'att.hold': 'Hold still', 'att.processing': 'PROCESSING…', 'att.saving': 'Saving to the web…',
        'att.notfound': 'FINGERPRINT NOT RECOGNISED', 'att.notenrol': 'Not enrolled yet, or the finger slipped. Try again, or go to the ENROLL tab.',
        'att.pressfirst.t': 'PRESS A BUTTON FIRST', 'att.pressfirst': 'Press TIME IN or TIME OUT first, then scan again.',
        'att.already.t': 'ALREADY TIMED IN', 'att.already': 'Already timed in since {t}. To leave, press TIME OUT.',
        'att.noopen.t': 'NO OPEN TIME IN', 'att.noopen': 'No open time in. If you forgot to time in, ask the office.',
        'att.wrongshift.t': 'REJECTED', 'att.wrongshift': '{shift} shift. TIME IN is open {a}–{b}. Nothing was recorded.',
        'att.justin.t': 'JUST TIMED IN', 'att.justin': 'Timed in a moment ago, at {t}. Press TIME OUT again when you are leaving.',
        'att.repeat.t': 'ALREADY RECORDED', 'att.repeat': '{what} was recorded at {t}. A second scan this soon is not counted.',
        'att.notreg.t': 'NOT REGISTERED YET',
        'att.modebtn.t': 'PRESS A BUTTON', 'att.modebtn': 'The office switched this kiosk to the TIME IN / TIME OUT buttons. Press one, then scan.',
        'att.gps.t': 'LOCATION NOT CONFIRMED',
        'att.timein': 'TIME IN', 'att.timeout': 'TIME OUT', 'att.again': 'TIME IN AGAIN',
        'att.logged': '{session} {verb} logged at {site} · {time}',
        'att.paidfrom': 'Paid hours start at {t}.', 'att.gap': '{a}–{b} is not counted.',
        'att.autoclosed': '{session} was closed at {t} as AUTO. The office will review it.',
        'att.ot': '{h} h overtime ({a}–{b}).', 'att.lunch': 'Lunch {a}–{b}. Scan again when you are back.',
        'att.error': 'SCAN ERROR', 'att.servererr': 'SERVER ERROR', 'att.checkconn': 'Check the connection to the server',
        'att.retry': 'Please try again', 'bdg.none': 'NOTHING RECORDED', 'bdg.rejected': 'REJECTED',
        'sess.AM': 'AM SESSION', 'sess.PM': 'PM SESSION', 'sess.n.AM': 'NIGHT · FIRST HALF', 'sess.n.PM': 'NIGHT · SECOND HALF',

        'ds.day': 'DAY SHIFT', 'ds.night': 'NIGHT SHIFT', 'ds.lunch': '{t} LUNCH', 'ds.break': '{t} BREAK',
        'ds.cut': 'CUT-OFF', 'ds.now': 'NOW', 'ds.first': '1ST', 'ds.second': '2ND',

        'conn.checking': 'Connecting…', 'conn.online': 'System online', 'conn.offline': 'Sensor offline',

        'mon.title': 'WHO IS ON SITE TODAY', 'mon.tap': 'Tap a name for details',
        'mon.working': 'Working now', 'mon.onsite': 'On site today', 'mon.ot': 'Overtime', 'mon.otnow': 'On overtime now',
        'mon.employee': 'Employee', 'mon.in': 'In', 'mon.out': 'Out',
        'mon.amin': 'AM In', 'mon.amout': 'AM Out', 'mon.pmin': 'PM In', 'mon.pmout': 'PM Out',
        'mon.total': 'Paid hrs', 'mon.otcol': 'OT', 'mon.status': 'Status',
        'mon.loading': 'Loading attendance…', 'mon.none': 'No attendance yet today', 'mon.failed': 'Cannot load the board — retrying',
        'mon.workingb': 'WORKING', 'mon.doneb': 'DONE', 'mon.autob': 'AUTO-CLOSED', 'mon.since': 'Since',
        'mon.lunchb': 'LUNCH', 'mon.notbackb': 'NOT BACK', 'mon.otb': 'OVERTIME',
        'mon.pending': 'Pending registration', 'mon.day': 'DAY', 'mon.night': 'NIGHT',

        'modal.am': 'AM Session', 'modal.pm': 'PM Session', 'modal.n1': 'First half', 'modal.n2': 'Second half',
        'modal.paid': 'Paid hours', 'modal.ot': 'Overtime', 'modal.late': 'Late', 'modal.inside': 'ON SITE', 'modal.none': 'No entries',
        'modal.auto': 'AUTO — the office will review', 'modal.shift': '{shift} SHIFT', 'modal.ontime': 'On time',

        'sum.bysession': 'TODAY BY SESSION', 'sum.attention': 'NEEDS ATTENTION',
        'sum.working': 'Working now', 'sum.onsite': 'On site today', 'sum.lunch': 'On lunch break', 'sum.done': 'Done for today',
        'sum.ot': 'Overtime today', 'sum.otnow': 'On overtime now', 'sum.review': 'Needs review', 'sum.late': 'Late this morning',
        'sum.ofonsite': 'of {n} on site today', 'sum.daynight': '{d} day · {n} night', 'sum.notback': '{n} not back yet',
        'sum.lastout': 'Last out {t}', 'sum.othours': '{h} h in all', 'sum.since': 'since {t}', 'sum.reviewsub': 'AUTO-closed — the office checks',
        'sum.ontime': 'Everyone on time', 'sum.nobody': 'Nobody yet', 'sum.ofcrew': 'of {n} at this site', 'sum.nofp': 'No fingerprint', 'sum.hasfp': 'Has fingerprint',
        'sum.auto': '{n} AUTO', 'sum.still': '{n} still on site', 'sum.note': 'Day shift · AM {a} · lunch {b} · PM {c}',
        'sum.night': '{n} on the night shift', 'sum.nightworking': '{n} working now', 'sum.nightdone': 'all done',
        'sum.otsince': 'On overtime since {t}', 'sum.autoclosed': '{session} out closed at {t} as AUTO — forgot to scan out',
        'sum.notbackd': 'Out {t} for lunch · not back yet', 'sum.leftearly': 'Out {t} · no PM time in', 'sum.lated': 'Timed in {t} · {m} min late',
        'sum.halfday': 'HALF DAY', 'sum.reviewb': 'REVIEW', 'sum.notbackb': 'NOT BACK',
        'sum.clear': 'All clear — nothing needs you right now.',
        'sum.updated': 'Updated {t} · every 15 s', 'sum.same': 'Same numbers as the web Attendance page',
        'sum.offline': 'Cannot reach the web — showing the last numbers',

        'enr.capture': 'CAPTURE FINGERPRINT', 'enr.pick': 'Choose a name from the list on the right',
        'enr.pickfirst': 'CHOOSE A NAME FIRST', 'enr.detailsweb': 'Details come from the web — only the finger is needed here',
        'enr.s1': 'Touch', 'enr.s2': 'Lift', 'enr.s3': 'Again', 'enr.s4': 'Done', 'enr.start': 'START SCAN',
        'enr.workers': 'WORKERS AT THIS SITE', 'enr.nofinger': 'No fingerprint yet', 'enr.hasfinger': 'Fingerprint on file',
        'enr.loading': 'Loading the list…',
        'enr.note': 'New name? Add it in the web system first — position, rate and Arawan/Contractual are set there. It appears here on its own.',
        'enr.empty': 'No workers at this site yet.', 'enr.emptysub': 'Add them in the web system first.',
        'enr.failed': 'Could not load the list.', 'enr.new': 'NEW', 'enr.hasfp': 'HAS FINGERPRINT', 'enr.needsfp': 'NEEDS FINGERPRINT',
        'enr.ready': 'READY FOR', 'enr.replace': 'REPLACE FINGERPRINT OF', 'enr.presskey': 'Press START SCAN',
        'enr.already': 'already has fingerprint #', 'enr.willreplace': 'If you continue, it will be replaced.',
        'enr.picknamefirst': 'Choose a name first.', 'enr.preparing': 'PREPARING THE SENSOR…',
        'enr.touch': 'TOUCH THE SENSOR', 'enr.press': 'Press down and hold still', 'enr.lift': 'LIFT YOUR FINGER',
        'enr.liftnow': 'Raise your finger now', 'enr.again': 'TOUCH AGAIN', 'enr.secondtime': 'Second time, to be sure',
        'enr.captured': 'FINGERPRINT CAPTURED', 'enr.saving': 'Saving to the web…', 'enr.notcapt': 'NOT CAPTURED',
        'enr.tryagain': 'Try again', 'enr.wrong': 'SOMETHING WENT WRONG', 'enr.complete': 'COMPLETE',
        'enr.timeout': 'Timed out. Try again.', 'enr.nosvc': 'The fingerprint service on the Pi is not running.',
        'enr.saved': 'saved with fingerprint #', 'enr.notsaved': 'Could not save to the web',

        'ai.verify': 'VERIFY IDENTITY FIRST', 'ai.unlock': 'SCAN YOUR FINGER TO UNLOCK',
        'ai.privacy': 'For privacy, only you can see your own payroll.', 'ai.lock': 'I AM DONE — LOCK',
        'ai.title': 'MY PAY THIS CUTOFF', 'ai.scanleft': 'Scan your fingerprint on the left to see your pay.',
        'ai.q1': 'How was my pay computed?', 'ai.q2': 'How was my OT counted?', 'ai.q3': 'How do I pay my vale?',
        // v8
        'win.kioskflag': 'Chromium was started with --kiosk, which has no way out of full screen. Change --kiosk to --start-fullscreen in the autostart file, then restart the Pi.',
        'win.notool': 'Missing on the Pi: {tool}. Run: sudo apt install -y {tool}',
        'win.nodisplay': 'The Pi service cannot find the screen. Restart the fingerprint service after the desktop has loaded.',
        'win.nowindow': 'The Pi cannot find the Chromium window.',
        'win.failed': 'The Pi could not do it: {msg}',
        'win.hidden': 'Hidden. It comes back by itself in 5 minutes.',
        'enr.dup.t': 'FINGER ALREADY REGISTERED', 'enr.dup': 'This finger belongs to {name} (#{slot}). Use another finger.',
        'enr.dupother': 'This finger belongs to another worker (#{slot}). Use another finger.',
        'att.amopen.t': 'TIME OUT {s} FIRST', 'att.amopen': 'Your {s} is still open since {t}. Press TIME OUT first.',
        'att.sessdone.t': '{s} DONE', 'att.sessdone': 'Your {s} is complete (IN {a} · OUT {b}). Next is {n}.',
        'att.daydone.t': 'DONE FOR TODAY', 'att.daydone': 'All your scans for today are recorded. See you tomorrow.',
        'att.afternoon': 'the afternoon', 'att.secondhalf': 'the 2nd half',
        'pill.off': 'OFF-SHIFT', 'ds.dayover': 'DAY SHIFT · DONE',
        'sum.brk': 'On break', 'sum.latenight': 'Late this shift', 'sum.notenight': 'Night shift · 1st half {a} · break {b} · 2nd half {c}',
        'sum.breakgap': '{t} BREAK', 'n.in1': '1ST IN', 'n.out1': '1ST OUT', 'n.in2': '2ND IN', 'n.out2': '2ND OUT',
        'grp.first': '1ST HALF', 'grp.second': '2ND HALF',
        // v7 — everything that used to be written straight into the code
        'brand.sub': 'Attendance Kiosk', 'worker': 'Worker', 'shift.of': '{shift} shift',
        'site.nonehint': ' — add a site in the web system, then refresh.',
        'site.notpi': '"{site}" was not saved to the kiosk ({err}). Records may be filed to the wrong site — restart the fingerprint service.',
        'site.loadfail': 'Could not load the site list. The scanner still works — try again in a moment.',
        'win.hold': 'Hold ▁ for 2 seconds to hide the kiosk',
        'win.kiosk': 'The Pi opened the kiosk in full screen. Hold ▁ for 2 s to hide it.',
        'win.exit': 'Exit full screen', 'win.enter': 'Full screen',
        'btn.in': 'TIME IN', 'btn.in.sub': 'ARRIVING', 'btn.out': 'TIME OUT', 'btn.out.sub': 'LEAVING',
        'res.in': 'TIMED IN', 'res.again': 'IN AGAIN', 'res.out': 'TIMED OUT',
        'sum.lunchgap': '{t} LUNCH', 'enr.refresh': 'Refresh',
        'ai.verified': 'VERIFIED', 'ai.verifiedname': 'VERIFIED — {name}', 'ai.scanning': 'SCANNING…',
        'ai.notread': 'NOT READ — TRY AGAIN', 'ai.notread.t': 'The sensor did not recognise the finger',
        'ai.notread.d': 'The finger touched the sensor but no template matched. If you just enrolled, check the ENROLL tab for your name — if it is not there, the sync removed it.',
        'ai.loading': 'GETTING YOUR PAYROLL…', 'ai.noweb': 'CANNOT REACH THE WEB',
        'ai.webcode.t': 'The web answered {code}', 'ai.webcode.d': 'Called: {url}. If this is 500, check the log on Railway.',
        'ai.norecord': 'NO PAYROLL RECORD YET',
        'ai.norecord.d': 'No payroll was found for this finger. If the web system was just updated, wait for the deploy to finish, then try again.',
        'ai.noserver': 'CANNOT REACH THE SERVER', 'ai.nosensor': 'NO CONNECTION TO THE SENSOR',
        'ai.nosensor.t': 'Cannot reach the fingerprint service', 'ai.nosensor.d': '{url}/scan is not answering — {err}. Restart the service on the Pi.',
        'ai.nodetail': 'no details', 'ai.showadmin': 'Show this to whoever set up the system',
        'ai.incomplete.t': 'The kiosk did not load completely',
        'ai.incomplete.d': 'script.js could not be read (window.jeyanco). Check that script.js and kiosk-ai.js are in the same folder, then refresh with Ctrl+Shift+R.',
        'ai.timer': 'Locks by itself in {t}', 'ai.lockprivacy': 'Locked for your privacy.', 'ai.locked': 'Locked.',
        'ai.scanagain': 'Scan your finger again to open.',
        'pay.days': 'Days worked', 'pay.hours': 'Hours worked', 'pay.hrs': 'hrs', 'pay.ot': 'Overtime pay',
        'pay.gross': 'Gross', 'pay.bonus': 'Bonus', 'pay.deductions': 'Deductions', 'pay.cutoff': 'CUTOFF',
        'pay.net': 'You will receive this cutoff', 'pay.vale': 'Vale (cash advance)',
        'pay.valenote': 'The vale is your remaining balance. Subtract it from the amount above to see the cash you will get — or ask the office how to pay it.',
    },

    tl: {
        'site.here': 'NASAAN KA', 'site.loading': 'Kinukuha ang mga site…', 'site.none': 'Walang nakuhang site',
        'site.pick': 'Pindutin ang site na pinagtatrabahuhan mo',
        'site.saved': 'Naka-save sa web', 'site.saving': 'Sine-save…', 'site.notsaved': 'Hindi na-save sa web',
        'site.retry': 'Hindi pa alam ng web ang "{site}". Naka-save sa kiosk — susubukan ulit kada 30 seg.',
        'tab.attendance': 'ATTENDANCE', 'tab.enroll': 'PAGKUHA NG DALIRI', 'tab.payroll': 'SAHOD KO', 'tab.summary': 'BUOD',

        'win.fullscreen': 'Buong screen', 'win.minimize': 'Hawakan para itago',
        'win.holding': 'Hawakan pa…', 'win.nomin': 'Kailangan ang kiosk service sa Pi para mag-minimize',
        'pill.am': 'AM SESSION', 'pill.pm': 'PM SESSION', 'pill.night': 'NIGHT SHIFT',

        'mode.buttons': 'BUTTONS', 'mode.auto': 'AUTOMATIC',
        'att.scan': 'PAG-SCAN NG DALIRI',
        'att.press': 'PINDUTIN ANG TIME IN O TIME OUT', 'att.thenscan': 'Tapos idampi ang daliri sa sensor',
        'att.cap': 'Pindutin muna, tapos i-scan ang daliri', 'att.scannow': 'I-SCAN NA',
        'att.armed.in': 'TIME IN — I-SCAN ANG DALIRI', 'att.armed.out': 'TIME OUT — I-SCAN ANG DALIRI',
        'att.within': '{session} · sa loob ng {n} segundo',
        'att.auto.place': 'IDAMPI ANG DALIRI', 'att.auto.hint': 'Walang pipindutin — alam ng kiosk kung PASOK o LABAS.',
        'an.now': 'KAPAG NAG-SCAN NGAYON · {t}', 'an.notin': 'kung wala ka pang pasok', 'an.isin': 'kung naka-pasok ka na',
        'an.closed': 'Sarado na ang TIME IN — labas na lang ang puwede',
        'att.scanning': 'BINABASA…', 'att.hold': 'Huwag igalaw', 'att.processing': 'PINAPROSESO…', 'att.saving': 'Sine-save sa web…',
        'att.notfound': 'HINDI NAKILALA ANG DALIRI', 'att.notenrol': 'Wala pang daliri, o dumulas. Subukan ulit, o pumunta sa PAGKUHA NG DALIRI.',
        'att.pressfirst.t': 'PINDUTIN MUNA ANG BUTTON', 'att.pressfirst': 'Pindutin muna ang TIME IN o TIME OUT, tapos mag-scan ulit.',
        'att.already.t': 'NAKA-TIME IN KA NA', 'att.already': 'Naka-time in ka na mula {t}. Kung aalis ka, pindutin ang TIME OUT.',
        'att.noopen.t': 'WALANG BUKAS NA PASOK', 'att.noopen': 'Wala kang bukas na pasok. Kung nakalimutan mong mag-time in, ipaayos sa opisina.',
        'att.wrongshift.t': 'TINANGGIHAN', 'att.wrongshift': '{shift} shift ka. Bukas ang TIME IN mula {a} hanggang {b}. Walang naitala.',
        'att.justin.t': 'KAKA-PASOK LANG', 'att.justin': 'Kaka-time in mo lang, {t}. Pindutin ulit ang TIME OUT kapag aalis ka na.',
        'att.repeat.t': 'NAITALA NA', 'att.repeat': 'Naitala na ang {what} mo sa {t}. Hindi binibilang ang kasunod na scan agad-agad.',
        'att.notreg.t': 'HINDI PA REHISTRADO',
        'att.modebtn.t': 'PINDUTIN ANG BUTTON', 'att.modebtn': 'Ibinalik ng opisina sa TIME IN / TIME OUT buttons. Pindutin muna, tapos mag-scan.',
        'att.gps.t': 'HINDI TUGMA ANG LOKASYON',
        'att.timein': 'PASOK', 'att.timeout': 'LABAS', 'att.again': 'PASOK ULIT',
        'att.logged': '{session} {verb} naitala sa {site} · {time}',
        'att.paidfrom': 'Magsisimula ang bayad sa {t}.', 'att.gap': 'Hindi bilang ang {a}–{b}.',
        'att.autoclosed': 'Isinara ang {session} sa {t} bilang AUTO. Susuriin ng opisina.',
        'att.ot': 'May {h} oras na OT ({a}–{b}).', 'att.lunch': 'Tanghalian {a}–{b}. Mag-scan ulit pagbalik mo.',
        'att.error': 'MAY MALI SA SCAN', 'att.servererr': 'MAY MALI SA SERVER', 'att.checkconn': 'Tingnan ang koneksyon sa server',
        'att.retry': 'Subukan ulit', 'bdg.none': 'WALANG NAITALA', 'bdg.rejected': 'TINANGGIHAN',
        'sess.AM': 'AM SESSION', 'sess.PM': 'PM SESSION', 'sess.n.AM': 'NIGHT · UNANG HATI', 'sess.n.PM': 'NIGHT · IKALAWANG HATI',

        'ds.day': 'DAY SHIFT', 'ds.night': 'NIGHT SHIFT', 'ds.lunch': 'TANGHALIAN {t}', 'ds.break': 'PAHINGA {t}',
        'ds.cut': 'CUT-OFF', 'ds.now': 'NGAYON', 'ds.first': '1ST', 'ds.second': '2ND',

        'conn.checking': 'Kumokonekta…', 'conn.online': 'Gumagana ang sistema', 'conn.offline': 'Patay ang sensor',

        'mon.title': 'SINO ANG NASA SITE NGAYON', 'mon.tap': 'I-tap ang pangalan para sa detalye',
        'mon.working': 'Nagtatrabaho ngayon', 'mon.onsite': 'Nasa site ngayong araw', 'mon.ot': 'Overtime', 'mon.otnow': 'Nasa OT ngayon',
        'mon.employee': 'Manggagawa', 'mon.in': 'Pasok', 'mon.out': 'Labas',
        'mon.amin': 'Pasok AM', 'mon.amout': 'Labas AM', 'mon.pmin': 'Pasok PM', 'mon.pmout': 'Labas PM',
        'mon.total': 'Bayad na oras', 'mon.otcol': 'OT', 'mon.status': 'Kalagayan',
        'mon.loading': 'Kinukuha ang attendance…', 'mon.none': 'Wala pang pasok ngayong araw', 'mon.failed': 'Hindi makuha ang listahan — susubukan ulit',
        'mon.workingb': 'NASA LOOB', 'mon.doneb': 'TAPOS NA', 'mon.autob': 'KUSANG ISINARA', 'mon.since': 'Mula',
        'mon.lunchb': 'TANGHALIAN', 'mon.notbackb': 'WALA PA', 'mon.otb': 'OVERTIME',
        'mon.pending': 'Hindi pa rehistrado', 'mon.day': 'DAY', 'mon.night': 'NIGHT',

        'modal.am': 'AM Session', 'modal.pm': 'PM Session', 'modal.n1': 'Unang hati', 'modal.n2': 'Ikalawang hati',
        'modal.paid': 'Bayad na oras', 'modal.ot': 'Overtime', 'modal.late': 'Huli', 'modal.inside': 'NASA LOOB', 'modal.none': 'Walang pasok',
        'modal.auto': 'AUTO — susuriin ng opisina', 'modal.shift': '{shift} SHIFT', 'modal.ontime': 'Nasa oras',

        'sum.bysession': 'NGAYONG ARAW, KADA SESSION', 'sum.attention': 'DAPAT BANTAYAN',
        'sum.working': 'Nagtatrabaho', 'sum.onsite': 'Nasa site', 'sum.lunch': 'Nasa tanghalian', 'sum.done': 'Tapos na',
        'sum.ot': 'May OT ngayon', 'sum.otnow': 'Naka-OT ngayon', 'sum.review': 'Susuriin', 'sum.late': 'Nahuli kanina',
        'sum.ofonsite': 'sa {n} na nasa site', 'sum.daynight': '{d} day · {n} night', 'sum.notback': '{n} hindi pa bumabalik',
        'sum.lastout': 'Huling labas {t}', 'sum.othours': '{h} oras lahat', 'sum.since': 'mula {t}', 'sum.reviewsub': 'AUTO — susuriin',
        'sum.ontime': 'Lahat ay nasa oras', 'sum.nobody': 'Wala pa', 'sum.ofcrew': 'sa {n} dito sa site', 'sum.nofp': 'Walang daliri', 'sum.hasfp': 'May daliri na',
        'sum.auto': '{n} AUTO', 'sum.still': '{n} nasa site pa', 'sum.note': 'Day shift · AM {a} · tanghalian {b} · PM {c}',
        'sum.night': '{n} sa night shift', 'sum.nightworking': '{n} nagtatrabaho', 'sum.nightdone': 'tapos na lahat',
        'sum.otsince': 'Naka-OT mula {t}', 'sum.autoclosed': 'Isinara ang {session} sa {t} bilang AUTO — nakalimutang mag-scan',
        'sum.notbackd': 'Lumabas {t} para mananghalian · wala pa', 'sum.leftearly': 'Lumabas {t} · walang pasok sa hapon', 'sum.lated': 'Pumasok {t} · huli ng {m} min',
        'sum.halfday': 'KALAHATING ARAW', 'sum.reviewb': 'SURIIN', 'sum.notbackb': 'WALA PA',
        'sum.clear': 'Walang problema — walang kailangang asikasuhin ngayon.',
        'sum.updated': 'Na-update {t} · kada 15 seg', 'sum.same': 'Parehong bilang ng web Attendance page',
        'sum.offline': 'Hindi maabot ang web — huling bilang ang nakikita',

        'enr.capture': 'PAGKUHA NG DALIRI', 'enr.pick': 'Pumili ng pangalan sa kanan',
        'enr.pickfirst': 'PUMILI MUNA NG PANGALAN', 'enr.detailsweb': 'Nasa web ang detalye — daliri lang ang kailangan dito',
        'enr.s1': 'Dampi', 'enr.s2': 'Alis', 'enr.s3': 'Ulit', 'enr.s4': 'Tapos', 'enr.start': 'SIMULAN ANG SCAN',
        'enr.workers': 'MGA MANGGAGAWA DITO', 'enr.nofinger': 'Wala pang daliri', 'enr.hasfinger': 'May daliri na',
        'enr.loading': 'Kinukuha ang listahan…',
        'enr.note': 'Bagong pangalan? Idagdag muna ito sa web system — doon inilalagay ang posisyon, rate, at kung Arawan o Contractual. Kusang lalabas dito.',
        'enr.empty': 'Wala pang manggagawa sa site na ito.', 'enr.emptysub': 'Idagdag muna sila sa web system.',
        'enr.failed': 'Hindi makuha ang listahan.', 'enr.new': 'BAGO', 'enr.hasfp': 'MAY DALIRI NA', 'enr.needsfp': 'WALA PANG DALIRI',
        'enr.ready': 'HANDA NA PARA KAY', 'enr.replace': 'PALITAN ANG DALIRI NI', 'enr.presskey': 'Pindutin ang SIMULAN ANG SCAN',
        'enr.already': 'ay may daliri na #', 'enr.willreplace': 'Kung ituloy mo, mapapalitan ito.',
        'enr.picknamefirst': 'Pumili muna ng pangalan.', 'enr.preparing': 'INIHAHANDA ANG SENSOR…',
        'enr.touch': 'IDAMPI ANG DALIRI', 'enr.press': 'Idiin at huwag igalaw', 'enr.lift': 'ALISIN ANG DALIRI',
        'enr.liftnow': 'Itaas muna ang daliri', 'enr.again': 'IDAMPI ULIT', 'enr.secondtime': 'Pangalawang beses, pantiyak',
        'enr.captured': 'NAKUHA NA ANG DALIRI', 'enr.saving': 'Sinasave sa web…', 'enr.notcapt': 'HINDI NAKUHA',
        'enr.tryagain': 'Subukan ulit', 'enr.wrong': 'MAY MALI', 'enr.complete': 'TAPOS NA',
        'enr.timeout': 'Nag-timeout. Subukan ulit.', 'enr.nosvc': 'Hindi tumatakbo ang fingerprint service sa Pi.',
        'enr.saved': 'nakuha na ang daliri #', 'enr.notsaved': 'Hindi na-save sa web',

        'ai.verify': 'PATUNAYAN MUNA KUNG SINO KA', 'ai.unlock': 'I-SCAN ANG DALIRI PARA MAGBUKAS',
        'ai.privacy': 'Para sa privacy, ikaw lang ang makakakita ng sarili mong sahod.', 'ai.lock': 'TAPOS NA AKO — I-LOCK',
        'ai.title': 'SAHOD MO SA CUTOFF', 'ai.scanleft': 'I-scan ang daliri mo sa kaliwa para makita ang sahod mo.',
        'ai.q1': 'Paano kinuwenta ang sahod ko?', 'ai.q2': 'Paano binilang ang OT ko?', 'ai.q3': 'Paano ko babayaran ang vale?',
        // v8
        'win.kioskflag': 'Naka --kiosk ang Chromium, kaya walang labasan sa full screen. Palitan ang --kiosk ng --start-fullscreen sa autostart file, tapos i-restart ang Pi.',
        'win.notool': 'Kulang sa Pi: {tool}. I-run: sudo apt install -y {tool}',
        'win.nodisplay': 'Hindi makita ng service ang screen. I-restart ang fingerprint service kapag bukas na ang desktop.',
        'win.nowindow': 'Hindi makita ng Pi ang Chromium window.',
        'win.failed': 'Hindi nagawa ng Pi: {msg}',
        'win.hidden': 'Nakatago. Kusa itong babalik pagkalipas ng 5 minuto.',
        'enr.dup.t': 'NAKA-REGISTER NA ANG DALIRI', 'enr.dup': 'Kay {name} (#{slot}) ang daliring ito. Gumamit ng ibang daliri.',
        'enr.dupother': 'May ibang manggagawang may-ari ng daliring ito (#{slot}). Gumamit ng ibang daliri.',
        'att.amopen.t': 'MAG-TIME OUT MUNA SA {s}', 'att.amopen': 'Bukas pa ang {s} mo mula {t}. Mag-TIME OUT muna.',
        'att.sessdone.t': 'TAPOS NA ANG {s}', 'att.sessdone': 'Kumpleto na ang {s} mo (IN {a} · OUT {b}). Sa {n} na ang susunod.',
        'att.daydone.t': 'TAPOS NA NGAYONG ARAW', 'att.daydone': 'Naitala na lahat ng scan mo ngayong araw. Kita tayo bukas.',
        'att.afternoon': 'hapon', 'att.secondhalf': 'ikalawang hati',
        'pill.off': 'WALANG SHIFT', 'ds.dayover': 'DAY SHIFT · TAPOS NA',
        'sum.brk': 'Nasa pahinga', 'sum.latenight': 'Nahuli ngayong shift', 'sum.notenight': 'Night shift · 1st half {a} · pahinga {b} · 2nd half {c}',
        'sum.breakgap': 'PAHINGA {t}', 'n.in1': 'PASOK 1', 'n.out1': 'LABAS 1', 'n.in2': 'PASOK 2', 'n.out2': 'LABAS 2',
        'grp.first': 'UNANG HATI', 'grp.second': 'IKALAWANG HATI',
        // v7
        'brand.sub': 'Kiosk ng Attendance', 'worker': 'Manggagawa', 'shift.of': 'Shift: {shift}',
        'site.nonehint': ' — magdagdag ng site sa web system, tapos i-refresh.',
        'site.notpi': 'Hindi na-save sa kiosk ang "{site}" ({err}). Baka mapunta sa maling site ang mga tala — i-restart ang fingerprint service.',
        'site.loadfail': 'Hindi makuha ang listahan ng site. Gumagana pa rin ang scanner — subukan ulit mamaya.',
        'win.hold': 'Hawakan ang ▁ nang 2 segundo para itago ang kiosk',
        'win.kiosk': 'Naka-full screen na ang kiosk mula sa Pi. Hawakan ang ▁ nang 2 seg para itago.',
        'win.exit': 'Lumabas sa full screen', 'win.enter': 'Buong screen',
        'btn.in': 'PASOK', 'btn.in.sub': 'TIME IN', 'btn.out': 'LABAS', 'btn.out.sub': 'TIME OUT',
        'res.in': 'PASOK', 'res.again': 'PASOK ULIT', 'res.out': 'LABAS',
        'sum.lunchgap': 'KAIN {t}', 'enr.refresh': 'I-refresh',
        'ai.verified': 'KUMPIRMADO', 'ai.verifiedname': 'KUMPIRMADO — {name}', 'ai.scanning': 'BINABASA…',
        'ai.notread': 'HINDI MABASA — SUBUKAN ULIT', 'ai.notread.t': 'Hindi nakilala ng sensor ang daliri',
        'ai.notread.d': 'Nakadampi ang daliri pero walang katugmang template sa sensor. Kung kaka-register mo lang, tingnan sa PAGKUHA NG DALIRI kung nasa listahan ka — kung wala, binura ito ng sync.',
        'ai.loading': 'KINUKUHA ANG SAHOD MO…', 'ai.noweb': 'HINDI MAABOT ANG WEB',
        'ai.webcode.t': 'Sumagot ang web ng {code}', 'ai.webcode.d': 'Tinawag: {url}. Kung 500 ito, tingnan ang log sa Railway.',
        'ai.norecord': 'WALA PANG PAYROLL RECORD',
        'ai.norecord.d': 'Hindi mahanap ang payroll para sa daliring ito. Kung kaka-update lang ng web system, hintayin munang matapos ang deploy bago subukan ulit.',
        'ai.noserver': 'HINDI MAABOT ANG SERVER', 'ai.nosensor': 'WALANG KONEKSYON SA SENSOR',
        'ai.nosensor.t': 'Hindi maabot ang fingerprint service', 'ai.nosensor.d': 'Hindi sumasagot ang {url}/scan — {err}. I-restart ang service sa Pi.',
        'ai.nodetail': 'walang detalye', 'ai.showadmin': 'Ipakita ito sa naglagay ng system',
        'ai.incomplete.t': 'Hindi kumpleto ang pagkarga ng kiosk',
        'ai.incomplete.d': 'Hindi mabasa ang script.js (window.jeyanco). Tingnan kung magkasama sa iisang folder ang script.js at kiosk-ai.js, tapos i-refresh nang Ctrl+Shift+R.',
        'ai.timer': 'Kusang magla-lock sa {t}', 'ai.lockprivacy': 'Nag-lock para sa privacy mo.', 'ai.locked': 'Naka-lock na.',
        'ai.scanagain': 'I-scan ulit ang daliri mo para magbukas.',
        'pay.days': 'Araw na pumasok', 'pay.hours': 'Oras na trabaho', 'pay.hrs': 'oras', 'pay.ot': 'Bayad sa overtime',
        'pay.gross': 'Gross', 'pay.bonus': 'Bonus', 'pay.deductions': 'Mga bawas', 'pay.cutoff': 'CUTOFF',
        'pay.net': 'Matatanggap sa cutoff na ito', 'pay.vale': 'Vale (utang na naunang kinuha)',
        'pay.valenote': 'Ang vale ay natitirang balanse mo. Ibawas ito sa itaas para makita ang malalabas na cash — o itanong sa opisina kung paano ito babayaran.',
    },
};

let LANG = 'en';
try { LANG = localStorage.getItem('jeyanco_lang') || 'en'; } catch (e) { /* private mode */ }
if (!I18N[LANG]) LANG = 'en';

/** Look up a phrase and fill {placeholders}. English, then the key, as fallbacks. */
function t(key, vars) {
    let s = (I18N[LANG] && I18N[LANG][key]) || I18N.en[key] || key;
    if (vars) Object.keys(vars).forEach(k => { s = s.split('{' + k + '}').join(vars[k] == null ? '' : vars[k]); });
    return s;
}

function applyLanguage() {
    document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = t(el.dataset.i18n); });
    document.querySelectorAll('.lang-opt').forEach(b => b.classList.toggle('active', b.dataset.lang === LANG));
    document.querySelectorAll('[data-i18n-aria]').forEach(el => { el.setAttribute('aria-label', t(el.dataset.i18nAria)); });
    document.documentElement.lang = LANG;
    const mn = document.getElementById('btn-minimize');   if (mn) mn.title = t('win.minimize');
    if (typeof paintFullscreen === 'function') paintFullscreen();

    // v7: the picked name, the site line and the date follow the language too.
    if (pickedEmp && !ENROLLING) renderPick(); else if (!pickedEmp) resetEnrollIdle();
    const site = siteBySlug(ACTIVE_SITE);
    if (site) applySiteLabels(site); else setText('brand-sub', t('brand.sub'));
    renderSiteSwitcher();
    renderRoster();
    updateClock();
    window.dispatchEvent(new CustomEvent('langchange', { detail: { lang: LANG } }));
}

function setLanguage(lang) {
    if (!I18N[lang]) return;
    LANG = lang;
    try { localStorage.setItem('jeyanco_lang', lang); } catch (e) { /* ignore */ }
    applyLanguage();
}

// ── HELPERS ──────────────────────────────────────────────────────────────
async function safeParseJSON(res) {
    const text = await res.text();
    try { return JSON.parse(text); }
    catch (e) { console.error('Non-JSON:', text.slice(0, 300)); throw new Error('Server did not return JSON'); }
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function apiFetch(path, options = {}, timeoutMs = 8000) {
    return fetch(`${apiBase}${path}`, { cache: 'no-store', ...options, signal: AbortSignal.timeout(timeoutMs) });
}

function flaskFetch(path, options = {}, timeoutMs = 8000) {
    return fetch(`${flaskBase}${path}`, { cache: 'no-store', ...options, signal: AbortSignal.timeout(timeoutMs) });
}

function setText(id, txt) { const el = document.getElementById(id); if (el) el.textContent = txt; }

/** True when Flask answered "I do not have that route" rather than answering. */
function isMissingRoute(res) { return res.status === 404 || res.status === 405 || res.status === 501; }

// ── SITES ────────────────────────────────────────────────────────────────
// Picked BY HAND. Nothing derives it — not GPS, not the tracker. The pick
// goes to the Pi (for every record and the GPS tracker) and on to the web
// (for the dashboard map), and the chip says whether the web has it.
let SITES       = [];
let ACTIVE_SITE = null;
let KIOSK_CODE  = null;
let SITE_SYNC   = null;      // 'saving' | 'ok' | 'bad'
let siteRetry   = null;

window.KIOSK_ACTIVE_SITE = null;
window.jeyanco = window.jeyanco || {};

function siteBySlug(slug) { return SITES.find(s => s.slug === slug) || null; }

/** The site + device stamp a request needs when it cannot go through Flask. */
function siteStamp() {
    const site  = siteBySlug(ACTIVE_SITE);
    const stamp = {};
    if (KIOSK_CODE)      stamp.kiosk_code = KIOSK_CODE;
    if (site && site.id) stamp.site_id    = site.id;
    return stamp;
}

function showSiteWarning(msg) {
    const el = document.getElementById('site-warning');
    if (!el) return;
    if (!msg) { el.style.display = 'none'; el.innerHTML = ''; return; }
    el.innerHTML = `<i class="fas fa-triangle-exclamation"></i> ${escapeHtml(msg)}`;
    el.style.display = 'flex';
}

async function loadSites() {
    let sites = [];
    let activeFromPi = null;

    try {
        const data = await safeParseJSON(await flaskFetch('/sites', {}, 8000));
        if (data.success && Array.isArray(data.sites)) {
            sites        = data.sites;
            activeFromPi = data.active && data.active.slug ? data.active.slug : null;
            KIOSK_CODE   = data.kiosk_code || null;
        }
    } catch (e) { console.warn('Flask /sites unreachable:', e.message); }

    if (!sites.length) {
        try {
            const data = await safeParseJSON(await apiFetch('/sites', { headers: { 'Accept': 'application/json' } }));
            if (data.success && Array.isArray(data.sites)) sites = data.sites;
        } catch (e) { console.warn('Laravel /sites unreachable:', e.message); }
    }

    if (!sites.length) {
        try { sites = JSON.parse(localStorage.getItem('jeyanco_sites_cache') || '[]'); } catch (e) { sites = []; }
    } else {
        try { localStorage.setItem('jeyanco_sites_cache', JSON.stringify(sites)); } catch (e) { /* ignore */ }
    }

    SITES = sites
        .filter(s => s && s.name)
        .map(s => ({
            id: s.id,
            slug: s.slug || String(s.name).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-'),
            name: s.name,
            location: s.location || '',
        }))
        .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));

    if (!SITES.length) {
        showSiteWarning(t('site.none') + t('site.nonehint'));
        renderSiteSwitcher();
        return;
    }

    let remembered = null;
    try { remembered = localStorage.getItem('jeyanco_active_site'); } catch (e) { /* ignore */ }
    const wanted = [activeFromPi, remembered].find(slug => slug && siteBySlug(slug));

    // Only push the site again when it is not already what the kiosk holds;
    // a five-minute refresh should not re-announce the same site.
    const slug = wanted || SITES[0].slug;
    if (slug !== ACTIVE_SITE) await setActiveSite(slug);
    else renderSiteSwitcher();
}

function renderSiteSwitcher() {
    const wrap = document.getElementById('site-switch-btns');
    if (!wrap) return;

    if (!SITES.length) {
        wrap.innerHTML = `<span class="site-loading">${escapeHtml(t('site.none'))}</span>`;
        return;
    }

    wrap.innerHTML = SITES.map(s => `
        <button type="button" class="site-opt${s.slug === ACTIVE_SITE ? ' active' : ''}"
                data-site="${escapeHtml(s.slug)}" title="${escapeHtml(s.location || s.name)}">
            <i class="fas fa-location-dot"></i>${escapeHtml(s.name)}
        </button>`).join('');

    wrap.querySelectorAll('.site-opt').forEach(btn => {
        btn.addEventListener('click', () => setActiveSite(btn.dataset.site));
    });

    const cur  = document.getElementById('site-bar-current');
    const site = siteBySlug(ACTIVE_SITE);
    if (!cur) return;

    const chip = SITE_SYNC === 'ok'     ? `<span class="site-sync ok"><i class="fas fa-cloud-arrow-up"></i>${escapeHtml(t('site.saved'))}</span>`
               : SITE_SYNC === 'saving' ? `<span class="site-sync saving"><i class="fas fa-rotate"></i>${escapeHtml(t('site.saving'))}</span>`
               : SITE_SYNC === 'bad'    ? `<span class="site-sync bad"><i class="fas fa-triangle-exclamation"></i>${escapeHtml(t('site.notsaved'))}</span>`
               : '';

    cur.innerHTML = site
        ? `<i class="fas fa-circle-check"></i> ${escapeHtml(site.name)}`
          + (site.location ? `<small>${escapeHtml(site.location)}</small>` : '') + chip
        : `<span class="site-pick-hint">${escapeHtml(t('site.pick'))}</span>`;
}

// v7: switching sites is quick now. The button lights up at once, the lists
// empty and say "Loading…" at once, and they reload the moment the Pi has
// the new site — they no longer wait for the web to confirm it (up to 8 s),
// and the ATTENDANCE board no longer waits for its next 15-second refresh.
// Tapping several sites quickly is safe: the Pi is told in order, and only
// the last tap reloads the lists.
let siteToken = 0;
let piQueue   = Promise.resolve();

function postSiteToPi(site) {
    const run = async () => {
        try {
            const data = await safeParseJSON(await flaskFetch('/set-site', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ site: site.slug }),
            }, 5000));
            if (!data.success) throw new Error(data.message || 'The Pi did not accept the site');
            const warn = document.getElementById('site-warning');
            if (warn && warn.textContent.includes(site.name)) showSiteWarning(null);
            else if (SITE_SYNC !== 'bad') showSiteWarning(null);
            return true;
        } catch (e) {
            showSiteWarning(t('site.notpi', { site: site.name, err: e.message }));
            return false;
        }
    };
    const p = piQueue.then(run, run);
    piQueue = p.catch(() => {});
    return p;
}

async function setActiveSite(slug) {
    const site = siteBySlug(slug);
    if (!site) return;

    const changed = slug !== ACTIVE_SITE;
    const token   = ++siteToken;

    ACTIVE_SITE = slug;
    window.KIOSK_ACTIVE_SITE = slug;
    try { localStorage.setItem('jeyanco_active_site', slug); } catch (e) { /* ignore */ }

    applySiteLabels(site);
    SITE_SYNC = 'saving';
    renderSiteSwitcher();

    // The old site's names must not stay on screen while the new ones load.
    if (changed) {
        clearRosterForSite();
        window.dispatchEvent(new CustomEvent('sitechanging', { detail: { site: slug, id: site.id } }));
    }

    // 1. The Pi. Attendance, enrolment, the board and the GPS tracker read it there.
    await postSiteToPi(site);
    if (token !== siteToken) return;          // a newer tap took over

    // 2. Reload what this site shows — straight away.
    window.dispatchEvent(new CustomEvent('sitechange', { detail: { site: slug, id: site.id } }));
    loadRoster();
    loadKioskSettings();

    // 3. The web, in the background — so the map knows before anyone scans.
    pushSiteToWeb();
}

async function pushSiteToWeb() {
    const site = siteBySlug(ACTIVE_SITE);
    if (!site) return;
    if (siteRetry) { clearTimeout(siteRetry); siteRetry = null; }
    const slug = site.slug;

    try {
        const res  = await apiFetch('/active-site', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ kiosk_code: KIOSK_CODE || undefined, site_id: site.id, site: site.slug }),
        }, 8000);
        const data = await safeParseJSON(res);
        if (slug !== ACTIVE_SITE) return;           // another site was picked meanwhile
        if (!data.success) throw new Error(data.message || 'rejected');
        SITE_SYNC = 'ok';
        const warn = document.getElementById('site-warning');
        if (warn && warn.textContent.includes('web')) showSiteWarning(null);
    } catch (e) {
        if (slug !== ACTIVE_SITE) return;
        SITE_SYNC = 'bad';
        showSiteWarning(t('site.retry', { site: site.name }));
        siteRetry = setTimeout(pushSiteToWeb, 30000);
    }
    renderSiteSwitcher();
}

function applySiteLabels(site) {
    setText('brand-sub', `${site.name} — ${t('brand.sub')}`);
    setText('att-site-label', site.location ? `${site.name} — ${site.location}` : site.name);
    document.title = `Jeyanco Construction — ${site.name} Kiosk`;
}

function refreshSites() {
    loadSites()
        .catch(e => {
            console.warn('loadSites failed:', e && e.message);
            showSiteWarning(t('site.loadfail'));
        })
        .finally(() => { renderSiteSwitcher(); loadKioskSettings(); });
}

// ── SETTINGS FROM THE WEB ────────────────────────────────────────────────
// System Settings → Kiosk decides whether the ATTENDANCE tab shows the TIME
// IN / TIME OUT buttons or records from the scan alone, and the hours of each
// shift. Checked every few seconds, and remembered, so a kiosk that boots
// without signal keeps its mode and its hours.
const SETTINGS_CACHE_KEY = 'jeyanco_kiosk_settings';
const DEFAULT_SETTINGS = { mode: 'buttons', repeat_guard_seconds: 180, idle_return_seconds: 60, shifts: [] };

let KIOSK_SETTINGS = (() => {
    try {
        const cached = JSON.parse(localStorage.getItem(SETTINGS_CACHE_KEY) || 'null');
        if (cached && cached.mode) return Object.assign({}, DEFAULT_SETTINGS, cached);
    } catch (e) { /* ignore */ }
    return Object.assign({}, DEFAULT_SETTINGS);
})();

function normaliseSettings(att) {
    const out = Object.assign({}, DEFAULT_SETTINGS);
    out.mode = att && att.mode === 'auto' ? 'auto' : 'buttons';
    out.repeat_guard_seconds = Math.max(0, parseInt(att && att.repeat_guard_seconds, 10) || 180);
    out.idle_return_seconds  = Math.max(15, parseInt(att && att.idle_return_seconds, 10) || 60);
    out.shifts = Array.isArray(att && att.shifts) ? att.shifts.filter(s => s && s.am_start && s.pm_end) : [];
    return out;
}

// The version of the settings this kiosk holds. Sent with every question;
// the web answers "same" until something is saved there.
const SETTINGS_V_KEY = 'jeyanco_kiosk_settings_v';
let SETTINGS_V = null;
try { SETTINGS_V = localStorage.getItem(SETTINGS_V_KEY); } catch (e) { /* ignore */ }

let settingsBusy = false;
async function loadKioskSettings() {
    if (settingsBusy) return;
    settingsBusy = true;
    try {
        const params = siteStamp();
        if (SETTINGS_V) params.v = SETTINGS_V;
        const qs   = new URLSearchParams(params).toString();
        const data = await safeParseJSON(await apiFetch('/settings' + (qs ? '?' + qs : ''),
            { headers: { 'Accept': 'application/json' } }, 8000));

        if (data && data.success && data.same) return;      // nothing changed

        if (data && data.success && data.attendance) {
            const next    = normaliseSettings(data.attendance);
            const changed = JSON.stringify(next) !== JSON.stringify(KIOSK_SETTINGS);
            KIOSK_SETTINGS = next;
            SETTINGS_V     = data.v || null;
            try {
                localStorage.setItem(SETTINGS_CACHE_KEY, JSON.stringify(next));
                if (SETTINGS_V) localStorage.setItem(SETTINGS_V_KEY, SETTINGS_V); else localStorage.removeItem(SETTINGS_V_KEY);
            } catch (e) { /* ignore */ }
            if (changed) {
                window.dispatchEvent(new CustomEvent('kiosksettings', { detail: next }));
                updateSessionPill();
            }
        }
    } catch (e) {
        // Keep the last known settings — the scanner must not stop over this.
        console.warn('settings unreachable:', e.message);
    } finally {
        settingsBusy = false;
    }
}

// ── TABS ─────────────────────────────────────────────────────────────────
function currentTab() {
    const el = document.querySelector('.tab-content.active');
    return el ? el.id.replace(/^tab-/, '') : 'attendance';
}

function switchTab(tab, btn) {
    const panel = document.getElementById(`tab-${tab}`);
    if (!panel) return;

    document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    panel.classList.add('active');
    (btn || document.querySelector(`.tab-btn[data-tab="${tab}"]`))?.classList.add('active');
    lastTouch = Date.now();

    if (tab === 'attendance') { if (window.startAttendancePolling) window.startAttendancePolling(); }
    else if (window.stopAttendancePolling) window.stopAttendancePolling();

    if (tab === 'summary') { if (window.startSummaryPolling) window.startSummaryPolling(); }
    else if (window.stopSummaryPolling) window.stopSummaryPolling();

    if (tab === 'register') { loadRoster(); startRosterPolling(); } else stopRosterPolling();
}

// ── IDLE RETURN ──────────────────────────────────────────────────────────
// Left on another tab, the kiosk goes back to ATTENDANCE by itself — the
// gate must never be left showing the payroll screen or the enrol list.
// It never interrupts an enrolment or an open payroll.
let lastTouch = Date.now();
let ENROLLING = false;

function watchIdle() {
    ['pointerdown', 'keydown', 'touchstart'].forEach(ev =>
        document.addEventListener(ev, () => { lastTouch = Date.now(); }, { passive: true, capture: true }));

    setInterval(() => {
        const tab = currentTab();
        if (tab === 'attendance') return;
        if (Date.now() - lastTouch < (KIOSK_SETTINGS.idle_return_seconds || 60) * 1000) return;
        if (tab === 'register' && ENROLLING) return;
        if (tab === 'askai' && typeof window.aiSessionActive === 'function' && window.aiSessionActive()) return;
        switchTab('attendance');
    }, 5000);
}


// ── LISTS THAT SCROLL BY THEMSELVES ──────────────────────────────────────
// Two lists outgrow their box: the workers on the ENROLL tab and the board
// on ATTENDANCE. Nobody at the gate should have to find a scroll bar, so
// they walk down by themselves — a row every couple of seconds, a rest at
// each end, and back to the top. A touch stops it; it picks up again after
// a quiet spell. It never moves a list that already fits, and never while
// somebody is choosing a name.
const AUTO_SCROLL = { rowSeconds: 2, restMs: 5000, touchMs: 15000, upMs: 1200 };

const autoBoxes = [
    { sel: '#tab-register .roster-list', chip: 'roster-autochip', tab: 'register',
      idle: () => !ENROLLING && !pickedEmp },
    { sel: '#tab-attendance .monitor-table-wrap', chip: 'mon-autochip', tab: 'attendance',
      idle: () => !document.querySelector('.emp-modal-overlay.open') },
];

let autoFrame = null, autoLast = 0;

function autoScrollTick(t) {
    autoFrame = requestAnimationFrame(autoScrollTick);
    const dt = autoLast ? Math.min(100, t - autoLast) : 0;
    autoLast = t;
    const now = Date.now();
    const tab = currentTab();

    autoBoxes.forEach(b => {
        const box  = document.querySelector(b.sel);
        const chip = document.getElementById(b.chip);
        if (!box) return;

        const room = box.scrollHeight - box.clientHeight;
        if (chip) chip.hidden = room <= 2 || b.tab !== tab;
        if (room <= 2) { b.state = 'rest'; b.until = 0; return; }
        if (b.tab !== tab || !b.idle()) { b.pos = box.scrollTop; return; }
        if (now < (b.held || 0)) { b.pos = box.scrollTop; return; }
        if (now < (b.until || 0)) return;

        if (b.state !== 'down' && b.state !== 'up') {
            b.state = box.scrollTop >= room - 1 ? 'up' : 'down';
            b.pos   = box.scrollTop;
        }
        if (b.state === 'down') {
            const row = box.querySelector('tbody tr, .roster-item');
            b.pos = Math.min(room, (b.pos || 0) + (row ? row.offsetHeight : 40) / (AUTO_SCROLL.rowSeconds * 1000) * dt);
            box.scrollTop = b.pos;
            if (b.pos >= room - 1) { b.state = 'rest'; b.until = now + AUTO_SCROLL.restMs; }
        } else {
            b.pos = Math.max(0, (b.pos || 0) - room / AUTO_SCROLL.upMs * dt);
            box.scrollTop = b.pos;
            if (b.pos <= 0) { b.state = 'rest'; b.until = now + AUTO_SCROLL.restMs; }
        }
    });
}

function watchAutoScroll() {
    autoBoxes.forEach(b => {
        const box = document.querySelector(b.sel);
        if (!box || box.dataset.autoWatched) return;
        box.dataset.autoWatched = '1';
        const hold = () => { b.held = Date.now() + AUTO_SCROLL.touchMs; b.state = 'rest'; b.until = 0; };
        ['pointerdown', 'wheel', 'touchstart'].forEach(ev => box.addEventListener(ev, hold, { passive: true }));
    });
    if (!autoFrame) autoFrame = requestAnimationFrame(autoScrollTick);
}

// ── CLOCK ────────────────────────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    setText('digital-clock', now.toLocaleTimeString('en-GB'));
    let date;
    try {
        date = now.toLocaleDateString(LANG === 'tl' ? 'fil-PH' : 'en-US', {
            weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    } catch (e) {
        date = now.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
    }
    setText('digital-date', date.toUpperCase());
    updateSessionPill();
}

/**
 * AM, PM or NIGHT — read from the hours set on the web (kiosk-clock.js knows
 * them). Without them, the old rule: 6 PM to 6 AM is night, noon splits the day.
 */
function updateSessionPill() {
    const pill = document.getElementById('session-pill');
    const lbl  = document.getElementById('session-label');
    if (!pill || !lbl) return;
    // v8: the pill follows the same shift as the rest of the screen. Outside
    // the day shift, with no night shift set on the web, it says OFF-SHIFT —
    // it used to say NIGHT SHIFT by the clock while everything else said DAY.
    let mode = window.jeyanco && window.jeyanco.pillMode ? window.jeyanco.pillMode() : null;
    if (!mode) {
        if (window.jeyanco && window.jeyanco.pillMode) mode = 'off';
        else { const h = new Date().getHours(); mode = (h >= 18 || h < 6) ? 'night' : (h < 12 ? 'am' : 'pm'); }
    }
    lbl.textContent = t('pill.' + mode);
    const icon = pill.querySelector('i');
    if (icon) icon.className = mode === 'am' ? 'fas fa-sun' : mode === 'pm' ? 'fas fa-cloud-sun' : mode === 'off' ? 'fas fa-power-off' : 'fas fa-moon';
    pill.classList.toggle('pm', mode === 'pm');
    pill.classList.toggle('off', mode === 'off');
    pill.classList.toggle('night', mode === 'night');
}

// ── WINDOW CONTROLS ──────────────────────────────────────────────────────
// ⛶ toggles full screen; ▁ asks the Pi to hide the window and must be held
// for 2 seconds (the Pi brings it back after 5 minutes on its own).
//
// v7 — why both looked dead on a freshly opened kiosk:
//   • The Pi opens Chromium already full screen (kiosk mode). The page did
//     not know that, so ⛶ showed "expand", and a tap only asked for a full
//     screen the kiosk already had — nothing moved. Now the button reads the
//     real state on load, and says so when the full screen is the Pi's.
//   • ▁ only works when HELD. A quick tap did nothing and said nothing; now
//     it says "hold for 2 seconds". On a touch screen the hold was also cut
//     short by the tiniest slide of the finger; the button now keeps the
//     finger until it is lifted.
const HOLD_MS = 2000;
let winTipTimer = null;

function showWinTip(msg, ms) {
    const tip = document.getElementById('win-tip');
    if (!tip) return;
    clearTimeout(winTipTimer);
    tip.textContent = msg; tip.hidden = false;
    if (ms) winTipTimer = setTimeout(() => { tip.hidden = true; }, ms);
}

/** Full screen of any kind: the page's own, or the one the Pi started Chromium in. */
function kioskIsFull() {
    if (document.fullscreenElement) return true;
    try { if (window.matchMedia('(display-mode: fullscreen)').matches) return true; } catch (e) { /* old browser */ }
    return window.innerWidth >= screen.width - 2 && window.innerHeight >= screen.height - 2;
}

function paintFullscreen() {
    const fs = document.getElementById('btn-fullscreen');
    if (!fs) return;
    const on = kioskIsFull();
    fs.classList.toggle('on', on);
    fs.innerHTML = `<i class="fas fa-${on ? 'compress' : 'expand'}"></i>`;
    fs.title = on ? t('win.exit') : t('win.enter');
}

function initWindowControls() {
    const fs  = document.getElementById('btn-fullscreen');
    const min = document.getElementById('btn-minimize');

    if (fs) {
        fs.addEventListener('click', async () => {
            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                    setTimeout(paintFullscreen, 300);
                    return;
                }
                // v8: the Pi presses Chromium's own F11 — the only way in and
                // out of the full screen Chromium was started in.
                const pi = await askWindowService('/window/fullscreen');
                if (pi.ok) { setTimeout(paintFullscreen, 600); setTimeout(paintFullscreen, 1500); return; }
                if (pi.code) { showWinTip(windowProblem(pi), 7000); return; }
                if (kioskIsFull()) {
                    // Full screen set by the Pi's kiosk mode — a web page is not
                    // allowed to leave it. Say so instead of doing nothing.
                    showWinTip(t('win.kiosk'), 4000);
                } else {
                    await document.documentElement.requestFullscreen();
                }
            } catch (e) {
                console.warn('fullscreen:', e.message);
            }
            setTimeout(paintFullscreen, 300);
        });
        document.addEventListener('fullscreenchange', paintFullscreen);
        window.addEventListener('resize', paintFullscreen);
        paintFullscreen();
        // Chromium can finish going full screen a moment after the page loads.
        setTimeout(paintFullscreen, 1500);
        setTimeout(paintFullscreen, 5000);
    }

    if (min) {
        let started = 0, frame = null, pid = null;

        const stop = (e) => {
            if (e && pid !== null && e.pointerId !== pid) return;
            const heldFor = started ? Date.now() - started : 0;
            if (frame) cancelAnimationFrame(frame);
            frame = null; started = 0; pid = null;
            min.classList.remove('holding'); min.style.removeProperty('--hold');
            // Let go too soon: tell them how, instead of silence.
            if (heldFor > 0 && heldFor < HOLD_MS) showWinTip(t('win.hold'), 2500);
        };

        const tick = () => {
            if (!started) return;
            const pct = Math.min(100, ((Date.now() - started) / HOLD_MS) * 100);
            min.style.setProperty('--hold', pct.toFixed(0));
            showWinTip(`${t('win.holding')} ${Math.max(0, (HOLD_MS - (Date.now() - started)) / 1000).toFixed(1)}s`);
            if (pct >= 100) {
                started = 0;
                stop();
                showWinTip('…', 0);
                minimizeWindow();
                return;
            }
            frame = requestAnimationFrame(tick);
        };

        min.addEventListener('pointerdown', (e) => {
            if (e.button !== undefined && e.button > 0) return;
            e.preventDefault();
            pid = e.pointerId;
            try { min.setPointerCapture(e.pointerId); } catch (x) { /* ignore */ }
            started = Date.now();
            min.classList.add('holding');
            frame = requestAnimationFrame(tick);
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(ev => min.addEventListener(ev, stop));
        min.addEventListener('contextmenu', e => e.preventDefault());   // a long press is not a right-click
    }
}

/**
 * v8: ask testing.py to do something to the window. { ok } on success,
 * { code, msg, tool } when the Pi answered but could not, {} when there is
 * no window service at all (an older testing.py).
 */
async function askWindowService(path) {
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const res  = await flaskFetch(path, { method: 'POST' }, 6000);
            if (isMissingRoute(res)) return {};
            const data = await safeParseJSON(res);
            if (data.success) return { ok: true };
            return { code: data.code || 'failed', msg: data.message || '', tool: data.tool || '' };
        } catch (e) {
            console.warn(path + ' (try ' + (attempt + 1) + '):', e.message);
            // Right after the Pi boots the service can be a moment late.
            if (attempt === 0) await new Promise(r => setTimeout(r, 1000));
        }
    }
    return {};
}

/** What the Pi said, in the language on screen. */
function windowProblem(p) {
    switch (p.code) {
        case 'kiosk_mode': return t('win.kioskflag');
        case 'no_tool':    return t('win.notool', { tool: p.tool || 'wlrctl wtype xdotool' });
        case 'no_display': return t('win.nodisplay');
        case 'no_window':  return t('win.nowindow');
        default:           return t('win.failed', { msg: p.msg || p.code });
    }
}

async function minimizeWindow() {
    const pi = await askWindowService('/window/minimize');
    if (pi.ok) { showWinTip(t('win.hidden'), 2500); return; }
    if (pi.code) { showWinTip(windowProblem(pi), 7000); return; }
    // No window service on this Pi: leaving the page's own full screen is the
    // most the browser can do by itself.
    if (document.fullscreenElement) { try { await document.exitFullscreen(); } catch (x) { /* ignore */ } }
    showWinTip(t('win.nomin'), 3500);
}

// ── DRAG TO SCROLL ───────────────────────────────────────────────────────
// v7: the lists move under the finger. Press anywhere on the names and drag
// up or down — no scroll bar to find (it is hidden now). A short tap still
// picks a name or opens a row; only a drag of more than a few pixels
// scrolls, and a flick keeps gliding for a moment.
//
// A touch screen that Chromium treats as a real touch screen already scrolls
// by itself; this is for the one that arrives as a mouse, which is how most
// Pi touch screens show up — and why only the scroll bar used to work.
const DRAG_SCROLL = ['#roster-list', '.monitor-table-wrap', '#sum-attn', '#ai-pay-card'];
const DRAG_MIN_PX = 8;

function enableDragScroll(box) {
    if (!box || box.dataset.dragScroll) return;
    box.dataset.dragScroll = '1';

    let pid = null, startY = 0, startTop = 0, moved = false, suppress = false;
    let lastY = 0, lastT = 0, vel = 0, glide = null;

    const stopGlide = () => { if (glide) cancelAnimationFrame(glide); glide = null; };

    box.addEventListener('pointerdown', e => {
        stopGlide();
        suppress = false;
        if (e.pointerType === 'touch') return;          // real touch: the browser scrolls it
        if (e.button !== undefined && e.button > 0) return;
        pid = e.pointerId; moved = false;
        startY = lastY = e.clientY; startTop = box.scrollTop;
        lastT = performance.now(); vel = 0;
    });

    box.addEventListener('pointermove', e => {
        if (e.pointerId !== pid) return;
        const dy = e.clientY - startY;
        if (!moved) {
            if (Math.abs(dy) < DRAG_MIN_PX) return;
            moved = true;
            box.classList.add('drag-scrolling');
            try { box.setPointerCapture(pid); } catch (x) { /* ignore */ }
        }
        box.scrollTop = startTop - dy;
        const now = performance.now(), dt = now - lastT;
        if (dt > 0) vel = 0.8 * ((lastY - e.clientY) / dt) + 0.2 * vel;   // px per ms
        lastY = e.clientY; lastT = now;
        e.preventDefault();
    });

    const end = e => {
        if (e.pointerId !== pid) return;
        pid = null;
        if (!moved) return;
        moved = false;
        suppress = true;                                // this was a drag, not a tap
        box.classList.remove('drag-scrolling');
        if (performance.now() - lastT > 80) vel = 0;    // held still before letting go
        let prev = performance.now();
        const step = now => {
            const dt = Math.min(40, now - prev); prev = now;
            vel *= Math.pow(0.94, dt / 16);
            if (Math.abs(vel) < 0.02) { glide = null; return; }
            const before = box.scrollTop;
            box.scrollTop += vel * dt;
            if (box.scrollTop === before) { glide = null; return; }   // hit an end
            glide = requestAnimationFrame(step);
        };
        if (Math.abs(vel) > 0.05) glide = requestAnimationFrame(step);
    };
    box.addEventListener('pointerup', end);
    box.addEventListener('pointercancel', end);

    // The click that follows a drag must not pick a name or open a row.
    box.addEventListener('click', e => {
        if (suppress) { suppress = false; e.stopPropagation(); e.preventDefault(); }
    }, true);
    box.addEventListener('dragstart', e => e.preventDefault());
    box.addEventListener('wheel', stopGlide, { passive: true });
}

function watchDragScroll() {
    DRAG_SCROLL.forEach(sel => document.querySelectorAll(sel).forEach(enableDragScroll));
}

// ── FINGERPRINT RING ─────────────────────────────────────────────────────
function setRing(ringId, iconId, state) {
    const ring = document.getElementById(ringId);
    const icon = document.getElementById(iconId);
    if (!ring || !icon) return;
    ring.className = 'fp-ring';
    const icons = { success: 'fa-check', error: 'fa-times', muted: 'fa-hand-pointer' };
    icon.className = 'fas fp-icon ' + (icons[state] || 'fa-fingerprint');
    if (state && state !== 'idle') ring.classList.add(state);
}

// ═════════════════════════════════════════════════════════════════════════
//  ENROL TAB — names from the web, finger from here
// ═════════════════════════════════════════════════════════════════════════
// Who still needs a fingerprint, for the SUMMARY tiles — the two counters
// that used to sit above this list.
let ROSTER_COUNTS = { pending: 0, enrolled: 0, total: 0 };

let roster       = [];
let pickedEmp    = null;
let enrolledSlot = null;
let rosterTimer  = null;
let knownIds     = null;
let rosterGen    = 0;      // v7: an answer for an older site is thrown away

/** v7: a new site was tapped — its list, not the old one, is what shows next. */
function clearRosterForSite() {
    rosterGen++;
    roster = [];
    knownIds = null;          // the new site's names are not "NEW"
    ROSTER_COUNTS = { pending: 0, enrolled: 0, total: 0 };
    if (pickedEmp && !ENROLLING) {
        pickedEmp = null; enrolledSlot = null;
        resetRegSteps(); rosterStatus('');
        setRing('reg-ring', 'reg-icon', 'idle');
        resetEnrollIdle();
        const btn = document.getElementById('reg-start-btn');
        if (btn) btn.disabled = true;
    }
    const list = document.getElementById('roster-list');
    if (list) { list.innerHTML = `<div class="roster-empty">${escapeHtml(t('enr.loading'))}</div>`; list.scrollTop = 0; }
    window.dispatchEvent(new CustomEvent('rostercounts', { detail: ROSTER_COUNTS }));
}

function rosterStatus(msg, kind) {
    const el = document.getElementById('reg-status');
    if (!el) return;
    el.className   = 'reg-status' + (kind ? ' ' + kind : '');
    el.textContent = msg || '';
}

/** The enrol panel with nobody picked. */
function resetEnrollIdle() {
    const who = document.getElementById('enroll-who');
    if (who) {
        who.classList.remove('has-pick');
        who.innerHTML = `<i class="fas fa-hand-pointer enroll-who-icon"></i>
                         <p class="enroll-who-text">${escapeHtml(t('enr.pick'))}</p>`;
    }
    if (!ENROLLING) {
        setText('reg-status-text', t('enr.pickfirst'));
        setText('reg-hint', t('enr.detailsweb'));
    }
}

function startRosterPolling() { if (rosterTimer) clearInterval(rosterTimer); rosterTimer = setInterval(loadRoster, 12000); }
function stopRosterPolling()  { if (rosterTimer) { clearInterval(rosterTimer); rosterTimer = null; } }

async function loadRoster() {
    const list = document.getElementById('roster-list');
    if (!list) return;
    const gen = rosterGen;

    try {
        let data;
        const viaFlask = await flaskFetch('/roster', { headers: { 'Accept': 'application/json' } }, 10000).catch(() => null);

        if (viaFlask && !isMissingRoute(viaFlask)) {
            data = await safeParseJSON(viaFlask);
        } else {
            const qs = new URLSearchParams(siteStamp()).toString();
            data = await safeParseJSON(await apiFetch('/roster' + (qs ? '?' + qs : ''),
                { headers: { 'Accept': 'application/json' } }, 10000));
        }

        if (gen !== rosterGen) return;          // the site changed while this was loading
        if (!data.success) throw new Error(data.message || t('enr.failed'));

        const incoming = data.employees || [];
        if (knownIds !== null) incoming.forEach(e => { if (!knownIds.has(e.id)) e.just_arrived = true; });
        knownIds = new Set(incoming.map(e => e.id));

        roster = incoming;
        // The picked worker may have been removed on the web meanwhile.
        if (pickedEmp && !ENROLLING && !roster.some(e => e.id === pickedEmp.id)) { pickedEmp = null; resetEnrollIdle(); }
        renderRoster();

        const c = data.counts || {};
        ROSTER_COUNTS = { pending: c.pending || 0, enrolled: c.enrolled || 0, total: incoming.length };
        window.dispatchEvent(new CustomEvent('rostercounts', { detail: ROSTER_COUNTS }));
    } catch (err) {
        if (gen !== rosterGen) return;
        list.innerHTML = `<div class="roster-empty error"><i class="fas fa-triangle-exclamation"></i>
            ${escapeHtml(t('enr.failed'))}<br>${escapeHtml(err.message || '')}</div>`;
    }
}

function renderRoster() {
    const list = document.getElementById('roster-list');
    if (!list) return;

    if (!roster.length) {
        list.innerHTML = `<div class="roster-empty"><i class="fas fa-user-slash"></i>
            ${escapeHtml(t('enr.empty'))}<br>${escapeHtml(t('enr.emptysub'))}</div>`;
        return;
    }

    list.innerHTML = roster.map(e => {
        const picked = pickedEmp && pickedEmp.id === e.id;
        const badge  = e.enrolled
            ? `<span class="rbadge done"><i class="fas fa-check"></i> ${escapeHtml(t('enr.hasfp'))}</span>`
            : `<span class="rbadge pending"><i class="fas fa-hourglass-half"></i> ${escapeHtml(t('enr.needsfp'))}</span>`;
        const isNew = e.just_arrived || e.is_new;
        const shift = e.shift ? ` &middot; ${escapeHtml(e.shift.name)}` : '';

        return `<button type="button"
                        class="roster-item${picked ? ' picked' : ''}${e.enrolled ? ' is-done' : ''}${e.just_arrived ? ' arrived' : ''}"
                        data-id="${e.id}">
            <div class="roster-main">
                <div class="roster-name">${escapeHtml(e.name)}
                    ${isNew ? `<span class="rnew">${escapeHtml(t('enr.new'))}</span>` : ''}</div>
                <div class="roster-sub">${escapeHtml(e.position || t('worker'))} &middot; ${escapeHtml(e.employment_label || '')}${shift}</div>
            </div>
            ${badge}
        </button>`;
    }).join('');

    list.querySelectorAll('.roster-item').forEach(btn => {
        btn.addEventListener('click', () => pickEmployee(Number(btn.dataset.id)));
    });
}

function pickEmployee(id) {
    if (ENROLLING) return;                    // never swap names mid-capture
    const emp = roster.find(e => e.id === id);
    if (!emp) return;

    pickedEmp = emp; enrolledSlot = null;
    renderRoster(); resetRegSteps();
    setRing('reg-ring', 'reg-icon', 'idle');
    renderPick();
    const btn = document.getElementById('reg-start-btn');
    if (btn) btn.disabled = false;
    rosterStatus('');
}

/** The picked worker, in the language on screen. */
function renderPick() {
    const emp = pickedEmp;
    if (!emp) return;
    const who = document.getElementById('enroll-who');
    if (who) {
        who.innerHTML = `
            <div class="who-name">${escapeHtml(emp.name)}</div>
            <div class="who-sub">${escapeHtml(emp.position || t('worker'))} &middot; ${escapeHtml(emp.employment_label || '')}</div>
            ${emp.enrolled ? `<div class="who-warn"><i class="fas fa-triangle-exclamation"></i>
                 ${escapeHtml(emp.name)} ${escapeHtml(t('enr.already'))}${escapeHtml(String(emp.fingerprint_id))}.
                 ${escapeHtml(t('enr.willreplace'))}</div>` : ''}`;
        who.classList.add('has-pick');
    }

    // After a capture the ring and the text say COMPLETE — leave that alone.
    if (enrolledSlot == null) {
        setText('reg-status-text', (emp.enrolled ? t('enr.replace') : t('enr.ready')) + ' ' + emp.name.toUpperCase());
        setText('reg-hint', t('enr.presskey'));
    }
}

async function startEnroll() {
    if (!pickedEmp) { rosterStatus(t('enr.picknamefirst'), 'error'); return; }
    if (ENROLLING) return;

    const btn = document.getElementById('reg-start-btn');
    btn.disabled = true; enrolledSlot = null;
    ENROLLING = true;
    resetRegSteps();
    setRing('reg-ring', 'reg-icon', 'scanning');
    rosterStatus('');
    setText('reg-status-text', t('enr.preparing'));

    try {
        // v8: the worker's own slot, so replacing his finger is not a "duplicate".
        const allow = pickedEmp.enrolled && pickedEmp.fingerprint_id != null ? pickedEmp.fingerprint_id : null;
        const data = await safeParseJSON(await flaskFetch('/enroll/start', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ allow_slot: allow }) }));
        if (!data.success) { ENROLLING = false; showRegError(data.message || t('enr.notcapt')); btn.disabled = false; return; }
        pollEnroll();
    } catch (err) {
        ENROLLING = false;
        showRegError(t('enr.nosvc'));
        btn.disabled = false;
    }
}

async function pollEnroll() {
    let attempts = 0;
    const btn = document.getElementById('reg-start-btn');
    const poll = setInterval(async () => {
        attempts++;
        if (attempts > 60) {
            clearInterval(poll);
            ENROLLING = false;
            showRegError(t('enr.timeout'));
            btn.disabled = false;
            return;
        }
        try {
            const data = await safeParseJSON(await flaskFetch('/enroll/status', {}, 5000));
            const steps = {
                scanning_1: { txt: t('enr.touch'),    hint: t('enr.press'),      ind: '1' },
                remove:     { txt: t('enr.lift'),     hint: t('enr.liftnow'),    ind: '2' },
                scanning_2: { txt: t('enr.again'),    hint: t('enr.secondtime'), ind: '3' },
                done:       { txt: t('enr.captured'), hint: t('enr.saving'),     ind: '4' },
                error:      { txt: t('enr.notcapt'),  hint: t('enr.tryagain'),   ind: null },
                duplicate:  { txt: t('enr.dup.t'),    hint: '',                  ind: null },
            };
            const st = steps[data.status] || steps.scanning_1;
            setText('reg-status-text', st.txt);
            setText('reg-hint', st.hint);
            if (st.ind) setRegStep(st.ind);

            if (data.status === 'done') {
                clearInterval(poll);
                enrolledSlot = data.fingerprint_id;
                await attachFingerprint();
            } else if (data.status === 'error') {
                clearInterval(poll);
                ENROLLING = false;
                showRegError(data.message || t('enr.notcapt'));
                btn.disabled = false;
            } else if (data.status === 'duplicate') {
                // v8: one finger, one worker. Nothing was saved on the sensor.
                clearInterval(poll);
                ENROLLING = false;
                showDuplicate(data.slot);
                btn.disabled = false;
            }
        } catch (e) { /* keep polling */ }
    }, 1500);
}

async function attachFingerprint() {
    const btn = document.getElementById('reg-start-btn');

    try {
        const body = { employee_id: pickedEmp.id, fingerprint_id: String(enrolledSlot) };
        let out;
        const viaFlask = await flaskFetch('/save-fingerprint', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body) }, 15000).catch(() => null);

        if (viaFlask && !isMissingRoute(viaFlask)) {
            out = await safeParseJSON(viaFlask);
        } else {
            out = await safeParseJSON(await apiFetch('/save-fingerprint', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(Object.assign({}, body, siteStamp())) }, 15000));
        }

        if (!out.success) throw new Error(out.message || t('enr.notsaved'));

        setRing('reg-ring', 'reg-icon', 'success');
        setText('reg-status-text', t('enr.complete'));
        setText('reg-hint', `${pickedEmp.name} — #${enrolledSlot}`);
        rosterStatus(`${pickedEmp.name} ${t('enr.saved')}${enrolledSlot}`, 'success');

        ENROLLING = false;
        await loadRoster();

        setTimeout(() => {
            if (ENROLLING) return;
            pickedEmp = null; enrolledSlot = null;
            setRing('reg-ring', 'reg-icon', 'idle');
            resetEnrollIdle();
            resetRegSteps(); rosterStatus(''); renderRoster();
            if (btn) btn.disabled = true;
        }, 5000);
    } catch (err) {
        ENROLLING = false;
        showRegError(err.message || t('enr.notsaved'));
        rosterStatus(err.message || t('enr.notsaved'), 'error');
    }

    if (btn) btn.disabled = !pickedEmp;
}

/** v8: whose finger it is, from the list on screen when we can. */
function showDuplicate(slot) {
    const owner = roster.find(e => e.enrolled && String(e.fingerprint_id) === String(slot));
    const msg = owner ? t('enr.dup', { name: owner.name, slot }) : t('enr.dupother', { slot });
    setRing('reg-ring', 'reg-icon', 'error');
    setText('reg-status-text', t('enr.dup.t'));
    setText('reg-hint', msg);
    rosterStatus(msg, 'error');
    resetRegSteps();
}

function showRegError(msg) {
    setRing('reg-ring', 'reg-icon', 'error');
    setText('reg-status-text', t('enr.wrong'));
    setText('reg-hint', msg);
}

function setRegStep(step) {
    ['1', '2', '3', '4'].forEach(n => {
        const el = document.getElementById(`reg-ind-${n}`);
        if (!el) return;
        const num = parseInt(n, 10), tgt = parseInt(step, 10);
        el.className = num < tgt ? 'enroll-step done' : num === tgt ? 'enroll-step active' : 'enroll-step';
    });
}

function resetRegSteps() {
    ['1', '2', '3', '4'].forEach(n => {
        const el = document.getElementById(`reg-ind-${n}`);
        if (el) el.className = 'enroll-step';
    });
}

// Older names, kept so nothing that calls them breaks.
async function loadRegisteredList() { return loadRoster(); }
async function loadEmployeeCount()  { return loadRoster(); }

// Handed to kiosk-clock.js, kiosk-summary.js and kiosk-ai.js so they do not
// re-implement any of this.
Object.assign(window.jeyanco, {
    flaskBase, apiBase, apiFetch, flaskFetch,
    parseJSON: safeParseJSON, escapeHtml, setRing, setText, t,
    lang: () => LANG,
    activeSite: () => siteBySlug(ACTIVE_SITE),
    siteStamp, isMissingRoute,
    settings: () => KIOSK_SETTINGS,
    rosterCounts: () => ROSTER_COUNTS,
    reloadSettings: loadKioskSettings,
    currentTab,
    dragScroll: enableDragScroll,
});

// ── INIT ─────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    applyLanguage();
    document.querySelectorAll('.lang-opt').forEach(b => b.addEventListener('click', () => setLanguage(b.dataset.lang)));

    updateClock();
    setInterval(updateClock, 1000);
    watchAutoScroll();
    watchDragScroll();
    initWindowControls();
    watchIdle();

    // The scanner starts first and waits on no network call: the sensor is the
    // whole point of the kiosk and must not hang off anything remote.
    if (window.startAttendancePolling) window.startAttendancePolling();
    loadRoster();

    loadKioskSettings();
    setInterval(loadKioskSettings, SETTINGS_REFRESH_MS);
    // Back from a dropped signal: ask straight away rather than on the next tick.
    window.addEventListener('online', loadKioskSettings);

    refreshSites();
    setInterval(refreshSites, 5 * 60 * 1000);
});
