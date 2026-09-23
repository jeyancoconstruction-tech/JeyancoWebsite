{{-- Chrome for the app-wide notification system. Kept apart from
     _notify.blade.php because that one is included in the <body>, and the
     layout renders @stack('styles') in the <head> before it — a push from
     down there would arrive after the stack had already been written out,
     and the CSS would be silently dropped. This half is included in the
     head; the markup and the script stay in the body. --}}
<style>
/* ── Toast stack ───────────────────────────────────────────────────────────
   Below the topbar, out of the way of the page's own header row. */
#jy-toasts {
    position: fixed;
    top: calc(var(--topbar-height, 68px) + 14px);
    right: 18px;
    z-index: 9600;              /* ui-fixes.css reserves 9500+ for toasts */
    display: flex; flex-direction: column; gap: 9px;
    width: min(360px, calc(100vw - 36px));
    pointer-events: none;       /* the stack never blocks the page… */
}
#jy-toasts > * { pointer-events: auto; }   /* …but each toast is clickable */

.jy-toast {
    position: relative;
    display: flex; align-items: flex-start; gap: 11px;
    padding: 12px 13px 13px;
    border-radius: 12px;
    border: 1px solid var(--border, #e4e7ec);
    background: var(--bg-elevated, #fff);
    /* Two shadows rather than one: a tight contact shadow to lift the card
       off the page and a wide soft one for depth. A single large blur reads
       as a smudge on a light background. */
    box-shadow: 0 1px 2px rgba(16, 24, 40, .08), 0 12px 32px -8px rgba(16, 24, 40, .22);
    font-size: .85rem; line-height: 1.45;
    color: var(--text-primary, #101828);
    overflow: hidden;
    animation: jyToastIn .22s cubic-bezier(.2, .8, .3, 1);
}
@keyframes jyToastIn {
    from { opacity: 0; transform: translateX(14px) scale(.98); }
    to   { opacity: 1; transform: none; }
}
.jy-toast.is-leaving {
    opacity: 0; transform: translateX(14px);
    transition: opacity .22s ease, transform .22s ease;
}
/* One rule respects a reader who has asked for less movement. */
@media (prefers-reduced-motion: reduce) {
    .jy-toast, .jy-toast.is-leaving { animation: none; transition: opacity .15s ease; transform: none; }
}

/* The icon sits in a soft tinted chip — the same idea as the confirm
   dialog's round icon, so a toast and a dialog read as one family rather
   than as two components that happen to share a colour scheme. */
.jy-toast-icon {
    flex: none;
    width: 26px; height: 26px; border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8rem; line-height: 1;
}
.jy-toast-body { flex: 1; min-width: 0; overflow-wrap: anywhere; padding-top: 3px; }
.jy-toast-title {
    display: block; font-weight: 700; margin-bottom: 2px;
    letter-spacing: -.01em; color: var(--text-primary, #101828);
}
/* A toast with a title reads as heading + detail, so the detail steps
   back. The title keeps its own colour above, which wins over this. */
.jy-toast-body:has(.jy-toast-title) { color: var(--text-secondary, #344054); }
.jy-toast-x {
    flex: none; width: 24px; height: 24px; padding: 0; margin: 1px -3px 0 0;
    background: none; border: none; border-radius: 50%; cursor: pointer;
    color: var(--text-muted, #667085); font-size: .75rem; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .12s, color .12s;
}
.jy-toast-x:hover { background: var(--bg-subtle, #f8f9fb); color: var(--text-primary, #101828); }
.jy-toast-x:focus-visible { outline: 2px solid var(--brand, #1668dc); outline-offset: 1px; }

/* The bar is the timer made visible — it says the toast is going to leave, so
   its disappearance does not read as something being taken away. */
.jy-toast-bar {
    position: absolute; left: 0; bottom: 0; height: 3px; width: 100%;
    transform-origin: left; opacity: .5; border-radius: 0 3px 3px 0;
}
@keyframes jyToastBar { from { transform: scaleX(1); } to { transform: scaleX(0); } }

/* The tone is carried by the icon chip and the timer bar. It used to be a
   3px stripe down the left edge as well; with the chip there that was the
   same thing said twice, and the stripe was the half that told you least. */
.jy-toast-success .jy-toast-bar { background: var(--success, #027a48); }
.jy-toast-error   .jy-toast-bar { background: var(--danger,  #b42318); }
.jy-toast-warning .jy-toast-bar { background: var(--warning, #b54708); }
.jy-toast-info    .jy-toast-bar { background: var(--brand,   #1668dc); }
.jy-toast-success .jy-toast-icon { background: var(--success-soft, #ecfdf3); color: var(--success, #027a48); }
.jy-toast-error   .jy-toast-icon { background: var(--danger-soft,  #fef3f2); color: var(--danger,  #b42318); }
.jy-toast-warning .jy-toast-icon { background: var(--warning-soft, #fffaeb); color: var(--warning, #b54708); }
.jy-toast-info    .jy-toast-icon { background: var(--brand-subtle, #eaf2fd); color: var(--brand,   #1668dc); }

/* ── Confirm dialog ────────────────────────────────────────────────────── */
#jy-confirm[hidden] { display: none !important; }
#jy-confirm {
    position: fixed; inset: 0; z-index: 9700;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    /* The colour alone is the fallback; where it is supported the blur puts
       the page behind out of focus, so the question is the only thing with
       an edge on the screen. */
    background: rgba(8, 15, 26, .5);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    animation: jyFade .15s ease;
}
@keyframes jyFade { from { opacity: 0; } to { opacity: 1; } }
.jy-confirm-box {
    width: min(430px, 100%);
    border-radius: 14px;
    border: 1px solid var(--border, #e4e7ec);
    background: var(--bg-elevated, #fff);
    box-shadow: 0 1px 2px rgba(2, 8, 20, .1), 0 24px 56px -12px rgba(2, 8, 20, .38);
    overflow: hidden;
    animation: jyPop .18s cubic-bezier(.2, .8, .3, 1);
}
@keyframes jyPop { from { opacity: 0; transform: translateY(6px) scale(.98); } to { opacity: 1; transform: none; } }
.jy-confirm-head { display: flex; align-items: flex-start; gap: 12px; padding: 18px 20px 0; }
.jy-confirm-icon {
    flex: none; width: 38px; height: 38px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.jy-confirm-danger  .jy-confirm-icon { background: var(--danger-soft,  #fef3f2); color: var(--danger,  #b42318); }
.jy-confirm-warning .jy-confirm-icon { background: var(--warning-soft, #fffaeb); color: var(--warning, #b54708); }
.jy-confirm-brand   .jy-confirm-icon { background: var(--brand-subtle, #eaf2fd); color: var(--brand,   #1668dc); }
.jy-confirm-title { margin: 2px 0 0; font-size: 1rem; font-weight: 700; color: var(--text-primary, #101828); }
.jy-confirm-msg {
    margin: 8px 0 0; padding: 0 20px 0 70px;
    font-size: .875rem; line-height: 1.5; color: var(--text-secondary, #344054);
    overflow-wrap: anywhere;
}
.jy-confirm-foot {
    display: flex; justify-content: flex-end; gap: 9px;
    margin-top: 18px; padding: 14px 20px;
    background: var(--bg-subtle, #f8f9fb);
    border-top: 1px solid var(--border, #e4e7ec);
}
.jy-btn {
    padding: 9px 18px; border-radius: 9px; cursor: pointer;
    font-size: .875rem; font-weight: 600; line-height: 1.35;
    border: 1px solid transparent;
    transition: background .12s, filter .12s, box-shadow .12s, transform .06s;
}
.jy-btn:active { transform: translateY(1px); }
.jy-btn-cancel {
    background: var(--bg-elevated, #fff);
    border-color: var(--border-md, #d0d5dd);
    color: var(--text-secondary, #344054);
}
.jy-btn-cancel:hover { background: var(--bg-subtle, #f8f9fb); color: var(--text-primary, #101828); }
.jy-btn-go { color: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
.jy-confirm-danger  .jy-btn-go { background: var(--danger,  #b42318); }
.jy-confirm-warning .jy-btn-go { background: var(--warning, #b54708); }
.jy-confirm-brand   .jy-btn-go { background: var(--brand,   #1668dc); }
.jy-btn-go:hover { filter: brightness(1.08); }
.jy-btn:focus-visible { outline: 2px solid var(--brand, #1668dc); outline-offset: 2px; }

/* Movement is a nicety; a reader who has asked for less should not get the
   pop or the button dip either. */
@media (prefers-reduced-motion: reduce) {
    #jy-confirm, .jy-confirm-box { animation: none; }
    .jy-btn { transition: none; }
    .jy-btn:active { transform: none; }
}

@media (max-width: 520px) {
    #jy-toasts { top: auto; bottom: 16px; right: 12px; left: 12px; width: auto; }
    .jy-confirm-msg { padding-left: 20px; }
}
</style>
