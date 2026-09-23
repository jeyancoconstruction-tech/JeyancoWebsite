{{-- The entry loader's markup and its script. The check that decides whether
     it runs at all, and everything that draws it, are in _loading_head —
     they have to be read before this paints. See the notes there.

     JeyancoLoader.show() / .hide() / .setMessage(text, icon) /
     .setProgress(0-100) are on window for anything that wants to put the
     overlay up by hand; data-manual on #jp-loader turns off the auto-hide. --}}

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

        <!-- 12 hour ticks; each lights up as the sweep passes it -->
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

        <!-- sweeping "second hand" on the outer ring -->
        <g class="jp-orbit">
          <path class="jp-orbit-tail" d="M 33.33 33.33 A 66 66 0 0 1 80 14"/>
          <circle class="jp-orbit-dot" cx="80" cy="14" r="3"/>
        </g>
      </svg>

      {{-- The mark, with the monogram behind it: the file is served off the
           app's own public folder, and if it ever is not there the badge
           should still read as Jeyanco rather than as an empty circle. --}}
      <div class="jp-core" id="jp-core">
        <img src="{{ asset('images/logo-mark.png') }}" alt=""
             onerror="this.parentNode.classList.add('no-logo')">
        <span class="jp-monogram">J</span>
      </div>
    </div>

    <div class="jp-brand">
      <div class="jp-wordmark">JEYANCO <span>PAYROLL</span></div>
      <div class="jp-sub">{{ __('Jeyanco Construction') }}</div>
    </div>

    <div class="jp-progress" id="jp-progress">
      <div class="jp-progress-bar" id="jp-progress-bar"></div>
    </div>

    <div class="jp-status" id="jp-status">
      <svg id="jp-status-icon" viewBox="0 0 24 24"></svg>
      <span><span id="jp-status-text">{{ __('Syncing attendance logs') }}</span><span class="jp-dots"></span></span>
    </div>

  </div>
</div>

<script>
(function () {
  var ICONS = {
    calendar: '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M16 3v4M8 3v4M4 11h16M9 16l2 2 4-4"/>',
    clock:    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    users:    '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 3.13a4 4 0 0 1 0 7.75M21 21v-2a4 4 0 0 0-3-3.85"/>',
    peso:     '<path d="M8 19V5h3.5a4.5 4.5 0 0 1 0 9H8M18 8H6M18 11H6"/>'
  };

  var STEPS = [
    { icon: 'calendar', text: @json(__('Syncing attendance logs')) },
    { icon: 'clock',    text: @json(__('Computing hours worked')) },
    { icon: 'users',    text: @json(__('Loading employee records')) },
    { icon: 'peso',     text: @json(__('Preparing payroll summary')) }
  ];

  var el     = document.getElementById('jp-loader');
  var status = document.getElementById('jp-status');
  var icon   = document.getElementById('jp-status-icon');
  var label  = document.getElementById('jp-status-text');
  var prog   = document.getElementById('jp-progress');
  var bar    = document.getElementById('jp-progress-bar');

  var i = 0, timer = null, pinned = false, shownAt = Date.now();

  function render(step) { icon.innerHTML = ICONS[step.icon]; label.textContent = step.text; }

  function swap(fn) {
    status.classList.add('is-swapping');
    setTimeout(function () { fn(); status.classList.remove('is-swapping'); }, 350);
  }

  function cycle() {
    clearInterval(timer);
    timer = setInterval(function () {
      if (pinned) return;
      i = (i + 1) % STEPS.length;
      swap(function () { render(STEPS[i]); });
    }, 1900);
  }

  function reset() {
    i = 0; pinned = false;
    render(STEPS[0]);
    prog.classList.remove('is-determinate');
    bar.style.width = '';
  }

  var api = {
    show: function () {
      reset();
      shownAt = Date.now();
      el.style.display = '';
      void el.offsetWidth; // restart transition
      el.classList.remove('is-hidden');
      cycle();
    },
    hide: function () {
      var wait = Math.max(0, 700 - (Date.now() - shownAt));
      setTimeout(function () {
        el.classList.add('is-hidden');
        clearInterval(timer);
        setTimeout(function () {
          if (el.classList.contains('is-hidden')) el.style.display = 'none';
        }, 520);
      }, wait);
    },
    setMessage: function (text, iconName) {
      pinned = true;
      swap(function () { render({ icon: iconName || 'clock', text: text }); });
    },
    setProgress: function (pct) {
      prog.classList.add('is-determinate');
      bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
    }
  };

  window.JeyancoLoader = api;
  render(STEPS[0]);

  // Internal navigation: loader stays off, only manual show() can bring it back
  if (document.documentElement.classList.contains('jp-skip')) {
    el.classList.add('is-hidden');
    el.style.display = 'none';
    document.documentElement.classList.remove('jp-skip');
    return;
  }

  cycle();

  if (!el.hasAttribute('data-manual')) {
    if (document.readyState === 'complete') api.hide();
    else window.addEventListener('load', api.hide);
  }
})();
</script>
