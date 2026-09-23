{{-- The loading screen: the check that decides whether it runs, and its chrome.

     This half is in the <head> because the check has to stamp <html> BEFORE
     the styles below are read. Set any later and the overlay paints for a
     frame on every internal click — the flash it exists to avoid.

     It runs when the site is OPENED: a typed address, a bookmark, a link from
     outside. A click inside the app, a form submit and the back button all
     skip it and go straight to the page.

     Michael's design, from login.html. Changed for the app rather than for
     the look: every colour here is named --jp-* and scoped to the overlay, so
     the partial can be included on the dashboard layout — which loads the
     app's own design tokens — without either set of names touching the other.
     The mark is served from /images rather than inlined three times as
     base64. --}}

<script>
/* Opened, or navigated to? A referrer from this same site means a click
   inside the app, and back_forward means the back button — neither is an
   arrival. Both checks are wrapped: a referrer can be an unparseable string,
   and Navigation Timing is not everywhere. */
(function () {
  var d = document.documentElement, internal = false;
  try { internal = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (e) {}
  var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
  d.classList.add((internal || nav.type === 'back_forward') ? 'jp-ready' : 'jp-loading');
})();
</script>

<style>
/* ── The overlay ─────────────────────────────────────────────────────────
   z-index above the toasts and the confirm dialog, which reserve 9500–9700. */
#jp-loader {
    --jp-bg: #0a0f1e;
    --jp-brand: #1E5C9B;
    --jp-brand-light: #4F8FD1;
    --jp-text: #E6EDF7;
    --jp-muted: #8A98B3;

    position: fixed; inset: 0; z-index: 9800;
    display: none; place-items: center;
    background:
        radial-gradient(ellipse 60% 45% at 50% 42%, rgba(30,92,155,.22), transparent 70%),
        var(--jp-bg);
    font-family: "Manrope", system-ui, -apple-system, "Segoe UI", sans-serif;
    color: var(--jp-text);
    transition: opacity .7s ease .15s, visibility 0s linear .85s;
}
.jp-loading #jp-loader, #jp-loader.is-on { display: grid; }
.jp-loading body { overflow: hidden; }

/* The same blueprint grid the sign-in panel uses, so the two read as one
   surface and the hand-over does not look like a change of scene. */
#jp-loader::before {
    content: ""; position: absolute; inset: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(127,176,230,.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(127,176,230,.07) 1px, transparent 1px),
        linear-gradient(rgba(127,176,230,.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(127,176,230,.035) 1px, transparent 1px);
    background-size: 80px 80px, 80px 80px, 16px 16px, 16px 16px;
    -webkit-mask-image: radial-gradient(circle at 50% 45%, #000 0%, transparent 60%);
            mask-image: radial-gradient(circle at 50% 45%, #000 0%, transparent 60%);
}
#jp-loader.is-leaving { opacity: 0; visibility: hidden; }
#jp-loader.is-on { animation: jp-fade .45s ease backwards; }
@keyframes jp-fade { from { opacity: 0; } }

.jp-stage {
    position: relative;
    display: flex; flex-direction: column; align-items: center; gap: 26px;
    animation: jp-enter .8s var(--jp-ease) both;
    transition: transform .8s var(--jp-ease), opacity .55s ease, filter .6s ease;
}
/* The dial pulls forward and blurs away rather than simply fading: it reads
   as the screen being lifted off the page, not as a light being switched. */
#jp-loader.is-leaving .jp-stage { transform: scale(1.08); opacity: 0; filter: blur(6px); }

/* ── The dial ─────────────────────────────────────────────────────────── */
.jp-dial { position: relative; width: 156px; height: 156px; }
.jp-halo {
    position: absolute; inset: 18px; border-radius: 50%;
    background: radial-gradient(circle, rgba(30,92,155,.55), transparent 70%);
    filter: blur(16px);
    animation: jp-breathe 3.6s ease-in-out infinite;
}
.jp-dial svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.jp-track { fill: none; stroke: rgba(255,255,255,.07); stroke-width: 1; }
.jp-tick { stroke: var(--jp-brand-light); stroke-width: 2; stroke-linecap: round; opacity: .22; animation: jp-tick 3s linear infinite; }
.jp-tick.major { stroke-width: 2.5; }
.jp-orbit { transform-box: view-box; transform-origin: 80px 80px; animation: jp-spin 3s linear infinite; }
.jp-orbit-tail { fill: none; stroke: url(#jp-tail); stroke-width: 2; stroke-linecap: round; }
.jp-orbit-dot { fill: #fff; filter: drop-shadow(0 0 5px var(--jp-brand-light)); }

.jp-core {
    position: absolute; inset: 36px; border-radius: 50%;
    display: grid; place-items: center; overflow: hidden;
    background: #0E3F7A;
    box-shadow: 0 0 0 1px rgba(127,176,230,.3), 0 10px 30px rgba(0,0,0,.45);
}
.jp-core img { width: 100%; height: 100%; object-fit: cover; }

/* ── The name, the bar, the line under it ────────────────────────────── */
.jp-brand { text-align: center; }
.jp-wordmark { font-size: 15px; font-weight: 800; letter-spacing: .32em; padding-left: .32em; }
.jp-wordmark span { color: var(--jp-brand-light); font-weight: 700; }
.jp-sub { margin-top: 6px; font-size: 11px; font-weight: 600; letter-spacing: .2em; text-transform: uppercase; color: var(--jp-muted); }

.jp-progress { position: relative; width: 200px; height: 3px; border-radius: 99px; background: rgba(255,255,255,.07); overflow: hidden; }
.jp-progress-bar {
    position: absolute; top: 0; bottom: 0; left: 0; width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--jp-brand), #6FA6E0);
    box-shadow: 0 0 10px rgba(79,143,209,.7);
    transition: width .5s var(--jp-ease);
}

.jp-status {
    height: 20px; margin-top: -12px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    font-size: 13px; color: var(--jp-muted);
    transition: opacity .3s ease, transform .3s ease;
}
.jp-status.is-swapping { opacity: 0; transform: translateY(4px); }
.jp-status svg {
    width: 16px; height: 16px; color: var(--jp-brand-light);
    fill: none; stroke: currentColor; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

@keyframes jp-spin    { to { transform: rotate(360deg); } }
@keyframes jp-tick    { 0% { opacity: 1; } 35%, 100% { opacity: .22; } }
@keyframes jp-breathe { 0%,100% { opacity: .55; transform: scale(.92); } 50% { opacity: 1; transform: scale(1.05); } }
@keyframes jp-enter   { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }

/* ── The entrance the loader hands over to ───────────────────────────────
   Anything marked .rv waits hidden while the loader runs, then rises in,
   each one a little after the last (--jp-d, in milliseconds). A page with no
   .rv elements is unaffected, which is how the dashboard layout can include
   this partial and get only the overlay.

   .draw and .fade are for line art: the outlines draw themselves in like a
   blueprint, and the detail arrives after them. */
:root { --jp-ease: cubic-bezier(.2, .8, .2, 1); }

.rv { opacity: 0; transform: translateY(14px); }
.rv-left { transform: translateX(-18px); }
.jp-ready .rv {
    opacity: 1; transform: none;
    transition: opacity .7s ease, transform .9s var(--jp-ease);
    transition-delay: calc(var(--jp-d, 0) * 1ms);
}

.draw { stroke-dasharray: 1; stroke-dashoffset: 1; }
.jp-ready .draw {
    stroke-dashoffset: 0;
    transition: stroke-dashoffset 1.8s cubic-bezier(.45, 0, .2, 1);
    transition-delay: calc(var(--jp-d, 0) * 1ms);
}
.fade { opacity: 0; }
.jp-ready .fade { opacity: 1; transition: opacity 1s ease; transition-delay: calc(var(--jp-d, 0) * 1ms); }

/* A reader who has asked for less movement gets the screen and the page,
   and none of the choreography between them. */
@media (prefers-reduced-motion: reduce) {
    #jp-loader, #jp-loader *, .rv, .draw, .fade {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
        transition-delay: 0s !important;
    }
}
</style>
