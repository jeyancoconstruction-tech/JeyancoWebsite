{{-- The sign-in page's entrance: the panel, the fields and the skyline come
     in piece by piece when the site is opened.

     It used to wait behind a full-screen loading overlay that held every
     arrival for at least 1.9 seconds and came back up on Sign In. That was
     time spent looking at a splash instead of the form, so the overlay is
     gone: the page is usable the moment it is drawn, and the entrance plays
     straight away, over half its old delays.

     Only on an ARRIVAL — a typed address, a bookmark, a link from outside. A
     click inside the site, a form coming back with an error and the back
     button show the page as it is, at once.

     The classes are .rv, .draw and .jp-fade. The last was .fade, which is also
     Bootstrap's class for every dialog and tab: loaded on the app's layout,
     its one-second transition made each tab switch wait a second and each
     dialog take two to close. --}}

<script>
/* Opened, or navigated to? A referrer from this same site means a click
   inside the app, and back_forward means the back button — neither is an
   arrival. Both checks are wrapped: a referrer can be an unparseable string,
   and Navigation Timing is not everywhere. */
(function () {
  var d = document.documentElement, internal = false;
  try { internal = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (e) {}
  var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
  if (internal || nav.type === 'back_forward') return;

  // Hidden only for as long as it takes to be drawn once, then let go: the
  // transitions below need a frame in the starting position to run from.
  // They are switched off again once the last piece is in, so the button
  // and the fields answer the mouse on their own timings, not the entrance's.
  d.classList.add('jp-enter');
  function go() {
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        d.classList.replace('jp-enter', 'jp-entering');
        setTimeout(function () { d.classList.remove('jp-entering'); }, 3000);
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
  else go();

  // Restored from the browser's cache mid-entrance: nothing left to finish it.
  window.addEventListener('pageshow', function (e) { if (e.persisted) d.classList.remove('jp-enter', 'jp-entering'); });
})();
</script>

<style>
:root { --jp-ease: cubic-bezier(.2, .8, .2, 1); }

/* Each piece rises in a little after the last (--jp-d, in milliseconds,
   played at half speed so the form is in place in about a third of a
   second). */
.draw { stroke-dasharray: 1; }

.jp-entering .rv      { transition: opacity .7s ease, transform .9s var(--jp-ease); }
.jp-entering .draw    { transition: stroke-dashoffset 1.8s cubic-bezier(.45, 0, .2, 1); }
.jp-entering .jp-fade { transition: opacity 1s ease; }
.jp-entering .rv, .jp-entering .draw, .jp-entering .jp-fade { transition-delay: calc(var(--jp-d, 0) * .5ms); }

.jp-enter .rv      { opacity: 0; transform: translateY(14px); }
.jp-enter .rv-left { transform: translateX(-18px); }
.jp-enter .draw    { stroke-dashoffset: 1; }
.jp-enter .jp-fade { opacity: 0; }

/* A reader who has asked for less movement gets the page, and none of the
   choreography. */
@media (prefers-reduced-motion: reduce) {
    .jp-entering .rv, .jp-entering .draw, .jp-entering .jp-fade {
        transition-duration: .01ms !important;
        transition-delay: 0s !important;
    }
}
</style>
