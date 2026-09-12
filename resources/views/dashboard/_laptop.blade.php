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

/* ── 4 · Shorter viewports get a tighter frame all round ─────────────────── */
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
