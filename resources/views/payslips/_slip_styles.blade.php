{{-- The slip's own styling, tokenised like the rest of the app so it reads
     correctly in both themes on screen, and in plain ink when printed. --}}
<style>
.slip {
    background: var(--bg-surface, #fff); color: var(--text-primary, #0f1e33);
    border: 1px solid var(--border, #e4e9f0); border-radius: var(--radius-lg, 12px);
    padding: 20px 22px; max-width: 760px; margin: 0 auto 18px;
    font-size: 13px;
}
.slip-head {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 18px;
    padding-bottom: 14px; border-bottom: 2px solid var(--border, #e4e9f0);
}
.slip-brand { display: flex; align-items: center; gap: 11px; min-width: 0; }
.slip-logo { width: 44px; height: 44px; object-fit: contain; flex: none; }
.slip-company { font-size: 15px; font-weight: 800; letter-spacing: -.01em; }
.slip-addr { font-size: 11px; color: var(--text-muted, #8a96a8); }
.slip-meta { text-align: right; flex: none; }
.slip-doc { font-size: 11px; font-weight: 800; letter-spacing: .16em; color: var(--brand, #1769e0); }
.slip-ref { font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
.slip-period { font-size: 11px; color: var(--text-secondary, #5b6a80); }

.slip-emp {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px 18px; padding: 14px 0; border-bottom: 1px solid var(--border, #e4e9f0);
}
.slip-emp span {
    display: block; font-size: .64rem; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--text-muted, #8a96a8); margin-bottom: 2px;
}
.slip-emp strong { font-size: 12.5px; font-weight: 600; }

.slip-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; padding: 14px 0; }
@media (max-width: 575px) { .slip-cols { grid-template-columns: 1fr; gap: 14px; } }
.slip-col-head {
    font-size: .66rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
    color: var(--text-muted, #8a96a8); padding-bottom: 6px; margin-bottom: 6px;
    border-bottom: 1px solid var(--border, #e4e9f0);
}
.slip-line {
    display: flex; justify-content: space-between; gap: 12px;
    padding: 4px 0; font-size: 12.5px;
}
.slip-line b { font-variant-numeric: tabular-nums; font-weight: 600; }
.slip-line.muted { color: var(--text-muted, #8a96a8); }
.slip-total {
    display: flex; justify-content: space-between; gap: 12px;
    margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border, #e4e9f0);
    font-size: 12.5px; font-weight: 700;
}
.slip-total b { font-variant-numeric: tabular-nums; }

.slip-net {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; margin-top: 4px;
    background: var(--brand-subtle, #eaf1fd); border-radius: var(--radius-md, 10px);
    font-size: 13px; font-weight: 800; letter-spacing: .04em; color: var(--brand, #1769e0);
}
.slip-net b { font-size: 19px; font-variant-numeric: tabular-nums; }

.slip-foot {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin-top: 22px; font-size: 11px; color: var(--text-muted, #8a96a8);
}
.slip-sign span {
    display: block; width: 180px; border-bottom: 1px solid var(--text-muted, #8a96a8);
    margin-bottom: 4px;
}

@media print {
    /* The app's chrome is not part of the document being handed over. */
    .sidebar, .topbar, .chatbot-fab, .chatbot-window, .mod-head-actions,
    .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
    body { background: #fff !important; }
    .slip {
        border: none; box-shadow: none; max-width: none; margin: 0;
        padding: 0; page-break-inside: avoid;
    }
    .slip + .slip { page-break-before: always; margin-top: 0; }
    .slip-net { background: #eef4fd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

