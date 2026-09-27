<!DOCTYPE html>
{{-- The kiosk's own screen, run inside the web for the monitor in System
     Settings → Kiosks. The CSS and scripts under public/kiosk-screen are the
     kiosk's v8 files, unchanged; this page is its layout, and
     kiosk-monitor.js stands in for the Pi (the sensor and the window
     service) and answers the kiosk's reads from this system — so it draws
     exactly what the device draws, from the same data. View only: nothing
     here records a scan, enrols a finger or changes the kiosk's site. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $kiosk->name }} — Kiosk screen</title>
    <base href="{{ asset('kiosk-screen') }}/">
    <link rel="icon" href="JeyancoLogo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="style.css?v={{ $v }}">
    <link rel="stylesheet" href="kiosk-sites.css?v={{ $v }}">
    <link rel="stylesheet" href="kiosk-v3.css?v={{ $v }}">
    <link rel="stylesheet" href="kiosk-screen.css?v={{ $v }}">
    <style>
        /* The board on the ATTENDANCE tab shows names and times only (v8). */
        .monitor-table .c-paid, .monitor-table .c-ot { display: none !important; }
        /* The monitor's own mark, so a photo of it is never taken for the kiosk. */
        .mon-viewonly { position: fixed; right: 8px; bottom: 6px; z-index: 50; font: 800 10px Inter, sans-serif; letter-spacing: .12em;
                        color: #8b98ab; background: rgba(10,14,20,.85); border: 1px solid #2b3644; border-radius: 5px; padding: 3px 7px; pointer-events: none; }
    </style>
    <script>
        window.KIOSK_MONITOR = @json(['api' => $api, 'kiosk' => $kiosk->code]);
    </script>
    <script src="{{ asset('js/kiosk-monitor.js') }}?v={{ $v }}"></script>
</head>
<body>

<header class="top-bar">
    <div class="brand">
        <img src="JeyancoLogo.png" alt="Jeyanco" class="brand-logo" onerror="this.style.display='none'">
        <div class="brand-text">
            <span class="brand-name">JEYANCO CONSTRUCTION</span>
            <span class="brand-sub" id="brand-sub">Attendance Kiosk</span>
        </div>
    </div>
    <div class="header-right">
        <div class="lang-switch" id="lang-switch">
            <button class="lang-opt active" data-lang="en" type="button">EN</button>
            <button class="lang-opt" data-lang="tl" type="button">TL</button>
        </div>
        <div class="session-pill" id="session-pill"><i class="fas fa-sun"></i> <span id="session-label">AM SESSION</span></div>
        <div class="clock-block">
            <div id="digital-clock" class="clock-time">00:00:00</div>
            <div id="digital-date" class="clock-date"></div>
        </div>
        <div class="win-ctl">
            <button class="win-btn" id="btn-fullscreen" type="button" title="Full screen"><i class="fas fa-expand"></i></button>
            <button class="win-btn" id="btn-minimize" type="button" title="Hold to minimize"><i class="fas fa-window-minimize"></i></button>
            <div class="win-tip" id="win-tip" hidden></div>
        </div>
    </div>
</header>

<div class="site-bar">
    <div class="site-bar-label"><i class="fas fa-location-dot"></i><span data-i18n="site.here">YOU ARE AT</span></div>
    <div class="site-bar-btns" id="site-switch-btns"><span class="site-loading" data-i18n="site.loading">Loading sites…</span></div>
    <div class="site-bar-current" id="site-bar-current"></div>
</div>
<div class="site-warning" id="site-warning" style="display:none;"></div>

<nav class="tab-nav">
    <button class="tab-btn active" data-tab="attendance" type="button" onclick="switchTab('attendance', this)"><i class="fas fa-fingerprint"></i> <span data-i18n="tab.attendance">ATTENDANCE</span></button>
    <button class="tab-btn" data-tab="register" type="button" onclick="switchTab('register', this)"><i class="fas fa-user-plus"></i> <span data-i18n="tab.enroll">ENROLL FINGERPRINT</span></button>
    <button class="tab-btn" data-tab="askai" type="button" onclick="switchTab('askai', this)"><i class="fas fa-comments-dollar"></i> <span data-i18n="tab.payroll">MY PAYROLL</span></button>
    <button class="tab-btn" data-tab="summary" type="button" onclick="switchTab('summary', this)"><i class="fas fa-chart-simple"></i> <span data-i18n="tab.summary">SUMMARY</span></button>
</nav>

{{-- ════════ ATTENDANCE ════════ --}}
<main class="kiosk-main tab-content active" id="tab-attendance">
    <section class="panel scan-panel" id="att-panel">
        <div class="panel-label">
            <i class="fas fa-fingerprint"></i> <span data-i18n="att.scan">BIOMETRIC SCAN</span>
            <span class="mode-chip" id="att-mode-chip"></span>
        </div>
        <div class="fp-stage">
            <div class="fp-display">
                <div class="fp-ring" id="att-ring"><i class="fas fa-fingerprint fp-icon" id="att-icon"></i></div>
                <p class="fp-status-text" id="att-status-text" data-i18n="att.press">PRESS TIME IN OR TIME OUT</p>
                <p class="fp-hint" id="att-hint" data-i18n="att.thenscan">Then place your finger on the sensor</p>
                <div class="clock-bar" id="att-bar" hidden><span id="att-bar-fill"></span></div>
                <div class="auto-now" id="att-auto-now" hidden>
                    <div class="an-k" id="an-k"></div>
                    <div class="an-r"><span class="an-p in" id="an-in"></span><span id="an-in-t"></span></div>
                    <div class="an-r"><span class="an-p out" id="an-out"></span><span id="an-out-t"></span></div>
                </div>
            </div>
            <div class="scan-result" id="att-result">
                <div class="sr-verb" id="sr-verb"></div>
                <div class="sr-who" id="sr-who" hidden>
                    <span class="sr-av"><i class="fas fa-user"></i></span>
                    <div class="sr-id"><div class="sr-name" id="sr-name"></div><div class="sr-sub" id="sr-sub"></div></div>
                    <div class="sr-clock" id="sr-time" hidden><b id="sr-time-v"></b><span id="sr-time-s"></span></div>
                </div>
                <div class="sr-notes" id="sr-notes"></div>
                <div class="sr-bar"><span id="sr-bar"></span></div>
            </div>
        </div>
        <div class="clock-btns" id="att-btns">
            <button class="clock-btn in" id="btn-time-in" type="button" data-type="time_in"></button>
            <button class="clock-btn out" id="btn-time-out" type="button" data-type="time_out"></button>
        </div>
        <div class="clock-cap" id="att-cap"></div>
        <div class="day-strip" id="att-strip"></div>
        <div class="site-info">
            <div class="site-row"><i class="fas fa-map-marker-alt"></i><span id="att-site-label">—</span></div>
            <div class="site-row"><i class="fas fa-circle status-dot" id="conn-dot"></i><span id="conn-label" data-i18n="conn.checking">Connecting…</span></div>
        </div>
    </section>

    <section class="panel monitor-panel">
        <div class="panel-label">
            <i class="fas fa-users"></i> <span data-i18n="mon.title">WHO IS ON SITE TODAY</span>
            <span class="auto-chip" id="mon-autochip" hidden></span>
            <span class="live-dot" title="Realtime"></span>
        </div>
        <div class="monitor-table-wrap">
            <table class="monitor-table">
                <thead>
                    <tr class="grp">
                        <th class="col-emp"></th>
                        <th colspan="2" class="g" id="grp-am">AM</th>
                        <th class="c-gap" id="grp-gap">12</th>
                        <th colspan="2" class="g" id="grp-pm">PM</th>
                        <th class="c-paid"></th><th class="c-ot"></th><th class="c-st"></th>
                    </tr>
                    <tr>
                        <th class="col-emp" data-i18n="mon.employee">Employee</th>
                        <th data-i18n="mon.in">In</th><th data-i18n="mon.out">Out</th>
                        <th class="c-gap"></th>
                        <th data-i18n="mon.in">In</th><th data-i18n="mon.out">Out</th>
                        <th class="c-paid" data-i18n="mon.total">Paid hrs</th>
                        <th class="c-ot" data-i18n="mon.otcol">OT</th>
                        <th class="c-st" data-i18n="mon.status">Status</th>
                    </tr>
                </thead>
                <tbody id="monitor-body">
                    <tr class="mon-empty"><td colspan="9" data-i18n="mon.loading">Loading attendance…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

{{-- ════════ ENROLL FINGERPRINT ════════ --}}
<main class="kiosk-main tab-content" id="tab-register">
    <section class="panel scan-panel">
        <div class="panel-label"><i class="fas fa-fingerprint"></i> <span data-i18n="enr.capture">CAPTURE FINGERPRINT</span></div>
        <div class="enroll-who" id="enroll-who">
            <i class="fas fa-hand-pointer enroll-who-icon"></i>
            <p class="enroll-who-text" data-i18n="enr.pick">Choose a name from the list on the right</p>
        </div>
        <div class="fp-display">
            <div class="fp-ring" id="reg-ring"><i class="fas fa-fingerprint fp-icon" id="reg-icon"></i></div>
            <p class="fp-status-text" id="reg-status-text" data-i18n="enr.pickfirst">CHOOSE A NAME FIRST</p>
            <p class="fp-hint" id="reg-hint" data-i18n="enr.detailsweb">Details come from the web — only the finger is needed here</p>
        </div>
        <div class="enroll-steps">
            <div class="enroll-step" id="reg-ind-1"><div class="step-dot">1</div><div class="step-lbl" data-i18n="enr.s1">Touch</div></div>
            <div class="step-line"></div>
            <div class="enroll-step" id="reg-ind-2"><div class="step-dot">2</div><div class="step-lbl" data-i18n="enr.s2">Lift</div></div>
            <div class="step-line"></div>
            <div class="enroll-step" id="reg-ind-3"><div class="step-dot">3</div><div class="step-lbl" data-i18n="enr.s3">Again</div></div>
            <div class="step-line"></div>
            <div class="enroll-step" id="reg-ind-4"><div class="step-dot">4</div><div class="step-lbl" data-i18n="enr.s4">Done</div></div>
        </div>
        <div id="reg-status" class="reg-status"></div>
        <div class="panel-foot">
            <button class="btn-enroll" id="reg-start-btn" type="button" onclick="startEnroll()" disabled><i class="fas fa-fingerprint"></i> <span data-i18n="enr.start">START SCAN</span></button>
        </div>
    </section>
    <section class="panel log-panel">
        <div class="panel-label">
            <i class="fas fa-users"></i> <span data-i18n="enr.workers">WORKERS AT THIS SITE</span>
            <span class="auto-chip" id="roster-autochip" hidden></span>
            <button class="reg-list-refresh" type="button" onclick="loadRoster()" data-i18n-aria="enr.refresh" aria-label="Refresh"><i class="fas fa-rotate"></i></button>
        </div>
        <div class="roster-list" id="roster-list"><div class="roster-empty" data-i18n="enr.loading">Loading the list…</div></div>
        <div class="reg-list-note"><i class="fas fa-circle-info"></i><span data-i18n="enr.note">New name? Add it in the web system first.</span></div>
    </section>
</main>

{{-- ════════ MY PAYROLL ════════ --}}
{{-- A worker's pay opens on the kiosk with their own finger; the monitor has
     none, so this tab stays locked here. --}}
<main class="kiosk-main tab-content" id="tab-askai">
    <section class="panel scan-panel">
        <div class="panel-label"><i class="fas fa-shield-halved"></i> <span data-i18n="ai.verify">VERIFY IDENTITY FIRST</span></div>
        <div class="fp-display">
            <div class="fp-ring" id="ai-ring"><i class="fas fa-fingerprint fp-icon" id="ai-icon"></i></div>
            <p class="fp-status-text" id="ai-status-text" data-i18n="ai.unlock">SCAN YOUR FINGER TO UNLOCK</p>
            <p class="fp-hint" data-i18n="ai.privacy">For privacy, only you can see your own payroll.</p>
        </div>
    </section>
    <section class="panel chat-panel">
        <div class="panel-label"><i class="fas fa-peso-sign"></i> <span data-i18n="ai.title">MY PAY THIS CUTOFF</span></div>
        <div class="chat-locked" id="chat-locked">
            <i class="fas fa-lock chat-lock-icon"></i>
            <p data-i18n="ai.scanleft">Scan your fingerprint on the left to see your pay.</p>
        </div>
    </section>
</main>

{{-- ════════ SUMMARY ════════ --}}
<main class="kiosk-main tab-content" id="tab-summary">
    <div class="sum-tiles" id="sum-tiles"></div>
    <div class="sum-row">
        <section class="panel sum-sessions">
            <div class="panel-label"><i class="fas fa-chart-column"></i> <span data-i18n="sum.bysession">TODAY BY SESSION</span><span class="sum-note" id="sum-note"></span></div>
            <div class="punches" id="sum-punches"></div>
            <div class="day-strip" id="sum-strip"></div>
            <div class="sum-night" id="sum-night" hidden></div>
            <div class="sum-foot" id="sum-foot"></div>
        </section>
        <section class="panel">
            <div class="panel-label"><i class="fas fa-bell"></i> <span data-i18n="sum.attention">NEEDS ATTENTION</span><span class="mode-chip" id="sum-attn-count">0</span></div>
            <div class="attn-list" id="sum-attn"></div>
        </section>
    </div>
</main>

<span class="mon-viewonly">VIEW ONLY · MONITOR</span>

<script src="script.js?v={{ $v }}"></script>
<script src="kiosk-clock.js?v={{ $v }}"></script>
<script src="kiosk-summary.js?v={{ $v }}"></script>
</body>
</html>
