@extends('layouts')

@section('page_title', 'System Settings')

{{-- System Settings, built to Michael's jeyanco-settings.html row for row
     (2026-09-27): the same sections, fields, wording, toggles, live preview,
     unsaved-changes bar and audit filters — without its sidebar and top bar,
     which the app has already. Rows the mockup has no place for (password
     length, lockout length, back to Attendance) keep their saved values and
     sit in the section they belong to, drawn the same way. Every field saves
     through its section's own action (SystemSettingsController::updateAll). --}}

@php
    use App\Models\SystemSetting;
    use Illuminate\Support\Str;

    $s = $system;
    $v = fn (string $key, $fallback = null) => old($key, $s->{$key} ?? $fallback);
    // A switch as the form posts it: "1" or "0".
    $sw = fn (string $key) => $s->enabled($key) ? '1' : '0';
    $isOn = fn (string $key) => (string) old($key, $sw($key)) === '1';

    $name    = (string) $v('company_name');
    $tagline = (string) $v('company_tagline');
    $address = (string) $v('company_address');
    $tin     = (string) $v('company_tin');
    $words   = preg_split('/\s+/', trim($name) ?: 'Company name');

    // Each list offers the mockup's choices plus whatever is saved now.
    $choices = fn (array $offer, $saved) => collect($offer)->push((int) $saved)->filter()->unique()->sort()->values();
    $hours   = fn (int $m) => $m % 60 === 0 ? ($m / 60) . ' ' . Str::plural('hour', $m / 60) : ($m < 60 ? $m . ' minutes' : intdiv($m, 60) . ' h ' . ($m % 60) . ' min');
    $secs    = fn (int $x) => $x >= 3600 && $x % 3600 === 0 ? ($x / 3600) . ' ' . Str::plural('hour', $x / 3600)
                            : ($x % 60 === 0 ? ($x / 60) . ' ' . Str::plural('minute', $x / 60) : $x . ' seconds');
    $span    = fn (int $x) => $x % 60 === 0 ? ($x / 60) . ' min' : ($x < 60 ? $x . ' s' : intdiv($x, 60) . ' min ' . ($x % 60) . ' s');
    $after   = fn (int $m) => 'After ' . ($m % 60 === 0 ? ($m / 60) . ' ' . Str::plural('hour', $m / 60) : $m . ' min');

    $savedTheme = $s->default_theme ?: 'dark';
    $savedMode  = $s->kioskMode();
    $savedGuard = (int) ($s->kiosk_repeat_guard_seconds ?? 180);
    $savedIdle  = (int) ($s->kiosk_idle_return_seconds ?? 60);
    $savedOff   = (int) ($s->kiosk_offline_alert_minutes ?: 10);
    $accent     = old('accent_color', $s->accent_color ?: 'blue');
    $density    = old('table_density', $s->table_density ?: 'comfortable');
    $lockout    = (int) $v('lockout_seconds');
    $openNow    = old('kiosk_opens_minutes', $opens);

    $savedAt = $lastSaved?->created_at ?? ($s->exists ? $s->updated_at : null);

    // After a refused save, open the section the first problem is in.
    $fieldSection = [
        'company_name' => 'company', 'company_tagline' => 'company', 'company_address' => 'company', 'company_tin' => 'company', 'logo' => 'company',
        'default_theme' => 'appearance', 'accent_color' => 'appearance', 'table_density' => 'appearance',
        'session_timeout_minutes' => 'security', 'password_min_length' => 'security', 'max_login_attempts' => 'security', 'lockout_seconds' => 'security',
        'kiosk_attendance_mode' => 'kiosk', 'kiosk_repeat_guard_seconds' => 'kiosk', 'kiosk_idle_return_seconds' => 'kiosk',
        'kiosk_offline_alert_minutes' => 'kiosk', 'kiosk_opens_minutes' => 'kiosk',
    ];
    foreach ($errors->keys() as $k) {
        if (isset($fieldSection[$k])) { $section = $fieldSection[$k]; break; }
    }

    $svg = fn (string $path, string $w = '1.8') => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" stroke-linecap="round" aria-hidden="true">' . $path . '</svg>';
    $nav = [
        'ORGANIZATION' => ['company' => ['Company', '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>']],
        'SYSTEM' => [
            'appearance' => ['Appearance', '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 000 18z" fill="currentColor"/>'],
            'security'   => ['Security', '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>'],
            'kiosk'      => ['Kiosks', '<path d="M12 11v3a8 8 0 01-1.5 4.7M8.5 7.2A5 5 0 0117 11v1.5M7 11a5 5 0 01.3-1.8M16.9 16a13 13 0 01-.9 3M5 16.5A12 12 0 006 11a6 6 0 011.2-3.6M12 3a8 8 0 018 8v1M4 11a8 8 0 012-5.3"/>'],
            'notif'      => ['Notifications', '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>'],
            'audit'      => ['Audit logs', '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>'],
        ],
    ];

    $A = $audit;
    $week = now()->startOfWeek();
    $weekEnd = $week->copy()->addDays(6);
    $accentRules = json_encode(collect(SystemSetting::ACCENTS)->keys()->mapWithKeys(fn ($k) => [$k => SystemSetting::accentRules($k)]));
    $auditKeep = request()->only(['action', 'person', 'range', 'quick', 'subject_type', 'subject_id', 'from', 'to', 'user_id']);
@endphp

@push('styles')
<style>
/* The mockup's palette, read from the app's own tokens so the page follows
   the theme and the chosen accent like every other page. */
.ss {
    --panel: var(--surface); --panel-2: #f6f8fc; --panel-3: #eaf0f8;
    --line: var(--border-md); --line-soft: var(--border);
    --text: var(--text-primary); --muted: var(--text-secondary); --faint: var(--text-muted);
    --accent: var(--brand); --accent-soft: var(--brand-subtle); --accent-text: var(--brand-strong);
    --shadow: 0 1px 2px rgba(15,27,45,.05), 0 6px 18px rgba(15,27,45,.05);
    --mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
    display: flex; flex-direction: column; gap: 16px; color: var(--text);
}
html[data-bs-theme="dark"] .ss { --panel-2: #15233a; --panel-3: #1b2b46; --shadow: 0 1px 0 rgba(255,255,255,.03) inset, 0 8px 24px rgba(0,0,0,.25); }
html[data-bs-theme] .main-content .ss > .page-head { margin-bottom: -4px !important; }
.ss [hidden] { display: none !important; }
.ss-card { background: var(--panel); border: 1px solid var(--line-soft); border-radius: 14px; box-shadow: var(--shadow); }
.ss-btn { border: 1px solid var(--line); background: var(--panel); border-radius: 10px; height: 38px; padding: 0 14px; font-weight: 600; font-size: 13px; display: inline-flex; gap: 8px; align-items: center; cursor: pointer; color: var(--text); white-space: nowrap; text-decoration: none; }
.ss-btn svg { width: 16px; height: 16px; flex: none; }
.ss-btn:hover { border-color: var(--accent); color: var(--text); }
.ss-btn.pri { background: var(--accent); border-color: var(--accent); color: #fff; }
.ss-btn.ghost { background: transparent; border-color: transparent; color: var(--muted); }
.ss-btn.ghost:hover { color: var(--text); border-color: var(--line); }
.ss-btn.danger { color: var(--danger); }
.ss-btn:disabled { opacity: .6; cursor: default; }
.ss-saved { display: flex; gap: 8px; align-items: center; font-size: 12.5px; color: var(--muted); }
.ss-saved svg { width: 15px; height: 15px; }
.ss-saved b { color: var(--text); font-weight: 600; }
.ss-alert { display: flex; gap: 10px; padding: 12px 14px; border-radius: 12px; background: var(--danger-soft); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 30%, transparent); font-size: 13px; }
.ss-alert ul { margin: 4px 0 0; padding-left: 18px; }

.ss-set { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 18px; align-items: start; }

/* Settings nav */
.ss-nav { position: sticky; top: calc(var(--topbar-height, 60px) + 16px); padding: 10px; display: flex; flex-direction: column; gap: 2px; }
.ss-nav h6 { margin: 10px 10px 6px; font-size: 10px; letter-spacing: .14em; color: var(--faint); font-weight: 700; }
.ss-nav h6:first-child { margin-top: 4px; }
.ss-si { display: flex; gap: 10px; align-items: center; padding: 0 10px; height: 40px; border-radius: 9px; border: 0; background: none; color: var(--muted); font-size: 13.5px; font-weight: 500; cursor: pointer; text-align: left; width: 100%; }
.ss-si svg { width: 17px; height: 17px; flex: none; }
.ss-si:hover { background: var(--panel-2); color: var(--text); }
.ss-si.on { background: var(--accent-soft); color: var(--accent-text); font-weight: 700; }
.ss-si:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
.ss-nav .hint { margin: 10px 6px 2px; padding: 10px 10px 4px; border-top: 1px solid var(--line-soft); font-size: 12px; color: var(--muted); line-height: 1.5; }
.ss-nav .hint a { color: var(--accent-text); font-weight: 600; text-decoration: none; }

/* Section */
.ss-sec { display: flex; flex-direction: column; gap: 14px; }
.ss-sec-h { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.ss-sec-h h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--text); }
.ss-sec-h p { margin: 3px 0 0; color: var(--muted); font-size: 13px; }
.ss-split { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 14px; align-items: start; }
.ss-grp { padding: 4px 18px; }
.ss-grp > h4 { margin: 14px 0 2px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
.ss-row { display: grid; grid-template-columns: minmax(0, 190px) minmax(0, 1fr); gap: 16px; align-items: start; padding: 14px 0; border-bottom: 1px solid var(--line-soft); }
.ss-row:last-child { border-bottom: 0; }
.ss-row .lb b { display: block; font-size: 13.5px; font-weight: 600; color: var(--text); }
.ss-row .lb small { display: block; color: var(--muted); font-size: 12px; margin-top: 3px; line-height: 1.45; }
.ss-opt { font-size: 10.5px; font-weight: 700; color: var(--muted); border: 1px solid var(--line); border-radius: 6px; padding: 1px 6px; margin-left: 6px; vertical-align: 1px; }
.ss-inp { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
html[data-bs-theme] .ss .ss-inp input[type=text],
html[data-bs-theme] .ss .ss-inp input[type=number],
html[data-bs-theme] .ss .ss-inp select {
    border: 1px solid var(--line) !important; background: var(--panel-2) !important; border-radius: 10px !important;
    height: 40px; padding: 0 12px; outline: none; color: var(--text) !important; font-size: 13.5px; width: 100%; box-shadow: none !important;
}
html[data-bs-theme] .ss .ss-inp input:focus,
html[data-bs-theme] .ss .ss-inp select:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 3px var(--accent-soft) !important; }
.ss .ss-inp select option { background: var(--panel); }
.ss-inp .cnt { align-self: flex-end; font-size: 11px; color: var(--faint); font-family: var(--mono); }
.ss-inp.short { max-width: 220px; }
.ss-err { font-size: 12px; color: var(--danger); font-weight: 600; }

/* Logo */
.ss-logo-up { display: flex; gap: 14px; align-items: center; }
.ss-logo-prev { width: 64px; height: 64px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #2b64b8, #123a7a 70%); border: 2px solid #6fa3ea; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 18px; flex: none; overflow: hidden; }
.ss-logo-prev img { width: 100%; height: 100%; object-fit: cover; }
.ss-drop { flex: 1; border: 1.5px dashed var(--line); border-radius: 12px; padding: 12px 14px; display: flex; gap: 10px; align-items: center; cursor: pointer; color: var(--muted); font-size: 12.5px; margin: 0; }
.ss-drop:hover, .ss-drop.over { border-color: var(--accent); background: var(--accent-soft); }
.ss-drop b { color: var(--text); }
.ss-drop u { color: var(--accent-text); text-decoration: none; font-weight: 600; }
.ss-drop svg { width: 20px; height: 20px; flex: none; }

/* Segmented choices, swatches and switches (radios and checkboxes underneath) */
.ss-seg { display: inline-flex; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; background: var(--panel-2); max-width: 100%; }
.ss-seg label { margin: 0; cursor: pointer; }
.ss-seg input, .ss-sw input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
.ss-seg span { padding: 0 14px; height: 38px; color: var(--muted); font-size: 13px; font-weight: 600; display: flex; gap: 7px; align-items: center; }
.ss-seg input:checked + span { background: var(--accent); color: #fff; }
.ss-seg input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: -2px; }
.ss-tg { position: relative; width: 42px; height: 24px; flex: none; margin: 0; }
.ss-tg input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; z-index: 1; }
.ss-tg span { position: absolute; inset: 0; border-radius: 999px; background: var(--panel-3); border: 1px solid var(--line); transition: background .2s; }
.ss-tg span::after { content: ""; position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .2s; }
.ss-tg input:checked + span { background: var(--accent); border-color: var(--accent); }
.ss-tg input:checked + span::after { transform: translateX(18px); }
.ss-tg input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }
.ss-tgrow { display: flex; gap: 12px; align-items: center; }
.ss-tgrow > small { color: var(--muted); font-size: 12px; }
.ss-tgrow .ss-inp.short { flex: none; }
.ss-tgrow .ss-inp.short.off select { opacity: .45; pointer-events: none; }
.ss-sw { display: flex; gap: 8px; }
.ss-sw label { margin: 0; }
.ss-sw span { display: block; width: 30px; height: 30px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: var(--c); }
.ss-sw input:checked + span { border-color: var(--text); box-shadow: 0 0 0 2px var(--panel) inset; }
.ss-sw input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }

/* Live preview */
.ss-prev { position: sticky; top: calc(var(--topbar-height, 60px) + 16px); padding: 14px; display: flex; flex-direction: column; gap: 12px; }
.ss-prev h4 { margin: 0; font-size: 13px; font-weight: 700; display: flex; gap: 8px; align-items: center; color: var(--text); }
.ss-prev h4 svg { width: 16px; height: 16px; color: var(--accent-text); }
.ss-ptabs { display: flex; gap: 4px; background: var(--panel-2); border-radius: 9px; padding: 3px; }
.ss-ptabs button { flex: 1; border: 0; background: none; color: var(--muted); font-size: 12px; font-weight: 600; height: 28px; border-radius: 7px; cursor: pointer; }
.ss-ptabs button.on { background: var(--panel); color: var(--text); box-shadow: 0 1px 2px rgba(0,0,0,.15); }
.ss-slip { background: #fff; color: #0f1b2d; border-radius: 10px; padding: 14px 16px; font-size: 11px; box-shadow: 0 6px 18px rgba(0,0,0,.2); }
.ss-slip header { display: flex; gap: 10px; align-items: center; border-bottom: 2px solid #0f1b2d; padding-bottom: 10px; margin-bottom: 10px; }
.ss-lg { width: 36px; height: 36px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #2b64b8, #123a7a 70%); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 11px; flex: none; overflow: hidden; }
.ss-lg img { width: 100%; height: 100%; object-fit: cover; }
.ss-slip header b { display: block; font-size: 13px; letter-spacing: .04em; overflow-wrap: anywhere; }
.ss-slip header small { display: block; color: #56657d; font-size: 10.5px; line-height: 1.4; }
.ss-slip .ln { height: 6px; border-radius: 3px; background: #e7ecf4; margin: 6px 0; }
.ss-slip .net { display: flex; justify-content: space-between; font-weight: 800; border-top: 1px solid #d6dde8; padding-top: 8px; margin-top: 8px; }
.ss-sbar { border-radius: 10px; background: linear-gradient(135deg, #123a7a, #0b1a33); padding: 14px; display: flex; gap: 10px; align-items: center; color: #fff; }
.ss-sbar .ss-lg { border: 1.5px solid #6fa3ea; }
.ss-sbar b { display: block; font-size: 15px; letter-spacing: .1em; overflow-wrap: anywhere; }
.ss-sbar small { font-size: 9.5px; letter-spacing: .38em; color: #a9bbd9; }
.ss-tabp { border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
.ss-tabp .bar { display: flex; gap: 8px; align-items: center; padding: 8px 10px; background: var(--panel-2); font-size: 12px; min-width: 0; }
.ss-tabp .bar i { width: 14px; height: 14px; border-radius: 50%; background: var(--accent); flex: none; }
.ss-tabp .bar span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ss-tabp .body { height: 70px; background: linear-gradient(135deg, #123a7a, #0b1a33); }
.ss-prev .note { font-size: 11.5px; color: var(--muted); margin: 0; }

/* Audit log */
.ss-tools { display: flex; gap: 8px; flex-wrap: wrap; padding: 12px 18px; border-bottom: 1px solid var(--line-soft); }
.ss-sel { display: flex; align-items: center; gap: 8px; border: 1px solid var(--line); background: var(--panel); border-radius: 10px; padding: 0 10px; height: 38px; margin: 0; }
.ss-sel svg { width: 15px; height: 15px; color: var(--muted); flex: none; }
html[data-bs-theme] .ss .ss-sel input, html[data-bs-theme] .ss .ss-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none; color: var(--text); font-size: 14px; height: 36px; padding: 0; min-width: 0;
}
.ss .ss-sel select option { background: var(--panel); }
.ss-chip { display: inline-flex; gap: 8px; align-items: center; font-size: 12px; font-weight: 600; background: var(--accent-soft); color: var(--accent-text); border-radius: 999px; padding: 0 12px; height: 38px; }
.ss-chip a { color: inherit; text-decoration: none; font-weight: 800; }
.ss-log { display: grid; grid-template-columns: 120px minmax(0, 1fr) auto; gap: 12px; padding: 11px 18px; border-bottom: 1px solid var(--line-soft); font-size: 13px; align-items: center; color: var(--text); }
.ss-log:last-child { border-bottom: 0; }
.ss-log time { font-family: var(--mono); font-size: 12px; color: var(--muted); }
.ss-log > div { min-width: 0; overflow-wrap: anywhere; }
.ss-log small { display: block; color: var(--muted); font-size: 12px; }
.ss-pill { font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 3px 9px; background: var(--panel-3); color: var(--muted); white-space: nowrap; }
.ss-empty { padding: 32px; text-align: center; color: var(--muted); margin: 0; }
.ss-more { display: flex; justify-content: center; padding: 10px; border-top: 1px solid var(--line-soft); }

/* Unsaved bar */
.ss-bar { position: fixed; left: 50%; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); transform: translate(-50%, 160%); display: flex; gap: 10px; align-items: center; background: var(--text-primary); color: var(--bg-body); border-radius: 14px; padding: 8px 8px 8px 16px; box-shadow: 0 20px 50px rgba(0,0,0,.35); z-index: 1050; transition: transform .35s cubic-bezier(.2, .8, .2, 1); max-width: calc(100% - 32px); visibility: hidden; }
.ss-bar.show { transform: translate(-50%, 0); visibility: visible; }
.ss-bar > span { font-size: 13px; font-weight: 600; display: flex; gap: 8px; align-items: center; }
.ss-bar > span i { width: 8px; height: 8px; border-radius: 50%; background: var(--warning); flex: none; }
.ss-bar .ss-btn { height: 34px; }
.ss-bar .ss-btn.ghost { color: var(--bg-body); opacity: .75; }
.ss-bar .ss-btn.ghost:hover { opacity: 1; border-color: transparent; }

@media (max-width: 1300px) { .ss-split { grid-template-columns: minmax(0, 1fr); } .ss-prev { position: static; } }
@media (prefers-reduced-motion: reduce) { .ss-bar { transition: none; } }
</style>
@endpush

@section('content')
<div class="ss">
    <x-page-header :title="__('Settings')">
        <x-slot:actions>
            <span class="ss-saved">{!! $svg('<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 8v4l3 2"/>', '2') !!}
                @if($savedAt)
                    <span>{{ __('Last saved') }} <b>{{ $savedAt->format('M j, Y · g:i A') }}</b>@if($lastSaved?->user_name) {{ __('by') }} {{ $lastSaved->user_name }}@endif</span>
                @else
                    <span>{{ __('Never changed — showing the built-in defaults') }}</span>
                @endif
            </span>
        </x-slot:actions>
    </x-page-header>

    @if($errors->any())
        <div class="ss-alert" role="alert">
            <div><strong>{{ __('Nothing was saved.') }}</strong> {{ __('Fix these and save again:') }}
                <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        </div>
    @endif

    <div class="ss-set">
        <nav class="ss-card ss-nav" aria-label="{{ __('Settings sections') }}">
            @foreach($nav as $group => $items)
                <h6>{{ __($group) }}</h6>
                @foreach($items as $key => [$label, $path])
                    <button type="button" class="ss-si {{ $section === $key ? 'on' : '' }}" data-s="{{ $key }}" @if($section === $key) aria-current="page" @endif>{!! $svg($path) !!}{{ __($label) }}</button>
                @endforeach
            @endforeach
            <p class="hint">{{ __('Pay rates, shifts and holidays live in') }} <a href="{{ route('settings.index') }}">{{ __('Payroll Settings') }}</a>.</p>
        </nav>

        <div>
            <form method="POST" action="{{ route('system-settings.update-all') }}" enctype="multipart/form-data" id="ssForm" novalidate>
                @csrf
                @method('PUT')

                {{-- ── COMPANY ─────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="company" @if($section !== 'company') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Company') }}</h3><p>{{ __('Shown on payslips, receipts, the sidebar and the sign-in page.') }}</p></div></div>
                    <div class="ss-split">
                        <div class="ss-card ss-grp">
                            <h4>{{ __('Identity') }}</h4>
                            <div class="ss-row"><div class="lb"><b>{{ __('Company name') }}</b><small>{{ __('First word is the big line in the sidebar; the rest goes under it.') }}</small></div>
                                <div class="ss-inp"><input type="text" id="company_name" name="company_name" maxlength="120" value="{{ $name }}" data-track data-saved="{{ $s->company_name }}" data-label="{{ __('Company name') }}"><span class="cnt" data-cnt="company_name"></span>@error('company_name')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                            <div class="ss-row"><div class="lb"><b>{{ __('Line under the name') }}</b><small>{{ __('Printed under the name on payslips and receipts.') }}</small></div>
                                <div class="ss-inp"><input type="text" id="company_tagline" name="company_tagline" maxlength="160" value="{{ $tagline }}" data-track data-saved="{{ $s->company_tagline }}" data-label="{{ __('Line under the name') }}"><span class="cnt" data-cnt="company_tagline"></span>@error('company_tagline')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                            <div class="ss-row"><div class="lb"><b>{{ __('Address') }}<span class="ss-opt">{{ __('Optional') }}</span></b><small>{{ __('Printed on the batch payslip only.') }}</small></div>
                                <div class="ss-inp"><input type="text" id="company_address" name="company_address" maxlength="255" value="{{ $address }}" data-track data-saved="{{ $s->company_address }}" data-label="{{ __('Address') }}"><span class="cnt" data-cnt="company_address"></span>@error('company_address')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                            <div class="ss-row"><div class="lb"><b>{{ __('TIN') }}<span class="ss-opt">{{ __('Optional') }}</span></b><small>{{ __('Shown on remittance reports.') }}</small></div>
                                <div class="ss-inp"><input type="text" id="company_tin" name="company_tin" maxlength="40" value="{{ $tin }}" placeholder="000-000-000-000" data-track data-saved="{{ $s->company_tin }}" data-label="{{ __('TIN') }}">@error('company_tin')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                            <h4>{{ __('Logo') }}</h4>
                            <div class="ss-row"><div class="lb"><b>{{ __('Company logo') }}</b><small>{{ __('Square PNG or JPG, at least 256 × 256, up to 2 MB.') }}</small></div>
                                <div class="ss-inp">
                                    <div class="ss-logo-up"><div class="ss-logo-prev" id="logoPrev"><img src="{{ $s->logoUrl() }}" alt="" data-logo></div>
                                        <label class="ss-drop" id="drop" for="logo">{!! $svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>') !!}<span data-drop-text><b>{{ __('Drop an image here') }}</b> {{ __('or') }} <u>{{ __('browse') }}</u></span></label>
                                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg" hidden data-track data-saved="" data-label="{{ __('Logo') }}">
                                    </div>
                                    @error('logo')<span class="ss-err">{{ $message }}</span>@enderror
                                </div></div>
                        </div>
                        <aside class="ss-card ss-prev" aria-label="{{ __('Live preview') }}">
                            <h4>{!! $svg('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>', '2') !!}{{ __('Live preview') }}</h4>
                            <div class="ss-ptabs" role="group" aria-label="{{ __('Preview') }}"><button class="on" data-p="slip" type="button">{{ __('Payslip') }}</button><button data-p="side" type="button">{{ __('Sidebar') }}</button><button data-p="tab" type="button">{{ __('Sign-in') }}</button></div>
                            <div data-pv="slip"><div class="ss-slip"><header><span class="ss-lg"><img src="{{ $s->logoUrl() }}" alt="" data-logo></span><div><b data-b="name">{{ $name }}</b><small data-b="sub">{{ $tagline }}</small><small data-b="addr">{{ $address }}</small></div></header><div style="display:flex;justify-content:space-between;font-weight:800"><span>PAYSLIP</span><span style="font-weight:500;color:#56657d">{{ $week->format('M j') }} – {{ $weekEnd->format($week->month === $weekEnd->month ? 'j, Y' : 'M j, Y') }}</span></div><div class="ln" style="width:90%"></div><div class="ln" style="width:70%"></div><div class="ln" style="width:80%"></div><div class="net"><span>NET PAY</span><span>₱4,860.00</span></div></div></div>
                            <div data-pv="side" hidden><div class="ss-sbar"><span class="ss-lg"><img src="{{ $s->logoUrl() }}" alt="" data-logo></span><div><b data-b="first">{{ $words[0] }}</b><small data-b="rest">{{ implode(' ', array_slice($words, 1)) }}</small></div></div></div>
                            <div data-pv="tab" hidden><div class="ss-tabp"><div class="bar"><i></i><span data-b="tab">{{ $name }} | Sign in</span></div><div class="body"></div></div></div>
                            <p class="note">{{ __('Updates as you type. Nothing changes for other users until you save.') }}</p>
                        </aside>
                    </div>
                </section>

                {{-- ── APPEARANCE ──────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="appearance" @if($section !== 'appearance') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Appearance') }}</h3><p>{{ __('Default look for everyone. Each user can still switch from the top bar.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        <div class="ss-row"><div class="lb"><b>{{ __('Default theme') }}</b><small>{{ __("Used when a user hasn't picked one.") }}</small></div>
                            <div class="ss-inp"><div class="ss-seg" role="radiogroup" aria-label="{{ __('Default theme') }}">
                                @foreach(['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $val => $label)
                                    <label><input type="radio" name="default_theme" value="{{ $val }}" @checked(old('default_theme', $savedTheme) === $val) data-track data-saved="{{ $savedTheme }}" data-label="{{ __('Default theme') }}"><span>{{ __($label) }}</span></label>
                                @endforeach
                            </div>@error('default_theme')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Accent color') }}</b><small>{{ __('Buttons, links and highlights.') }}</small></div>
                            <div class="ss-sw" role="radiogroup" aria-label="{{ __('Accent color') }}">
                                @foreach(SystemSetting::ACCENTS as $val => [$label, $swatch])
                                    <label title="{{ __($label) }}"><input type="radio" name="accent_color" value="{{ $val }}" aria-label="{{ __($label) }}" @checked($accent === $val) data-track data-saved="{{ $s->accent_color ?: 'blue' }}" data-label="{{ __('Accent color') }}"><span style="--c:{{ $swatch }}"></span></label>
                                @endforeach
                            </div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Table density') }}</b><small>{{ __('Compact fits more rows on smaller screens.') }}</small></div>
                            <div class="ss-inp"><div class="ss-seg" role="radiogroup" aria-label="{{ __('Density') }}">
                                @foreach(SystemSetting::DENSITIES as $val => $label)
                                    <label><input type="radio" name="table_density" value="{{ $val }}" @checked($density === $val) data-track data-saved="{{ $s->table_density ?: 'comfortable' }}" data-label="{{ __('Table density') }}"><span>{{ __($label) }}</span></label>
                                @endforeach
                            </div></div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Intro animation') }}</b><small>{{ __('Play the site animation when the sign-in page opens fresh.') }}</small></div>
                            <div class="ss-tgrow"><input type="hidden" name="signin_intro" value="0"><label class="ss-tg"><input type="checkbox" name="signin_intro" value="1" @checked($isOn('signin_intro')) aria-label="{{ __('Intro animation') }}" data-track data-saved="{{ $sw('signin_intro') }}" data-label="{{ __('Intro animation') }}"><span></span></label><small></small></div></div>
                    </div>
                </section>

                {{-- ── SECURITY ────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="security" @if($section !== 'security') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Security') }}</h3><p>{{ __('Sign-in rules for every account.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        <div class="ss-row"><div class="lb"><b>{{ __('Session length') }}</b><small>{{ __('Users are signed out after this long without activity.') }}</small></div>
                            <div class="ss-inp short"><select name="session_timeout_minutes" data-track data-saved="{{ (int) $s->session_timeout_minutes }}" data-label="{{ __('Session length') }}">
                                @foreach($choices([30, 60, 120, 480], $s->session_timeout_minutes) as $m)<option value="{{ $m }}" @selected((int) $v('session_timeout_minutes') === $m)>{{ $hours($m) }}</option>@endforeach
                            </select>@error('session_timeout_minutes')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Minimum password length') }}</b><small>{{ __('Checked when a password is set or reset.') }}</small></div>
                            <div class="ss-inp short"><input type="number" name="password_min_length" min="8" max="64" value="{{ (int) $v('password_min_length') }}" data-track data-saved="{{ (int) $s->password_min_length }}" data-label="{{ __('Minimum password length') }}">@error('password_min_length')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Failed sign-in limit') }}</b><small>{{ __('Account locks for') }} <span data-lockout>{{ $secs($lockout) }}</span> {{ __('after this many tries.') }}</small></div>
                            <div class="ss-inp short"><input type="number" id="tries" name="max_login_attempts" min="3" max="20" value="{{ (int) $v('max_login_attempts') }}" data-track data-saved="{{ (int) $s->max_login_attempts }}" data-label="{{ __('Failed sign-in limit') }}">@error('max_login_attempts')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Lockout length') }}</b><small>{{ __('How long a locked account waits before it can try again.') }}</small></div>
                            <div class="ss-inp short"><select name="lockout_seconds" data-track data-saved="{{ (int) $s->lockout_seconds }}" data-label="{{ __('Lockout length') }}">
                                @foreach($choices([30, 60, 300, 900], $s->lockout_seconds) as $x)<option value="{{ $x }}" data-text="{{ $secs($x) }}" @selected($lockout === $x)>{{ $secs($x) }}</option>@endforeach
                            </select>@error('lockout_seconds')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Google sign-in') }}</b><small>{{ __('Allow accounts to sign in with Google.') }}</small></div>
                            <div class="ss-tgrow"><input type="hidden" name="google_sign_in" value="0"><label class="ss-tg"><input type="checkbox" name="google_sign_in" value="1" @checked($isOn('google_sign_in')) aria-label="{{ __('Google sign-in') }}" data-track data-saved="{{ $sw('google_sign_in') }}" data-label="{{ __('Google sign-in') }}"><span></span></label><small></small></div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Sign out everyone') }}</b><small>{{ __('Ends all active sessions except yours.') }}</small></div>
                            <div><button class="ss-btn danger" type="button" data-sign-out-all>{{ __('Sign out all sessions') }}</button></div></div>
                    </div>
                </section>

                {{-- ── KIOSKS ──────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="kiosk" @if($section !== 'kiosk') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Kiosks') }}</h3><p>{{ __('How fingerprint scans are recorded at the sites.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        <div class="ss-row"><div class="lb"><b>{{ __('Scan mode') }}</b><small>{{ __('Automatic fills the next slot: 1st in, 1st out, 2nd in, 2nd out.') }}</small></div>
                            <div class="ss-inp"><div class="ss-seg" role="radiogroup" aria-label="{{ __('Scan mode') }}">
                                @foreach([SystemSetting::KIOSK_AUTO => 'Automatic', SystemSetting::KIOSK_BUTTONS => 'Worker picks'] as $val => $label)
                                    <label><input type="radio" name="kiosk_attendance_mode" value="{{ $val }}" @checked(old('kiosk_attendance_mode', $savedMode) === $val) data-track data-saved="{{ $savedMode }}" data-label="{{ __('Scan mode') }}"><span>{{ __($label) }}</span></label>
                                @endforeach
                            </div>@error('kiosk_attendance_mode')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Kiosk opens before shift') }}</b><small>{{ __('Earliest time a worker can scan in.') }}</small></div>
                            <div class="ss-inp short"><select name="kiosk_opens_minutes" data-track data-saved="{{ $opens }}" data-label="{{ __('Kiosk opens before shift') }}">
                                @if($opens === null)<option value="" @selected((string) $openNow === '')>{{ __('Varies by shift') }}</option>@endif
                                @foreach($choices([30, 60, 90], $opens) as $m)<option value="{{ $m }}" @selected((string) $openNow === (string) $m)>{{ $m }} min</option>@endforeach
                            </select>@error('kiosk_opens_minutes')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Ignore duplicate scans') }}</b><small>{{ __("Skips repeat scans within the set time, so double taps don't count.") }}</small></div>
                            <div class="ss-inp"><div class="ss-tgrow"><input type="hidden" name="kiosk_repeat_guard_on" value="0"><label class="ss-tg"><input type="checkbox" id="kdupOn" name="kiosk_repeat_guard_on" value="1" @checked($isOn('kiosk_repeat_guard_on')) aria-label="{{ __('Ignore duplicate scans') }}" data-track data-saved="{{ $sw('kiosk_repeat_guard_on') }}" data-label="{{ __('Ignore duplicate scans') }}"><span></span></label><small></small>
                                <div class="ss-inp short" id="kdupWrap"><select name="kiosk_repeat_guard_seconds" aria-label="{{ __('Duplicate window') }}" data-track data-saved="{{ $savedGuard }}" data-label="{{ __('Duplicate window') }}">
                                    @foreach($choices([60, 180, 300], $savedGuard) as $x)<option value="{{ $x }}" @selected((int) old('kiosk_repeat_guard_seconds', $savedGuard) === $x)>{{ $span($x) }}</option>@endforeach
                                </select></div></div>
                            @error('kiosk_repeat_guard_seconds')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Unknown fingerprints') }}</b><small>{{ __("Tell admins when a finger that isn't registered is scanned.") }}</small></div>
                            <div class="ss-tgrow"><input type="hidden" name="kiosk_unknown_alert" value="0"><label class="ss-tg"><input type="checkbox" name="kiosk_unknown_alert" value="1" @checked($isOn('kiosk_unknown_alert')) aria-label="{{ __('Unknown fingerprints') }}" data-track data-saved="{{ $sw('kiosk_unknown_alert') }}" data-label="{{ __('Unknown fingerprints') }}"><span></span></label><small></small></div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Offline alert') }}</b><small>{{ __('Notify admins when a kiosk stops sending scans.') }}</small></div>
                            <div class="ss-inp short"><select name="kiosk_offline_alert_minutes" data-track data-saved="{{ $savedOff }}" data-label="{{ __('Offline alert') }}">
                                @foreach($choices([10, 30, 60], $savedOff) as $m)<option value="{{ $m }}" @selected((int) old('kiosk_offline_alert_minutes', $savedOff) === $m)>{{ $after($m) }}</option>@endforeach
                            </select>@error('kiosk_offline_alert_minutes')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                        <div class="ss-row"><div class="lb"><b>{{ __('Back to ATTENDANCE after') }}</b><small>{{ __('Idle time on another tab before the kiosk returns to the scanner.') }}</small></div>
                            <div class="ss-inp short"><select name="kiosk_idle_return_seconds" data-track data-saved="{{ $savedIdle }}" data-label="{{ __('Back to ATTENDANCE after') }}">
                                @foreach($choices([30, 60, 120], $savedIdle) as $x)<option value="{{ $x }}" @selected((int) old('kiosk_idle_return_seconds', $savedIdle) === $x)>{{ $span($x) }}</option>@endforeach
                            </select>@error('kiosk_idle_return_seconds')<span class="ss-err">{{ $message }}</span>@enderror</div></div>
                    </div>
                </section>

                {{-- ── NOTIFICATIONS ───────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="notif" @if($section !== 'notif') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Notifications') }}</h3><p>{{ __('What admins get notified about.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        @foreach([
                            'notify_missing_scans' => ['Missing scans', 'A worker clocked in and has no time out.'],
                            'notify_remittances'   => ['Remittance reminders', 'In the last week of the month after, until it is marked paid.'],
                            'notify_payroll'       => ['Payroll ready', "The week's payroll total, and any cash advance over ₱5,000."],
                            'notify_email'         => ['Also send by email', "Copies of alerts to each admin's email."],
                        ] as $key => [$title, $hint])
                            <div class="ss-row"><div class="lb"><b>{{ __($title) }}</b><small>{{ __($hint) }}</small></div>
                                <div class="ss-tgrow"><input type="hidden" name="{{ $key }}" value="0"><label class="ss-tg"><input type="checkbox" name="{{ $key }}" value="1" @checked($isOn($key)) aria-label="{{ __($title) }}" data-track data-saved="{{ $sw($key) }}" data-label="{{ __($title) }}"><span></span></label><small></small></div></div>
                        @endforeach
                    </div>
                </section>

                <div class="ss-bar" id="ssBar" role="region" aria-label="{{ __('Unsaved changes') }}"><span><i></i>{{ __('You have unsaved changes') }}</span><button class="ss-btn ghost" type="button" data-discard>{{ __('Discard') }}</button><button class="ss-btn pri" type="submit" data-save>{{ __('Save changes') }}</button></div>
            </form>

            <form method="POST" action="{{ route('system-settings.sign-out-all') }}" id="ssSignOut" hidden>@csrf</form>

            {{-- ── AUDIT LOGS ──────────────────────────────────────────────── --}}
            <section class="ss-sec" data-sec="audit" @if($section !== 'audit') hidden @endif>
                <div class="ss-sec-h"><div><h3>{{ __('Audit logs') }}</h3><p>{{ __("Every change in the system. Logs can't be edited or deleted.") }}</p></div>
                    <a class="ss-btn" id="auditExport" href="{{ route('audit-logs.export', array_filter(request()->only(['q', 'module']) + $auditKeep, fn ($x) => $x !== null && $x !== '')) }}" download>{!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M12 11v6M9 14l3 3 3-3"/>') !!}{{ __('Export') }}</a></div>
                <div class="ss-card">
                    <form class="ss-tools" method="GET" action="{{ route('system-settings.about') }}" id="ssAuditTools" role="search">
                        <input type="hidden" name="section" value="audit">
                        @foreach($auditKeep as $k => $val)
                            @foreach((array) $val as $one)<input type="hidden" name="{{ is_array($val) ? $k . '[]' : $k }}" value="{{ $one }}">@endforeach
                        @endforeach
                        <label class="ss-sel">{!! $svg('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>', '2') !!}<input type="search" name="q" value="{{ $A['q'] }}" placeholder="{{ __('Search activity') }}" aria-label="{{ __('Search activity') }}" autocomplete="off"></label>
                        <label class="ss-sel"><select name="module" aria-label="{{ __('Area') }}">
                            <option value="">{{ __('All areas') }}</option>
                            @foreach($A['modules'] as $m => $n)<option value="{{ $m }}" @selected(in_array($m, $A['selected']['module'], true))>{{ $m }}</option>@endforeach
                        </select></label>
                        @if($A['subject'])<span class="ss-chip">{{ __('One record:') }} {{ $A['subject'] }} <a href="{{ route('system-settings.about', ['section' => 'audit']) }}" aria-label="{{ __('Clear') }}">×</a></span>@endif
                    </form>
                    <div id="auditEntries" data-live="audit">
                        @forelse($A['logs'] as $log)
                            <div class="ss-log"><time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y · g:i:s A') }}">{{ $log->created_at->format('M j · g:i A') }}</time><div>{{ $log->description ?: Str::ucfirst($log->action) }}<small>{{ $log->user_name ?: 'System' }}</small></div><span class="ss-pill">{{ $log->module }}</span></div>
                        @empty
                            <p class="ss-empty">{{ __('No activity matches.') }}</p>
                        @endforelse
                    </div>
                    <div class="ss-more" id="auditMore" @if(! $A['logs']->hasMorePages()) hidden @endif>
                        @if($A['logs']->hasMorePages())
                            <button type="button" class="ss-btn ghost" data-next="{{ $A['logs']->nextPageUrl() }}">{{ __('Show more') }} · {{ number_format($A['logs']->total() - $A['logs']->lastItem()) }} {{ __('older') }}</button>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('ssForm');
    if (!form) return;
    const $  = s => document.querySelector(s);
    const $$ = s => [...document.querySelectorAll(s)];
    const bar = document.getElementById('ssBar');
    let current = @json($section);

    // ── Section nav ──────────────────────────────────────────────────────
    $$('.ss-si').forEach(b => b.addEventListener('click', () => {
        current = b.dataset.s;
        $$('.ss-si').forEach(x => {
            x.classList.toggle('on', x === b);
            if (x === b) x.setAttribute('aria-current', 'page'); else x.removeAttribute('aria-current');
        });
        $$('.ss-sec').forEach(s => { s.hidden = s.dataset.sec !== current; });
        const url = new URL(location.href);
        url.searchParams.set('section', current);
        history.replaceState(null, '', url);
    }));

    // ── Counters and the live preview ────────────────────────────────────
    const val = n => form.elements[n].value;
    function counters() { $$('[data-cnt]').forEach(c => { const i = document.getElementById(c.dataset.cnt); c.textContent = i.value.length + '/' + i.maxLength; }); }
    function preview() {
        const n = val('company_name').trim() || @json(__('Company name')), w = n.split(/\s+/);
        const set = (k, v) => $$('[data-b="' + k + '"]').forEach(e => { e.textContent = v; });
        set('name', n); set('first', w[0]); set('rest', w.slice(1).join(' ')); set('tab', n + ' | Sign in');
        set('sub', val('company_tagline')); set('addr', val('company_address'));
        $$('[data-b="rest"],[data-b="sub"],[data-b="addr"]').forEach(e => { e.hidden = !e.textContent.trim(); });
        counters();
    }
    ['company_name', 'company_tagline', 'company_address'].forEach(n => form.elements[n].addEventListener('input', preview));
    $$('.ss-ptabs button').forEach(b => b.addEventListener('click', () => {
        $$('.ss-ptabs button').forEach(x => x.classList.toggle('on', x === b));
        $$('[data-pv]').forEach(p => { p.hidden = p.dataset.pv !== b.dataset.p; });
    }));

    // ── Logo ─────────────────────────────────────────────────────────────
    const file = document.getElementById('logo');
    const drop = document.getElementById('drop');
    const dropText = drop.querySelector('[data-drop-text]');
    const dropDefault = dropText.innerHTML;
    const savedLogo = $('[data-logo]').getAttribute('src');
    const say = m => window.Notify && window.Notify.warning(m);
    function takeFile() {
        const f = file.files && file.files[0];
        if (f && !/^image\/(png|jpeg)$/.test(f.type)) { file.value = ''; say(@json(__('Use a PNG or JPG image'))); return takeFile(); }
        if (f && f.size > 2 * 1024 * 1024) { file.value = ''; say(@json(__('Image is over 2 MB'))); return takeFile(); }
        const src = f ? URL.createObjectURL(f) : savedLogo;
        $$('[data-logo]').forEach(img => { img.src = src; });
        dropText.innerHTML = f ? '<b>' + f.name.replace(/[<>&"]/g, '') + '</b> · <u>' + @json(__('change')) + '</u>' : dropDefault;
    }
    file.addEventListener('change', takeFile);
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', e => {
        if (!e.dataTransfer.files.length) return;
        file.files = e.dataTransfer.files;
        file.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // ── Switches read On / Off; the duplicate window follows its switch ──
    function tgLabels() { $$('.ss-tgrow').forEach(r => { const c = r.querySelector('input[type=checkbox]'); r.querySelector(':scope > small').textContent = c.checked ? @json(__('On')) : @json(__('Off')); }); }
    function dupState() { document.getElementById('kdupWrap').classList.toggle('off', !document.getElementById('kdupOn').checked); }
    function lockText() { const o = form.elements.lockout_seconds.selectedOptions[0]; $('[data-lockout]').textContent = o ? o.dataset.text : ''; }

    // ── Accent and density show on this page before they are saved ───────
    const accents = {!! $accentRules !!};
    function looks() {
        const pick = form.querySelector('[name="accent_color"]:checked');
        let tag = document.getElementById('accentPreview');
        if (pick && pick.value !== pick.dataset.saved) {
            if (!tag) { tag = document.createElement('style'); tag.id = 'accentPreview'; document.head.appendChild(tag); }
            tag.textContent = accents[pick.value] || '';
        } else if (tag) tag.remove();
        const d = form.querySelector('[name="table_density"]:checked');
        document.body.classList.toggle('density-compact', !!d && d.value === 'compact');
    }

    // ── Unsaved changes ──────────────────────────────────────────────────
    const fields = [...form.querySelectorAll('[data-track]')];
    const valueOf = el => {
        if (el.type === 'radio') return form.querySelector('[name="' + el.name + '"]:checked')?.value ?? '';
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        if (el.type === 'file') return el.files && el.files.length ? ' file' : '';
        return el.value;
    };
    function dirty() {
        const seen = new Set();
        return fields.filter(el => {
            if (seen.has(el.name)) return false;
            seen.add(el.name);
            return String(valueOf(el)) !== String(el.dataset.saved ?? '');
        });
    }
    const sectionOf = el => el.closest('.ss-sec')?.dataset.sec;
    function update() {
        bar.classList.toggle('show', dirty().length > 0);
        tgLabels(); dupState(); lockText(); looks();
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);

    bar.querySelector('[data-discard]').addEventListener('click', () => {
        form.reset();
        setTimeout(() => { takeFile(); preview(); update(); }, 0);
    });

    let leaving = false;
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[data-generated]').forEach(i => i.remove());
        const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; i.dataset.generated = '1'; form.appendChild(i); };
        new Set(dirty().map(sectionOf)).forEach(s => add('sections[]', s));
        add('current', current);
        leaving = true;
        const b = bar.querySelector('[data-save]');
        b.disabled = true;
        b.textContent = @json(__('Saving…'));
    });

    // Leaving with changes still unsaved asks first, in the app's own dialog,
    // naming them — the browser's box could only ever say "changes".
    // beforeunload stays underneath for the exits a page cannot catch.
    async function mayLeave() {
        const changed = dirty();
        if (leaving || !changed.length || !window.Notify) return true;
        const names = [...new Set(changed.map(el => el.dataset.label || el.name))].join(', ');
        const ok = await window.Notify.confirm({
            title:        @json(__('Leave without saving?')),
            message:      changed.length === 1
                            ? @json(__('Your change to :names has not been saved yet.')).replace(':names', names)
                            : @json(__(':count changes have not been saved yet: :names'))
                                .replace(':count', changed.length).replace(':names', names),
            confirmLabel: @json(__('Leave and discard')),
            cancelLabel:  @json(__('Stay on this page')),
            tone:         'warning',
        });
        if (ok) leaving = true;
        return ok;
    }

    document.addEventListener('click', async function (e) {
        if (leaving || e.defaultPrevented || e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;   // opening a new tab
        const link = e.target.closest('a[href]');
        if (!link || link.hasAttribute('download')) return;
        if (link.target && link.target !== '_self') return;
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) return;
        let url;
        try { url = new URL(href, location.href); } catch (err) { return; }
        if (url.origin !== location.origin) return;                     // another site: the browser's guard
        if (url.href === location.href || !dirty().length) return;
        e.preventDefault();
        if (await mayLeave()) location.href = url.href;
    }, true);

    window.addEventListener('beforeunload', e => {
        if (!leaving && dirty().length) { e.preventDefault(); e.returnValue = ''; }
    });

    // ── Sign out everyone ────────────────────────────────────────────────
    $('[data-sign-out-all]').addEventListener('click', async () => {
        if (!window.Notify) return;
        const ok = await window.Notify.confirm({
            title:        @json(__('Sign out all sessions?')),
            message:      @json(__('Everyone else is signed out on their next click, on every device. You stay signed in here.')),
            confirmLabel: @json(__('Sign out all sessions')),
            cancelLabel:  @json(__('Cancel')),
            tone:         'danger',
        });
        if (!ok || !(await mayLeave())) return;
        leaving = true;
        document.getElementById('ssSignOut').submit();
    });

    // ── Audit logs: search and area apply as you type or pick ────────────
    const tools = document.getElementById('ssAuditTools');
    const list  = document.getElementById('auditEntries');
    const more  = document.getElementById('auditMore');
    const exp   = document.getElementById('auditExport');
    const exportBase = @json(route('audit-logs.export'));
    let asked = 0;
    function auditUrl() {
        const url = new URL(tools.action, location.href);
        new FormData(tools).forEach((v, k) => { if (v !== '') url.searchParams.append(k, v); });
        return url;
    }
    async function read(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(res.status);
        return new DOMParser().parseFromString(await res.text(), 'text/html');
    }
    function showMore(doc) {
        const next = doc.getElementById('auditMore');
        more.innerHTML = next ? next.innerHTML : '';
        more.hidden = !next || next.hidden;
    }
    async function filter() {
        const url = auditUrl(), mine = ++asked;
        try {
            const doc = await read(url);
            if (mine !== asked) return;                         // a newer search has already gone out
            list.innerHTML = doc.getElementById('auditEntries').innerHTML;
            showMore(doc);
            history.replaceState(null, '', url);
            const e = new URL(exportBase, location.href);
            url.searchParams.forEach((v, k) => { if (k !== 'section') e.searchParams.append(k, v); });
            exp.href = e.href;
        } catch (err) { if (mine === asked) tools.submit(); }
    }
    let t;
    tools.addEventListener('submit', e => { e.preventDefault(); filter(); });
    tools.elements.q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(filter, 250); });
    tools.elements.module.addEventListener('change', filter);
    more.addEventListener('click', async e => {
        const b = e.target.closest('[data-next]');
        if (!b) return;
        b.disabled = true;
        try {
            const doc = await read(b.dataset.next);
            list.insertAdjacentHTML('beforeend', doc.getElementById('auditEntries').innerHTML);
            showMore(doc);
        } catch (err) { b.disabled = false; }
    });

    preview(); update();
})();
</script>
@endpush
