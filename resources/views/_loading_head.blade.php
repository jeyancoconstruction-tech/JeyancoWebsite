{{-- The entry loader: the check that decides whether it runs, and its chrome.

     This half is in the <head> because the check has to set jp-skip on <html>
     BEFORE the styles below are read. Set any later and the overlay paints
     for a frame on every internal click — the flash this is meant to avoid.

     It shows when the site is OPENED: a typed address, a bookmark, a link
     from outside. A click inside the app, a form submit and the back button
     all skip it, which is what tells it apart from a loader on every page.

     The design and the markup are Michael's, from jeyanco-loader.html. Kept
     as written apart from what an app needs and a demo page does not: the
     logo goes through asset(), the demo page's own styles are dropped, and
     the palette is namespaced --jp-* so it cannot collide with the app's
     tokens. --}}

<script>
/* Opened, or navigated to? A referrer from this same site means a click
   inside the app, and back_forward means the back button — neither is an
   arrival, so neither gets the splash. Both checks are wrapped: a referrer
   can be an unparseable string, and Navigation Timing is not everywhere. */
(function () {
  var internal = false;
  try {
    internal = !!document.referrer &&
               new URL(document.referrer).origin === location.origin;
  } catch (e) {}
  var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
  if (internal || nav.type === 'back_forward') {
    document.documentElement.classList.add('jp-skip');
  }
})();
</script>

<style>
  :root {
    --jp-bg: #0a0f1e;
    --jp-brand: #1E5C9B;
    --jp-brand-light: #4F8FD1;
    --jp-brand-glow: rgba(30, 92, 155, 0.45);
    --jp-text: #E6EDF7;
    --jp-muted: #8A98B3;
    --jp-line: rgba(255, 255, 255, 0.07);
    --jp-cycle: 3s; /* one full clock sweep */
  }

  #jp-loader {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: grid;
    place-items: center;
    background:
      radial-gradient(ellipse 60% 45% at 50% 38%, rgba(30, 92, 155, 0.18), transparent 70%),
      var(--jp-bg);
    font-family: "Inter", "Segoe UI", system-ui, -apple-system, Roboto, sans-serif;
    color: var(--jp-text);
    transition: opacity .5s ease, visibility .5s ease;
  }

  /* faint blueprint grid — a quiet nod to construction */
  #jp-loader::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(var(--jp-line) 1px, transparent 1px),
      linear-gradient(90deg, var(--jp-line) 1px, transparent 1px);
    background-size: 44px 44px;
    -webkit-mask-image: radial-gradient(circle at 50% 42%, #000 0%, transparent 55%);
            mask-image: radial-gradient(circle at 50% 42%, #000 0%, transparent 55%);
    opacity: .6;
    pointer-events: none;
  }

  #jp-loader.is-hidden { opacity: 0; visibility: hidden; }
  .jp-skip #jp-loader { display: none; }
  #jp-loader.is-hidden .jp-stage { transform: translateY(-6px) scale(.98); }

  .jp-stage {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 26px;
    transition: transform .5s ease;
    animation: jp-enter .7s cubic-bezier(.2, .8, .2, 1) both;
  }

  /* ---------- Clock dial ---------- */
  .jp-dial { position: relative; width: 148px; height: 148px; }

  .jp-halo {
    position: absolute;
    inset: 18px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--jp-brand-glow), transparent 70%);
    filter: blur(14px);
    animation: jp-breathe 3.6s ease-in-out infinite;
  }

  .jp-dial svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }

  .jp-track { fill: none; stroke: rgba(255, 255, 255, 0.06); stroke-width: 1; }

  .jp-tick {
    stroke: var(--jp-brand-light);
    stroke-width: 2;
    stroke-linecap: round;
    opacity: .22;
    animation: jp-tick var(--jp-cycle) linear infinite;
  }
  .jp-tick.major { stroke-width: 2.5; }

  .jp-orbit {
    transform-box: view-box;
    transform-origin: 80px 80px;
    animation: jp-spin var(--jp-cycle) linear infinite;
  }
  .jp-orbit-tail {
    fill: none;
    stroke: url(#jp-tail);
    stroke-width: 2;
    stroke-linecap: round;
  }
  .jp-orbit-dot { fill: #fff; filter: drop-shadow(0 0 5px var(--jp-brand-light)); }

  /* center badge with the logo */
  .jp-core {
    position: absolute;
    inset: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: linear-gradient(160deg, #13203b, #0c1428);
    border: 1px solid rgba(79, 143, 209, 0.22);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 8px 24px rgba(0, 0, 0, 0.35);
  }
  .jp-core img { width: 42px; height: 42px; object-fit: contain; }
  .jp-monogram {
    display: none;
    font-weight: 700;
    font-size: 30px;
    letter-spacing: -.02em;
    background: linear-gradient(160deg, #fff, var(--jp-brand-light));
    -webkit-background-clip: text;
            background-clip: text;
    color: transparent;
  }
  .jp-core.no-logo img { display: none; }
  .jp-core.no-logo .jp-monogram { display: block; }

  /* ---------- Wordmark ---------- */
  .jp-brand { text-align: center; }
  .jp-wordmark {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .32em;
    padding-left: .32em; /* optically center tracked text */
  }
  .jp-wordmark span { color: var(--jp-brand-light); font-weight: 600; }
  .jp-sub {
    margin-top: 6px;
    font-size: 11px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--jp-muted);
  }

  /* ---------- Progress + status ---------- */
  .jp-progress {
    position: relative;
    width: 200px;
    height: 3px;
    border-radius: 99px;
    background: rgba(255, 255, 255, 0.06);
    overflow: hidden;
  }
  .jp-progress-bar {
    position: absolute;
    top: 0; bottom: 0; left: 0;
    width: 38%;
    border-radius: inherit;
    background: linear-gradient(90deg, transparent, var(--jp-brand) 30%, var(--jp-brand-light));
    animation: jp-slide 1.6s cubic-bezier(.65, 0, .35, 1) infinite;
  }
  .jp-progress.is-determinate .jp-progress-bar {
    animation: none;
    transform: none;
    background: linear-gradient(90deg, var(--jp-brand), var(--jp-brand-light));
    transition: width .4s ease;
  }

  .jp-status {
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: -12px;
    font-size: 13px;
    color: var(--jp-muted);
    transition: opacity .35s ease, transform .35s ease;
  }
  .jp-status.is-swapping { opacity: 0; transform: translateY(4px); }
  .jp-status svg {
    width: 16px; height: 16px;
    stroke: var(--jp-brand-light);
    fill: none;
    stroke-width: 1.75;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
  }
  .jp-dots::after {
    content: "";
    display: inline-block;
    width: 1.2em;
    text-align: left;
    animation: jp-dots 1.4s steps(4, end) infinite;
  }

  /* ---------- Keyframes ---------- */
  @keyframes jp-spin    { to { transform: rotate(360deg); } }
  @keyframes jp-tick    { 0% { opacity: 1; } 35%, 100% { opacity: .22; } }
  @keyframes jp-breathe { 0%, 100% { opacity: .55; transform: scale(.92); } 50% { opacity: 1; transform: scale(1.05); } }
  @keyframes jp-slide   { 0% { transform: translateX(-110%); } 100% { transform: translateX(280%); } }
  @keyframes jp-enter   { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
  @keyframes jp-dots    { 0% { content: ""; } 25% { content: "."; } 50% { content: ".."; } 75% { content: "..."; } }

  @media (prefers-reduced-motion: reduce) {
    .jp-orbit, .jp-tick, .jp-halo, .jp-stage, .jp-dots::after { animation: none !important; }
    .jp-tick { opacity: .5; }
    .jp-progress-bar { animation-duration: 3s; }
  }
</style>
