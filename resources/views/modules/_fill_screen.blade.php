{{-- Only the list scrolls, and its card reaches the bottom.

     A page whose list grows without end otherwise grows with it: the heading,
     the totals and the filters scroll away, and the column heads go with them,
     so by the tenth row nothing on screen says which column is which.

     Included rather than copied — Leave & Advances and the Employee Directory
     both size a list this way, and two copies of a measuring script drift.

     To use it: mark the card `data-fill-screen`, mark the element inside it
     that should scroll `data-fill-scroll`, and include this partial. The
     module kit's list wraps carry their own class instead, so both forms are
     matched below. The list needs a sticky `thead` of its own to keep its
     column heads while it scrolls. --}}
<style>
/* A list boxed to the screen: it scrolls on its own, and reaching its end
   does not carry on into the page. */
.mod-table-wrap.is-fitted,
[data-fill-scroll].is-fitted { overflow-y: auto; overscroll-behavior: contain; }

/* While a list card runs to the bottom of the screen, the layout's padding
   under the page and the card's own margin are what would stop it short. The
   script sets the class, so a page without such a list keeps both. Specific
   enough to beat the theme files, which set that padding per theme with
   !important (design-tokens.css, enterprise.css, density.css). */
html.fills-screen[data-bs-theme] .main-content .container-fluid.py-4 { padding-bottom: 0 !important; }
html.fills-screen [data-fill-screen] { margin-bottom: 0 !important; }
</style>

@push('scripts')
<script>
// ── Only the list scrolls, and its card reaches the bottom ─────────────────
// The heading, the tabs, the totals and the filters stay where they are; the
// list's card runs down to the bottom of the screen, and the list scrolls
// inside it with its column heads pinned and its pager at the foot. The card
// goes all the way down however few rows there are, so the page ends in the
// same place whatever is in it. Measured rather than written as a fixed
// calc(), because what sits above the list is not one height — the totals
// wrap on a narrower window, and a validation alert can appear above them.
// On a phone the page scrolls as a whole: a list boxed into what is left of
// a small screen would be a sliver.
(function () {
    const wraps = [...document.querySelectorAll(
        '.mod-card[data-fill-screen] > .mod-table-wrap, [data-fill-screen] [data-fill-scroll]'
    )];
    if (!wraps.length) return;

    const MIN  = 200;                                   // never less than about three rows
    const wide = window.matchMedia('(min-width: 768px)');
    const root = document.documentElement;

    function fit() {
        root.classList.remove('fills-screen');
        wraps.forEach(wrap => {
            wrap.style.height = '';
            wrap.classList.remove('is-fitted');
        });

        if (!wide.matches) return;

        // The layout's own padding and the card's margin under the list are
        // what kept the card short of the bottom; a page with such a list
        // does without them.
        root.classList.add('fills-screen');

        wraps.forEach(wrap => {
            const rect = wrap.getBoundingClientRect();
            const top  = rect.top + window.scrollY;
            const card = wrap.closest('[data-fill-screen]');
            // The rest of the card under the list — its pager, its count line,
            // and its edge.
            const foot = card.getBoundingClientRect().bottom - rect.bottom;
            // Under the card, the same gap the page leaves above it, so the
            // bottom reads as part of the page's own spacing.
            const prev = card.previousElementSibling;
            const gap  = prev ? Math.max(0, card.getBoundingClientRect().top - prev.getBoundingClientRect().bottom) : 13;
            const room = Math.floor(window.innerHeight - top - foot - gap);

            wrap.style.height = Math.max(MIN, room) + 'px';
            wrap.classList.add('is-fitted');
        });

        // Once more, by what is left over. Fitting the list can move what sits
        // above it — the page's scrollbar goes, the width changes, a line of
        // text rewraps — so the first measurement can be a few pixels out.
        const over = root.scrollHeight - window.innerHeight;

        if (over > 0) {
            wraps.forEach(wrap => {
                wrap.style.height = Math.max(MIN, wrap.clientHeight - over) + 'px';
            });
        }
    }

    // After a resize the layout can still be settling on the frame this runs
    // in, so it measures again on the frame after.
    let queued = false;
    const refit = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            fit();
            requestAnimationFrame(() => { queued = false; fit(); });
        });
    };

    window.addEventListener('resize', refit);
    wide.addEventListener?.('change', refit);
    document.fonts?.ready.then(refit);

    // A page that empties or refills its list under the filters changes what
    // sits below it — an empty-state panel appears, a count line rewraps — and
    // the card would then reach past the bottom of the screen. Such a page
    // says so rather than leaving the measurement stale.
    document.addEventListener('fill-screen:refit', refit);

    fit();
})();
</script>
@endpush
