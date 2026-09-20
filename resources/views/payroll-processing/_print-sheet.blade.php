{{-- Every worker's payslip for the period on as few A4 sheets as possible,
     six to a page, for the office to print once and cut up.

     It lives inside the page rather than behind a link: a separate tab is a
     second thing to close, and the figures are already here. On screen it is
     hidden; the print stylesheet hides the app around it and shows this. --}}
<div id="ppSheet" class="ps-mode-{{ $sheetMode ?? 'grid' }}" aria-hidden="true">
    <div class="ps-grid">
        @foreach($sheet as $s)
            @php $r = $s['row']; $sl = $s['slip']; @endphp
            <article class="ps-card">
                <header class="ps-head">
                    <img class="ps-logo" alt=""
                         src="{{ $company?->logoUrl() ?? asset('images/JeyancoLogo.png') }}"
                         onerror="this.onerror=null;this.src='{{ asset('images/JeyancoLogo.png') }}'">
                    <div class="ps-co">
                        <div class="co">{{ $company?->company_name ?? 'JEYANCO CONSTRUCTION' }}</div>
                        <div class="sub">{{ $company?->company_tagline ?? 'Payroll Dept.' }}</div>
                    </div>
                    <div class="ps-doc">
                        <div class="lbl">PAYSLIP</div>
                        <div class="per">{{ $period['span'] }}</div>
                    </div>
                </header>

                <div class="ps-emp">
                    <span class="who">{{ $r['name'] }}</span>
                    <span class="meta">{{ $sl['meta'] }}</span>
                </div>
                <div class="ps-basis">{{ $sl['basis'] }}</div>

                <div class="ps-cols">
                    <div>
                        <h6>Earnings</h6>
                        @foreach($sl['earn'] as [$k, $v])
                            <div class="ln"><span class="k">{{ $k }}</span><span class="v">{{ $peso($v) }}</span></div>
                        @endforeach
                        <div class="ln sum"><span class="k">Gross pay</span><span class="v">{{ $peso($sl['gross']) }}</span></div>
                    </div>
                    <div>
                        <h6>Deductions</h6>
                        @foreach($sl['ded'] as [$k, $v])
                            <div class="ln"><span class="k">{{ $k }}</span><span class="v">{{ $peso($v) }}</span></div>
                        @endforeach
                        <div class="ln sum"><span class="k">Total deductions</span><span class="v">{{ $peso($sl['deductions']) }}</span></div>
                    </div>
                </div>

                {{-- The bonus reaches net without passing through gross, so the
                     arithmetic is spelled out rather than left to be inferred. --}}
                <div class="ps-math">
                    <span>{{ $peso($sl['gross']) }}</span>
                    <span>&minus; {{ $peso($sl['deductions']) }}</span>
                    <span>+ {{ $peso($sl['bonus']) }}</span>
                </div>

                <div class="ps-net">
                    <span class="k">NET PAY</span>
                    <span class="v">{{ $peso($sl['net']) }}</span>
                </div>

                <footer class="ps-foot">
                    <span class="sign">Received by</span>
                    <span class="iss">Issued {{ now()->format('M d, Y') }}</span>
                </footer>
            </article>
        @endforeach
    </div>
</div>

<style>
/* Hidden until the browser is laying out pages — the sheet is a print
   artefact, not a second copy of the screen. */
#ppSheet { display: none; }

@media print {
    @page { size: A4 portrait; margin: 8mm; }

    /* The app's chrome is not part of the document being handed over. */
    .sidebar, .topbar, .chatbot-fab, .chatbot-window,
    .pp, .no-print { display: none !important; }
    /* The app's own gutters would otherwise push the last row a few
       millimetres past the page and spill a sliver onto a second sheet. */
    html, body, .main-content { margin: 0 !important; padding: 0 !important; }
    body { background: #fff !important; }

    #ppSheet {
        display: block !important;
        font-family: Inter, system-ui, sans-serif;
        color: #0f1e33;
    }

    /* Two across, three down: six slips to the sheet. Three rows of 87mm
       and two 5mm gaps come to 271mm, inside A4's 281mm of printable height,
       with enough left over that a stray millimetre cannot spill the last
       row onto a second sheet. */
    .ps-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5mm;
    }
    .ps-card {
        height: 87mm;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        border: 1px dashed #94a3b8;   /* where to cut */
        border-radius: 2mm;
        padding: 3.4mm 3.8mm;
        font-size: 7.4pt;
        line-height: 1.32;
        break-inside: avoid;
        page-break-inside: avoid;
        overflow: hidden;
    }
    /* Six to a page falls out of the arithmetic rather than being forced:
       three rows of 89mm plus two 5mm gaps is 277mm, inside A4's 281mm of
       printable height. Forcing a break here instead adds a blank sheet,
       because the row has already ended where the page does. */

    /* A payslip printed on its own has a whole sheet to use, so it uses it:
       one column, no cut line, and type at a size meant for reading rather
       than for fitting six to a page. */
    #ppSheet.ps-mode-single .ps-grid { grid-template-columns: 1fr; }
    #ppSheet.ps-mode-single .ps-card {
        height: auto; border: none; border-radius: 0;
        padding: 0; font-size: 11pt; line-height: 1.5;
    }
    #ppSheet.ps-mode-single .ps-logo { width: 14mm; height: 14mm; }
    #ppSheet.ps-mode-single .ps-co .co { font-size: 15pt; }
    #ppSheet.ps-mode-single .ps-co .sub,
    #ppSheet.ps-mode-single .ps-doc .per,
    #ppSheet.ps-mode-single .ps-basis,
    #ppSheet.ps-mode-single .ps-emp .meta,
    #ppSheet.ps-mode-single .ps-math { font-size: 9pt; }
    #ppSheet.ps-mode-single .ps-doc .lbl { font-size: 12pt; }
    #ppSheet.ps-mode-single .ps-emp .who { font-size: 14pt; }
    #ppSheet.ps-mode-single .ps-emp .meta { max-width: 55%; }
    #ppSheet.ps-mode-single .ps-cols { flex: none; gap: 10mm; }
    #ppSheet.ps-mode-single .ps-cols h6 { font-size: 9pt; }
    #ppSheet.ps-mode-single .ps-cols .ln { padding: 1.1mm 0; }
    #ppSheet.ps-mode-single .ps-net { margin-top: 6mm; padding: 4mm 6mm; }
    #ppSheet.ps-mode-single .ps-net .k { font-size: 11pt; }
    #ppSheet.ps-mode-single .ps-net .v { font-size: 20pt; }
    #ppSheet.ps-mode-single .ps-foot { margin-top: 12mm; font-size: 9pt; }
    #ppSheet.ps-mode-single .ps-foot .sign { min-width: 60mm; padding-top: 2mm; }

    .ps-head {
        display: flex; align-items: center; gap: 2mm;
        border-bottom: .6pt solid #1769e0;
        padding-bottom: 1.4mm; margin-bottom: 1.6mm;
    }
    .ps-logo { width: 7mm; height: 7mm; object-fit: contain; flex: none; }
    .ps-co { flex: 1; min-width: 0; }
    .ps-co .co {
        font-size: 7.6pt; font-weight: 800; color: #1769e0;
        letter-spacing: .2pt; line-height: 1.1;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ps-co .sub { font-size: 5.8pt; color: #5b6a80; }
    .ps-doc { text-align: right; flex: none; }
    .ps-doc .lbl { font-size: 7pt; font-weight: 800; letter-spacing: 1pt; color: #5b6a80; }
    .ps-doc .per { font-size: 5.8pt; color: #5b6a80; }

    .ps-emp {
        display: flex; justify-content: space-between; align-items: baseline;
        gap: 2mm; margin-bottom: .6mm;
    }
    .ps-emp .who { font-size: 8.2pt; font-weight: 700; }
    .ps-emp .meta {
        font-size: 5.8pt; color: #5b6a80; text-align: right;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 48%;
    }
    .ps-basis {
        font-size: 5.8pt; color: #5b6a80; margin-bottom: 1.4mm;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .ps-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; flex: 1; min-height: 0; }
    .ps-cols > div { display: flex; flex-direction: column; min-width: 0; }
    .ps-cols h6 {
        margin: 0 0 .8mm; font-size: 5.6pt; font-weight: 800;
        text-transform: uppercase; letter-spacing: .4pt; color: #5b6a80;
        border-bottom: .5pt solid #d8dfe8; padding-bottom: .5mm;
    }
    .ps-cols .ln {
        display: flex; justify-content: space-between; gap: 1.5mm;
        padding: .28mm 0; font-variant-numeric: tabular-nums;
    }
    .ps-cols .ln .k {
        color: #5b6a80; min-width: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ps-cols .ln .v { font-weight: 600; flex: none; }
    .ps-cols .ln.sum {
        margin-top: auto; padding-top: .8mm;
        border-top: .5pt solid #b9c4d2; font-weight: 800;
    }

    .ps-math {
        display: flex; justify-content: space-between; gap: 2mm;
        font-size: 5.8pt; color: #5b6a80; font-variant-numeric: tabular-nums;
        margin-top: 1.2mm;
    }

    .ps-net {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 1mm; padding: 1.2mm 2.4mm;
        border: .8pt solid #1769e0; border-radius: 1.5mm;
        background: #eaf1fd;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .ps-net .k { font-size: 6.4pt; font-weight: 800; letter-spacing: .5pt; color: #1769e0; }
    .ps-net .v { font-size: 10pt; font-weight: 900; color: #1769e0; font-variant-numeric: tabular-nums; }

    .ps-foot {
        display: flex; justify-content: space-between; align-items: flex-end;
        margin-top: 1.4mm; font-size: 5.6pt; color: #5b6a80;
    }
    .ps-foot .sign { border-top: .5pt solid #8a96a8; padding-top: .5mm; min-width: 26mm; }
}
</style>
