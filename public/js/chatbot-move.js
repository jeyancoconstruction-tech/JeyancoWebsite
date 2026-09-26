/* =========================================================================
 * chatbot-move.js — the floating chat button can be moved out of the way.
 *
 * It sits in the bottom-right corner, which is exactly where a table's last
 * column keeps its buttons, so now and then it covered the one you wanted.
 * Drag it anywhere, with a mouse or a finger; it stays inside the window.
 * Where it is dragged lasts only while the page is open: every page loads
 * with the button back in its corner.
 *
 * A tap or a click still opens the chat. Only a press that moves more than a
 * few pixels is a drag, and the click that ends a drag does not open it.
 *
 * Once it has been moved, the chat window opens beside the button rather than
 * in the corner, on whichever side has room. Left where it started, nothing
 * about either one changes.
 * ========================================================================= */
(function () {
    'use strict';

    var fab = document.getElementById('chatbot-fab');
    var win = document.getElementById('chatbot-window');
    if (!fab || !window.addEventListener) { return; }

    var EDGE = 8;     // closest it may come to the edge of the screen
    var GAP  = 12;    // between the button and the chat window
    var SLOP = 6;     // movement before a press counts as a drag

    var saved   = null;     // {right, bottom} in px once dragged on this page
    var press   = null;     // the drag in progress
    var dragged = false;    // the next click ends a drag: swallow it

    // The first version kept the spot in the browser and opened every page
    // with the button there. It starts in its corner now; clear what that
    // version left behind.
    try { localStorage.removeItem('jeyanco-chatbot-pos'); } catch (e) {}

    // ── Where it is ─────────────────────────────────────────────────────────

    /**
     * The button's own box, and the frame its right/bottom are measured in.
     *
     * Not innerWidth: the page's scrollbar belongs to <body>, and a fixed
     * element here is placed inside the frame that leaves, 15px narrower than
     * the window. Read off the button itself (where it is, plus its offsets)
     * it is right whatever the page does. Its hover and drag zoom are taken
     * out by working from its centre and its unscaled size.
     */
    function frame() {
        var s = fab.offsetWidth;
        if (!s) { return null; }                    // hidden (a dialog is open)
        var cs = getComputedStyle(fab), r = fab.getBoundingClientRect();
        var left = r.left + r.width / 2 - s / 2, top = r.top + r.height / 2 - s / 2;
        return {
            size: s, left: left, top: top,
            w: left + s + (parseFloat(cs.right) || 0),
            h: top + s + (parseFloat(cs.bottom) || 0)
        };
    }

    /** Kept on screen, however the window has been resized since. */
    function clamp(p, f) {
        if (!f) { return p; }
        return {
            right:  Math.round(Math.min(Math.max(p.right, EDGE), Math.max(EDGE, f.w - f.size - EDGE))),
            bottom: Math.round(Math.min(Math.max(p.bottom, EDGE), Math.max(EDGE, f.h - f.size - EDGE)))
        };
    }

    function put(p) {
        fab.style.right  = p.right + 'px';
        fab.style.bottom = p.bottom + 'px';
        fab.style.left   = 'auto';
        fab.style.top    = 'auto';
        place();
    }

    // ── The chat window follows ─────────────────────────────────────────────

    /**
     * Beside the button: above it when the button is in the lower half of the
     * screen, below it otherwise; lined up with its right edge when it is on
     * the right, its left edge when on the left. Never past the screen's edge,
     * and never taller than the room it has.
     */
    function place() {
        if (!win || !saved) { return; }
        var f = frame();
        if (!f) { return; }

        win.style.maxHeight = '';
        var cs     = getComputedStyle(win);
        var cssMax = parseFloat(cs.maxHeight) || 520;
        var width  = parseFloat(cs.width) || 360;
        var right  = f.left + f.size / 2 >= f.w / 2;
        var above  = f.top + f.size / 2 >= f.h / 2;

        if (width >= f.w - 2 * EDGE - 4) {
            // A phone: the window is as wide as the screen allows, centred.
            win.style.left  = Math.max(EDGE, (f.w - width) / 2) + 'px';
            win.style.right = 'auto';
        } else if (right) {
            win.style.right = Math.min(Math.max(f.w - f.left - f.size, EDGE), f.w - width - EDGE) + 'px';
            win.style.left  = 'auto';
        } else {
            win.style.left  = Math.min(Math.max(f.left, EDGE), f.w - width - EDGE) + 'px';
            win.style.right = 'auto';
        }

        if (above) {
            win.style.bottom    = (f.h - f.top + GAP) + 'px';
            win.style.top       = 'auto';
            win.style.maxHeight = Math.max(160, Math.min(cssMax, f.top - GAP - EDGE)) + 'px';
        } else {
            win.style.top       = (f.top + f.size + GAP) + 'px';
            win.style.bottom    = 'auto';
            win.style.maxHeight = Math.max(160, Math.min(cssMax, f.h - f.top - f.size - GAP - EDGE)) + 'px';
        }

        win.style.transformOrigin = (above ? 'bottom ' : 'top ') + (right ? 'right' : 'left');
    }

    // ── Dragging ────────────────────────────────────────────────────────────

    fab.addEventListener('pointerdown', function (e) {
        if (e.button !== undefined && e.button !== 0) { return; }
        var f = frame();
        if (!f) { return; }
        press = {
            id: e.pointerId, x: e.clientX, y: e.clientY, frame: f, moving: false,
            right: f.w - f.left - f.size, bottom: f.h - f.top - f.size
        };
        dragged = false;
        try { fab.setPointerCapture(e.pointerId); } catch (err) {}
        // Once the button moves, whatever was under the press shows through,
        // and if that is a picture or a link (the sidebar logo, an avatar)
        // the browser starts dragging it instead and cancels this one. A
        // mouse press here selects nothing and drags nothing of its own.
        if (e.pointerType === 'mouse') { e.preventDefault(); }
    });

    document.addEventListener('dragstart', function (e) {
        if (press) { e.preventDefault(); }
    }, true);

    fab.addEventListener('pointermove', function (e) {
        if (!press || e.pointerId !== press.id) { return; }
        var dx = e.clientX - press.x, dy = e.clientY - press.y;

        if (!press.moving) {
            if (Math.abs(dx) < SLOP && Math.abs(dy) < SLOP) { return; }
            press.moving = true;
            fab.classList.add('is-dragging');
        }

        saved = clamp({ right: press.right - dx, bottom: press.bottom - dy }, press.frame);
        put(saved);
        e.preventDefault();
    });

    function release(e) {
        if (!press || e.pointerId !== press.id) { return; }
        if (press.moving) {
            dragged = true;
            fab.classList.remove('is-dragging');
        }
        press = null;
    }
    fab.addEventListener('pointerup', release);
    fab.addEventListener('pointercancel', release);

    // The click that ends a drag is not a request to open the chat. Caught on
    // the way down, before the layout's own handler on the button sees it.
    document.addEventListener('click', function (e) {
        if (dragged && fab.contains(e.target)) {
            dragged = false;
            e.stopPropagation();
            e.preventDefault();
        }
    }, true);

    // ── Keeping it right ────────────────────────────────────────────────────

    /** Where it was dragged, pulled back on screen if the window shrinks. */
    window.addEventListener('resize', function () {
        if (!saved) { return; }
        put(saved);
        put(clamp(saved, frame()));
    });

    // The layout opens the window by adding .open; place it as that happens,
    // before it is drawn.
    if (win && window.MutationObserver) {
        new MutationObserver(function () {
            if (win.classList.contains('open')) { place(); }
        }).observe(win, { attributes: true, attributeFilter: ['class'] });
    }
})();
