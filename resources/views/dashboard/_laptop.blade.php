{{-- Laptop sizing.

     The one-screen layout was measured at a 820px viewport and holds up there.
     A real 1366x768 laptop gives about 625px once browser chrome and the
     taskbar are gone, and at that height three separate things went wrong and
     compounded:

       the action bar wrapped to a second line        ~55px
       the figures broke into two rows of three       ~70px
       what was left, 214px, was split between two
       rows — a 33px chart with its labels cut off

     Each is fixed at its cause rather than by shrinking everything. The order
     matters: reclaiming the header and the figures is what makes the grid
     large enough that the panels never need crushing. --}}
<style>
/* ── 1 · The figures stay on one row far longer ───────────────────────────
   Six tiles across 1366px is 227px each, which is ample — the old 1399px
   breakpoint was pessimistic and cost a whole extra row exactly where the
   height was tightest. */
@media (min-width: 1150px) {
    .dash-kpis { grid-template-columns: repeat(6, 1fr) !important; }
}
@media (max-width: 1149px) and (min-width: 860px) {
    .dash-kpis { grid-template-columns: repeat(3, 1fr) !important; }
}

/* Below a laptop the tiles get shorter rather than wrapping further. */
@media (min-width: 1150px) and (max-width: 1500px) {
    .kpi { padding: 8px 10px; gap: 7px; }
    .kpi-ic { width: 27px; height: 27px; flex: 0 0 27px; font-size: 11px; }
    .kpi-value { font-size: .95rem; }
    .kpi-label { font-size: .57rem; }
    .kpi-delta { font-size: .6rem; }
}

/* ── 2 · The action bar does not wrap ─────────────────────────────────────
   Greeting on the left, actions and clock on the right, on one line. Below
   1500px the buttons lose their padding before they lose their labels, and
   the greeting truncates before anything is pushed to a second row. */
@media (min-width: 992px) {
    .dash-bar { flex-wrap: nowrap; padding: 8px 12px; }
    .dash-greet { min-width: 0; flex: 1 1 auto; }
    .dash-greet h1 {
        font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .dash-greet p {
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: .72rem;
    }
    .dash-bar-right { flex-wrap: nowrap; flex: 0 0 auto; gap: 6px; }
}
@media (min-width: 992px) and (max-width: 1500px) {
    .dash-act { height: 29px; padding: 0 9px; font-size: 11.5px; gap: 5px; }
    .dash-clock { padding: 4px 9px 4px 6px; gap: 7px; }
    .dash-clock-ic { width: 23px; height: 23px; font-size: 11px; }
    .dash-clock-time { font-size: 12px; }
    .dash-clock-date { font-size: 9.5px; }
}
/* The greeting is the first thing worth giving up when it is truly narrow —
   the actions are what the bar is for. */
@media (min-width: 992px) and (max-width: 1280px) {
    .dash-greet p { display: none; }
}

/* ── 3 · A panel has a floor ──────────────────────────────────────────────
   Two rows sharing 214px produced a 33px chart, which is not a chart. The
   rows will not go below 168px; if a viewport genuinely cannot hold that, the
   page scrolls by a few dozen pixels instead, which is far better than
   rendering something unreadable and calling it fitted. */
.dash-grid { grid-template-rows: minmax(160px, 0.88fr) minmax(160px, 1.12fr) !important; }

/* ── 4 · Under about 700px of viewport the rail drops its group labels ────
   Twenty items plus six labels cannot fit 625px at a legible row height.
   The labels are the part that can go: a hairline above each group keeps the
   grouping visible, and every destination stays reachable without scrolling —
   which is the whole point of the rail. */
/* 700-790px: ten pixels short of the base tier, which is enough to scroll.
   Trim the row, keep the labels. 20x26 + 5x18 + 22 + 46 = 698px. */
@media (min-height: 701px) and (max-height: 790px) {
    .nav-menu .nav-link { padding: 4px 11px !important; line-height: 18px !important; }
    .menu-section { margin: 5px 0 2px !important; font-size: 8px !important; }
    .sidebar-top { padding: 11px 9px !important; }
    .brand-title { padding: 2px 6px 9px !important; }
    .logo-wrapper, .brand-icon { width: 28px !important; height: 28px !important; }
}

@media (max-height: 700px) {
    .menu-section {
        font-size: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        height: 9px !important;
        position: relative;
    }
    .menu-section::after {
        content: ''; position: absolute; left: 12px; right: 12px; top: 4px;
        border-top: 1px solid var(--sidebar-border, rgba(255,255,255,0.07));
    }
    .nav-menu .menu-section:first-of-type { display: none !important; }

    .nav-menu .nav-link {
        padding: 4px 11px !important;
        line-height: 17px !important;
        font-size: 12px !important;
    }
    .sidebar-top { padding: 10px 8px !important; }
    .brand-title { padding: 2px 6px 8px !important; }
    .logo-wrapper, .brand-icon { width: 26px !important; height: 26px !important; }
    .brand-text { font-size: 11px !important; }

    /* 20 x 25 + 5 x 9 + 20 + 40 = 605px, inside a 625px viewport. */
}

/* ── 5 · Shorter viewports get a tighter frame all round ─────────────────── */
@media (max-height: 700px) and (min-width: 992px) {
    /* -48px, not -40: .main-content carries min-height:100vh and the topbar sits
       inside it, so the container's own padding has to come out of the same
       100vh the rail is measured against. Six pixels of scroll is not much,
       but it is the difference between fits and nearly fits. */
    .dash { --dash-gap: 9px; height: calc(100vh - var(--topbar-height, 60px) - 48px); }
    .main-content .container-fluid.py-4 { padding: 10px 16px 14px !important; }
    .panel-head { padding: 6px 10px; }
    .panel-head h2 { font-size: .74rem; }
    .row-item { padding: 6px 10px; }
    .row-av { width: 24px; height: 24px; flex: 0 0 24px; font-size: 9.5px; }
    .row-title { font-size: 11.5px; }
    .row-sub { font-size: 10px; }
    .dash-bar { padding: 6px 11px; }
    .dash-greet h1 { font-size: .88rem; }
    .kpi { padding: 7px 9px; }
    .kpi-value { font-size: .9rem; }
}
</style>
