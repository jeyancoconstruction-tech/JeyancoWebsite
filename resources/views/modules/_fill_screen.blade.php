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
     column heads while it scrolls.

     A list with its totals in a sticky `tfoot` also puts an empty
     `<tbody data-fill-gap aria-hidden="true"><tr><td colspan="…"></td></tr></tbody>`
     just above the foot. While the rows are too few to fill the box, the
     script stretches it, so the totals sit on the card's bottom edge rather
     than under the last row (Michael, 2026-10-15: Payroll Records and
     Reports). It is in the markup, not added by the script, so a live
     refresh finds it where the server wrote it. --}}
<style>
/* A list boxed to the screen: it scrolls on its own, and reaching its end
   does not carry on into the page. */
.mod-table-wrap.is-fitted,
[data-fill-scroll].is-fitted { overflow-y: auto; overscroll-behavior: contain; }

/* The room a short list leaves above its totals. Only space: no padding,
   no lines, no hover tint, and not part of a sticky first column. */
[data-fill-gap] td { height: 0; padding: 0 !important; border: 0 !important; background: transparent !important; position: static !important; }

/* Down on the bottom edge the totals meet the chat button, which starts in
   the bottom-right corner, right over the last total (Net pay). While such a
   list is boxed the button starts just above the totals row instead, over
   the rows, and the chat window opens above it as it does from the corner.
   Making room in the last column would have pushed a wide week sideways on a
   laptop screen. Dragged, the button still goes wherever it is put. */
html.fills-screen:has([data-fill-gap]) .chatbot-fab { bottom: 64px; }
html.fills-screen:has([data-fill-gap]) .chatbot-window { bottom: 130px; }

/* On paper the list runs its whole length, totals straight after it. */
@media print {
    [data-fill-scroll] { height: auto !important; }
    [data-fill-gap] { display: none !important; }
}

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

    // The empty row above a list's totals, if it has any. Looked up each
    // time: a list that empties loses its foot and the row with it.
    const gapOf = wrap => wrap.querySelector(':scope > table > [data-fill-gap] td');

    function fit() {
        root.classList.remove('fills-screen');
        wraps.forEach(wrap => {
            wrap.style.height = '';
            wrap.classList.remove('is-fitted');
            const gap = gapOf(wrap);
            if (gap) gap.style.height = '';
        });

        if (!wide.matches) return;

        // The layout's own padding and the card's margin under the list are
        // what kept the card short of the bottom; a page with such a list
        // does without them.
        root.classList.add('fills-screen');

        wraps.forEach(wrap => {
            // A list on the tab that is not open has no height to measure,
            // and measuring it anyway sized it against a top of zero. It is
            // left alone until its tab is shown, which asks for a refit.
            if (!wrap.offsetParent) { return; }

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
                if (!wrap.offsetParent) { return; }
                wrap.style.height = Math.max(MIN, wrap.clientHeight - over) + 'px';
            });
        }

        // A list too short to fill its box takes the room it leaves above its
        // totals, so they sit on the bottom edge however few rows there are.
        // Once the rows fill the box the foot's sticky bottom holds it there
        // while they scroll. Rounded down, so the list never runs a fraction
        // past the box and grows a scrollbar for nothing.
        wraps.forEach(wrap => {
            const gap = gapOf(wrap);
            if (!gap || !wrap.classList.contains('is-fitted')) { return; }
            const left = wrap.clientHeight - gap.closest('table').getBoundingClientRect().height;
            if (left >= 1) gap.style.height = Math.floor(left) + 'px';
        });
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

    // A live refresh puts the list back as the server wrote it, without the
    // heights measured here, and with rows that may be more or fewer.
    document.addEventListener('live:updated', refit);

    fit();
})();
</script>
@endpush
