/* =========================================================================
 * chatbot-full.js — the floating chat, full screen.
 *
 * Jeyanco AI left the sidebar; the floating chat is how it is reached. Its
 * expand button grows the window into the Jeyanco AI page (quick prompts
 * down the side, the same conversation beside them) over the page it was
 * opened on, which blurs behind it. The shrink button, Esc or a click on the
 * blurred page brings it back to its corner, conversation and all. Close
 * from full screen puts it away.
 *
 * The move: the messages step back, the window grows from where it was to
 * the middle of the screen while the page behind blurs, and the prompts and
 * the conversation settle into place. Shrinking plays it the other way.
 * Anyone who has asked the system for less motion gets the switch at once.
 *
 * Nothing here is remembered: every page opens the chat in its corner.
 * ========================================================================= */
(function () {
    'use strict';

    var win      = document.getElementById('chatbot-window');
    var backdrop = document.getElementById('chatbot-backdrop');
    var fullBtn  = document.getElementById('chatbot-full-btn');
    if (!win || !backdrop || !fullBtn) { return; }

    var body       = win.querySelector('.chatbot-body');
    var main       = win.querySelector('.chatbot-main');
    var panel      = document.getElementById('cb-prompts');
    var promptsBtn = document.getElementById('chatbot-prompts-btn');
    var closeBtn   = document.getElementById('chatbot-minimize-btn');
    var input      = document.getElementById('chatbot-input');
    var icon       = fullBtn.querySelector('i');

    var GROW   = 'cubic-bezier(.22, 1, .36, 1)';   // leaves quickly, settles slowly
    var SHRINK = 'cubic-bezier(.4, 0, .2, 1)';
    var titles = { grow: fullBtn.getAttribute('title') || 'Full screen', shrink: fullBtn.getAttribute('data-exit') || 'Exit full screen' };

    var mq     = function (q) { return window.matchMedia ? window.matchMedia(q).matches : false; };
    var calm   = function () { return mq('(prefers-reduced-motion: reduce)') || typeof win.animate !== 'function'; };
    var narrow = function () { return mq('(max-width: 768px)'); };

    var full = false;       // in full screen, or on the way there
    var busy = false;       // a move is playing
    var panelOpen = true;   // the quick prompts, while in full screen
    var behind = [];        // the page, made inert while the chat covers it

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Play an animation; null when motion is off. */
    function play(el, frames, opts) {
        if (!el || calm()) { return null; }
        return el.animate(frames, opts);
    }
    function done(anims) {
        return Promise.all(anims.filter(Boolean).map(function (a) {
            return a.finished.catch(function () {});
        }));
    }

    /** Where the window is, as the keyframe that holds it there. */
    function box() {
        var r = win.getBoundingClientRect();
        return {
            top: r.top + 'px', left: r.left + 'px', width: r.width + 'px', height: r.height + 'px',
            right: 'auto', bottom: 'auto', maxHeight: 'none'
        };
    }

    /** The drag script places the window with inline styles; full screen has its own place. */
    function unplace() {
        ['left', 'right', 'top', 'bottom', 'maxHeight'].forEach(function (p) { win.style[p] = ''; });
    }
    function replace() {
        if (window.jeyancoChat && window.jeyancoChat.place) { window.jeyancoChat.place(); }
    }

    function showBackdrop() {
        backdrop.hidden = false;
        void backdrop.offsetWidth;            // start from clear, so it fades
        backdrop.classList.add('on');
    }
    function hideBackdrop() {
        backdrop.classList.remove('on');
        var gone = function () { if (!backdrop.classList.contains('on')) { backdrop.hidden = true; } };
        if (calm()) { gone(); } else { setTimeout(gone, 480); }
    }

    /** The page behind can be neither scrolled nor tabbed into while covered. */
    function cover(on) {
        if (on) {
            behind = [document.getElementById('sidebar'), document.querySelector('.main-content')]
                .filter(function (el) { return el && !el.contains(win); });
            behind.forEach(function (el) { el.inert = true; });
            if (window.jeyancoUI) { window.jeyancoUI.lockScroll(); }
        } else {
            behind.forEach(function (el) { el.inert = false; });
            behind = [];
            if (window.jeyancoUI) { window.jeyancoUI.unlockScroll(); }
        }
    }

    function sync() {
        if (icon) { icon.className = full ? 'fas fa-compress' : 'fas fa-expand'; }
        fullBtn.setAttribute('title', full ? titles.shrink : titles.grow);
        fullBtn.setAttribute('aria-pressed', full ? 'true' : 'false');
        if (full) { win.setAttribute('aria-modal', 'true'); } else { win.removeAttribute('aria-modal'); }
    }

    /** The quick prompts slide shut and open (ai.css eases the width). */
    function setPanel(open, instant) {
        panelOpen = open;
        if (!panel) { return; }
        if (instant) { panel.style.transition = 'none'; }
        panel.classList.toggle('collapsed', !open);
        if (instant) { void panel.offsetWidth; panel.style.transition = ''; }
        if (promptsBtn) { promptsBtn.setAttribute('aria-pressed', open ? 'true' : 'false'); }
    }

    // ── Growing ─────────────────────────────────────────────────────────────

    function grow() {
        if (full || busy || !win.classList.contains('open')) { return; }
        busy = true;
        full = true;

        var from = box();
        // The corner's messages step back first, so nothing is seen squeezed.
        var out = play(body, [{ opacity: 1 }, { opacity: 0 }], { duration: 90, easing: 'ease-out', fill: 'forwards' });

        done([out]).then(function () {
            unplace();
            win.classList.add('is-full');
            document.body.classList.add('cb-full-on');
            setPanel(narrow() ? false : panelOpen, true);
            sync();
            showBackdrop();
            cover(true);

            var to = box();
            win.classList.add('is-moving');
            var anims = [
                play(win, [from, to], { duration: 480, easing: GROW }),
                play(body, [{ opacity: 0 }, { opacity: 1 }], { duration: 300, delay: 230, easing: 'ease-out', fill: 'backwards' }),
                play(panel, [{ transform: narrow() ? 'translateY(-10px)' : 'translateX(-18px)' }, { transform: 'none' }],
                     { duration: 440, delay: 210, easing: GROW, fill: 'backwards' }),
                play(main, [{ transform: 'translateY(12px)' }, { transform: 'none' }], { duration: 440, delay: 250, easing: GROW, fill: 'backwards' })
            ];
            if (out) { out.cancel(); }

            done(anims).then(function () {
                win.classList.remove('is-moving');
                busy = false;
                if (input && !narrow()) { input.focus({ preventScroll: true }); }
            });
        });
    }

    // ── Shrinking back to the corner ────────────────────────────────────────

    function shrink() {
        if (!full || busy) { return; }
        busy = true;

        var from = box();
        hideBackdrop();
        var out = play(body, [{ opacity: 1 }, { opacity: 0 }], { duration: 110, easing: 'ease-in', fill: 'forwards' });

        done([out]).then(function () {
            full = false;
            win.classList.remove('is-full');
            document.body.classList.remove('cb-full-on');
            sync();
            replace();

            var to = box();
            win.classList.add('is-moving');     // the drag script waits until it lands
            var anims = [
                play(win, [from, to], { duration: 420, easing: SHRINK }),
                play(body, [{ opacity: 0 }, { opacity: 1 }], { duration: 220, delay: 260, easing: 'ease-out', fill: 'backwards' })
            ];
            if (out) { out.cancel(); }
            cover(false);

            done(anims).then(function () {
                win.classList.remove('is-moving');
                busy = false;
                if (input && !narrow()) { input.focus({ preventScroll: true }); }
            });
        });
    }

    // ── Closing from full screen ────────────────────────────────────────────

    // Ahead of the layout's own handler on the same button, which would take
    // the window away at once: from full screen it steps back and fades.
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            if (!full) { return; }                 // in the corner: the layout closes it
            e.stopImmediatePropagation();
            e.preventDefault();
            if (busy) { return; }
            busy = true;

            hideBackdrop();
            var away = play(win, [
                { opacity: 1, transform: 'none' },
                { opacity: 0, transform: 'translateY(14px) scale(.97)' }
            ], { duration: 260, easing: SHRINK, fill: 'forwards' });

            done([away]).then(function () {
                full = false;
                win.classList.remove('open', 'is-full');
                document.body.classList.remove('cb-full-on');
                if (away) { away.cancel(); }
                sync();
                cover(false);
                replace();
                busy = false;
            });
        });
    }

    // ── Wiring ──────────────────────────────────────────────────────────────

    fullBtn.addEventListener('click', function () { full ? shrink() : grow(); });
    backdrop.addEventListener('click', shrink);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && full) { e.preventDefault(); shrink(); }
    });

    if (promptsBtn) {
        promptsBtn.addEventListener('click', function () { setPanel(!panelOpen); });
    }

    // The prompts: pick a category, then a prompt sends it into the chat.
    if (panel) {
        panel.addEventListener('click', function (e) {
            var cat = e.target.closest('.cat-btn');
            if (cat) {
                panel.querySelectorAll('.cat-btn').forEach(function (b) { b.classList.toggle('active', b === cat); });
                panel.querySelectorAll('.prompt-group').forEach(function (g) {
                    g.classList.toggle('active', g.getAttribute('data-group') === cat.getAttribute('data-cat'));
                });
                return;
            }
            var chip = e.target.closest('.prompt-chip');
            if (chip && window.jeyancoChat && window.jeyancoChat.send) {
                // On a phone the prompts cover the chat; put them away first.
                if (narrow()) { setPanel(false); }
                window.jeyancoChat.send(chip.getAttribute('data-msg'));
            }
        });
    }

    sync();
})();
