{{--
    One notification system for the whole app.

    Before this there were five ways a CRUD action could answer: the browser's
    own alert() and confirm() boxes, two copy-pasted flashToast() functions
    with hardcoded light-mode colours, and a Bootstrap alert echoed inline on
    nine different pages. The same delete could look completely different
    depending on which screen you were on.

    Everything now goes through window.Notify:

        Notify.success('Saved.')            Notify.error('Could not save.')
        Notify.warning('Nothing selected.') Notify.info('Copied.')
        await Notify.confirm({ title, message, confirmLabel, tone })

    And declaratively, which is how the inline onsubmit="return confirm(...)"
    handlers were replaced without rewriting each one by hand:

        <form data-confirm="Delete this?" data-confirm-tone="danger">
        <button data-confirm="Approve this run?">

    No Bootstrap dependency: the dialog is its own markup, so it works on any
    page whether or not the bundle has loaded.

    Colours are theme tokens, so light and dark both follow the same rules and
    there is no dark-mode twin of this file to keep in step.
--}}


<div id="jy-toasts" aria-live="polite" aria-atomic="false"></div>

<div id="jy-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="jy-confirm-title">
    <div class="jy-confirm-box" id="jy-confirm-box">
        <div class="jy-confirm-head">
            <span class="jy-confirm-icon" id="jy-confirm-icon" aria-hidden="true"><i class="fas fa-triangle-exclamation"></i></span>
            <h2 class="jy-confirm-title" id="jy-confirm-title">{{ __('Are you sure?') }}</h2>
        </div>
        <p class="jy-confirm-msg" id="jy-confirm-msg"></p>
        <div class="jy-confirm-foot">
            <button type="button" class="jy-btn jy-btn-cancel" id="jy-confirm-no">{{ __('Cancel') }}</button>
            <button type="button" class="jy-btn jy-btn-go" id="jy-confirm-yes">{{ __('Confirm') }}</button>
        </div>
    </div>
</div>

<script>
/* Inline, and early in the body, so window.Notify exists before any page
   script that might reach for it. It needs no Bootstrap and no DOM beyond the
   two elements directly above. */
(function () {
    const stack   = document.getElementById('jy-toasts');
    const dialog  = document.getElementById('jy-confirm');
    const dBox    = document.getElementById('jy-confirm-box');
    const dIcon   = document.getElementById('jy-confirm-icon');
    const dTitle  = document.getElementById('jy-confirm-title');
    const dMsg    = document.getElementById('jy-confirm-msg');
    const dYes    = document.getElementById('jy-confirm-yes');
    const dNo     = document.getElementById('jy-confirm-no');

    const ICONS = {
        success: 'fa-circle-check',
        error:   'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info:    'fa-circle-info',
    };
    const DEFAULT_TIMEOUT = { success: 3600, info: 3600, warning: 5200, error: 7000 };
    const PENDING_KEY     = 'jy-notify-pending';   // survives one location.reload()

    function dismiss(el) {
        if (!el || el.dataset.leaving) return;
        el.dataset.leaving = '1';
        el.classList.add('is-leaving');
        setTimeout(() => el.remove(), 240);
    }

    function show(opts) {
        if (!stack) return null;

        const tone    = ICONS[opts.tone] ? opts.tone : 'info';
        const message = String(opts.message ?? '');
        if (!message && !opts.title) return null;

        // An error is worth reading twice; the same success repeated four times
        // is just noise, so an identical live toast is refreshed, not stacked.
        const dupe = [...stack.children].find(c => c.dataset.key === tone + '|' + message);
        if (dupe) {
            dupe.style.animation = 'none';
            void dupe.offsetWidth;
            dupe.style.animation = '';
            return dupe;
        }

        const timeout = opts.timeout === 0 ? 0 : (opts.timeout || DEFAULT_TIMEOUT[tone]);

        const el = document.createElement('div');
        el.className   = 'jy-toast jy-toast-' + tone;
        el.dataset.key = tone + '|' + message;
        el.setAttribute('role', tone === 'error' ? 'alert' : 'status');

        const icon = document.createElement('i');
        icon.className = 'fas ' + ICONS[tone] + ' jy-toast-icon';
        icon.setAttribute('aria-hidden', 'true');

        const body = document.createElement('div');
        body.className = 'jy-toast-body';
        if (opts.title) {
            const t = document.createElement('strong');
            t.className   = 'jy-toast-title';
            t.textContent = opts.title;
            body.appendChild(t);
        }
        // textContent, never innerHTML: these strings carry worker names and
        // server messages, and one of them will contain an apostrophe or an
        // angle bracket sooner or later.
        body.appendChild(document.createTextNode(message));

        const x = document.createElement('button');
        x.type      = 'button';
        x.className = 'jy-toast-x';
        x.setAttribute('aria-label', @json(__('Dismiss')));
        x.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
        x.addEventListener('click', () => dismiss(el));

        el.append(icon, body, x);

        if (timeout > 0) {
            const bar = document.createElement('span');
            bar.className     = 'jy-toast-bar';
            bar.style.animation = `jyToastBar ${timeout}ms linear forwards`;
            el.appendChild(bar);

            let timer = setTimeout(() => dismiss(el), timeout);
            // Reading a long message should not be a race against the timer.
            el.addEventListener('mouseenter', () => {
                clearTimeout(timer);
                bar.style.animationPlayState = 'paused';
            });
            el.addEventListener('mouseleave', () => {
                bar.style.animationPlayState = 'running';
                timer = setTimeout(() => dismiss(el), 1200);
            });
        }

        stack.appendChild(el);

        // A screenful of toasts hides the page behind them.
        while (stack.children.length > 4) dismiss(stack.firstElementChild);

        return el;
    }

    // ── Confirm ─────────────────────────────────────────────────────────────
    let resolver = null;
    let lastFocus = null;

    function closeDialog(answer) {
        if (!resolver) return;
        const done = resolver;
        resolver = null;
        dialog.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
        done(answer);
    }

    function ask(opts) {
        opts = opts || {};
        // Two dialogs at once cannot happen, but if it is asked for, the first
        // question is answered no rather than left hanging forever.
        if (resolver) closeDialog(false);

        const tone = ['danger', 'warning', 'brand'].includes(opts.tone) ? opts.tone : 'danger';
        dBox.className   = 'jy-confirm-box jy-confirm-' + tone;
        dIcon.innerHTML  = '<i class="fas ' + (tone === 'brand' ? 'fa-circle-question' : 'fa-triangle-exclamation') + '"></i>';
        dTitle.textContent = opts.title || @json(__('Are you sure?'));
        dMsg.textContent   = opts.message || '';
        dMsg.hidden        = ! opts.message;
        dYes.textContent   = opts.confirmLabel || @json(__('Confirm'));
        dNo.textContent    = opts.cancelLabel  || @json(__('Cancel'));

        lastFocus = document.activeElement;
        dialog.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(() => dYes.focus(), 30);

        return new Promise(resolve => { resolver = resolve; });
    }

    dYes.addEventListener('click', () => closeDialog(true));
    dNo.addEventListener('click',  () => closeDialog(false));
    dialog.addEventListener('mousedown', e => { if (e.target === dialog) closeDialog(false); });
    document.addEventListener('keydown', e => {
        if (resolver && e.key === 'Escape') { e.preventDefault(); closeDialog(false); }
    });

    window.Notify = {
        show,
        success: (m, o) => show({ ...(o || {}), tone: 'success', message: m }),
        error:   (m, o) => show({ ...(o || {}), tone: 'error',   message: m }),
        warning: (m, o) => show({ ...(o || {}), tone: 'warning', message: m }),
        info:    (m, o) => show({ ...(o || {}), tone: 'info',    message: m }),
        confirm: ask,
        dismissAll: () => [...stack.children].forEach(dismiss),

        /**
         * For the AJAX deletes that finish with location.reload(): a toast
         * shown just before a reload is on screen for a few frames and then
         * gone with the page, so the action reads as having done nothing.
         * This parks the message and the load below picks it up.
         */
        afterReload(tone, message) {
            try {
                const q = JSON.parse(sessionStorage.getItem(PENDING_KEY) || '[]');
                q.push({ tone, message });
                sessionStorage.setItem(PENDING_KEY, JSON.stringify(q));
            } catch (e) {
                show({ tone, message });   // private mode, or storage disabled
            }
        },
    };

    // Anything parked by afterReload() before the page went away.
    try {
        const parked = JSON.parse(sessionStorage.getItem(PENDING_KEY) || '[]');
        sessionStorage.removeItem(PENDING_KEY);
        parked.forEach(item => show(item));
    } catch (e) { /* nothing parked, or no storage */ }

    // ── Declarative confirm ─────────────────────────────────────────────────
    // Delegated, so rows swapped in by live polling are covered too. A form
    // with data-confirm submits only after the dialog says yes; the second
    // submit carries a flag so it is not asked again.
    document.addEventListener('submit', async function (e) {
        const form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.jyConfirmed) return;

        e.preventDefault();
        const ok = await ask({
            title:        form.dataset.confirmTitle,
            message:      form.dataset.confirm,
            confirmLabel: form.dataset.confirmLabel,
            tone:         form.dataset.confirmTone,
        });
        if (!ok) return;

        form.dataset.jyConfirmed = '1';
        // requestSubmit keeps the submitter's name/value and re-runs native
        // validation; submit() would skip both.
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
    }, true);

    // Buttons and links that are not a form submit of their own.
    document.addEventListener('click', async function (e) {
        const el = e.target.closest('[data-confirm]');
        if (!el || el.tagName === 'FORM' || el.closest('form[data-confirm]')) return;
        if (el.dataset.jyConfirmed) { delete el.dataset.jyConfirmed; return; }

        e.preventDefault();
        e.stopPropagation();
        const ok = await ask({
            title:        el.dataset.confirmTitle,
            message:      el.dataset.confirm,
            confirmLabel: el.dataset.confirmLabel,
            tone:         el.dataset.confirmTone,
        });
        if (!ok) return;

        el.dataset.jyConfirmed = '1';
        el.click();
    }, true);

    // ── Server flash ────────────────────────────────────────────────────────
    // The single channel for what a Create, Update or Delete did. Nine pages
    // used to echo these as an inline Bootstrap alert instead, each in its own
    // place on the page.
    @if(session('success'))
        show({ tone: 'success', message: @json(session('success')) });
    @endif
    @if(session('error'))
        show({ tone: 'error', message: @json(session('error')) });
    @endif
    @if(session('warning'))
        show({ tone: 'warning', message: @json(session('warning')) });
    @endif
    @if(session('status') && ! session('success'))
        show({ tone: 'info', message: @json(session('status')) });
    @endif
})();
</script>
