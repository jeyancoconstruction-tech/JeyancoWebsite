@extends('layouts')

@section('page_title', 'Company')

@php
    $name    = old('company_name', $system->company_name);
    $tagline = old('company_tagline', $system->company_tagline);
    $address = old('company_address', $system->company_address);
    $parts   = preg_split('/\s+/', trim((string) $name), 2);
@endphp

@push('styles')
@include('system._kit')
<style>
.co-grid { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 14px; align-items: start; }
@media (max-width: 1200px) { .co-grid { grid-template-columns: 1fr; } }
.co-grid .st-row { grid-template-columns: 1fr; gap: 10px; padding: 14px 18px 16px; }
.co-grid .st-row-l { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.co-grid .st-row-l .t { flex: none; }
.co-grid .st-row-l .d { margin: 0; }
.logo-row { display: flex; gap: 14px; align-items: stretch; flex-wrap: wrap; }
.logo-cur { width: 76px; min-height: 76px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-subtle); display: grid; place-items: center; flex: none; position: relative; }
.logo-cur img { width: 54px; height: 54px; object-fit: contain; }
.logo-cur .lbl { position: absolute; bottom: -9px; left: 50%; transform: translateX(-50%); font-size: 9.5px; font-weight: 700; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; padding: 0 5px; color: var(--text-muted); white-space: nowrap; }
.drop { flex: 1; min-width: 240px; border: 1.5px dashed var(--border-md); border-radius: 10px; background: var(--bg-subtle); padding: 14px 16px; display: flex; align-items: center; gap: 12px; margin: 0; cursor: pointer; transition: border-color .15s, background .15s; }
.drop:hover, .drop.over { border-color: var(--brand); background: var(--brand-subtle); }
.drop.dirty { border-color: var(--warning); }
.drop .ic { width: 38px; height: 38px; border-radius: 9px; background: var(--surface); border: 1px solid var(--border); display: grid; place-items: center; color: var(--brand); flex: none; }
.drop .ic svg { width: 18px; height: 18px; }
.drop .t { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.drop .t u { color: var(--brand); text-decoration: none; }
.drop .d { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

.wh { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; margin: 0; }
.wh th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); font-weight: 700; padding: 8px 12px; text-align: center; border-bottom: 1px solid var(--border); background: var(--bg-subtle); }
.wh th:first-child { text-align: left; padding-left: 16px; }
.wh td { padding: 9px 12px; border-bottom: 1px solid var(--border); text-align: center; color: var(--text-secondary); }
.wh td:first-child { text-align: left; font-weight: 600; color: var(--text-primary); padding-left: 16px; }
.wh td:first-child .s { display: block; font-size: 11px; color: var(--text-muted); font-weight: 500; margin-top: 1px; }
.wh tr:last-child td { border-bottom: none; }
.wh .y, .wh .c { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: var(--brand); }
.wh .c { background: transparent; border: 2px solid var(--brand); }
.wh .n { display: inline-block; width: 10px; height: 2px; background: var(--border-md); vertical-align: middle; }
.wh .hl { background: color-mix(in srgb, var(--warning) 8%, var(--surface)); }
.wh th.hl { background: color-mix(in srgb, var(--warning) 14%, var(--bg-subtle)); color: var(--warning); box-shadow: inset 0 3px 0 var(--warning); }
.wh-leg { display: flex; gap: 16px; padding: 9px 16px; border-top: 1px solid var(--border); font-size: 11.5px; color: var(--text-muted); flex-wrap: wrap; }
.wh-leg span { display: inline-flex; align-items: center; gap: 6px; }

.pv { position: sticky; top: 16px; }
.pv-cap { display: flex; align-items: center; gap: 8px; padding: 11px 14px; border-bottom: 1px solid var(--border); min-height: 48px; }
.pv-cap > svg { width: 15px; height: 15px; color: var(--text-muted); }
.pv-cap .sx-badge { margin-left: auto; }
.pv-lbl { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px 0; }
.paper { margin: 8px 14px 14px; background: #fff; border-radius: 4px; border: 1px solid #EAECF0; box-shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 20px rgba(16,24,40,.10); padding: 16px 16px 18px; color: #101828; }
.paper-h { display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 2px solid #101828; }
.paper-h img { width: 40px; height: 40px; object-fit: contain; flex: none; }
.paper-co { font-size: 13.5px; font-weight: 800; letter-spacing: .02em; line-height: 1.15; word-break: break-word; }
.paper-sub { font-size: 9.5px; color: #475467; margin-top: 2px; }
.paper-ghost { font-size: 9px; color: #98A2B3; margin-top: 2px; border: 1px dashed #D0D5DD; border-radius: 3px; padding: 0 4px; display: inline-block; }
.paper-meta { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; font-size: 9px; color: #475467; }
.paper-meta b { font-size: 10.5px; letter-spacing: .12em; color: #101828; }
.paper-emp { display: flex; justify-content: space-between; margin-top: 11px; font-size: 9.5px; color: #344054; }
.paper-emp b { color: #101828; }
.paper-l { height: 6px; border-radius: 3px; background: #F2F4F7; margin-top: 8px; }
.paper-tot { display: flex; justify-content: space-between; margin-top: 11px; padding-top: 8px; border-top: 1px solid #EAECF0; font-size: 10px; font-weight: 700; }
.rail { margin: 8px 14px 14px; border-radius: 8px; background: linear-gradient(180deg, #123566, #0d2a4f); padding: 13px 14px; display: flex; align-items: center; gap: 10px; }
.rail img { width: 32px; height: 32px; object-fit: contain; filter: drop-shadow(0 1px 3px rgba(0,0,0,.45)); flex: none; }
.rail .l1 { font-size: 14px; font-weight: 800; color: #fff; letter-spacing: .04em; line-height: 1.1; text-transform: uppercase; }
.rail .l2 { font-size: 8.5px; font-weight: 600; letter-spacing: .22em; color: #7c93b5; margin-top: 3px; text-transform: uppercase; }
.pv-note { font-size: 11.5px; color: var(--text-muted); padding: 0 14px 14px; line-height: 1.5; display: flex; gap: 7px; }
.pv-note svg { width: 13px; height: 13px; flex: none; margin-top: 2px; }
</style>
@endpush

@section('content')
<div class="sx-page">
    @include('settings._head', [
        'title' => 'Company',
        'sub'   => 'Who the company says it is — on payslips, the payroll receipt, the sidebar and the sign-in page.',
    ])

    <div class="st-wrap">
        @include('settings._hub')

        <form method="POST" action="{{ route('system-settings.about.update') }}" enctype="multipart/form-data" data-sx-form>
            @csrf
            @method('PUT')

            <div class="co-grid">
                <div>
                    <div class="sx-card">
                        <div class="sx-card-head"><span class="sx-idx">A</span><h2 class="sx-card-title">Company identity</h2><span class="sx-card-note">Printed exactly as typed — capitals included</span></div>

                        <div class="st-row">
                            <div class="st-row-l">
                                <label class="t" for="company_name">Company name <span class="edited" data-edited="company_name" hidden>Edited</span></label>
                                <div class="d">The first word becomes the big line in the sidebar; the rest sits under it.</div>
                            </div>
                            <div class="st-field">
                                <input class="sx-input wide" id="company_name" name="company_name" value="{{ $name }}" maxlength="120" required
                                       data-track data-saved="{{ $system->company_name }}" data-label="Company name">
                                <div class="st-help">
                                    <span data-was="company_name" hidden><i data-lucide="undo-2"></i> Was <span class="was">{{ $system->company_name }}</span></span>
                                    <span class="st-count" data-count-for="company_name">{{ mb_strlen((string) $name) }} / 120</span>
                                </div>
                            </div>
                        </div>

                        <div class="st-row">
                            <div class="st-row-l">
                                <label class="t" for="company_tagline">Line under the name <span class="edited" data-edited="company_tagline" hidden>Edited</span></label>
                                <div class="d">Printed under the name on the batch payslip and the payroll receipt.</div>
                            </div>
                            <div class="st-field">
                                <input class="sx-input wide" id="company_tagline" name="company_tagline" value="{{ $tagline }}" maxlength="160" required
                                       data-track data-saved="{{ $system->company_tagline }}" data-label="Line under the name">
                                <div class="st-help">
                                    <span data-was="company_tagline" hidden><i data-lucide="undo-2"></i> Was <span class="was">{{ $system->company_tagline }}</span></span>
                                    <span class="st-count" data-count-for="company_tagline">{{ mb_strlen((string) $tagline) }} / 160</span>
                                </div>
                            </div>
                        </div>

                        <div class="st-row">
                            <div class="st-row-l">
                                <label class="t" for="company_address">Address <span class="sx-badge muted nodot" style="height:18px;font-size:10.5px">Optional</span> <span class="edited" data-edited="company_address" hidden>Edited</span></label>
                                <div class="d">Only the batch payslip prints it, and only once it’s set.</div>
                            </div>
                            <div class="st-field">
                                <input class="sx-input wide" id="company_address" name="company_address" value="{{ $address }}" maxlength="255" placeholder="Street, barangay, city"
                                       data-track data-saved="{{ $system->company_address }}" data-label="Address">
                                <div class="st-help">
                                    <span data-was="company_address" hidden><i data-lucide="undo-2"></i> Was <span class="was">{{ $system->company_address ?: 'not set' }}</span></span>
                                    <span class="st-count" data-count-for="company_address">{{ mb_strlen((string) $address) }} / 255</span>
                                </div>
                            </div>
                        </div>

                        <div class="st-row">
                            <div class="st-row-l">
                                <span class="t">Logo <span class="edited" data-edited="logo" hidden>New file</span></span>
                                <div class="d">Replaces the logo on payslips, the receipt, the sidebar and the sign-in page.</div>
                            </div>
                            <div class="st-field">
                                <div class="logo-row">
                                    <div class="logo-cur"><img src="{{ $system->logoUrl() }}" alt="Current logo" data-logo><span class="lbl" data-logo-label>CURRENT</span></div>
                                    <label class="drop" for="logo" data-drop data-dirty-wrap="logo">
                                        <span class="ic"><i data-lucide="image-up"></i></span>
                                        <span>
                                            <span class="t" data-drop-text>Drop a PNG or JPG here, or <u>browse</u></span>
                                            <span class="d" style="display:block">Up to 2 MB · a square image with a transparent background looks best</span>
                                        </span>
                                    </label>
                                    <input type="file" id="logo" name="logo" accept="image/*" hidden data-track data-saved="" data-label="Logo">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sx-card" style="margin-top:14px">
                        <div class="sx-card-head"><span class="sx-idx">B</span><h2 class="sx-card-title">Where these appear</h2><span class="sx-card-note">Read from the templates that print them</span></div>
                        <div class="sx-table-wrap">
                            <table class="wh">
                                <thead><tr><th>Printed on</th><th data-col="company_name">Name</th><th data-col="company_tagline">Line</th><th data-col="company_address">Address</th><th data-col="logo">Logo</th></tr></thead>
                                <tbody>
                                    <tr><td>Payslips — batch print<span class="s">every payslip in a run</span></td><td data-col="company_name"><span class="y"></span></td><td data-col="company_tagline"><span class="y"></span></td><td data-col="company_address"><span class="c"></span></td><td data-col="logo"><span class="y"></span></td></tr>
                                    <tr><td>Payslip — single view</td><td data-col="company_name"><span class="y"></span></td><td data-col="company_tagline"><span class="n"></span></td><td data-col="company_address"><span class="n"></span></td><td data-col="logo"><span class="y"></span></td></tr>
                                    <tr><td>Payroll receipt<span class="s">Payroll Processing &amp; Payroll Records</span></td><td data-col="company_name"><span class="y"></span></td><td data-col="company_tagline"><span class="y"></span></td><td data-col="company_address"><span class="n"></span></td><td data-col="logo"><span class="y"></span></td></tr>
                                    <tr><td>Sidebar</td><td data-col="company_name"><span class="y"></span></td><td data-col="company_tagline"><span class="n"></span></td><td data-col="company_address"><span class="n"></span></td><td data-col="logo"><span class="y"></span></td></tr>
                                    <tr><td>Sign-in page</td><td data-col="company_name"><span class="y"></span></td><td data-col="company_tagline"><span class="n"></span></td><td data-col="company_address"><span class="n"></span></td><td data-col="logo"><span class="c"></span></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="wh-leg">
                            <span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--brand)"></span>Always</span>
                            <span><span style="display:inline-block;width:9px;height:9px;border-radius:50%;border:2px solid var(--brand)"></span>Only when set / uploaded</span>
                            <span><span style="display:inline-block;width:10px;height:2px;background:var(--border-md)"></span>Not printed</span>
                        </div>
                    </div>

                    @include('settings._savebar')
                </div>

                <aside class="sx-card pv">
                    <div class="pv-cap"><i data-lucide="printer"></i><h2 class="sx-card-title">Live preview</h2><span class="sx-badge ok" data-pv-badge>Saved</span></div>
                    <div class="pv-lbl"><span class="sx-label">Batch payslip header</span><span class="sx-label">Sample figures</span></div>
                    <div class="paper">
                        <div class="paper-h">
                            <img src="{{ $system->logoUrl() }}" alt="" data-logo>
                            <div style="min-width:0">
                                <div class="paper-co" data-pv="company_name">{{ $name }}</div>
                                <div class="paper-sub" data-pv="company_tagline">{{ $tagline }}</div>
                                <div class="paper-sub" data-pv="company_address" @if(! $address) hidden @endif>{{ $address }}</div>
                                <div class="paper-ghost" data-pv-ghost @if($address) hidden @endif>address prints here once set</div>
                            </div>
                        </div>
                        <div class="paper-meta"><b>PAYSLIP</b><span>{{ now()->startOfWeek()->format('M j') }} – {{ now()->startOfWeek()->addDays(5)->format('M j, Y') }}</span></div>
                        <div class="paper-emp"><span><b>Juan Dela Cruz</b> · Mason</span><span>Site A</span></div>
                        <div class="paper-l" style="width:92%"></div><div class="paper-l" style="width:78%"></div><div class="paper-l" style="width:85%"></div><div class="paper-l" style="width:60%"></div>
                        <div class="paper-tot"><span>NET PAY</span><span>₱ 4,860.00</span></div>
                    </div>
                    <div class="pv-lbl"><span class="sx-label">Sidebar</span><span class="sx-label">split on the first space</span></div>
                    <div class="rail">
                        <img src="{{ $system->logoUrl() }}" alt="" data-logo>
                        <div style="min-width:0"><div class="l1" data-rail="1">{{ $parts[0] ?? '' }}</div><div class="l2" data-rail="2">{{ $parts[1] ?? '' }}</div></div>
                    </div>
                    <div class="pv-note"><i data-lucide="info"></i><span>Both previews update as you type. The payslip keeps its own colours, so it looks the same in the dark theme as on paper.</span></div>
                </aside>
            </div>
        </form>
    </div>
</div>
@endsection

@include('settings._form-script')

@push('scripts')
<script>
(function () {
    const form = document.querySelector('[data-sx-form]');
    if (!form) return;
    const val = n => form.elements[n]?.value ?? '';
    const saved = {};
    ['company_name', 'company_tagline', 'company_address'].forEach(n => { saved[n] = form.elements[n].dataset.saved || ''; });
    const originalLogo = document.querySelector('[data-logo]')?.getAttribute('src');
    const fileInput = document.getElementById('logo');
    const drop = document.querySelector('[data-drop]');
    const dropText = document.querySelector('[data-drop-text]');
    const dropDefault = dropText.innerHTML;

    function draw() {
        document.querySelector('[data-pv="company_name"]').textContent = val('company_name');
        document.querySelector('[data-pv="company_tagline"]').textContent = val('company_tagline');
        const addr = val('company_address').trim();
        const a = document.querySelector('[data-pv="company_address"]');
        a.textContent = addr; a.hidden = !addr;
        document.querySelector('[data-pv-ghost]').hidden = !!addr;

        const parts = val('company_name').trim().split(/\s+/);
        document.querySelector('[data-rail="1"]').textContent = parts.shift() || '';
        document.querySelector('[data-rail="2"]').textContent = parts.join(' ');

        // The column of whatever changed is tinted in "Where these appear".
        const changed = new Set(Object.keys(saved).filter(n => val(n) !== saved[n]));
        if (fileInput.files.length) changed.add('logo');
        document.querySelectorAll('[data-col]').forEach(c => c.classList.toggle('hl', changed.has(c.dataset.col)));

        const badge = document.querySelector('[data-pv-badge]');
        badge.className = 'sx-badge ' + (changed.size ? 'warn' : 'ok');
        badge.textContent = changed.size ? 'Unsaved' : 'Saved';
    }

    function showFile() {
        const f = fileInput.files[0];
        document.querySelectorAll('[data-logo]').forEach(img => { img.src = f ? URL.createObjectURL(f) : originalLogo; });
        document.querySelector('[data-logo-label]').textContent = f ? 'NEW' : 'CURRENT';
        dropText.innerHTML = f ? 'Chosen: <u>' + f.name.replace(/[<>&]/g, '') + '</u>' : dropDefault;
    }

    fileInput.addEventListener('change', () => { showFile(); draw(); });
    ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('over')));
    drop.addEventListener('drop', e => {
        e.preventDefault();
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    form.addEventListener('sx:change', draw);
    form.addEventListener('sx:reset', () => { setTimeout(() => { showFile(); draw(); }, 0); });
    draw();
})();
</script>
@endpush
