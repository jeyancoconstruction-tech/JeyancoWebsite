{{-- What the system shows while it is fetching the next page.

     This is a server-rendered app: every sidebar click is a full page load,
     and the container it runs on is small and a long way from the office. So
     between the click and the new page there was nothing at all — the screen
     sat frozen on the old page with no sign that anything had been heard,
     which reads as a click that missed.

     Two pieces, deliberately in that order:

       1. A thin brand line across the top, the moment the page starts to
          leave. Most navigations are answered before anything else appears,
          and for those this is the whole of it.

       2. If the wait passes half a second, the card fades in: the mark
          sweeping like a hand round a dial, the name, and a line that says
          what is happening. A splash on EVERY navigation would add a wait
          the system does not have; this one only appears when there is
          already a wait to explain.

     It is hung off the page leaving rather than off a click, so it cannot
     strand itself over a click that was intercepted — the unsaved-changes
     guard in settings/_form-script.blade.php cancels navigation, and a
     loader started on the click would have sat there over the dialog. --}}
<style>
/* ── The line across the top ────────────────────────────────────────────
   Above everything, including the toasts, which reserve 9500–9700. */
#jy-load-bar {
    position: fixed; top: 0; left: 0; height: 3px; width: 0;
    z-index: 9850;
    background: linear-gradient(90deg,
        var(--brand, #1668dc),
        color-mix(in srgb, var(--brand, #1668dc) 55%, #fff));
    box-shadow: 0 0 10px color-mix(in srgb, var(--brand, #1668dc) 60%, transparent);
    opacity: 0; pointer-events: none;
}
#jy-load-bar.on {
    opacity: 1;
    /* It cannot know how far along the answer is, so it slows as it goes and
       never reaches the end: the page arriving is what finishes it. A bar
       that filled to 100% and waited would be telling a lie. */
    animation: jyLoadBar 14s cubic-bezier(.1, .8, .2, 1) forwards;
}
@keyframes jyLoadBar {
    0%   { width: 0; }
    20%  { width: 42%; }
    55%  { width: 74%; }
    100% { width: 93%; }
}

/* ── The card ───────────────────────────────────────────────────────────── */
#jy-loading {
    position: fixed; inset: 0; z-index: 9800;
    display: flex; align-items: center; justify-content: center;
    /* The page behind stays visible but out of focus, so the wait reads as
       this screen still being here rather than as a screen having been
       replaced by a blank one. */
    /* --bg-subtle is the page ground in both themes. An invented name
       here would have fallen through to its literal fallback and washed the
       dark theme out with a light one. */
    background: color-mix(in srgb, var(--bg-subtle, #f8f9fb) 82%, transparent);
    backdrop-filter: blur(6px) saturate(115%);
    -webkit-backdrop-filter: blur(6px) saturate(115%);
    opacity: 0;
    transition: opacity .28s ease;
}
#jy-loading[hidden] { display: none !important; }
#jy-loading.on { opacity: 1; }

.jy-load-card {
    display: flex; flex-direction: column; align-items: center;
    gap: 18px; padding: 34px 44px 30px;
    border-radius: 18px;
    background: var(--bg-elevated, #fff);
    border: 1px solid var(--border, #e4e7ec);
    box-shadow: 0 1px 2px rgba(16, 24, 40, .08), 0 28px 64px -16px rgba(16, 24, 40, .28);
    transform: translateY(8px) scale(.985);
    transition: transform .28s cubic-bezier(.2, .8, .3, 1);
}
#jy-loading.on .jy-load-card { transform: none; }

/* ── The dial ────────────────────────────────────────────────────────────
   A ring with one sweeping arc, four ticks at the quarters, and the mark in
   the middle. It is a clock read at a glance rather than drawn in detail:
   this system is about hours worked, and a hand going round says that
   without a picture of a clock face on a loading screen. */
.jy-load-dial { position: relative; width: 88px; height: 88px; flex: none; }

.jy-load-ring, .jy-load-sweep { position: absolute; inset: 0; border-radius: 50%; }

.jy-load-ring {
    border: 3px solid var(--border, #e4e7ec);
}
.jy-load-sweep {
    /* Masked to a ring of the same width as the track it runs on, so the arc
       sits in the groove instead of over it. */
    background: conic-gradient(from 0deg,
        transparent 0deg,
        color-mix(in srgb, var(--brand, #1668dc) 15%, transparent) 150deg,
        var(--brand, #1668dc) 340deg,
        var(--brand, #1668dc) 360deg);
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
            mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
    animation: jySweep 1.15s linear infinite;
}
@keyframes jySweep { to { transform: rotate(1turn); } }

/* The quarters. Enough of a dial to be read as one; a full twelve would be a
   clock face, which is more picture than a loading screen should carry. */
.jy-load-tick {
    position: absolute; left: 50%; top: 5px;
    width: 2px; height: 7px; margin-left: -1px;
    border-radius: 1px;
    background: var(--border-md, #d0d5dd);
    transform-origin: 50% 39px;
}
.jy-load-tick:nth-child(2) { transform: rotate(90deg); }
.jy-load-tick:nth-child(3) { transform: rotate(180deg); }
.jy-load-tick:nth-child(4) { transform: rotate(270deg); }

.jy-load-mark {
    position: absolute; inset: 15px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-subtle, #eaf2fd);
    overflow: hidden;
}
.jy-load-mark img { width: 34px; height: 34px; object-fit: contain; }

/* ── The name and the line under it ─────────────────────────────────────
   The sidebar's own lockup, so the wait looks like the system it belongs
   to rather than a generic splash. */
.jy-load-words { text-align: center; }
.jy-load-name {
    margin: 0; font-size: 1.05rem; font-weight: 800; letter-spacing: .02em;
    color: var(--text-primary, #101828);
}
.jy-load-sub {
    margin: 3px 0 0; font-size: .66rem; font-weight: 700;
    letter-spacing: .22em; text-transform: uppercase;
    color: var(--text-muted, #667085);
}
.jy-load-say {
    margin: 13px 0 0; font-size: .8rem;
    color: var(--text-secondary, #344054);
}

/* A track that says work is happening without claiming to know how much is
   left — the same honesty as the bar at the top. */
.jy-load-track {
    position: relative; width: 190px; height: 3px;
    border-radius: 3px; overflow: hidden;
    background: var(--bg-subtle, #f8f9fb);
    border: 1px solid var(--border, #e4e7ec);
}
.jy-load-track::after {
    content: ''; position: absolute; top: 0; bottom: 0; width: 42%;
    border-radius: 3px;
    background: linear-gradient(90deg,
        transparent,
        var(--brand, #1668dc),
        color-mix(in srgb, var(--brand, #1668dc) 40%, transparent));
    animation: jyLoadTrack 1.35s cubic-bezier(.45, .05, .55, .95) infinite;
}
@keyframes jyLoadTrack {
    0%   { left: -45%; }
    100% { left: 100%; }
}

/* A reader who has asked for less movement gets the card, the mark and the
   words — and none of the spinning. */
@media (prefers-reduced-motion: reduce) {
    #jy-load-bar.on { animation: none; width: 40%; }
    .jy-load-sweep  { animation: none; opacity: .65; }
    .jy-load-track::after { animation: none; left: 0; width: 100%; opacity: .4; }
    .jy-load-card, #jy-loading { transition: none; }
    #jy-loading.on .jy-load-card { transform: none; }
}

@media (max-width: 520px) {
    .jy-load-card { padding: 28px 30px 26px; }
    .jy-load-track { width: 150px; }
}
</style>

<div id="jy-load-bar" aria-hidden="true"></div>

<div id="jy-loading" hidden role="status" aria-live="polite" aria-label="{{ __('Loading') }}">
    <div class="jy-load-card">
        <div class="jy-load-dial" aria-hidden="true">
            <span class="jy-load-tick"></span>
            <span class="jy-load-tick"></span>
            <span class="jy-load-tick"></span>
            <span class="jy-load-tick"></span>
            <div class="jy-load-ring"></div>
            <div class="jy-load-sweep"></div>
            <div class="jy-load-mark">
                <img src="{{ asset('images/JeyancoLogo.png') }}" alt="">
            </div>
        </div>

        <div class="jy-load-words">
            <p class="jy-load-name">{{ __('Jeyanco Payroll') }}</p>
            <p class="jy-load-sub">{{ __('Payroll · Attendance') }}</p>
            <p class="jy-load-say" id="jy-load-say">{{ __('Loading…') }}</p>
        </div>

        <div class="jy-load-track"></div>
    </div>
</div>

<script>
(function () {
    const bar   = document.getElementById('jy-load-bar');
    const sheet = document.getElementById('jy-loading');
    if (!bar || !sheet) return;

    const CARD_AFTER = 480;    // a wait worth explaining, not every click
    const GIVE_UP    = 20000;  // nothing should hang this long; let the page back
    let cardTimer = null, giveUpTimer = null, running = false;

    function start() {
        if (running) return;
        running = true;

        bar.classList.add('on');

        cardTimer = setTimeout(() => {
            sheet.hidden = false;
            // The frame between being shown and being told to fade is what
            // makes it a fade rather than a flash.
            requestAnimationFrame(() => sheet.classList.add('on'));
        }, CARD_AFTER);

        giveUpTimer = setTimeout(stop, GIVE_UP);
    }

    function stop() {
        running = false;
        clearTimeout(cardTimer);
        clearTimeout(giveUpTimer);
        bar.classList.remove('on');
        sheet.classList.remove('on');
        sheet.hidden = true;
    }

    // The page is on its way out, whatever sent it: a link, a form, a script
    // setting location. A click would have been earlier but also wrong —
    // plenty of clicks are answered without going anywhere, and one that is
    // stopped by the unsaved-changes guard would have left this on screen.
    window.addEventListener('beforeunload', start);

    // Coming back to a page held in the browser's cache restores the DOM as
    // it was when it left — mid-navigation, with all of this showing.
    window.addEventListener('pageshow', e => { if (e.persisted) stop(); });

    // A navigation the user cancelled — the browser's own "leave site?" on a
    // form with unsaved changes — never unloads, so nothing else would take
    // this down.
    window.addEventListener('focus', () => { if (running) setTimeout(stop, 400); });
})();
</script>
