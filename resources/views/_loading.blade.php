{{-- The loading screen's markup and its controller. The check that decides
     whether it runs, and everything that draws it, are in _loading_head —
     they have to be read before this paints. See the notes there.

     It shows when the site is opened, and only then. Sign In does not raise
     it: the form submits the moment the button is pressed, and the button's
     own spinner says it is working (Michael, 2026-09-23).

     window.JeyancoLoader:
       .hide()         play the exit, then let the page build in underneath. --}}

<div id="jp-loader" role="status" aria-live="polite" aria-label="{{ __('Loading Jeyanco Payroll') }}">
  <div class="jp-stage">
    <div class="jp-dial">
      <div class="jp-halo"></div>
      <svg viewBox="0 0 160 160" aria-hidden="true">
        <defs>
          <linearGradient id="jp-tail" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#4F8FD1" stop-opacity="0"/>
            <stop offset="1" stop-color="#4F8FD1" stop-opacity=".9"/>
          </linearGradient>
        </defs>
        <circle class="jp-track" cx="80" cy="80" r="66"/>
        <g>
          <line class="jp-tick major" x1="80" y1="17" x2="80" y2="24" style="animation-delay:-3s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(30 80 80)"  style="animation-delay:-2.75s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(60 80 80)"  style="animation-delay:-2.5s"/>
          <line class="jp-tick major" x1="80" y1="17" x2="80" y2="24" transform="rotate(90 80 80)"  style="animation-delay:-2.25s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(120 80 80)" style="animation-delay:-2s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(150 80 80)" style="animation-delay:-1.75s"/>
          <line class="jp-tick major" x1="80" y1="17" x2="80" y2="24" transform="rotate(180 80 80)" style="animation-delay:-1.5s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(210 80 80)" style="animation-delay:-1.25s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(240 80 80)" style="animation-delay:-1s"/>
          <line class="jp-tick major" x1="80" y1="17" x2="80" y2="24" transform="rotate(270 80 80)" style="animation-delay:-.75s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(300 80 80)" style="animation-delay:-.5s"/>
          <line class="jp-tick" x1="80" y1="18" x2="80" y2="23" transform="rotate(330 80 80)" style="animation-delay:-.25s"/>
        </g>
        <g class="jp-orbit">
          <path class="jp-orbit-tail" d="M 33.33 33.33 A 66 66 0 0 1 80 14"/>
          <circle class="jp-orbit-dot" cx="80" cy="14" r="3"/>
        </g>
      </svg>
      <div class="jp-core"><img src="{{ asset('images/logo-mark.png') }}" alt=""></div>
    </div>

    <div class="jp-brand">
      <div class="jp-wordmark">JEYANCO <span>PAYROLL</span></div>
      <div class="jp-sub">{{ ($company ?? null)?->company_name ?? __('Jeyanco Construction') }}</div>
    </div>

    <div class="jp-progress"><div class="jp-progress-bar" id="jp-bar"></div></div>

    <div class="jp-status" id="jp-status">
      <svg id="jp-icon" viewBox="0 0 24 24"></svg>
      <span id="jp-text">{{ __('Connecting to server') }}</span>
    </div>
  </div>
</div>

<script>
(function () {
  var root   = document.documentElement;
  var el     = document.getElementById('jp-loader');
  var status = document.getElementById('jp-status');
  var icon   = document.getElementById('jp-icon');
  var text   = document.getElementById('jp-text');
  var bar    = document.getElementById('jp-bar');

  var ICONS = {
    server:   '<rect x="4" y="4" width="16" height="7" rx="2"/><rect x="4" y="13" width="16" height="7" rx="2"/><path d="M8 7.5h.01M8 16.5h.01"/>',
    calendar: '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M16 3v4M8 3v4M4 11h16M9 16l2 2 4-4"/>',
    peso:     '<path d="M8 19V5h3.5a4.5 4.5 0 0 1 0 9H8M18 8H6M18 11H6"/>',
    shield:   '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>'
  };

  var STEPS = [
    { icon: 'server',   text: @json(__('Connecting to server')),    pct: 28 },
    { icon: 'calendar', text: @json(__('Syncing attendance logs')), pct: 56 },
    { icon: 'peso',     text: @json(__('Loading payroll modules')), pct: 80 },
    { icon: 'shield',   text: @json(__('Securing your session')),   pct: 94 }
  ];

  var MIN_SHOW = 1900;   // long enough to feel intentional, short enough not to annoy
  var STEP_MS  = 480;

  // The longest the page stays covered once it has been read. The load event
  // it waits for also waits on every image and map tile — the dashboard's map
  // pulls its tiles from OpenStreetMap — and on a slow line that is many
  // seconds of a page that was ready long before, behind a splash.
  var MAX_WAIT = 3500;

  // Somebody who has asked for less movement is not asking to be held on a
  // splash screen either. The floor drops to a blink; everything else about
  // the screen is the same.
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) MIN_SHOW = 300;
  var start = Date.now(), timer = null, i = 0, hiding = false;

  function set(step) {
    icon.innerHTML = ICONS[step.icon];
    text.textContent = step.text;
    if (step.pct != null) bar.style.width = step.pct + '%';
  }

  function swap(step) {
    status.classList.add('is-swapping');
    setTimeout(function () { set(step); status.classList.remove('is-swapping'); }, 260);
  }

  function run() {
    i = 0; set(STEPS[0]);
    clearInterval(timer);
    timer = setInterval(function () {
      if (i < STEPS.length - 1) swap(STEPS[++i]);
    }, STEP_MS);
  }

  function hide() {
    if (hiding) return;
    hiding = true;
    var wait = Math.max(0, MIN_SHOW - (Date.now() - start));

    setTimeout(function () {
      clearInterval(timer);
      bar.style.width = '100%';
      setTimeout(function () {
        el.classList.add('is-leaving');          // the dial pulls forward and blurs out
        setTimeout(function () {                 // the page begins building underneath it
          root.classList.remove('jp-loading');
          root.classList.add('jp-entering');
          // Once the last piece is in, the entrance lets go of the page's
          // transitions, so the button answers on its own timings.
          setTimeout(function () { root.classList.remove('jp-entering'); }, 3200);
        }, 180);
        setTimeout(function () {
          el.classList.remove('is-leaving');
          el.style.display = 'none';
          // The cursor lands in the first field — but not on a phone, where
          // that throws the keyboard up over the page just arrived at.
          var f = document.getElementById('username');
          if (f && window.innerWidth > 480) f.focus({ preventScroll: true });
        }, 1000);
      }, 350);
    }, wait);
  }

  window.JeyancoLoader = { hide: hide };

  if (root.classList.contains('jp-loading')) {
    run();
    if (document.readyState === 'complete') {
      hide();
    } else {
      window.addEventListener('load', hide);

      // Never longer than MAX_WAIT once the page has been read, whatever
      // images are still arriving.
      var capped = function () { setTimeout(hide, Math.max(0, MAX_WAIT - (Date.now() - start))); };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', capped);
      else capped();
    }
  } else {
    el.style.display = 'none';
  }

  // Coming back from the browser's cache restores the DOM as it was when the
  // page left — possibly mid-flight, with the overlay up and nothing left
  // running to take it down.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    clearInterval(timer);
    el.classList.remove('is-leaving');
    el.style.display = 'none';
    root.classList.remove('jp-loading', 'jp-entering');
  });
})();
</script>
