// ─────────────────────────────────────────────────────────────────────────
//  JEYANCO ATTENDANCE KIOSK — v13 (RSC 2026-10-18)
//
//  Served from the web (/kiosk). Talks to two things:
//    • Laravel on Railway — this page's own site: settings, the server's
//      clock, the board, the roster.
//    • Flask on this Pi (http://127.0.0.1:5000) — the fingerprint sensor, the
//      window controls, and every call that needs the Pi's device key (a
//      scan, enrolment, a worker's DTR or complaint).
//
//  This file: language, the server clock, the site, tabs, idle return, the
//  window buttons, and the ENROLL tab (several fingers per worker).
//  ATTENDANCE is kiosk-clock.js, MY DTR kiosk-dtr.js, REPORT kiosk-report.js.
//
//  v13 against v12: no SUMMARY tab, no payroll card; the clock is the
//  server's from the first paint; MY DTR and REPORT added; a worker is
//  enrolled with two or more fingers; one stylesheet, filling the screen.
// ─────────────────────────────────────────────────────────────────────────

const CFG       = window.JEYANCO || {};
const apiBase   = window.JEYANCO_API_BASE   || CFG.api   || '/api/kiosk';
const flaskBase = window.JEYANCO_FLASK_BASE || CFG.flask || 'http://127.0.0.1:5000';

// How often the kiosk asks the web for its settings and the time.
const SETTINGS_REFRESH_MS = 5 * 1000;

// ── LANGUAGE ─────────────────────────────────────────────────────────────
const I18N = {
    en: {
        'site.waiting': 'Waiting for the office to set this kiosk\'s site', 'site.none': 'No site set',
        'site.loadfail': 'Could not load the site list. The scanner still works.',
        'site.notpi': '"{site}" was not saved on the Pi ({err}). Restart the fingerprint service.',
        'site.nocode': 'This kiosk does not know its code yet — is the fingerprint service running on the Pi?',
        'st.sensor': 'Sensor', 'st.web': 'Server', 'st.sensor.off': 'Sensor offline', 'st.web.off': 'Server offline',
        'tab.attendance': 'ATTENDANCE', 'tab.dtr': 'MY DTR', 'tab.report': 'REPORT', 'tab.enroll': 'ENROLL',
        'dock.peek': 'Tap the screen for the menu', 'theme.toggle': 'Light or dark',
        'pill.am': 'AM SESSION', 'pill.pm': 'PM SESSION', 'pill.night': 'NIGHT SHIFT', 'pill.off': 'OFF-SHIFT',
        'worker': 'Worker', 'shift.of': '{shift} shift',

        'win.minimize': 'Hold to minimize', 'win.holding': 'Keep holding…',
        'win.nomin': 'Minimize needs the kiosk service on the Pi',
        'win.kioskflag': 'Chromium was started with --kiosk, which has no way out of full screen. Use --start-fullscreen in the autostart file.',
        'win.notool': 'Missing on the Pi: {tool}. Run: sudo apt install -y {tool}',
        'win.nodisplay': 'The Pi service cannot find the screen. Restart the fingerprint service after the desktop has loaded.',
        'win.nowindow': 'The Pi cannot find the Chromium window.', 'win.failed': 'The Pi could not do it: {msg}',
        'win.hidden': 'Hidden. It comes back by itself in 5 minutes.',
        'win.hold': 'Hold ▁ for 2 seconds to hide the kiosk', 'win.kiosk': 'The kiosk is in full screen. Hold ▁ for 2 s to hide it.',
        'win.exit': 'Exit full screen', 'win.enter': 'Full screen',

        'mode.buttons': 'BUTTONS', 'mode.auto': 'AUTOMATIC',
        'att.scan': 'SCAN YOUR FINGER',
        'att.press': 'PRESS TIME IN OR TIME OUT', 'att.thenscan': 'Then place your finger on the sensor',
        'att.cap': 'Press first, then scan your finger',
        'att.armed.in': 'TIME IN — SCAN NOW', 'att.armed.out': 'TIME OUT — SCAN NOW',
        'att.within': '{session} · within {n} seconds',
        'att.auto.place': 'PLACE YOUR FINGER', 'att.auto.hint': 'No button needed — the kiosk knows if it is IN or OUT.',
        'att.waitclock': 'WAITING FOR THE SERVER TIME', 'att.waitclock.d': 'Attendance opens once the kiosk has the office\'s time.',
        'att.scanning': 'SCANNING…', 'att.hold': 'Hold still', 'att.processing': 'SAVING…', 'att.saving': 'Saving to the web…',
        'att.notfound': 'FINGER NOT RECOGNISED', 'att.notenrol': 'Not enrolled yet, or the finger slipped. Try again, or ask for the ENROLL tab.',
        'att.pressfirst.t': 'PRESS A BUTTON FIRST', 'att.pressfirst': 'Press TIME IN or TIME OUT first, then scan again.',
        'att.already.t': 'ALREADY TIMED IN', 'att.already': 'Already timed in since {t}. To leave, press TIME OUT.',
        'att.noopen.t': 'NO OPEN TIME IN', 'att.noopen': 'No open time in. If you forgot to time in, ask the office.',
        'att.wrongshift.t': 'REJECTED', 'att.wrongshift': '{shift} shift. TIME IN is open {a}–{b}. Nothing was recorded.',
        'att.justin.t': 'JUST TIMED IN', 'att.justin': 'Timed in a moment ago, at {t}. Press TIME OUT again when you are leaving.',
        'att.justout.t': 'JUST TIMED OUT', 'att.justout': 'Timed out a moment ago, at {t}. Nothing else was recorded.',
        'att.sessdone.srv': '{s} DONE',
        'att.notreg.t': 'NOT REGISTERED YET',
        'att.modebtn.t': 'PRESS A BUTTON', 'att.modebtn': 'The office switched this kiosk to the TIME IN / TIME OUT buttons. Press one, then scan.',
        'att.gps.t': 'LOCATION NOT CONFIRMED', 'att.site.t': 'NOT ASSIGNED TO THIS SITE', 'att.nosite.t': 'NO SITE SET FOR THIS KIOSK',
        'att.stale.t': 'SCAN AGAIN', 'att.stale': 'That scan is too old to record. Scan your finger again.',
        'att.paidfrom': 'Paid hours start at {t}.', 'att.autoclosed': '{session} was closed at {t} as AUTO. The office will review it.',
        'att.ot': '{h} h overtime ({a}–{b}).', 'att.lunch': 'Lunch {a}–{b}. Scan again when you are back.',
        'att.servererr': 'SERVER ERROR', 'att.checkconn': 'Check the internet connection of the kiosk',
        'att.retry': 'Please try again', 'bdg.rejected': 'REJECTED',
        'sess.AM': 'AM SESSION', 'sess.PM': 'PM SESSION', 'sess.n.AM': 'NIGHT · FIRST HALF', 'sess.n.PM': 'NIGHT · SECOND HALF',
        'att.amopen.t': 'TIME OUT {s} FIRST', 'att.amopen': 'Your {s} is still open since {t}. Press TIME OUT first.',
        'att.sessdone.t': '{s} DONE', 'att.sessdone': 'Your {s} is complete (IN {a} · OUT {b}). Next is {n}.',
        'att.daydone.t': 'DONE FOR TODAY', 'att.daydone': 'All your scans for today are recorded. See you tomorrow.',
        'att.afternoon': 'the afternoon', 'att.secondhalf': 'the 2nd half',
        'btn.in': 'TIME IN', 'btn.out': 'TIME OUT', 'res.in': 'TIMED IN', 'res.out': 'TIMED OUT',
        'grp.first': '1ST HALF', 'grp.second': '2ND HALF',

        'mon.title': 'TODAY AT THIS SITE', 'mon.employee': 'Employee',
        'mon.amin': 'AM In', 'mon.amout': 'AM Out', 'mon.pmin': 'PM In', 'mon.pmout': 'PM Out',
        'n.in1': '1st In', 'n.out1': '1st Out', 'n.in2': '2nd In', 'n.out2': '2nd Out', 'mon.status': 'Status',
        'mon.loading': 'Loading attendance…', 'mon.none': 'No attendance yet today', 'mon.failed': 'Cannot load the list — retrying',
        'mon.workingb': 'WORKING', 'mon.doneb': 'DONE', 'mon.lunchb': 'LUNCH', 'mon.notbackb': 'NOT BACK', 'mon.otb': 'OVERTIME',
        'mon.pending': 'Pending registration', 'mon.count': '{n} today · {w} working',
        'mon.f.all': 'All {n}', 'mon.f.working': 'Working', 'mon.f.lunch': 'Lunch', 'mon.f.notback': 'Not back', 'mon.f.none': 'Nobody here right now',

        'dtr.verify': 'SCAN TO OPEN YOUR DTR', 'dtr.privacy': 'Only you can open your own DTR.',
        'dtr.unlock': 'SCAN YOUR FINGER', 'dtr.scanning': 'SCANNING…', 'dtr.loading': 'OPENING YOUR DTR…',
        'dtr.notread': 'NOT READ — TRY AGAIN', 'dtr.noweb': 'CANNOT REACH THE SERVER', 'dtr.nosensor': 'NO CONNECTION TO THE SENSOR',
        'dtr.unknown': 'FINGER NOT REGISTERED', 'dtr.stale': 'Scan your finger again.',
        'dtr.this': 'THIS WEEK', 'dtr.last': 'LAST WEEK', 'dtr.done': 'I AM DONE — CLOSE',
        'dtr.title': 'DAILY TIME RECORD', 'dtr.scanleft': 'Scan your finger on the left to see your DTR.',
        'dtr.timer': 'Closes by itself in {t}', 'dtr.closed': 'Closed for your privacy.',
        'dtr.date': 'Date', 'dtr.in': 'In', 'dtr.out': 'Out', 'dtr.hours': 'Hours', 'dtr.remark': 'Remark',
        'dtr.days': 'Days worked', 'dtr.worked': 'Hours worked', 'dtr.ot': 'Overtime', 'dtr.late': 'Late',
        'dtr.absent': '{n} absent', 'dtr.missing': '{n} missing scan', 'dtr.noscan': 'No scan', 'dtr.period': 'PERIOD',
        'dtr.failed': 'The DTR could not be read. Try again.',
        'Complete': 'Complete', 'Missing scan': 'Missing scan', 'Absent': 'Absent', 'On leave': 'On leave',
        'Rest day': 'Rest day', 'Holiday': 'Holiday', 'In progress': 'In progress',

        'rep.verify': 'SCAN TO SEND A REPORT', 'rep.privacy': 'Your report goes to the office with your name.',
        'rep.unlock': 'SCAN YOUR FINGER', 'rep.title': 'REPORT A PROBLEM', 'rep.scanleft': 'Scan your finger on the left first.',
        'rep.history': 'MY REPORTS', 'rep.cancel': 'CANCEL', 'rep.send': 'SEND TO OFFICE', 'rep.sending': 'SENDING…',
        'rep.pickcat': '1 · What is it about?', 'rep.typemsg': '2 · Tap the keys, or a phrase below…',
        'rep.sent.t': 'SENT TO THE OFFICE', 'rep.sent.d': 'Reference #{id}. The answer will show here the next time you scan.',
        'rep.nohist': 'No reports yet.', 'rep.open': 'OPEN', 'rep.resolved': 'RESOLVED', 'rep.answer': 'Office',
        'rep.left': '{n} left today', 'rep.limit': 'You have sent the most reports allowed today. Please talk to the office.',
        'rep.failed': 'Not sent — {msg}', 'rep.timer': 'Closes in {t}',
        'cat.pay': 'Pay', 'cat.attendance': 'Attendance / DTR', 'cat.safety': 'Safety',
        'cat.equipment': 'Tools & equipment', 'cat.treatment': 'Treatment at work', 'cat.other': 'Other',
        'q.1': 'My time in is missing', 'q.2': 'My hours are wrong', 'q.3': 'My pay is short',
        'q.4': 'Unsafe work area', 'q.5': 'Broken tool or equipment', 'q.6': 'Please call me',

        'enr.capture': 'CAPTURE FINGERPRINT', 'enr.pick': 'Choose a name from the list on the right',
        'enr.pickfirst': 'CHOOSE A NAME FIRST', 'enr.detailsweb': 'Details come from the web — only the fingers are taken here',
        'enr.s1': 'Touch', 'enr.s2': 'Lift', 'enr.s3': 'Again', 'enr.s4': 'Done', 'enr.start': 'START SCAN',
        'enr.workers': 'NEED FINGERPRINTS', 'enr.loading': 'Loading the list…',
        'enr.note': 'New name? Add it in the web system first. Each worker needs {n} different fingers.',
        'enr.empty': 'No workers at this site yet.', 'enr.emptysub': 'Add them in the web system first.',
        'enr.failed': 'Could not load the list.', 'enr.new': 'NEW',
        'enr.alldone': 'Everyone here has their fingers on file.', 'enr.alldonesub': 'New workers appear here on their own.',
        'enr.fingers': '{k} of {n} fingers', 'enr.ready': 'READY — FINGER {k} OF {n}',
        'enr.presskey': 'Press START SCAN', 'enr.usefinger': 'Use a different finger each time (right index, then left index).',
        'enr.picknamefirst': 'Choose a name first.', 'enr.preparing': 'PREPARING THE SENSOR…',
        'enr.touch': 'TOUCH THE SENSOR', 'enr.press': 'Press down and hold still', 'enr.lift': 'LIFT YOUR FINGER',
        'enr.liftnow': 'Raise your finger now', 'enr.again': 'TOUCH AGAIN', 'enr.secondtime': 'Same finger, second time',
        'enr.captured': 'FINGERPRINT CAPTURED', 'enr.saving': 'Saving to the web…', 'enr.notcapt': 'NOT CAPTURED',
        'enr.tryagain': 'Try again', 'enr.wrong': 'SOMETHING WENT WRONG', 'enr.complete': 'COMPLETE',
        'enr.next': 'Finger {k} saved. Now a DIFFERENT finger — press START SCAN.',
        'enr.timeout': 'Timed out. Try again.', 'enr.nosvc': 'The fingerprint service on the Pi is not running.',
        'enr.saved': 'saved — {k} of {n} fingers', 'enr.notsaved': 'Could not save to the web',
        'enr.dup.t': 'FINGER ALREADY REGISTERED', 'enr.dup': 'This finger belongs to {name} (#{slot}). Use another finger.',
        'enr.dupself': 'This finger is already saved for {name}. Use a DIFFERENT finger.',
        'enr.dupother': 'This finger belongs to another worker (#{slot}). Use another finger.',
        'enr.refresh': 'Refresh',

        // v14 — the scan bar and the card a finger opens.
        'att.place': 'PLACE YOUR FINGER ON THE SENSOR', 'att.place.d': 'To time in or time out — your name appears, then choose.',
        'att.place.auto': 'It records IN or OUT by itself.',
        'pop.notin': 'Not timed in yet · {s}', 'pop.isin': 'Timed in since {t} · {s}', 'pop.done': 'Done for today',
        'pop.amdone': 'Morning done (out {t}) · next is the afternoon', 'pop.cancel': 'CANCEL',
        // v14 — REPORT: ready-made requests.
        'rq.missing': 'My time in or out is missing', 'rq.hours': 'My hours are wrong',
        'rq.pay': 'My pay is short or wrong', 'rq.vale': 'A question about my vale (cash advance)',
        'rq.unsafe': 'Unsafe work area', 'rq.tool': 'Broken tool or equipment',
        'rq.people': 'A problem with a co-worker or supervisor', 'rq.call': 'Please call me to the office',
        'rq.own': 'Something else — type it',
        'rep.back': 'BACK', 'rep.addnote': 'ADD A NOTE', 'rep.hidenote': 'HIDE KEYS',
        'rep.typenote': 'Optional: add a few words…', 'rep.typeown': 'Type what happened…',
    },

    tl: {
        'site.waiting': 'Hinihintay ang opisina na itakda ang site ng kiosk', 'site.none': 'Walang site',
        'site.loadfail': 'Hindi makuha ang listahan ng site. Gumagana pa rin ang scanner.',
        'site.notpi': 'Hindi na-save sa Pi ang "{site}" ({err}). I-restart ang fingerprint service.',
        'site.nocode': 'Hindi pa alam ng kiosk ang code nito — tumatakbo ba ang fingerprint service sa Pi?',
        'st.sensor': 'Sensor', 'st.web': 'Server', 'st.sensor.off': 'Patay ang sensor', 'st.web.off': 'Walang server',
        'tab.attendance': 'ATTENDANCE', 'tab.dtr': 'DTR KO', 'tab.report': 'REKLAMO', 'tab.enroll': 'DALIRI',
        'dock.peek': 'Pindutin ang screen para sa menu', 'theme.toggle': 'Maliwanag o madilim',
        'pill.am': 'AM SESSION', 'pill.pm': 'PM SESSION', 'pill.night': 'NIGHT SHIFT', 'pill.off': 'WALANG SHIFT',
        'worker': 'Manggagawa', 'shift.of': 'Shift: {shift}',

        'win.minimize': 'Hawakan para itago', 'win.holding': 'Hawakan pa…',
        'win.nomin': 'Kailangan ang kiosk service sa Pi para mag-minimize',
        'win.kioskflag': 'Naka --kiosk ang Chromium. Gamitin ang --start-fullscreen sa autostart.',
        'win.notool': 'Kulang sa Pi: {tool}. I-run: sudo apt install -y {tool}',
        'win.nodisplay': 'Hindi makita ng service ang screen. I-restart ang fingerprint service.',
        'win.nowindow': 'Hindi makita ng Pi ang Chromium window.', 'win.failed': 'Hindi nagawa ng Pi: {msg}',
        'win.hidden': 'Nakatago. Kusa itong babalik pagkalipas ng 5 minuto.',
        'win.hold': 'Hawakan ang ▁ nang 2 segundo para itago', 'win.kiosk': 'Naka-full screen ang kiosk. Hawakan ang ▁ nang 2 seg para itago.',
        'win.exit': 'Lumabas sa full screen', 'win.enter': 'Buong screen',

        'mode.buttons': 'BUTTONS', 'mode.auto': 'AUTOMATIC',
        'att.scan': 'I-SCAN ANG DALIRI',
        'att.press': 'PINDUTIN ANG PASOK O LABAS', 'att.thenscan': 'Tapos idampi ang daliri sa sensor',
        'att.cap': 'Pindutin muna, tapos i-scan ang daliri',
        'att.armed.in': 'PASOK — I-SCAN NA', 'att.armed.out': 'LABAS — I-SCAN NA',
        'att.within': '{session} · sa loob ng {n} segundo',
        'att.auto.place': 'IDAMPI ANG DALIRI', 'att.auto.hint': 'Walang pipindutin — alam ng kiosk kung PASOK o LABAS.',
        'att.waitclock': 'HINIHINTAY ANG ORAS NG SERVER', 'att.waitclock.d': 'Magbubukas ang attendance kapag nakuha na ang oras ng opisina.',
        'att.scanning': 'BINABASA…', 'att.hold': 'Huwag igalaw', 'att.processing': 'SINE-SAVE…', 'att.saving': 'Sine-save sa web…',
        'att.notfound': 'HINDI NAKILALA ANG DALIRI', 'att.notenrol': 'Wala pang daliri, o dumulas. Subukan ulit, o magpa-enroll.',
        'att.pressfirst.t': 'PINDUTIN MUNA ANG BUTTON', 'att.pressfirst': 'Pindutin muna ang PASOK o LABAS, tapos mag-scan ulit.',
        'att.already.t': 'NAKA-PASOK KA NA', 'att.already': 'Naka-pasok ka na mula {t}. Kung aalis ka, pindutin ang LABAS.',
        'att.noopen.t': 'WALANG BUKAS NA PASOK', 'att.noopen': 'Wala kang bukas na pasok. Kung nakalimutan mo, ipaayos sa opisina.',
        'att.wrongshift.t': 'TINANGGIHAN', 'att.wrongshift': '{shift} shift ka. Bukas ang PASOK mula {a} hanggang {b}. Walang naitala.',
        'att.justin.t': 'KAKA-PASOK LANG', 'att.justin': 'Kaka-pasok mo lang, {t}. Pindutin ulit ang LABAS kapag aalis ka na.',
        'att.justout.t': 'KAKA-LABAS LANG', 'att.justout': 'Kaka-labas mo lang, {t}. Walang ibang naitala.',
        'att.notreg.t': 'HINDI PA REHISTRADO',
        'att.modebtn.t': 'PINDUTIN ANG BUTTON', 'att.modebtn': 'Ibinalik ng opisina sa PASOK / LABAS buttons. Pindutin muna, tapos mag-scan.',
        'att.gps.t': 'HINDI TUGMA ANG LOKASYON', 'att.site.t': 'HINDI TAGA-SITE NA ITO', 'att.nosite.t': 'WALANG SITE ANG KIOSK',
        'att.stale.t': 'MAG-SCAN ULIT', 'att.stale': 'Luma na ang scan. I-scan ulit ang daliri mo.',
        'att.paidfrom': 'Magsisimula ang bayad sa {t}.', 'att.autoclosed': 'Isinara ang {session} sa {t} bilang AUTO. Susuriin ng opisina.',
        'att.ot': 'May {h} oras na OT ({a}–{b}).', 'att.lunch': 'Tanghalian {a}–{b}. Mag-scan ulit pagbalik mo.',
        'att.servererr': 'MAY MALI SA SERVER', 'att.checkconn': 'Tingnan ang internet ng kiosk',
        'att.retry': 'Subukan ulit', 'bdg.rejected': 'TINANGGIHAN',
        'sess.AM': 'AM SESSION', 'sess.PM': 'PM SESSION', 'sess.n.AM': 'NIGHT · UNANG HATI', 'sess.n.PM': 'NIGHT · IKALAWANG HATI',
        'att.amopen.t': 'MAG-LABAS MUNA SA {s}', 'att.amopen': 'Bukas pa ang {s} mo mula {t}. Mag-LABAS muna.',
        'att.sessdone.t': 'TAPOS NA ANG {s}', 'att.sessdone': 'Kumpleto na ang {s} mo (PASOK {a} · LABAS {b}). Sa {n} na ang susunod.',
        'att.daydone.t': 'TAPOS NA NGAYONG ARAW', 'att.daydone': 'Naitala na lahat ng scan mo ngayong araw. Kita tayo bukas.',
        'att.afternoon': 'hapon', 'att.secondhalf': 'ikalawang hati',
        'btn.in': 'PASOK', 'btn.out': 'LABAS', 'res.in': 'PUMASOK', 'res.out': 'LUMABAS',
        'grp.first': 'UNANG HATI', 'grp.second': 'IKALAWANG HATI',

        'mon.title': 'NGAYONG ARAW SA SITE', 'mon.employee': 'Manggagawa',
        'mon.amin': 'Pasok AM', 'mon.amout': 'Labas AM', 'mon.pmin': 'Pasok PM', 'mon.pmout': 'Labas PM',
        'n.in1': 'Pasok 1', 'n.out1': 'Labas 1', 'n.in2': 'Pasok 2', 'n.out2': 'Labas 2', 'mon.status': 'Kalagayan',
        'mon.loading': 'Kinukuha ang attendance…', 'mon.none': 'Wala pang pasok ngayong araw', 'mon.failed': 'Hindi makuha ang listahan — susubukan ulit',
        'mon.workingb': 'NASA LOOB', 'mon.doneb': 'TAPOS NA', 'mon.lunchb': 'TANGHALIAN', 'mon.notbackb': 'WALA PA', 'mon.otb': 'OVERTIME',
        'mon.pending': 'Hindi pa rehistrado', 'mon.count': '{n} ngayon · {w} nasa loob',
        'mon.f.all': 'Lahat {n}', 'mon.f.working': 'Nasa loob', 'mon.f.lunch': 'Tanghalian', 'mon.f.notback': 'Di pa bumalik', 'mon.f.none': 'Walang tao rito ngayon',

        'dtr.verify': 'I-SCAN PARA BUKSAN ANG DTR', 'dtr.privacy': 'Ikaw lang ang makakabukas ng sarili mong DTR.',
        'dtr.unlock': 'I-SCAN ANG DALIRI', 'dtr.scanning': 'BINABASA…', 'dtr.loading': 'BINUBUKSAN ANG DTR MO…',
        'dtr.notread': 'HINDI MABASA — SUBUKAN ULIT', 'dtr.noweb': 'HINDI MAABOT ANG SERVER', 'dtr.nosensor': 'WALANG KONEKSYON SA SENSOR',
        'dtr.unknown': 'HINDI REHISTRADO ANG DALIRI', 'dtr.stale': 'I-scan ulit ang daliri mo.',
        'dtr.this': 'NGAYONG LINGGO', 'dtr.last': 'NAKARAANG LINGGO', 'dtr.done': 'TAPOS NA AKO — ISARA',
        'dtr.title': 'DAILY TIME RECORD', 'dtr.scanleft': 'I-scan ang daliri mo sa kaliwa para makita ang DTR mo.',
        'dtr.timer': 'Kusang magsasara sa {t}', 'dtr.closed': 'Isinara para sa privacy mo.',
        'dtr.date': 'Petsa', 'dtr.in': 'Pasok', 'dtr.out': 'Labas', 'dtr.hours': 'Oras', 'dtr.remark': 'Tala',
        'dtr.days': 'Araw na pumasok', 'dtr.worked': 'Oras na trabaho', 'dtr.ot': 'Overtime', 'dtr.late': 'Huli',
        'dtr.absent': '{n} absent', 'dtr.missing': '{n} kulang na scan', 'dtr.noscan': 'Walang scan', 'dtr.period': 'PANAHON',
        'dtr.failed': 'Hindi mabasa ang DTR. Subukan ulit.',
        'Complete': 'Kumpleto', 'Missing scan': 'Kulang na scan', 'Absent': 'Absent', 'On leave': 'Naka-leave',
        'Rest day': 'Pahinga', 'Holiday': 'Holiday', 'In progress': 'Nasa loob',

        'rep.verify': 'I-SCAN PARA MAG-REKLAMO', 'rep.privacy': 'Makakarating sa opisina ang reklamo mo kasama ang pangalan mo.',
        'rep.unlock': 'I-SCAN ANG DALIRI', 'rep.title': 'MAG-REPORT NG PROBLEMA', 'rep.scanleft': 'I-scan muna ang daliri mo sa kaliwa.',
        'rep.history': 'MGA REKLAMO KO', 'rep.cancel': 'KANSELAHIN', 'rep.send': 'IPADALA SA OPISINA', 'rep.sending': 'IPINAPADALA…',
        'rep.pickcat': '1 · Tungkol saan?', 'rep.typemsg': '2 · Pindutin ang mga letra, o pumili sa ibaba…',
        'rep.sent.t': 'NAIPADALA NA SA OPISINA', 'rep.sent.d': 'Reference #{id}. Makikita mo rito ang sagot sa susunod mong scan.',
        'rep.nohist': 'Wala ka pang reklamo.', 'rep.open': 'BUKAS', 'rep.resolved': 'TAPOS NA', 'rep.answer': 'Opisina',
        'rep.left': '{n} pa ngayong araw', 'rep.limit': 'Naabot mo na ang dami ng reklamo ngayong araw. Kausapin ang opisina.',
        'rep.failed': 'Hindi naipadala — {msg}', 'rep.timer': 'Magsasara sa {t}',
        'cat.pay': 'Sahod', 'cat.attendance': 'Attendance / DTR', 'cat.safety': 'Kaligtasan',
        'cat.equipment': 'Gamit / Kagamitan', 'cat.treatment': 'Trato sa trabaho', 'cat.other': 'Iba pa',
        'q.1': 'Nawawala ang pasok ko', 'q.2': 'Mali ang oras ko', 'q.3': 'Kulang ang sahod ko',
        'q.4': 'Delikado ang lugar', 'q.5': 'Sira ang gamit', 'q.6': 'Pakitawagan ako',

        'enr.capture': 'PAGKUHA NG DALIRI', 'enr.pick': 'Pumili ng pangalan sa kanan',
        'enr.pickfirst': 'PUMILI MUNA NG PANGALAN', 'enr.detailsweb': 'Nasa web ang detalye — daliri lang ang kinukuha dito',
        'enr.s1': 'Dampi', 'enr.s2': 'Alis', 'enr.s3': 'Ulit', 'enr.s4': 'Tapos', 'enr.start': 'SIMULAN ANG SCAN',
        'enr.workers': 'KAILANGAN NG DALIRI', 'enr.loading': 'Kinukuha ang listahan…',
        'enr.note': 'Bagong pangalan? Idagdag muna sa web. Bawat manggagawa ay kailangan ng {n} magkaibang daliri.',
        'enr.empty': 'Wala pang manggagawa sa site na ito.', 'enr.emptysub': 'Idagdag muna sila sa web system.',
        'enr.failed': 'Hindi makuha ang listahan.', 'enr.new': 'BAGO',
        'enr.alldone': 'Kumpleto na ang daliri ng lahat dito.', 'enr.alldonesub': 'Kusang lalabas dito ang mga bagong manggagawa.',
        'enr.fingers': '{k} sa {n} daliri', 'enr.ready': 'HANDA — DALIRI {k} SA {n}',
        'enr.presskey': 'Pindutin ang SIMULAN ANG SCAN', 'enr.usefinger': 'Ibang daliri bawat beses (kanang hintuturo, tapos kaliwa).',
        'enr.picknamefirst': 'Pumili muna ng pangalan.', 'enr.preparing': 'INIHAHANDA ANG SENSOR…',
        'enr.touch': 'IDAMPI ANG DALIRI', 'enr.press': 'Idiin at huwag igalaw', 'enr.lift': 'ALISIN ANG DALIRI',
        'enr.liftnow': 'Itaas muna ang daliri', 'enr.again': 'IDAMPI ULIT', 'enr.secondtime': 'Parehong daliri, pangalawang beses',
        'enr.captured': 'NAKUHA NA ANG DALIRI', 'enr.saving': 'Sine-save sa web…', 'enr.notcapt': 'HINDI NAKUHA',
        'enr.tryagain': 'Subukan ulit', 'enr.wrong': 'MAY MALI', 'enr.complete': 'KUMPLETO NA',
        'enr.next': 'Na-save ang daliri {k}. IBANG daliri naman — pindutin ang SIMULAN ANG SCAN.',
        'enr.timeout': 'Nag-timeout. Subukan ulit.', 'enr.nosvc': 'Hindi tumatakbo ang fingerprint service sa Pi.',
        'enr.saved': 'na-save — {k} sa {n} daliri', 'enr.notsaved': 'Hindi na-save sa web',
        'enr.dup.t': 'NAKA-REGISTER NA ANG DALIRI', 'enr.dup': 'Kay {name} (#{slot}) ang daliring ito. Gumamit ng ibang daliri.',
        'enr.dupself': 'Naka-save na ang daliring ito kay {name}. IBANG daliri ang gamitin.',
        'enr.dupother': 'May ibang may-ari ng daliring ito (#{slot}). Gumamit ng ibang daliri.',
        'enr.refresh': 'I-refresh',

        'att.place': 'IDAMPI ANG DALIRI SA SENSOR', 'att.place.d': 'Para mag-pasok o labas — lalabas ang pangalan mo, tapos pumili.',
        'att.place.auto': 'Kusa nitong itatala ang PASOK o LABAS.',
        'pop.notin': 'Wala pang pasok · {s}', 'pop.isin': 'Naka-pasok mula {t} · {s}', 'pop.done': 'Tapos na ngayong araw',
        'pop.amdone': 'Tapos ang umaga (labas {t}) · hapon na ang susunod', 'pop.cancel': 'KANSELAHIN',
        'rq.missing': 'Nawawala ang pasok o labas ko', 'rq.hours': 'Mali ang oras ko',
        'rq.pay': 'Kulang o mali ang sahod ko', 'rq.vale': 'Tanong tungkol sa vale ko',
        'rq.unsafe': 'Delikado ang lugar ng trabaho', 'rq.tool': 'Sira ang gamit o kagamitan',
        'rq.people': 'Problema sa kasamahan o supervisor', 'rq.call': 'Pakitawag ako sa opisina',
        'rq.own': 'Iba pa — i-type',
        'rep.back': 'BUMALIK', 'rep.addnote': 'MAGDAGDAG NG SULAT', 'rep.hidenote': 'ITAGO ANG KEYS',
        'rep.typenote': 'Opsyonal: magdagdag ng ilang salita…', 'rep.typeown': 'I-type ang nangyari…',
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
    document.querySelectorAll('[data-i18n-aria]').forEach(el => { el.setAttribute('aria-label', t(el.dataset.i18nAria)); });
    document.querySelectorAll('.lang-opt').forEach(b => b.classList.toggle('active', b.dataset.lang === LANG));
    document.documentElement.lang = LANG === 'tl' ? 'fil' : 'en';
    const mn = document.getElementById('btn-minimize'); if (mn) mn.title = t('win.minimize');
    paintFullscreen();
    paintSite();
    paintStatus();
    setText('enr-note', t('enr.note', { n: FINGERS_REQUIRED }));
    if (pickedEmp && !ENROLLING) renderPick(); else if (!pickedEmp && !ENROLLING) resetEnrollIdle();
    renderRoster();
    updateClock();
    moveTabInd();
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
    catch (e) { console.error('Non-JSON:', text.slice(0, 300)); throw new Error('Server did not return JSON (' + res.status + ')'); }
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

// ── CONNECTION DOTS ──────────────────────────────────────────────────────
// Two plain lights in the header: the sensor (the Pi's service answers) and
// the server (the web answers). Nothing else about the machine is shown.
const STATUS = { sensor: null, web: null };

function setStatus(which, ok) {
    if (STATUS[which] === ok) return;
    STATUS[which] = ok;
    paintStatus();
}

function paintStatus() {
    [['sensor', 'dot-sensor', 'lbl-sensor'], ['web', 'dot-web', 'lbl-web']].forEach(([k, dot, lbl]) => {
        const d = document.getElementById(dot);
        if (d) d.className = 'k-dot' + (STATUS[k] === true ? ' ok' : STATUS[k] === false ? ' bad' : '');
        setText(lbl, t(STATUS[k] === false ? 'st.' + k + '.off' : 'st.' + k));
    });
}

// ── THE SERVER'S CLOCK ───────────────────────────────────────────────────
// RSC: "time reflected in the kiosk must be based from the server, not the
// device". The page arrives with the server's time in it; every settings
// answer (every 5 s) carries it again. The kiosk shows and decides by
// kioskNow() — the Pi's own clock only measures the seconds in between.
// What is RECORDED is always stamped by the server itself.
let CLOCK_SHIFT_MS = 0;
let CLOCK_SYNCED   = false;

function kioskNow() { return new Date(Date.now() + CLOCK_SHIFT_MS); }

function learnServerClock(data, sentAt, gotAt) {
    if (!data || typeof data.server_time !== 'number') return;
    // The web stamped its time somewhere in the round trip — take the middle.
    const epochShift = data.server_time + (gotAt - sentAt) / 2 - gotAt;
    // The office's zone, not the Pi's: a Pi left on UTC would be 8 hours out.
    const zoneShift = typeof data.tz_offset === 'number'
        ? (data.tz_offset + new Date().getTimezoneOffset()) * 60000 : 0;
    const next = Math.round(epochShift + zoneShift);
    const first = !CLOCK_SYNCED;
    CLOCK_SYNCED = true;
    if (first || Math.abs(next - CLOCK_SHIFT_MS) > 1500) {      // ignore network jitter
        CLOCK_SHIFT_MS = next;
        if (Math.abs(next) > 60000) console.warn(`Pi clock is ${Math.round(next / 1000)} s off — using the server's time.`);
        updateClock();
        if (first) window.dispatchEvent(new CustomEvent('clocksynced'));
    }
}

// The time the page was made with: good to a second or so, at once.
if (typeof CFG.server_time === 'number') {
    const now = Date.now();
    learnServerClock(CFG, now, now);
}

function updateClock() {
    const el = document.getElementById('digital-clock');
    if (!el) return;
    if (!CLOCK_SYNCED) { el.textContent = '--:--:--'; el.classList.add('unsynced'); return; }
    const now = kioskNow();
    el.classList.remove('unsynced');
    el.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
    let date;
    try {
        date = now.toLocaleDateString(LANG === 'tl' ? 'fil-PH' : 'en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    } catch (e) {
        date = now.toDateString();
    }
    setText('digital-date', date.toUpperCase());
    updateSessionPill();
}

function updateSessionPill() {
    const pill = document.getElementById('session-pill');
    const lbl  = document.getElementById('session-label');
    if (!pill || !lbl) return;
    let mode = window.jeyanco && window.jeyanco.pillMode ? window.jeyanco.pillMode() : null;
    if (!mode) mode = 'off';
    lbl.textContent = t('pill.' + mode);
    const icon = pill.querySelector('i');
    if (icon) icon.className = mode === 'am' ? 'fas fa-sun' : mode === 'pm' ? 'fas fa-cloud-sun' : mode === 'off' ? 'fas fa-power-off' : 'fas fa-moon';
    pill.className = 'session-pill' + (mode === 'am' ? '' : ' ' + mode);
}
window.updateSessionPill = updateSessionPill;

// ── THE SITE ─────────────────────────────────────────────────────────────
// Set by the office (System Settings → Kiosks → Kiosk site), shown in the
// header, locked. The kiosk is told on every settings answer and passes it
// to the Pi, which stamps it on everything it sends and gives it to the GPS
// tracker.
let SITES       = [];
let ACTIVE_SITE = null;          // { id, slug, name, location }
let ASSIGNED;                    // undefined = not heard yet; null = none set
let KIOSK_CODE  = new URLSearchParams(location.search).get('kiosk') || null;
let FINGERS_REQUIRED = Number(CFG.fingers) || 2;

window.jeyanco = window.jeyanco || {};

/** The site + device stamp a request needs when it cannot go through Flask. */
function siteStamp() {
    const stamp = {};
    if (KIOSK_CODE)                     stamp.kiosk_code = KIOSK_CODE;
    if (ACTIVE_SITE && ACTIVE_SITE.id)  stamp.site_id    = ACTIVE_SITE.id;
    return stamp;
}

function showSiteWarning(msg) {
    const el = document.getElementById('site-warning');
    if (!el) return;
    if (!msg) { el.hidden = true; el.innerHTML = ''; return; }
    el.innerHTML = `<i class="fas fa-triangle-exclamation"></i> ${escapeHtml(msg)}`;
    el.hidden = false;
}

function paintSite() {
    const box = document.getElementById('site-name');
    if (!box) return;
    const site = ASSIGNED === null ? null : ACTIVE_SITE;
    box.classList.toggle('waiting', !site);
    setText('site-name-text', site ? (site.location ? `${site.name} — ${site.location}` : site.name)
                                   : t(ASSIGNED === null ? 'site.waiting' : 'site.none'));
    document.title = site ? `Jeyanco — ${site.name} Kiosk` : 'Jeyanco Construction — Attendance Kiosk';
}

/** Ask the Pi who it is (its kiosk code) and which site it holds. */
async function loadPi() {
    try {
        const data = await safeParseJSON(await flaskFetch('/sites', {}, 6000));
        if (data && data.success) {
            if (!KIOSK_CODE && data.kiosk_code) KIOSK_CODE = data.kiosk_code;
            if (Array.isArray(data.sites)) SITES = data.sites;
            if (data.active && data.active.id != null && !ACTIVE_SITE) {
                ACTIVE_SITE = { id: data.active.id, slug: data.active.slug, name: data.active.name, location: '' };
                paintSite();
            }
            return true;
        }
    } catch (e) { console.warn('Flask /sites unreachable:', e.message); }
    return false;
}

let piSiteQueue = Promise.resolve();

/** Tell the Pi the site, so every record and the GPS tracker use it. */
function postSiteToPi(site) {
    const run = async () => {
        try {
            const data = await safeParseJSON(await flaskFetch('/set-site', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ site: site.slug || String(site.id) }),
            }, 6000));
            if (!data.success) throw new Error(data.message || 'The Pi did not accept the site');
            showSiteWarning(null);
        } catch (e) {
            showSiteWarning(t('site.notpi', { site: site.name, err: e.message }));
        }
    };
    piSiteQueue = piSiteQueue.then(run, run);
    return piSiteQueue;
}

/** The site from the office, on each settings answer. */
function applyAssignedSite(site) {
    const before = ACTIVE_SITE ? String(ACTIVE_SITE.id) : null;
    ASSIGNED = site || null;
    if (!site) { paintSite(); return; }

    const changed = before !== String(site.id);
    ACTIVE_SITE = { id: site.id, slug: site.slug, name: site.name, location: site.location || '' };
    window.KIOSK_ACTIVE_SITE = site.slug;
    paintSite();

    if (changed) {
        clearRosterForSite();
        window.dispatchEvent(new CustomEvent('sitechanging', { detail: { id: site.id } }));
        postSiteToPi(site).then(() => {
            window.dispatchEvent(new CustomEvent('sitechange', { detail: { id: site.id } }));
            loadRoster();
        });
    }
}

// ── SETTINGS FROM THE WEB ────────────────────────────────────────────────
// Buttons or Automatic, the hours of each shift, the idle return, the site,
// and the server's clock. Remembered, so a kiosk without signal keeps them.
const SETTINGS_CACHE_KEY = 'jeyanco_kiosk_settings';
const SETTINGS_V_KEY     = 'jeyanco_kiosk_settings_v';
const DEFAULT_SETTINGS   = { mode: 'buttons', idle_return_seconds: 60, shifts: [] };

let KIOSK_SETTINGS = (() => {
    try {
        const cached = JSON.parse(localStorage.getItem(SETTINGS_CACHE_KEY) || 'null');
        if (cached && cached.mode) return Object.assign({}, DEFAULT_SETTINGS, cached);
    } catch (e) { /* ignore */ }
    return Object.assign({}, DEFAULT_SETTINGS);
})();
let SETTINGS_V = null;
try { SETTINGS_V = localStorage.getItem(SETTINGS_V_KEY); } catch (e) { /* ignore */ }

function normaliseSettings(att) {
    return {
        mode: att && att.mode === 'auto' ? 'auto' : 'buttons',
        idle_return_seconds: Math.max(15, parseInt(att && att.idle_return_seconds, 10) || 60),
        shifts: Array.isArray(att && att.shifts) ? att.shifts.filter(s => s && s.am_start && s.pm_end) : [],
    };
}

// ── WHAT THE SCREEN SHOWS, FOR DEVICE MONITORING ─────────────────────────
// The tab and who is using it ride along with every settings question, and
// a tap sends them at once, so the office's copy of this screen follows it.
// Names only — never a worker's DTR or the words of a complaint.
function viewState() {
    const tab = currentTab();
    const v = { tab, theme: currentTheme(), dock: dockShown ? 1 : 0 };
    // ATTENDANCE: the card a finger opened — who, and choosing / saving / the answer.
    if (tab === 'attendance' && window.jeyanco.popView) Object.assign(v, window.jeyanco.popView() || {});
    if (tab === 'dtr' && window.jeyanco.dtrView) Object.assign(v, window.jeyanco.dtrView() || {});
    if (tab === 'report' && window.jeyanco.reportView) Object.assign(v, window.jeyanco.reportView() || {});
    if (tab === 'register' && pickedEmp) {
        v.who  = pickedEmp.name;
        v.step = (document.getElementById('reg-status-text') || {}).textContent || '';
    }
    return v;
}

// ── A NEW DEPLOY ─────────────────────────────────────────────────────────
// The web was updated since this page was opened. The kiosk picks up the new
// screen by itself — but only while nobody is using it: on ATTENDANCE, no
// button pressed, no result on screen, nothing being enrolled or written.
let reloadTimer = null;
function reloadWhenIdle() {
    if (reloadTimer) return;
    reloadTimer = setInterval(() => {
        const J = window.jeyanco;
        const idle = currentTab() === 'attendance' && !ENROLLING
            && !(J.popOpen && J.popOpen())
            && Date.now() - lastTouch > 15000;
        if (!idle) return;
        // Never a reload loop: at most once every 2 minutes.
        let last = 0;
        try { last = Number(sessionStorage.getItem('jeyanco_reload_at')) || 0; } catch (e) { /* ignore */ }
        if (Date.now() - last < 120000) return;
        try { sessionStorage.setItem('jeyanco_reload_at', String(Date.now())); } catch (e) { /* ignore */ }
        location.reload();
    }, 2000);
}

let lastView = '';
function reportView() {
    const now = JSON.stringify(viewState());
    if (now === lastView) return;
    loadKioskSettings();
}

let settingsBusy = false, settingsAgain = false;
async function loadKioskSettings() {
    if (settingsBusy) { settingsAgain = true; return; }
    settingsBusy = true;
    try {
        const params = siteStamp();
        if (SETTINGS_V) params.v = SETTINGS_V;
        const view = viewState();
        lastView = JSON.stringify(view);
        params.tab  = view.tab;
        params.view = lastView;
        const qs     = new URLSearchParams(params).toString();
        const sentAt = Date.now();
        const data   = await safeParseJSON(await apiFetch('/settings' + (qs ? '?' + qs : ''),
            { headers: { 'Accept': 'application/json' } }, 8000));
        setStatus('web', true);
        learnServerClock(data, sentAt, Date.now());
        if (data && data.build && CFG.build && String(data.build) !== String(CFG.build)) reloadWhenIdle();

        // The site is only believed once the kiosk knows which kiosk it is:
        // without a code the web would answer for its first kiosk.
        if (KIOSK_CODE && data && Object.prototype.hasOwnProperty.call(data, 'site')) applyAssignedSite(data.site);

        if (data && data.success && data.same) return;
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
        setStatus('web', false);
        console.warn('settings unreachable:', e.message);
    } finally {
        settingsBusy = false;
        if (settingsAgain) { settingsAgain = false; setTimeout(loadKioskSettings, 0); }
    }
}

// ── TABS ─────────────────────────────────────────────────────────────────
function currentTab() {
    const el = document.querySelector('.tab-content.active');
    return el ? el.id.replace(/^tab-/, '') : 'attendance';
}

function switchTab(tab) {
    const panel = document.getElementById(`tab-${tab}`);
    if (!panel) return;
    const was = currentTab();

    document.querySelectorAll('.tab-content').forEach(x => x.classList.toggle('active', x === panel));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    moveTabInd();
    lastTouch = Date.now();

    if (tab === 'attendance') { if (window.startAttendancePolling) window.startAttendancePolling(); }
    else if (window.stopAttendancePolling) window.stopAttendancePolling();

    if (tab === 'register') { loadRoster(); startRosterPolling(); } else stopRosterPolling();

    if (was !== tab) window.dispatchEvent(new CustomEvent('tabchange', { detail: { tab, was } }));
}

// ── IDLE RETURN ──────────────────────────────────────────────────────────
// Left on another tab, the kiosk goes back to ATTENDANCE by itself — never
// in the middle of an enrolment, an open DTR or a report being written.
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
        const hold = window.jeyanco.holds && window.jeyanco.holds[tab];
        if (typeof hold === 'function' && hold()) return;
        switchTab('attendance');
    }, 5000);
}

// Device Monitoring's copy of this screen: the theme and the dock follow the
// kiosk (kiosk-monitor.js), never this page's own clock or taps.
const MIRROR = !!window.KIOSK_MONITOR;

// ── THE DOCK ─────────────────────────────────────────────────────────────
// The four tabs sit in a dock at the bottom. On ATTENDANCE it slides away
// when nobody has touched the screen for DOCK_IDLE_MS, so the list has the
// room; a tap anywhere brings it back. On the other tabs it stays.
const DOCK_IDLE_MS = 8000;
let dockShown = true;

function setDock(show) {
    show = !!show;
    if (show === dockShown && document.body.classList.contains('dock-hidden') === !show) return;
    dockShown = show;
    document.body.classList.toggle('dock-hidden', !show);
}

function watchDock() {
    if (MIRROR) return;
    document.addEventListener('pointerdown', () => setDock(true), { passive: true, capture: true });
    setInterval(() => {
        if (currentTab() !== 'attendance') { setDock(true); return; }
        if (Date.now() - lastTouch >= DOCK_IDLE_MS) setDock(false);
    }, 1000);
}

/** The blue block behind the open tab, slid under it. */
function moveTabInd() {
    const ind = document.getElementById('tab-ind');
    const btn = document.querySelector('.tab-btn.active');
    if (!ind || !btn) return;
    ind.style.width = btn.offsetWidth + 'px';
    ind.style.transform = `translateX(${btn.offsetLeft - 6}px)`;
}

// ── LIGHT / DARK ─────────────────────────────────────────────────────────
// By itself: light from 6 AM to 6 PM by the server's clock, dark otherwise.
// The ☀/☾ button overrides it until the next change-over, then it is
// automatic again — so one worker's tap does not stick for days.
const THEME_KEY = 'jeyanco_theme';
let themeManual = null;          // { theme, part } while a tap overrides

try { themeManual = JSON.parse(localStorage.getItem(THEME_KEY) || 'null'); } catch (e) { themeManual = null; }

function autoTheme() {
    const h = (CLOCK_SYNCED ? kioskNow() : new Date()).getHours();
    return h >= 6 && h < 18 ? 'light' : 'dark';
}

function currentTheme() {
    return document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
}

function setTheme(theme, animate) {
    theme = theme === 'light' ? 'light' : 'dark';
    if (theme === currentTheme() && document.documentElement.dataset.theme) return;
    const root = document.documentElement;
    if (animate) {
        root.classList.add('theme-anim');
        setTimeout(() => root.classList.remove('theme-anim'), 350);
    }
    root.dataset.theme = theme;
}

function applyTheme(animate) {
    if (MIRROR) return;
    const part = autoTheme();
    if (themeManual && themeManual.part !== part) {
        themeManual = null;
        try { localStorage.removeItem(THEME_KEY); } catch (e) { /* ignore */ }
    }
    setTheme(themeManual ? themeManual.theme : part, animate);
}

function toggleTheme() {
    if (MIRROR) return;
    const next = currentTheme() === 'light' ? 'dark' : 'light';
    const part = autoTheme();
    themeManual = next === part ? null : { theme: next, part };
    try {
        if (themeManual) localStorage.setItem(THEME_KEY, JSON.stringify(themeManual));
        else localStorage.removeItem(THEME_KEY);
    } catch (e) { /* ignore */ }
    setTheme(next, true);
}

// ── LISTS THAT SCROLL BY THEMSELVES ──────────────────────────────────────
// The board and the enrol list walk down by themselves when they are longer
// than their box; a touch stops it for a while.
const AUTO_SCROLL = { rowSeconds: 2, restMs: 5000, touchMs: 15000, upMs: 1200 };
const autoBoxes = [
    { sel: '#roster-list', tab: 'register', idle: () => !ENROLLING && !pickedEmp },
    { sel: '#board-wrap',  tab: 'attendance', idle: () => true },
];
let autoFrame = null, autoLast = 0;

function autoScrollTick(ts) {
    autoFrame = requestAnimationFrame(autoScrollTick);
    const dt = autoLast ? Math.min(100, ts - autoLast) : 0;
    autoLast = ts;
    const now = Date.now(), tab = currentTab();

    autoBoxes.forEach(b => {
        const box = document.querySelector(b.sel);
        if (!box) return;
        const room = box.scrollHeight - box.clientHeight;
        if (room <= 2) { b.state = 'rest'; b.until = 0; return; }
        if (b.tab !== tab || !b.idle() || now < (b.held || 0)) { b.pos = box.scrollTop; return; }
        if (now < (b.until || 0)) return;
        if (b.state !== 'down' && b.state !== 'up') { b.state = box.scrollTop >= room - 1 ? 'up' : 'down'; b.pos = box.scrollTop; }
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

// ── WINDOW CONTROLS ──────────────────────────────────────────────────────
// ⛶ toggles full screen; ▁ asks the Pi to hide the window (held 2 s).
const HOLD_MS = 2000;
let winTipTimer = null;

function showWinTip(msg, ms) {
    const tip = document.getElementById('win-tip');
    if (!tip) return;
    clearTimeout(winTipTimer);
    tip.textContent = msg; tip.hidden = false;
    if (ms) winTipTimer = setTimeout(() => { tip.hidden = true; }, ms);
}

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

async function askWindowService(path) {
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const res = await flaskFetch(path, { method: 'POST' }, 6000);
            if (isMissingRoute(res)) return {};
            const data = await safeParseJSON(res);
            if (data.success) return { ok: true };
            return { code: data.code || 'failed', msg: data.message || '', tool: data.tool || '' };
        } catch (e) {
            if (attempt === 0) await new Promise(r => setTimeout(r, 1000));
        }
    }
    return {};
}

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
    if (document.fullscreenElement) { try { await document.exitFullscreen(); } catch (x) { /* ignore */ } }
    showWinTip(t('win.nomin'), 3500);
}

function initWindowControls() {
    const fs  = document.getElementById('btn-fullscreen');
    const min = document.getElementById('btn-minimize');

    if (fs) {
        fs.addEventListener('click', async () => {
            try {
                if (document.fullscreenElement) { await document.exitFullscreen(); setTimeout(paintFullscreen, 300); return; }
                const pi = await askWindowService('/window/fullscreen');
                if (pi.ok) { setTimeout(paintFullscreen, 600); setTimeout(paintFullscreen, 1500); return; }
                if (pi.code) { showWinTip(windowProblem(pi), 7000); return; }
                if (kioskIsFull()) showWinTip(t('win.kiosk'), 4000);
                else await document.documentElement.requestFullscreen();
            } catch (e) { console.warn('fullscreen:', e.message); }
            setTimeout(paintFullscreen, 300);
        });
        document.addEventListener('fullscreenchange', paintFullscreen);
        window.addEventListener('resize', paintFullscreen);
        setTimeout(paintFullscreen, 1500);
    }

    if (min) {
        let started = 0, frame = null, pid = null;
        const stop = (e) => {
            if (e && pid !== null && e.pointerId !== pid) return;
            const heldFor = started ? Date.now() - started : 0;
            if (frame) cancelAnimationFrame(frame);
            frame = null; started = 0; pid = null;
            min.classList.remove('holding'); min.style.removeProperty('--hold');
            if (heldFor > 0 && heldFor < HOLD_MS) showWinTip(t('win.hold'), 2500);
        };
        const tick = () => {
            if (!started) return;
            const pct = Math.min(100, ((Date.now() - started) / HOLD_MS) * 100);
            min.style.setProperty('--hold', pct.toFixed(0));
            showWinTip(`${t('win.holding')} ${Math.max(0, (HOLD_MS - (Date.now() - started)) / 1000).toFixed(1)}s`);
            if (pct >= 100) { started = 0; stop(); showWinTip('…', 0); minimizeWindow(); return; }
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
        min.addEventListener('contextmenu', e => e.preventDefault());
    }
}

// ── DRAG TO SCROLL ───────────────────────────────────────────────────────
// Most Pi touch screens arrive as a mouse: the lists move under the finger,
// a short tap still picks, a flick keeps gliding.
const DRAG_MIN_PX = 8;

function enableDragScroll(box) {
    if (!box || box.dataset.dragScroll) return;
    box.dataset.dragScroll = '1';
    let pid = null, startY = 0, startTop = 0, moved = false, suppress = false;
    let lastY = 0, lastT = 0, vel = 0, glide = null;
    const stopGlide = () => { if (glide) cancelAnimationFrame(glide); glide = null; };

    box.addEventListener('pointerdown', e => {
        stopGlide(); suppress = false;
        if (e.pointerType === 'touch' || (e.button !== undefined && e.button > 0)) return;
        pid = e.pointerId; moved = false;
        startY = lastY = e.clientY; startTop = box.scrollTop; lastT = performance.now(); vel = 0;
    });
    box.addEventListener('pointermove', e => {
        if (e.pointerId !== pid) return;
        const dy = e.clientY - startY;
        if (!moved) {
            if (Math.abs(dy) < DRAG_MIN_PX) return;
            moved = true; box.classList.add('drag-scrolling');
            try { box.setPointerCapture(pid); } catch (x) { /* ignore */ }
        }
        box.scrollTop = startTop - dy;
        const now = performance.now(), dt = now - lastT;
        if (dt > 0) vel = 0.8 * ((lastY - e.clientY) / dt) + 0.2 * vel;
        lastY = e.clientY; lastT = now;
        e.preventDefault();
    });
    const end = e => {
        if (e.pointerId !== pid) return;
        pid = null;
        if (!moved) return;
        moved = false; suppress = true; box.classList.remove('drag-scrolling');
        if (performance.now() - lastT > 80) vel = 0;
        let prev = performance.now();
        const step = now => {
            const dt = Math.min(40, now - prev); prev = now;
            vel *= Math.pow(0.94, dt / 16);
            if (Math.abs(vel) < 0.02) { glide = null; return; }
            const before = box.scrollTop;
            box.scrollTop += vel * dt;
            if (box.scrollTop === before) { glide = null; return; }
            glide = requestAnimationFrame(step);
        };
        if (Math.abs(vel) > 0.05) glide = requestAnimationFrame(step);
    };
    box.addEventListener('pointerup', end);
    box.addEventListener('pointercancel', end);
    box.addEventListener('click', e => { if (suppress) { suppress = false; e.stopPropagation(); e.preventDefault(); } }, true);
    box.addEventListener('dragstart', e => e.preventDefault());
    box.addEventListener('wheel', stopGlide, { passive: true });
}

// ── FINGERPRINT RING ─────────────────────────────────────────────────────
function setRing(ringId, iconId, state) {
    const ring = document.getElementById(ringId);
    const icon = document.getElementById(iconId);
    if (!ring || !icon) return;
    ring.className = 'fp-ring';
    // No fingerprint picture: a hand while it waits, then a check or a cross.
    const icons = { success: 'fa-check', error: 'fa-xmark', scanning: 'fa-ellipsis' };
    icon.className = 'fas fp-icon ' + (icons[state] || 'fa-hand-pointer');
    if (state && state !== 'idle') ring.classList.add(state);
}

// ═════════════════════════════════════════════════════════════════════════
//  ENROLL — names from the web, fingers from here
//
//  RSC: "Must register at least multiple fingers per employee". A worker
//  stays on this list until FINGERS_REQUIRED different fingers are on file.
//  Each START SCAN takes one finger; the sensor refuses a finger it already
//  holds — including this worker's own — so each one really is different.
// ═════════════════════════════════════════════════════════════════════════
let rosterAll    = [];       // everybody at the site (for "whose finger is this")
let roster       = [];       // those still short of fingers
let pickedEmp    = null;
let enrolledSlot = null;
let rosterTimer  = null;
let knownIds     = null;
let rosterGen    = 0;

const fingersOf = e => Number(e.fingers != null ? e.fingers : (e.enrolled ? 1 : 0));
const needOf    = e => Number(e.fingers_required || FINGERS_REQUIRED);

function clearRosterForSite() {
    rosterGen++;
    roster = []; rosterAll = []; knownIds = null;
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
}

function rosterStatus(msg, kind) {
    const el = document.getElementById('reg-status');
    if (!el) return;
    el.className = 'reg-status' + (kind ? ' ' + kind : '');
    el.textContent = msg || '';
}

function resetEnrollIdle() {
    const who = document.getElementById('enroll-who');
    if (who) {
        who.classList.remove('has-pick');
        who.innerHTML = `<i class="fas fa-hand-pointer"></i><span>${escapeHtml(t('enr.pick'))}</span>`;
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
            data = await safeParseJSON(await apiFetch('/roster' + (qs ? '?' + qs : ''), { headers: { 'Accept': 'application/json' } }, 10000));
        }
        if (gen !== rosterGen) return;
        if (!data.success) throw new Error(data.message || t('enr.failed'));
        if (data.fingers_required) FINGERS_REQUIRED = Number(data.fingers_required);

        const incoming = data.employees || [];
        if (knownIds !== null) incoming.forEach(e => { if (!knownIds.has(e.id)) e.just_arrived = true; });
        knownIds  = new Set(incoming.map(e => e.id));
        rosterAll = incoming;
        roster    = incoming.filter(e => e.complete === undefined ? !e.enrolled : !e.complete);

        // The picked worker, kept up to date — unless mid-capture.
        if (pickedEmp && !ENROLLING) {
            const fresh = incoming.find(e => e.id === pickedEmp.id);
            if (!fresh) { pickedEmp = null; enrolledSlot = null; resetEnrollIdle(); }
            else if (enrolledSlot == null) { pickedEmp = fresh; renderPick(); }
        }
        renderRoster();
        setText('enr-note', t('enr.note', { n: FINGERS_REQUIRED }));
    } catch (err) {
        if (gen !== rosterGen) return;
        list.innerHTML = `<div class="roster-empty error"><i class="fas fa-triangle-exclamation"></i>
            ${escapeHtml(t('enr.failed'))}<br>${escapeHtml(err.message || '')}</div>`;
    }
}

function fingerDots(e, next) {
    const need = Math.max(needOf(e), fingersOf(e) + (next ? 1 : 0));
    let html = '';
    for (let i = 0; i < need; i++) {
        const cls = i < fingersOf(e) ? 'got' : (next && i === fingersOf(e) ? 'next' : '');
        html += `<i class="fas fa-fingerprint ${cls}"></i>`;
    }
    return `<div class="fingers">${html}</div>`;
}

function renderRoster() {
    const list = document.getElementById('roster-list');
    if (!list || !knownIds) return;

    if (!roster.length) {
        const done = rosterAll.length > 0;
        list.innerHTML = `<div class="roster-empty"><i class="fas ${done ? 'fa-circle-check' : 'fa-user-slash'}"></i>
            ${escapeHtml(t(done ? 'enr.alldone' : 'enr.empty'))}<br>${escapeHtml(t(done ? 'enr.alldonesub' : 'enr.emptysub'))}</div>`;
        return;
    }

    list.innerHTML = roster.map(e => {
        const picked = pickedEmp && pickedEmp.id === e.id;
        const isNew  = e.just_arrived || e.is_new;
        return `<button type="button" class="roster-item${picked ? ' picked' : ''}${e.just_arrived ? ' arrived' : ''}" data-id="${e.id}">
            <div class="roster-main">
                <div class="roster-name">${escapeHtml(e.name)}${isNew ? `<span class="rnew">${escapeHtml(t('enr.new'))}</span>` : ''}</div>
                <div class="roster-sub">${escapeHtml(e.position || t('worker'))}${e.shift ? ' · ' + escapeHtml(e.shift.name) : ''}</div>
            </div>
            <span class="rbadge${fingersOf(e) > 0 ? ' done' : ''}"><i class="fas fa-fingerprint"></i> ${escapeHtml(t('enr.fingers', { k: fingersOf(e), n: needOf(e) }))}</span>
        </button>`;
    }).join('');

    list.querySelectorAll('.roster-item').forEach(btn => btn.addEventListener('click', () => pickEmployee(Number(btn.dataset.id))));
}

function pickEmployee(id) {
    if (ENROLLING) return;
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

function renderPick() {
    const emp = pickedEmp;
    if (!emp) return;
    const who = document.getElementById('enroll-who');
    if (who) {
        who.innerHTML = `<div class="who-name">${escapeHtml(emp.name)}</div>
            <div class="who-sub">${escapeHtml(emp.position || t('worker'))}</div>
            ${fingerDots(emp, fingersOf(emp) < Math.max(needOf(emp), Number(emp.fingers_max || needOf(emp))))}
            <div class="who-warn">${escapeHtml(t('enr.usefinger'))}</div>`;
        who.classList.add('has-pick');
    }
    if (enrolledSlot == null) {
        setText('reg-status-text', t('enr.ready', { k: fingersOf(emp) + 1, n: Math.max(needOf(emp), fingersOf(emp) + 1) }));
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
    setText('reg-hint', '');

    try {
        // Always a NEW finger: allow_slot is empty, so the sensor refuses one
        // it already holds — this worker's own included.
        const data = await safeParseJSON(await flaskFetch('/enroll/start', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ allow_slot: null }) }));
        if (!data.success) { ENROLLING = false; showRegError(data.message || t('enr.notcapt')); btn.disabled = false; return; }
        pollEnroll();
    } catch (err) {
        ENROLLING = false;
        showRegError(t('enr.nosvc'));
        btn.disabled = false;
    }
}

function pollEnroll() {
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
    const emp = pickedEmp;

    try {
        const body = { employee_id: emp.id, fingerprint_id: String(enrolledSlot) };
        const saveOnce = async () => {
            const viaFlask = await flaskFetch('/save-fingerprint', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body) }, 15000).catch(() => null);
            if (viaFlask && !isMissingRoute(viaFlask) && viaFlask.status < 500) return safeParseJSON(viaFlask);
            return safeParseJSON(await apiFetch('/save-fingerprint', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(Object.assign({}, body, siteStamp())) }, 15000));
        };

        // The finger is already on the sensor: retry before giving up, or the
        // slot is left for the sync to delete.
        let out = null, lastErr = null;
        for (let attempt = 0; attempt < 3 && !out; attempt++) {
            if (attempt) { setText('reg-hint', t('enr.saving') + ` (${attempt + 1}/3)`); await new Promise(r => setTimeout(r, 2500)); }
            try { out = await saveOnce(); } catch (e) { lastErr = e; }
        }
        if (!out) throw lastErr || new Error(t('enr.notsaved'));
        if (!out.success) throw new Error(out.message || t('enr.notsaved'));

        const ids  = Array.isArray(out.fingerprint_ids) ? out.fingerprint_ids : (emp.fingerprint_ids || []).concat(String(enrolledSlot));
        const have = Number(out.fingers || ids.length);
        emp.fingerprint_ids = ids; emp.fingers = have; emp.enrolled = true;
        const need = needOf(emp);
        const complete = out.complete !== undefined ? !!out.complete : have >= need;
        emp.complete = complete;

        setRing('reg-ring', 'reg-icon', 'success');
        rosterStatus(`${emp.name} ${t('enr.saved', { k: have, n: need })}`, 'success');
        ENROLLING = false;

        if (!complete) {
            // Another finger is needed: keep the worker picked, ready for it.
            enrolledSlot = null;
            setText('reg-status-text', t('enr.ready', { k: have + 1, n: need }));
            setText('reg-hint', t('enr.next', { k: have }));
            renderPick();
            setText('reg-hint', t('enr.next', { k: have }));
            resetRegSteps();
            if (btn) btn.disabled = false;
            loadRoster();
            return;
        }

        setText('reg-status-text', t('enr.complete'));
        setText('reg-hint', `${emp.name} — ${t('enr.fingers', { k: have, n: need })}`);
        await loadRoster();
        setTimeout(() => {
            if (ENROLLING || pickedEmp !== emp) return;
            pickedEmp = null; enrolledSlot = null;
            setRing('reg-ring', 'reg-icon', 'idle');
            resetEnrollIdle(); resetRegSteps(); rosterStatus(''); renderRoster();
            if (btn) btn.disabled = true;
        }, 5000);
    } catch (err) {
        ENROLLING = false;
        showRegError(err.message || t('enr.notsaved'));
        rosterStatus(err.message || t('enr.notsaved'), 'error');
    }
    if (btn) btn.disabled = !pickedEmp;
}

function showDuplicate(slot) {
    const s = String(slot);
    let msg;
    if (pickedEmp && (pickedEmp.fingerprint_ids || []).map(String).includes(s)) {
        msg = t('enr.dupself', { name: pickedEmp.name });
    } else {
        const owner = rosterAll.find(e => (e.fingerprint_ids || [e.fingerprint_id]).map(String).includes(s));
        msg = owner ? t('enr.dup', { name: owner.name, slot: s }) : t('enr.dupother', { slot: s });
    }
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
        const num = +n, tgt = +step;
        el.className = num < tgt ? 'enroll-step done' : num === tgt ? 'enroll-step active' : 'enroll-step';
    });
}

function resetRegSteps() {
    ['1', '2', '3', '4'].forEach(n => { const el = document.getElementById(`reg-ind-${n}`); if (el) el.className = 'enroll-step'; });
}

// Shared with kiosk-clock.js, kiosk-dtr.js and kiosk-report.js.
Object.assign(window.jeyanco, {
    flaskBase, apiBase, apiFetch, flaskFetch,
    parseJSON: safeParseJSON, escapeHtml, setRing, setText, t,
    tEn: key => I18N.en[key] || key,
    lang: () => LANG,
    activeSite: () => ACTIVE_SITE,
    kioskCode: () => KIOSK_CODE,
    siteStamp, isMissingRoute,
    settings: () => KIOSK_SETTINGS,
    reloadSettings: loadKioskSettings,
    currentTab, switchTab,
    dragScroll: enableDragScroll,
    now: kioskNow,
    clockSynced: () => CLOCK_SYNCED,
    setStatus,
    sensorDown: () => STATUS.sensor === false,
    holds: {},          // tab => () => true while that tab must not be left
    // Device Monitoring sets these from what the kiosk reports.
    setTheme, setDock,
});

// ── INIT ─────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.lang-opt').forEach(b => b.addEventListener('click', () => setLanguage(b.dataset.lang)));
    document.querySelectorAll('.tab-btn').forEach(b => b.addEventListener('click', () => switchTab(b.dataset.tab)));
    document.getElementById('reg-start-btn').addEventListener('click', startEnroll);
    document.getElementById('roster-refresh').addEventListener('click', loadRoster);
    const themeBtn = document.getElementById('btn-theme');
    if (themeBtn) themeBtn.addEventListener('click', toggleTheme);

    applyTheme(false);
    setInterval(() => applyTheme(true), 30000);
    window.addEventListener('clocksynced', () => applyTheme(false));
    applyLanguage();
    window.addEventListener('resize', moveTabInd);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(moveTabInd);
    watchDock();
    setInterval(updateClock, 1000);
    watchAutoScroll();
    ['#roster-list', '#board-wrap'].forEach(sel => enableDragScroll(document.querySelector(sel)));
    initWindowControls();
    watchIdle();

    // The scanner first: it waits on no network call.
    if (window.startAttendancePolling) window.startAttendancePolling();

    // Who this kiosk is (from the Pi), then its settings and its site.
    loadPi().then(ok => {
        if (!ok && !KIOSK_CODE) showSiteWarning(t('site.nocode'));
        loadKioskSettings();
        loadRoster();
    });
    loadKioskSettings();
    setInterval(loadKioskSettings, SETTINGS_REFRESH_MS);
    // A tap that changes the screen reaches Device Monitoring at once.
    setInterval(reportView, 700);
    // The Pi may come up after the page: keep asking until it has answered.
    const piRetry = setInterval(() => {
        if (KIOSK_CODE) { clearInterval(piRetry); return; }
        loadPi().then(ok => { if (ok) { showSiteWarning(null); loadKioskSettings(); loadRoster(); } });
    }, 10000);
    window.addEventListener('online', loadKioskSettings);
});
