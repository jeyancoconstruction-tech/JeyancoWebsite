{{-- The kiosk's screen: header, the tabs and the menu. Shared by the kiosk
     itself (kiosk/app) and its mirror in Device Monitoring (kiosk-screen).
     v16 (2026-11-09, RSC "make it simple, less info"): a site board. The
     clock leads; the list is a name, the last time in and a word for the
     status; the office's controls (language, light/dark, full screen,
     enrolment, the sensor and server) sit behind the ⋯ button. Every id the
     scripts use is kept. --}}
<!-- ── HEADER ─────────────────────────────────────────────────────────── -->
<header class="k-top">
    <div class="k-brand">
        <img src="JeyancoLogo.png" alt="Jeyanco">
        <div class="k-brand-name">JEYANCO</div>
        <span class="k-sep"></span>
        <div class="k-brand-site waiting" id="site-name"><i class="fas fa-location-dot"></i><span id="site-name-text">…</span></div>
    </div>
    <div class="k-tools">
        <span class="k-health" id="k-health"><i></i><span id="k-health-text"></span></span>
        <button class="k-more" id="btn-more" type="button" aria-haspopup="true" aria-expanded="false" data-i18n-aria="menu.open" aria-label="Menu"><i class="fas fa-ellipsis"></i></button>

        {{-- The office's controls. The ids are the ones the scripts have always used. --}}
        <div class="k-menu" id="k-menu" hidden>
            <div class="k-menu-cap" data-i18n="menu.office">For the office</div>
            <div class="k-menu-row"><i class="fas fa-language"></i><span data-i18n="menu.lang">Language</span>
                <div class="lang-switch">
                    <button class="lang-opt" data-lang="en" type="button">EN</button>
                    <button class="lang-opt" data-lang="tl" type="button">TL</button>
                </div>
            </div>
            <div class="k-menu-row"><i class="fas fa-circle-half-stroke"></i><span data-i18n="menu.theme">Light / dark</span>
                <button class="theme-btn" id="btn-theme" type="button" data-i18n-aria="theme.toggle" aria-label="Light or dark">
                    <i class="fas fa-sun"></i><i class="fas fa-moon"></i>
                    <span class="theme-knob"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></span>
                </button>
            </div>
            <div class="k-menu-row"><i class="fas fa-expand"></i><span data-i18n="menu.window">Window</span>
                <div class="win-ctl">
                    <button class="win-btn" id="btn-fullscreen" type="button"><i class="fas fa-expand"></i></button>
                    <button class="win-btn" id="btn-minimize" type="button"><i class="fas fa-window-minimize"></i></button>
                    <div class="win-tip" id="win-tip" hidden></div>
                </div>
            </div>
            <hr>
            <button class="k-menu-row k-menu-go" type="button" data-go="register"><i class="fas fa-fingerprint"></i><span data-i18n="tab.enroll">ENROLL</span><b id="k-menu-enroll"></b></button>
            <div class="k-menu-row"><i class="fas fa-signal"></i>
                <div class="k-status">
                    <span><i class="k-dot" id="dot-sensor"></i><span id="lbl-sensor" data-i18n="st.sensor">Sensor</span></span>
                    <span><i class="k-dot" id="dot-web"></i><span id="lbl-web" data-i18n="st.web">Server</span></span>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="site-warning" id="site-warning" hidden></div>

<!-- ═══ ATTENDANCE (kiosk-clock.js) ═════════════════════════════════════
     Left: the clock and what to do. Right: who is on site today. A finger
     on the sensor opens a card with the worker's name and TIME IN / OUT. -->
<main class="tab-content active" id="tab-attendance">
    <section class="k-left">
        <div class="k-clock-date" id="digital-date"></div>
        <div class="k-clock-time unsynced" id="digital-clock">--:--</div>
        <div class="session-pill" id="session-pill"><i class="fas fa-sun"></i><span id="session-label"></span></div>

        <!-- What to do, or what is wrong. -->
        <div class="scan-bar" id="att-panel">
            <div class="fp-ring" id="att-ring"><i class="fas fp-icon fa-fingerprint" id="att-icon"></i></div>
            <div class="sb-text">
                <span class="fp-status-text" id="att-status-text"></span>
                <span class="fp-hint" id="att-hint"></span>
            </div>
        </div>
    </section>

    <section class="board-panel">
        <div class="panel-label">
            <span data-i18n="mon.title">TODAY AT THIS SITE</span>
            <b class="board-n" id="board-n"></b>
            <span class="spacer"></span><span class="board-count" id="board-count"></span>
        </div>
        <div class="board-wrap" id="board-wrap">
            <table class="monitor-table">
                <thead>
                    <tr>
                        <th data-i18n="mon.employee">Employee</th>
                        <th data-i18n="mon.lastin">Last in</th>
                        <th data-i18n="mon.status">Status</th>
                    </tr>
                </thead>
                <tbody id="monitor-body">
                    <tr class="mon-empty"><td colspan="3"><span data-i18n="mon.loading">Loading attendance…</span></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- A finger was read: who it is, and IN or OUT. Then what was recorded. -->
    <div class="scan-pop" id="scan-pop" hidden>
        <div class="scan-result" id="att-result" aria-live="assertive">
            <div class="sr-who" id="sr-who">
                <div class="sr-av"><span class="wk"></span></div>
                <div class="sr-id"><div class="sr-name" id="sr-name"></div><div class="sr-sub" id="sr-sub"></div></div>
                <div class="sr-clock" id="sr-time"><b id="sr-time-v"></b><span id="sr-time-s"></span></div>
            </div>
            <div class="sp-state" id="sp-state"></div>
            <div class="clock-btns" id="att-btns">
                <button class="clock-btn in" id="btn-time-in" type="button"></button>
                <button class="clock-btn out" id="btn-time-out" type="button"></button>
            </div>
            <div class="sr-verb" id="sr-verb"></div>
            <div class="sr-notes" id="sr-notes"></div>
            <div class="sp-foot">
                <div class="sr-bar"><span id="sr-bar"></span></div>
                <button class="sp-cancel" id="sp-cancel" type="button" data-i18n="pop.cancel">CANCEL</button>
            </div>
        </div>
    </div>
</main>

<!-- ═══ MY DTR (kiosk-dtr.js) ═══════════════════════════════════════════ -->
<main class="tab-content" id="tab-dtr">
    <section class="panel">
        <div class="fp-display" id="dtr-scan">
            <div class="fp-ring" id="dtr-ring"><i class="fas fp-icon fa-hand-pointer" id="dtr-icon"></i></div>
            <div class="fp-status-text" id="dtr-status"></div>
            <div class="fp-hint" data-i18n="dtr.privacy">Only you can open your own DTR.</div>
        </div>
        <div class="who-card" id="dtr-who" hidden>
            <div class="sr-av"><span class="wk"></span></div>
            <div style="min-width:0"><div class="who-name" id="dtr-who-name"></div><div class="who-sub" id="dtr-who-sub"></div></div>
        </div>
        <div class="side-actions" id="dtr-actions" hidden>
            <div class="seg">
                <button type="button" data-week="0" class="active" data-i18n="dtr.this">THIS WEEK</button>
                <button type="button" data-week="1" data-i18n="dtr.last">LAST WEEK</button>
            </div>
            <button class="big-btn primary" type="button" id="dtr-lock"><i class="fas fa-lock"></i><span data-i18n="dtr.done">I AM DONE — CLOSE</span></button>
            <div class="timer" id="dtr-timer"></div>
        </div>
    </section>
    <section class="panel">
        <div class="locked" id="dtr-locked"><i class="fas fa-calendar-days"></i><p data-i18n="dtr.scanleft">Scan your finger on the left to see your DTR.</p></div>
        <div id="dtr-card" hidden style="display:flex;flex-direction:column;min-height:0;flex:1"></div>
    </section>
</main>

<!-- ═══ REPORT — complaints (kiosk-report.js) ═══════════════════════════ -->
<main class="tab-content" id="tab-report">
    <section class="panel">
        <div class="fp-display" id="rep-scan">
            <div class="fp-ring" id="rep-ring"><i class="fas fp-icon fa-hand-pointer" id="rep-icon"></i></div>
            <div class="fp-status-text" id="rep-status"></div>
            <div class="fp-hint" data-i18n="rep.privacy">Your report goes to the office with your name.</div>
        </div>
        <div class="who-card" id="rep-who" hidden>
            <div class="sr-av"><span class="wk"></span></div>
            <div style="min-width:0"><div class="who-name" id="rep-who-name"></div><div class="who-sub" id="rep-who-sub"></div></div>
        </div>
        <div class="panel-label" id="rep-hist-label" style="margin-top:var(--gap)" hidden><span data-i18n="rep.history">MY REPORTS</span></div>
        <div class="history" id="rep-history" hidden></div>
        <div class="side-actions" id="rep-actions" hidden>
            <button class="big-btn" type="button" id="rep-lock"><i class="fas fa-xmark"></i><span data-i18n="rep.cancel">CANCEL</span></button>
        </div>
    </section>
    <section class="panel">
        <div class="panel-label"><span data-i18n="rep.title">REPORT A PROBLEM</span><span class="spacer"></span><span class="timer" id="rep-timer"></span></div>
        <div class="locked" id="rep-locked"><i class="fas fa-comment"></i><p data-i18n="rep.scanleft">Scan your finger on the left first.</p></div>
        <!-- 1 · Ready-made requests: one tap each. -->
        <div class="rep-step" id="rep-form" hidden>
            <div class="preset-grid" id="rep-presets"></div>
        </div>
        <!-- 2 · Check it, add a note if wanted, send. The keys only appear when asked for. -->
        <div class="rep-step" id="rep-confirm" hidden>
            <div class="rep-pick" id="rep-pick"></div>
            <div class="msg-box" id="rep-msg" hidden></div>
            <div class="kbd" id="rep-kbd" hidden></div>
            <div class="rep-foot">
                <button class="big-btn" type="button" id="rep-back"><i class="fas fa-arrow-left"></i><span data-i18n="rep.back">BACK</span></button>
                <button class="big-btn" type="button" id="rep-note"><i class="fas fa-keyboard"></i><span data-i18n="rep.addnote">ADD A NOTE</span></button>
                <button class="big-btn green" type="button" id="rep-send"><i class="fas fa-paper-plane"></i><span data-i18n="rep.send">SEND TO OFFICE</span></button>
            </div>
            <div class="counter" id="rep-count"></div>
        </div>
        <div class="sent" id="rep-sent" hidden><i class="fas fa-circle-check"></i><b id="rep-sent-t"></b><p id="rep-sent-d" class="fp-hint"></p></div>
    </section>
</main>

<!-- ═══ ENROLL (script.js) — opened from the ⋯ menu ══════════════════════ -->
<main class="tab-content" id="tab-register">
    <section class="panel">
        <div class="panel-label"><span data-i18n="enr.capture">CAPTURE FINGERPRINT</span></div>
        <div class="enroll-who" id="enroll-who"></div>
        <div class="fp-display">
            <div class="fp-ring" id="reg-ring"><i class="fas fp-icon fa-hand-pointer" id="reg-icon"></i></div>
            <div class="fp-status-text" id="reg-status-text"></div>
            <div class="fp-hint" id="reg-hint"></div>
        </div>
        <div class="enroll-steps">
            <div class="enroll-step" id="reg-ind-1"><div class="step-dot">1</div><span data-i18n="enr.s1">Touch</span></div>
            <div class="step-line"></div>
            <div class="enroll-step" id="reg-ind-2"><div class="step-dot">2</div><span data-i18n="enr.s2">Lift</span></div>
            <div class="step-line"></div>
            <div class="enroll-step" id="reg-ind-3"><div class="step-dot">3</div><span data-i18n="enr.s3">Again</span></div>
            <div class="step-line"></div>
            <div class="enroll-step" id="reg-ind-4"><div class="step-dot">4</div><span data-i18n="enr.s4">Done</span></div>
        </div>
        <div class="reg-status" id="reg-status"></div>
        <div class="side-actions">
            <button class="big-btn primary" id="reg-start-btn" type="button" disabled><i class="fas fa-play"></i><span data-i18n="enr.start">START SCAN</span></button>
        </div>
    </section>
    <section class="panel">
        <div class="panel-label">
            <span data-i18n="enr.workers">NEED FINGERPRINTS</span>
            <span class="spacer"></span>
            <button class="icon-btn" type="button" id="roster-refresh" data-i18n-aria="enr.refresh" aria-label="Refresh"><i class="fas fa-rotate"></i></button>
        </div>
        <div class="roster-list" id="roster-list"><div class="roster-empty" data-i18n="enr.loading">Loading the list…</div></div>
        <div class="note"><i class="fas fa-circle-info"></i><span id="enr-note"></span></div>
    </section>
</main>

<!-- ── THE TABS: three words along the bottom ────────────────────────────
     Enrolment is the office's, so it is in the ⋯ menu, not here. -->
<div class="k-dockwrap">
    <nav class="k-tabs" id="k-tabs">
        <span class="tab-ind" id="tab-ind"></span>
        <button class="tab-btn active" type="button" data-tab="attendance"><i class="far fa-clock"></i><span data-i18n="tab.attendance">ATTENDANCE</span></button>
        <button class="tab-btn" type="button" data-tab="dtr"><i class="far fa-calendar"></i><span data-i18n="tab.dtr">MY DTR</span></button>
        <button class="tab-btn" type="button" data-tab="report"><i class="far fa-comment"></i><span data-i18n="tab.report">REPORT</span></button>
    </nav>
    <div class="dock-peek" data-i18n="dock.peek">Tap the screen for the menu</div>
</div>
