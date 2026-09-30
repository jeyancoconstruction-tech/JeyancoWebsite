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
        'kiosk_attendance_mode' => 'kiosk', 'kiosk_idle_return_seconds' => 'kiosk',
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
/* System Settings as one working surface (2026-09-27): the section list and
   the open section share one panel that reaches the bottom of the screen, and
   every section is split into its settings and a panel that shows what they
   do — the payslip, the app's look, the sign-in rules, the kiosk's own screen,
   the alerts. Nothing floats in empty space. Colours are the app's tokens, so
   the page follows the theme and the accent like every other page. */
.ss {
    --panel: var(--surface); --panel-2: var(--bg-subtle); --panel-3: #eaf0f8;
    --line: var(--border-md); --line-soft: var(--border);
    --text: var(--text-primary); --muted: var(--text-secondary); --faint: var(--text-muted);
    --accent: var(--brand); --accent-soft: var(--brand-subtle); --accent-text: var(--brand-strong);
    --mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
    display: flex; flex-direction: column; gap: 12px; color: var(--text);
    min-height: calc(100dvh - var(--topbar-height, 60px) - 44px);
}
html[data-bs-theme="dark"] .ss { --panel-3: #1b2b46; }
html[data-bs-theme] .main-content .ss > .page-head { margin-bottom: 0 !important; }
.ss [hidden] { display: none !important; }
.ss-btn { border: 1px solid var(--line); background: var(--panel); border-radius: 8px; height: 34px; padding: 0 12px; font-weight: 600; font-size: 12.5px; display: inline-flex; gap: 7px; align-items: center; cursor: pointer; color: var(--text); white-space: nowrap; text-decoration: none; }
.ss-btn svg { width: 15px; height: 15px; flex: none; }
.ss-btn:hover { border-color: var(--accent); color: var(--text); }
.ss-btn.pri { background: var(--accent); border-color: var(--accent); color: #fff; }
.ss-btn.ghost { background: transparent; border-color: transparent; color: var(--muted); }
.ss-btn.ghost:hover { color: var(--text); border-color: var(--line); }
.ss-btn.danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 35%, var(--line)); }
.ss-btn.danger:hover { background: var(--danger-soft); border-color: var(--danger); color: var(--danger); }
.ss-btn:disabled { opacity: .6; cursor: default; }
.ss-saved { display: flex; gap: 8px; align-items: center; font-size: 12.5px; color: var(--muted); }
.ss-saved svg { width: 15px; height: 15px; }
.ss-saved b { color: var(--text); font-weight: 600; }
.ss-alert { display: flex; gap: 10px; padding: 10px 14px; border-radius: 10px; background: var(--danger-soft); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 30%, transparent); font-size: 13px; }
.ss-alert ul { margin: 4px 0 0; padding-left: 18px; }

/* ── The panel: section list on the left, the open section beside it ───── */
.ss-set {
    flex: 1; display: grid; grid-template-columns: 224px minmax(0, 1fr); min-height: 0;
    background: var(--panel); border: 1px solid var(--line-soft); border-radius: 12px; overflow: hidden;
    box-shadow: var(--shadow-xs);
}
.ss-nav { display: flex; flex-direction: column; gap: 1px; padding: 10px 8px; background: var(--panel-2); border-right: 1px solid var(--line-soft); }
.ss-nav h6 { margin: 12px 10px 4px; font-size: 10px; letter-spacing: .14em; color: var(--faint); font-weight: 700; }
.ss-nav h6:first-child { margin-top: 2px; }
.ss-si {
    position: relative; display: grid; grid-template-columns: 18px minmax(0, 1fr) auto; gap: 10px; align-items: center;
    padding: 0 10px; height: 38px; border-radius: 7px; border: 0; background: none;
    color: var(--muted); font-size: 13px; font-weight: 500; cursor: pointer; text-align: left; width: 100%;
}
.ss-si svg { width: 17px; height: 17px; }
.ss-si:hover { background: var(--panel); color: var(--text); }
.ss-si.on { background: var(--panel); color: var(--text); font-weight: 700; box-shadow: 0 0 0 1px var(--line-soft); }
.ss-si.on::before { content: ""; position: absolute; left: -8px; top: 9px; bottom: 9px; width: 3px; border-radius: 0 3px 3px 0; background: var(--accent); }
.ss-si.on svg { color: var(--accent); }
.ss-si:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
.ss-si .meta { font-size: 11px; font-weight: 600; color: var(--faint); font-variant-numeric: tabular-nums; display: inline-flex; gap: 5px; align-items: center; white-space: nowrap; }
.ss-si .meta i { width: 7px; height: 7px; border-radius: 50%; background: var(--faint); }
.ss-si .meta i.ok { background: var(--success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 18%, transparent); }
.ss-si .meta i.late { background: var(--warning); }
.ss-si .meta i.off { background: var(--danger); }
.ss-nav .hint { margin: auto 4px 0; padding: 12px 8px 4px; border-top: 1px solid var(--line-soft); font-size: 12px; color: var(--muted); line-height: 1.5; }
.ss-nav .hint a { color: var(--accent-text); font-weight: 600; text-decoration: none; }

.ss-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
.ss-main > form { display: contents; }

/* ── A section: its heading bar, then settings | what they do ─────────── */
.ss-sec { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.ss-sec-h { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 18px; border-bottom: 1px solid var(--line-soft); }
.ss-sec-h h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text); display: flex; gap: 8px; align-items: baseline; }
.ss-sec-h h3 small { font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--faint); }
.ss-sec-h p { margin: 2px 0 0; color: var(--muted); font-size: 12.5px; }
.ss-split { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 340px; min-height: 0; }
.ss-split.wide-side { grid-template-columns: minmax(0, 300px) minmax(0, 1fr); }
/* The rows share the column's height, so a short section is a full sheet
   rather than a few lines at the top of an empty panel. */
.ss-grp { padding: 0 18px; min-width: 0; display: flex; flex-direction: column; }
.ss-grp > .ss-row { flex: 1 1 auto; align-items: center; }
.ss-grp > h4 { flex: none; }
.ss-grp > h4 { margin: 14px 0 0; font-size: 10.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--faint); font-weight: 700; }
.ss-row { display: grid; grid-template-columns: minmax(0, 200px) minmax(0, 1fr); gap: 16px; align-items: start; padding: 12px 0; border-bottom: 1px solid var(--line-soft); }
.ss-row:last-child { border-bottom: 0; }
.ss-grp.stack .ss-row { grid-template-columns: minmax(0, 1fr); gap: 8px; }
.ss-row .lb b { display: block; font-size: 13px; font-weight: 600; color: var(--text); }
.ss-row .lb small { display: block; color: var(--muted); font-size: 12px; margin-top: 2px; line-height: 1.45; }
.ss-opt { font-size: 10px; font-weight: 700; color: var(--muted); border: 1px solid var(--line); border-radius: 5px; padding: 0 5px; margin-left: 6px; vertical-align: 1px; }
.ss-inp { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
html[data-bs-theme] .ss .ss-inp input[type=text],
html[data-bs-theme] .ss .ss-inp input[type=number],
html[data-bs-theme] .ss .ss-inp select {
    border: 1px solid var(--line) !important; background: var(--panel) !important; border-radius: 8px !important;
    height: 36px; padding: 0 11px; outline: none; color: var(--text) !important; font-size: 13px; width: 100%; box-shadow: none !important;
}
html[data-bs-theme] .ss .ss-inp input:focus,
html[data-bs-theme] .ss .ss-inp select:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 3px var(--accent-soft) !important; }
.ss .ss-inp select option { background: var(--panel); }
.ss-inp .cnt { align-self: flex-end; font-size: 10.5px; color: var(--faint); font-family: var(--mono); }
.ss-inp.short { max-width: 240px; }
.ss-err { font-size: 12px; color: var(--danger); font-weight: 600; }

/* The right-hand panel of a section. */
.ss-side { background: var(--panel-2); border-left: 1px solid var(--line-soft); padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.ss-side h4 { margin: 0; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; color: var(--faint); display: flex; gap: 8px; align-items: center; }
.ss-side h4 svg { width: 14px; height: 14px; color: var(--accent); }
.ss-side .note { font-size: 11.5px; color: var(--muted); margin: 0; line-height: 1.5; }
.ss-cap { font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin: 0 0 6px; }

/* Logo */
.ss-logo-up { display: flex; gap: 14px; align-items: center; }
.ss-logo-prev { width: 56px; height: 56px; border-radius: 50%; background: #123a7a; border: 2px solid #6fa3ea; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 18px; flex: none; overflow: hidden; }
.ss-logo-prev img { width: 100%; height: 100%; object-fit: cover; }
.ss-drop { flex: 1; border: 1.5px dashed var(--line); border-radius: 10px; padding: 10px 12px; display: flex; gap: 10px; align-items: center; cursor: pointer; color: var(--muted); font-size: 12.5px; margin: 0; }
.ss-drop:hover, .ss-drop.over { border-color: var(--accent); background: var(--accent-soft); }
.ss-drop b { color: var(--text); }
.ss-drop u { color: var(--accent-text); text-decoration: none; font-weight: 600; }
.ss-drop svg { width: 20px; height: 20px; flex: none; }

/* Segmented choices, swatches and switches (radios and checkboxes underneath) */
.ss-seg { display: inline-flex; align-self: flex-start; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: var(--panel-2); max-width: 100%; }
.ss-seg label { margin: 0; cursor: pointer; }
.ss-seg label + label span { border-left: 1px solid var(--line); }
.ss-seg input, .ss-sw input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
.ss-seg span { padding: 0 13px; height: 34px; color: var(--muted); font-size: 12.5px; font-weight: 600; display: flex; gap: 7px; align-items: center; }
.ss-seg input:checked + span { background: var(--accent); color: #fff; }
.ss-seg input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: -2px; }
.ss-tg { position: relative; width: 38px; height: 22px; flex: none; margin: 0; }
.ss-tg input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; z-index: 1; }
.ss-tg span { position: absolute; inset: 0; border-radius: 999px; background: var(--panel-3); border: 1px solid var(--line); transition: background .2s; }
.ss-tg span::after { content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .2s; }
.ss-tg input:checked + span { background: var(--accent); border-color: var(--accent); }
.ss-tg input:checked + span::after { transform: translateX(16px); }
.ss-tg input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }
.ss-tgrow { display: flex; gap: 10px; align-items: center; }
.ss-tgrow > small { color: var(--muted); font-size: 12px; font-weight: 600; min-width: 22px; }
.ss-sw { display: flex; gap: 8px; flex-wrap: wrap; }
.ss-sw label { margin: 0; }
.ss-sw span { display: block; width: 28px; height: 28px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; background: var(--c); }
.ss-sw input:checked + span { border-color: var(--text); box-shadow: 0 0 0 2px var(--panel) inset; }
.ss-sw input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }

/* ── Company: the three places the name is printed, all at once ───────── */
.ss-slip { background: #fff; color: #0f1b2d; border-radius: 8px; padding: 12px 14px; font-size: 11px; border: 1px solid #d6dde8; }
.ss-slip header { display: flex; gap: 10px; align-items: center; border-bottom: 2px solid #0f1b2d; padding-bottom: 9px; margin-bottom: 9px; }
.ss-lg { width: 34px; height: 34px; border-radius: 50%; background: #123a7a; color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 11px; flex: none; overflow: hidden; }
.ss-lg img { width: 100%; height: 100%; object-fit: cover; }
.ss-slip header b { display: block; font-size: 12.5px; letter-spacing: .04em; overflow-wrap: anywhere; }
.ss-slip header small { display: block; color: #56657d; font-size: 10px; line-height: 1.4; }
.ss-slip .ln { height: 5px; border-radius: 3px; background: #e7ecf4; margin: 5px 0; }
.ss-slip .net { display: flex; justify-content: space-between; font-weight: 800; border-top: 1px solid #d6dde8; padding-top: 7px; margin-top: 7px; }
.ss-sbar { border-radius: 8px; background: #123566; padding: 12px; display: flex; gap: 10px; align-items: center; color: #fff; }
.ss-sbar .ss-lg { border: 1.5px solid #6fa3ea; }
.ss-sbar b { display: block; font-size: 14px; letter-spacing: .1em; overflow-wrap: anywhere; }
.ss-sbar small { font-size: 9px; letter-spacing: .34em; color: #a9bbd9; }
.ss-grow { flex: 1; display: flex; flex-direction: column; min-height: 150px; }
.ss-tabp { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: var(--panel); flex: 1; display: flex; flex-direction: column; }
.ss-tabp .bar { display: flex; gap: 8px; align-items: center; padding: 7px 10px; background: var(--panel-3); font-size: 11.5px; min-width: 0; }
.ss-tabp .bar i { width: 12px; height: 12px; border-radius: 50%; background: var(--accent); flex: none; }
.ss-tabp .bar span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ss-tabp .body { flex: 1; background: #123566; display: grid; place-items: center; padding: 14px; }
.ss-login { width: min(190px, 100%); background: #fff; border-radius: 8px; padding: 12px; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.ss-login .ss-lg { width: 28px; height: 28px; }
.ss-login b { font-size: 10.5px; letter-spacing: .06em; color: #0f1b2d; text-align: center; overflow-wrap: anywhere; }
.ss-login i { display: block; width: 100%; height: 14px; border-radius: 4px; border: 1px solid #d6dde8; background: #f8f9fb; }
.ss-login i:last-child { background: var(--accent); border-color: var(--accent); }

/* ── Appearance: a small copy of the app that takes the choices at once ── */
.ss-mock { --m-bg: #f2f4f7; --m-surface: #fff; --m-line: #e4e7ec; --m-text: #101828; --m-muted: #667085; --m-rail: #123566; --m-accent: var(--accent); --m-row: 30px;
    border: 1px solid var(--line); border-radius: 10px; overflow: hidden; display: grid; grid-template-columns: 64px minmax(0, 1fr); background: var(--m-bg); min-height: 250px; flex: 1; }
.ss-mock.is-dark { --m-bg: #0c1522; --m-surface: #131e2d; --m-line: #223049; --m-text: #e8edf5; --m-muted: #78879e; --m-rail: #0e1826; }
.ss-mock.is-compact { --m-row: 24px; }
.ss-mock .rail { background: var(--m-rail); padding: 10px 8px; display: flex; flex-direction: column; gap: 7px; }
.ss-mock .rail i { display: block; height: 7px; border-radius: 3px; background: rgba(255,255,255,.18); }
.ss-mock .rail i.on { background: var(--m-accent); }
.ss-mock .rail i:first-child { height: 18px; width: 18px; border-radius: 50%; background: rgba(255,255,255,.35); margin-bottom: 6px; }
.ss-mock .pg { padding: 10px; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.ss-mock .top { display: flex; justify-content: space-between; align-items: center; }
.ss-mock .top b { height: 9px; width: 70px; border-radius: 3px; background: var(--m-text); opacity: .8; }
.ss-mock .top span { height: 18px; width: 54px; border-radius: 5px; background: var(--m-accent); }
.ss-mock .card { background: var(--m-surface); border: 1px solid var(--m-line); border-radius: 7px; overflow: hidden; }
.ss-mock .tr { height: var(--m-row); display: flex; align-items: center; gap: 8px; padding: 0 8px; border-bottom: 1px solid var(--m-line); transition: height .2s; }
.ss-mock .tr:last-child { border-bottom: 0; }
.ss-mock .tr i { width: 12px; height: 12px; border-radius: 50%; background: var(--m-line); flex: none; }
.ss-mock .tr b { flex: 1; height: 6px; border-radius: 3px; background: var(--m-muted); opacity: .45; }
.ss-mock .tr em { width: 30px; height: 6px; border-radius: 3px; background: var(--m-accent); opacity: .8; }
.ss-mock .tr.hd { background: color-mix(in srgb, var(--m-line) 45%, transparent); }
.ss-mock .tr.hd b { opacity: .25; }
.ss-mock .lnk { font-size: 10px; font-weight: 700; color: var(--m-accent); }
.ss-kv { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 6px 12px; margin: 0; font-size: 12px; }
.ss-kv dt { color: var(--muted); font-weight: 500; }
.ss-kv dd { margin: 0; text-align: right; font-weight: 700; color: var(--text); }

/* ── Security: the rules as a sentence each, and the one button that bites ── */
.ss-rules { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; border: 1px solid var(--line-soft); border-radius: 10px; background: var(--panel); flex: 1; }
.ss-rules li { flex: 1; align-items: center; display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--line-soft); font-size: 12.5px; color: var(--muted); line-height: 1.45; }
.ss-rules li:last-child { border-bottom: 0; }
.ss-rules li b { color: var(--text); }
.ss-rules li > span:first-child { width: 28px; height: 28px; border-radius: 7px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); }
.ss-rules li > span:first-child svg { width: 15px; height: 15px; }
.ss-danger { border: 1px solid color-mix(in srgb, var(--danger) 35%, var(--line-soft)); border-radius: 10px; padding: 12px; background: var(--panel); display: flex; flex-direction: column; gap: 8px; }
.ss-danger b { font-size: 13px; color: var(--text); }
.ss-danger small { font-size: 12px; color: var(--muted); line-height: 1.45; }
.ss-danger .ss-btn { align-self: flex-start; }

/* ── Notifications: the bell, showing what would reach it ──────────────── */
.ss-bell { border: 1px solid var(--line-soft); border-radius: 10px; background: var(--panel); overflow: hidden; flex: 1; display: flex; flex-direction: column; }
.ss-bell > header { display: flex; justify-content: space-between; align-items: center; padding: 9px 12px; border-bottom: 1px solid var(--line-soft); font-size: 12.5px; font-weight: 700; }
.ss-bell > header span { font-size: 11px; font-weight: 700; color: #fff; background: var(--danger); border-radius: 999px; padding: 0 7px; }
.ss-bell .it { flex: 1; align-items: center; display: grid; grid-template-columns: 30px minmax(0, 1fr); gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--line-soft); transition: opacity .2s; }
.ss-bell .it:last-child { border-bottom: 0; }
.ss-bell .it > span { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; background: var(--c-soft); color: var(--c); }
.ss-bell .it > span svg { width: 15px; height: 15px; }
.ss-bell .it b { display: block; font-size: 12.5px; color: var(--text); }
.ss-bell .it small { display: block; font-size: 11.5px; color: var(--muted); line-height: 1.4; }
.ss-bell .it em { font-style: normal; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--accent); margin-left: 6px; }
.ss-bell .it.is-off { opacity: .38; }
.ss-bell .it.is-off b::after { content: " · off"; font-weight: 600; color: var(--faint); }

/* ── Audit log: the list fills the section and scrolls inside it ───────── */
.ss-audit { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.ss-tools { display: flex; gap: 8px; flex-wrap: wrap; padding: 10px 18px; border-bottom: 1px solid var(--line-soft); background: var(--panel-2); }
.ss-sel { display: flex; align-items: center; gap: 8px; border: 1px solid var(--line); background: var(--panel); border-radius: 8px; padding: 0 10px; height: 34px; margin: 0; }
.ss-sel svg { width: 15px; height: 15px; color: var(--muted); flex: none; }
html[data-bs-theme] .ss .ss-sel input, html[data-bs-theme] .ss .ss-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none; color: var(--text); font-size: 13px; height: 32px; padding: 0; min-width: 0;
}
.ss .ss-sel select option { background: var(--panel); }
.ss-chip { display: inline-flex; gap: 8px; align-items: center; font-size: 12px; font-weight: 600; background: var(--accent-soft); color: var(--accent-text); border-radius: 999px; padding: 0 12px; height: 34px; }
.ss-chip a { color: inherit; text-decoration: none; font-weight: 800; }
.ss-loglist { flex: 1; overflow-y: auto; min-height: 240px; max-height: calc(100dvh - var(--topbar-height, 60px) - 250px); }
.ss-log { display: grid; grid-template-columns: 124px minmax(0, 1fr) auto; gap: 12px; padding: 9px 18px; border-bottom: 1px solid var(--line-soft); font-size: 13px; align-items: center; color: var(--text); }
.ss-log:nth-child(even) { background: color-mix(in srgb, var(--panel-2) 55%, transparent); }
.ss-log time { font-family: var(--mono); font-size: 11.5px; color: var(--muted); }
.ss-log > div { min-width: 0; overflow-wrap: anywhere; }
.ss-log small { display: block; color: var(--muted); font-size: 11.5px; }
.ss-pill { font-size: 11px; font-weight: 700; border-radius: 5px; padding: 2px 8px; background: var(--panel-3); color: var(--muted); white-space: nowrap; }
.ss-empty { padding: 32px; text-align: center; color: var(--muted); margin: 0; }
.ss-more { display: flex; justify-content: center; padding: 8px; border-top: 1px solid var(--line-soft); }

/* ── Kiosks now: whether each kiosk is on and has what was saved here ── */
.kn { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
.kn-list { list-style: none; margin: 0; padding: 0; border: 1px solid var(--line-soft); border-radius: 10px; background: var(--panel); overflow: hidden; flex: none; display: flex; flex-direction: column; }
.kn-list li { flex: none; display: grid; grid-template-columns: 10px minmax(0, 1fr) auto; grid-auto-rows: min-content; align-content: center; gap: 6px 12px; align-items: center; padding: 12px 14px; border-bottom: 1px solid var(--line-soft); }
.kn-list li:last-child { border-bottom: 0; }
.kn-list li > i { width: 10px; height: 10px; border-radius: 50%; background: var(--danger); grid-row: 1; }
.kn-list li > i.ok { background: var(--success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 20%, transparent); }
.kn-list li > i.late { background: var(--warning); }
.kn-list b { font-size: 13.5px; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kn-list b small { font-weight: 600; color: var(--faint); font-size: 11.5px; margin-left: 6px; }
.kn-list em { font-style: normal; font-size: 11px; font-weight: 800; letter-spacing: .04em; padding: 2px 8px; border-radius: 5px; color: var(--danger); background: var(--danger-soft); justify-self: end; }
.kn-list em.ok { color: var(--success); background: var(--success-soft); }
.kn-list em.late { color: var(--warning); background: var(--warning-soft); }
.kn-list span { grid-column: 2 / 4; font-family: var(--mono); font-size: 11.5px; color: var(--muted); }
.kn-list dl { grid-column: 2 / 4; display: grid; grid-template-columns: minmax(0, 1fr); margin: 4px 0 0; border: 1px solid var(--line-soft); border-radius: 8px; background: var(--panel-2); }
.kn-list dl div { padding: 7px 10px; min-width: 0; display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
.kn-list dl div + div { border-top: 1px solid var(--line-soft); }
.kn-list dt { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--faint); }
.kn-list dd { margin: 0; font-size: 12.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kn-list dd.ok { color: var(--success); } .kn-list dd.bad { color: var(--danger); } .kn-list dd.warn { color: var(--warning); }
.kn-go { display: flex; gap: 12px; align-items: center; padding: 12px 14px; border-radius: 10px; background: var(--panel-3); color: var(--text); text-decoration: none; }
.kn-go:hover { color: var(--text); box-shadow: 0 0 0 1px var(--accent) inset; }
.kn-go svg { width: 20px; height: 20px; color: var(--accent); flex: none; }
.kn-go b { display: block; font-size: 13px; }
.kn-go small { display: block; font-size: 12px; color: var(--muted); }
.kn-rules { flex: 1; border: 1px solid var(--line-soft); border-radius: 10px; background: var(--panel); padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
.kn-rules h5 { margin: 0; font-size: 10.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--faint); font-weight: 700; }
.kn-rules ol { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 10px; counter-reset: kn; }
.kn-rules li { counter-increment: kn; display: grid; grid-template-columns: 22px 1fr; gap: 10px; font-size: 12.5px; color: var(--muted); line-height: 1.45; }
.kn-rules li::before { content: counter(kn); width: 22px; height: 22px; border-radius: 50%; background: var(--panel-3); color: var(--accent); font-weight: 800; font-size: 11.5px; display: grid; place-items: center; }
.kn-rules li b { color: var(--text); display: block; font-size: 12.5px; }
.kn-none { padding: 28px 14px; text-align: center; color: var(--muted); border: 1px dashed var(--line); border-radius: 10px; margin: 0; }

/* ── Kiosk site: where each kiosk stands, set here, not on the kiosk ─── */
.ks-list { display: flex; flex-direction: column; gap: 10px; }
.ks-card { border: 1px solid var(--line); border-radius: 12px; background: var(--panel); padding: 12px 14px; }
.ks-card.moving { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 14%, transparent); }
.ks-top { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.ks-top b { font-size: 13.5px; color: var(--text); }
.ks-code { font-family: var(--mono); font-size: 11.5px; color: var(--faint); }
.ks-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--danger); flex: none; }
.ks-dot.ok { background: var(--success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 20%, transparent); }
.ks-dot.late { background: var(--warning); }
.ks-geo { margin-left: auto; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: var(--panel-3); color: var(--muted); white-space: nowrap; }
.ks-geo.in { background: var(--success-soft); color: var(--success); }
.ks-geo.out { background: var(--danger-soft); color: var(--danger); }
.ks-geo.warn { background: var(--warning-soft); color: var(--warning); }
.ks-sites { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 6px; margin-top: 10px; }
.ks-site { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 8px 10px; border-radius: 9px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text); font: inherit; text-align: left; cursor: pointer; transition: border-color .15s, background .15s; }
.ks-site b { font-size: 13px; font-weight: 700; }
.ks-site small { font-size: 10.5px; color: var(--faint); font-weight: 600; letter-spacing: .02em; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ks-site:hover { border-color: var(--accent); }
.ks-site.on { background: var(--accent); border-color: var(--accent); color: #fff; }
.ks-site.on small { color: rgba(255, 255, 255, .8); }
.ks-site.on::after { content: ""; position: absolute; top: 9px; right: 9px; width: 7px; height: 7px; border-radius: 50%; background: #fff; }
.ks-site:disabled { cursor: progress; opacity: .7; }
.ks-site.pick { border-color: var(--accent); border-style: dashed; background: color-mix(in srgb, var(--accent) 10%, var(--panel-2)); }
.ks-site.on.was { background: var(--panel-2); border-color: var(--line); color: var(--text); }
.ks-site.on.was small { color: var(--faint); }
.ks-site.on.was::after { background: var(--faint); }
.ks-save { display: none; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px; padding: 9px 11px; border-radius: 9px; background: var(--panel-2); border: 1px solid var(--line); font-size: 12.5px; color: var(--muted); }
.ks-save.show { display: flex; }
.ks-save span { flex: 1 1 180px; } .ks-save span b { color: var(--text); }
.ks-save .ss-btn { height: 30px; padding: 0 14px; font-size: 12.5px; }
.ks-note { margin-top: 8px; font-size: 12px; color: var(--muted); min-height: 1em; }
.ks-note.ok { color: var(--success); } .ks-note.bad { color: var(--danger); }
.ks-del { border: 0; background: none; padding: 3px 6px; font: inherit; font-size: 11.5px; font-weight: 600; color: var(--faint); cursor: pointer; border-radius: 6px; }
.ks-del:hover { color: var(--danger); background: var(--danger-soft); }
.ks-add { border: 1px dashed var(--line); border-radius: 12px; padding: 0 14px; }
.ks-add > summary { list-style: none; cursor: pointer; padding: 11px 0; font-size: 12.5px; font-weight: 700; color: var(--accent); }
.ks-add > summary::-webkit-details-marker { display: none; }
.ks-add-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr auto; gap: 8px; padding-bottom: 12px; align-items: start; }
.ks-add-grid input, .ks-add-grid select { width: 100%; height: 34px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); color: var(--text); padding: 0 10px; font: inherit; font-size: 13px; }
.ks-add-hint { font-size: 11.5px; color: var(--faint); padding-bottom: 12px; margin-top: -4px; }
@media (max-width: 700px) { .ks-add-grid { grid-template-columns: 1fr 1fr; } }

/* Unsaved bar */
.ss-bar { position: fixed; left: 50%; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); transform: translate(-50%, 160%); display: flex; gap: 10px; align-items: center; background: var(--text-primary); color: var(--bg-body); border-radius: 12px; padding: 8px 8px 8px 16px; box-shadow: 0 20px 50px rgba(0,0,0,.35); z-index: 1050; transition: transform .35s cubic-bezier(.2, .8, .2, 1); max-width: calc(100% - 32px); visibility: hidden; }
.ss-bar.show { transform: translate(-50%, 0); visibility: visible; }
.ss-bar > span { font-size: 13px; font-weight: 600; display: flex; gap: 8px; align-items: center; }
.ss-bar > span i { width: 8px; height: 8px; border-radius: 50%; background: var(--warning); flex: none; }
.ss-bar .ss-btn { height: 32px; }
.ss-bar .ss-btn.ghost { color: var(--bg-body); opacity: .75; }
.ss-bar .ss-btn.ghost:hover { opacity: 1; border-color: transparent; }

@media (max-width: 1400px) { .ss-split { grid-template-columns: minmax(0, 1fr) 300px; } .ss-split.wide-side { grid-template-columns: minmax(0, 280px) minmax(0, 1fr); } }
@media (max-width: 1200px) {
    .ss-split, .ss-split.wide-side { grid-template-columns: minmax(0, 1fr); }
    .ss-side { border-left: 0; border-top: 1px solid var(--line-soft); }
}
@media (max-width: 900px) {
    .ss-set { grid-template-columns: minmax(0, 1fr); }
    .ss-nav { border-right: 0; border-bottom: 1px solid var(--line-soft); }
    .ss-si.on::before { display: none; }
    .ss-si { grid-template-columns: 18px auto auto; }
    .ss-nav .hint { display: none; }
    .ss-loglist { max-height: none; }
}
@media (max-width: 767.98px) {
    .ss-row { grid-template-columns: minmax(0, 1fr); gap: 8px; }
    .ss-sec-h, .ss-grp, .ss-side { padding-left: 14px; padding-right: 14px; }
    .ss-log { grid-template-columns: minmax(0, 1fr) auto; row-gap: 2px; }
    .ss-log time { grid-column: 1 / -1; }
}
@media (prefers-reduced-motion: reduce) { .ss-bar { transition: none; } }
</style>
@endpush

@section('content')
@php
    // What each item in the section list says beside its name.
    $kioskOn   = $kiosks->where('state', '!=', 'off')->count();
    $kioskDot  = $kiosks->isEmpty() ? '' : ($kiosks->contains('state', 'ok') ? 'ok' : ($kiosks->contains('state', 'late') ? 'late' : 'off'));
    $notifOn   = collect(['notify_missing_scans', 'notify_remittances', 'notify_payroll', 'notify_email'])->filter(fn ($k) => $s->enabled($k))->count();
    $meta = [
        'appearance' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'][$savedTheme] ?? '',
        'security'   => $hours((int) $s->session_timeout_minutes),
        'kiosk'      => $kiosks->isEmpty() ? '' : '<i class="' . $kioskDot . '"></i>' . $kioskOn . '/' . $kiosks->count() . ' ' . __('on'),
        'notif'      => $notifOn . '/4 ' . __('on'),
        'audit'      => number_format($A['logs']->total()),
    ];
    $accentHex = json_encode(collect(SystemSetting::ACCENTS)->map(fn ($a) => $a[2][0]));
    $ico = [
        'eye'   => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'key'   => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'shield'=> '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'g'     => '<path d="M20 12h-8M20 12a8 8 0 11-2.3-5.6"/>',
        'bell'  => '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>',
        'scan'  => '<path d="M12 11v3a8 8 0 01-1.5 4.7M8.5 7.2A5 5 0 0117 11v1.5M7 11a5 5 0 01.3-1.8M16.9 16a13 13 0 01-.9 3M5 16.5A12 12 0 006 11a6 6 0 011.2-3.6M12 3a8 8 0 018 8v1M4 11a8 8 0 012-5.3"/>',
        'peso'  => '<path d="M7 20V4h6a4 4 0 010 8H7M4 8h14M4 11h14"/>',
        'cal'   => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    ];
@endphp
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
        <nav class="ss-nav" aria-label="{{ __('Settings sections') }}">
            @foreach($nav as $group => $items)
                <h6>{{ __($group) }}</h6>
                @foreach($items as $key => [$label, $path])
                    <button type="button" class="ss-si {{ $section === $key ? 'on' : '' }}" data-s="{{ $key }}" @if($section === $key) aria-current="page" @endif>{!! $svg($path) !!}<span>{{ __($label) }}</span><span class="meta" @if($key === 'notif') data-notif-meta @endif>{!! $meta[$key] ?? '' !!}</span></button>
                @endforeach
            @endforeach
            <p class="hint">{{ __('Pay rates, shifts and holidays live in') }} <a href="{{ route('settings.index') }}">{{ __('Payroll Settings') }}</a>.</p>
        </nav>

        <div class="ss-main">
            <form method="POST" action="{{ route('system-settings.update-all') }}" enctype="multipart/form-data" id="ssForm" novalidate>
                @csrf
                @method('PUT')

                {{-- ── COMPANY ─────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="company" @if($section !== 'company') hidden @endif>
                    <header class="ss-sec-h"><div><h3>{{ __('Company') }} <small>{{ __('Organization') }}</small></h3><p>{{ __('Shown on payslips, receipts, the sidebar and the sign-in page.') }}</p></div></header>
                    <div class="ss-split">
                        <div class="ss-grp">
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
                        {{-- The three places the name is printed, side by side
                             rather than behind tabs: the column has the room. --}}
                        <aside class="ss-side" aria-label="{{ __('Live preview') }}">
                            <h4>{!! $svg($ico['eye'], '2') !!}{{ __('Live preview') }}</h4>
                            <div><p class="ss-cap">{{ __('Payslip') }}</p><div class="ss-slip"><header><span class="ss-lg"><img src="{{ $s->logoUrl() }}" alt="" data-logo></span><div><b data-b="name">{{ $name }}</b><small data-b="sub">{{ $tagline }}</small><small data-b="addr">{{ $address }}</small></div></header><div style="display:flex;justify-content:space-between;font-weight:800"><span>PAYSLIP</span><span style="font-weight:500;color:#56657d">{{ $week->format('M j') }} – {{ $weekEnd->format($week->month === $weekEnd->month ? 'j, Y' : 'M j, Y') }}</span></div><div class="ln" style="width:90%"></div><div class="ln" style="width:70%"></div><div class="net"><span>NET PAY</span><span>₱4,860.00</span></div></div></div>
                            <div><p class="ss-cap">{{ __('Sidebar') }}</p><div class="ss-sbar"><span class="ss-lg"><img src="{{ $s->logoUrl() }}" alt="" data-logo></span><div><b data-b="first">{{ $words[0] }}</b><small data-b="rest">{{ implode(' ', array_slice($words, 1)) }}</small></div></div></div>
                            <div class="ss-grow"><p class="ss-cap">{{ __('Sign-in') }}</p><div class="ss-tabp"><div class="bar"><i></i><span data-b="tab">{{ $name }} | Sign in</span></div><div class="body"><div class="ss-login"><span class="ss-lg"><img src="{{ $s->logoUrl() }}" alt="" data-logo></span><b data-b="name">{{ $name }}</b><i></i><i></i><i></i></div></div></div></div>
                            <p class="note">{{ __('Updates as you type. Nothing changes for other users until you save.') }}</p>
                        </aside>
                    </div>
                </section>

                {{-- ── APPEARANCE ──────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="appearance" @if($section !== 'appearance') hidden @endif>
                    <header class="ss-sec-h"><div><h3>{{ __('Appearance') }} <small>{{ __('System') }}</small></h3><p>{{ __('Default look for everyone. Each user can still switch from the top bar.') }}</p></div></header>
                    <div class="ss-split">
                        <div class="ss-grp">
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
                        <aside class="ss-side" aria-label="{{ __('Preview') }}">
                            <h4>{!! $svg($ico['eye'], '2') !!}{{ __('How it will look') }}</h4>
                            <div class="ss-mock" id="ssMock" aria-hidden="true">
                                <div class="rail"><i></i><i class="on"></i><i></i><i></i><i></i><i></i></div>
                                <div class="pg">
                                    <div class="top"><b></b><span></span></div>
                                    <div class="card">
                                        <div class="tr hd"><b></b></div>
                                        @for($r = 0; $r < 7; $r++)<div class="tr"><i></i><b style="max-width:{{ [70, 55, 80, 62, 74, 50, 66][$r] }}%"></b><em></em></div>@endfor
                                    </div>
                                    <span class="lnk">{{ __('View all') }} →</span>
                                </div>
                            </div>
                            <dl class="ss-kv">
                                <dt>{{ __('Theme') }}</dt><dd data-look="theme"></dd>
                                <dt>{{ __('Accent') }}</dt><dd data-look="accent"></dd>
                                <dt>{{ __('Rows') }}</dt><dd data-look="density"></dd>
                            </dl>
                            <p class="note">{{ __('The preview follows your picks before you save. A user who chose a theme in the top bar keeps it.') }}</p>
                        </aside>
                    </div>
                </section>

                {{-- ── SECURITY ────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="security" @if($section !== 'security') hidden @endif>
                    <header class="ss-sec-h"><div><h3>{{ __('Security') }} <small>{{ __('System') }}</small></h3><p>{{ __('Sign-in rules for every account.') }}</p></div></header>
                    <div class="ss-split">
                        <div class="ss-grp">
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
                        </div>
                        <aside class="ss-side" aria-label="{{ __('Sign-in rules') }}">
                            <h4>{!! $svg($ico['shield'], '2') !!}{{ __('The rules, as users meet them') }}</h4>
                            <ul class="ss-rules">
                                <li><span>{!! $svg($ico['clock'], '2') !!}</span><span data-rule="session"></span></li>
                                <li><span>{!! $svg($ico['key'], '2') !!}</span><span data-rule="password"></span></li>
                                <li><span>{!! $svg($ico['shield'], '2') !!}</span><span data-rule="lockout"></span></li>
                                <li><span>{!! $svg($ico['g'], '2') !!}</span><span data-rule="google"></span></li>
                            </ul>
                            <div class="ss-danger">
                                <b>{{ __('Sign out everyone') }}</b>
                                <small>{{ __('Ends all active sessions except yours.') }}</small>
                                <button class="ss-btn danger" type="button" data-sign-out-all>{{ __('Sign out all sessions') }}</button>
                            </div>
                        </aside>
                    </div>
                </section>

                {{-- ── KIOSKS ──────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="kiosk" @if($section !== 'kiosk') hidden @endif>
                    <header class="ss-sec-h"><div><h3>{{ __('Kiosks') }} <small>{{ __('System') }}</small></h3><p>{{ __('How fingerprint scans are recorded at the sites — and what each kiosk is showing right now.') }}</p></div>
                        <a class="ss-btn" href="{{ route('devices.index') }}">{!! $svg('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>') !!}{{ __('Device Monitoring') }}</a></header>
                    <div class="ss-split">
                        <div class="ss-grp stack">
                            {{-- Where each kiosk stands. Set here, not on the kiosk: it
                                 records only for this site's workers, and only inside
                                 the site's radius. Takes effect at once. --}}
                            <div class="ss-row"><div class="lb"><b>{{ __('Kiosk site') }}</b><small>{{ __("Where each kiosk is standing. It records attendance only for this site's workers, and only inside the site's radius. Pick a site, then press Save — the kiosk follows within a few seconds.") }}</small></div>
                                <div class="ks-list" data-ks-url="{{ url('system-settings/kiosk') }}" @if($s->checksKioskLocation()) data-ks-check="1" @endif>
                                    @foreach($kiosks as $k)
                                        @php
                                            $m = $k['map'];
                                            [$geoCls, $geoText] = match (true) {
                                                ! $k['site_id']        => ['warn', __('No site yet')],
                                                ! $s->checksKioskLocation() => ['warn', __('Location check is off')],
                                                ! $m['site']           => ['out', __('No location on Sites — refusing scans')],
                                                $m['lat'] === null     => ['warn', __('No GPS yet')],
                                                (bool) $m['inside']    => ['in', __('Inside') . ' · ' . $m['distance'] . ' m'],
                                                default                => ['out', __('Outside') . ' · ' . $m['distance'] . ' m — refusing scans'],
                                            };
                                        @endphp
                                        <div class="ks-card" data-ks="{{ $k['id'] }}">
                                            <div class="ks-top"><i class="ks-dot {{ $k['state'] }}"></i><b>{{ $k['name'] }}</b><span class="ks-code">{{ $k['code'] }}</span>
                                                <span class="ks-geo {{ $geoCls }}" data-ks-geo>{{ $geoText }}</span>
                                                @if($kiosks->count() > 1)
                                                    <button type="submit" form="ksDel{{ $k['id'] }}" class="ks-del" title="{{ __('Remove this kiosk') }}">{{ __('Remove') }}</button>
                                                @endif</div>
                                            <div class="ks-sites" role="radiogroup" aria-label="{{ __('Site of') }} {{ $k['name'] }}">
                                                @foreach($kioskSites as $st)
                                                    <button type="button" class="ks-site @if($k['site_id'] === $st->id) on @endif" role="radio" aria-checked="{{ $k['site_id'] === $st->id ? 'true' : 'false' }}" data-site="{{ $st->id }}">
                                                        <b>{{ $st->name }}</b>@if($st->location)<small>{{ $st->location }}</small>@endif</button>
                                                @endforeach
                                            </div>
                                            <div class="ks-save" data-ks-save>
                                                <span data-ks-ask></span>
                                                <button type="button" class="ss-btn" data-ks-cancel>{{ __('Cancel') }}</button>
                                                <button type="button" class="ss-btn pri" data-ks-go>{{ __('Save') }}</button>
                                            </div>
                                            <div class="ks-note" data-ks-note></div>
                                        </div>
                                    @endforeach
                                    <details class="ks-add" @if($errors->has('code') || $errors->has('name')) open @endif>
                                        <summary>+ {{ __('Add kiosk') }}</summary>
                                        <div class="ks-add-grid">
                                            <input form="ksAddForm" name="name" value="{{ old('name') }}" placeholder="{{ __('Name, e.g. Kiosk 2') }}" aria-label="{{ __('Kiosk name') }}" required>
                                            <input form="ksAddForm" name="code" value="{{ old('code') }}" placeholder="{{ __('Code, e.g. KIOSK_2') }}" aria-label="{{ __('Kiosk code') }}" required>
                                            <select form="ksAddForm" name="site_id" aria-label="{{ __('Site') }}">
                                                @foreach($kioskSites as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach
                                            </select>
                                            <button form="ksAddForm" type="submit" class="ss-btn pri">{{ __('Add') }}</button>
                                        </div>
                                        <div class="ks-add-hint">@error('name'){{ $message }} @enderror @error('code'){{ $message }}@else{{ __('The code must match KIOSK_CODE in testing.py on that kiosk\'s Pi.') }}@enderror</div>
                                    </details>
                                </div></div>
                            {{-- On: a scan the kiosk cannot place inside its site's radius is
                                 refused, as it always was. Off is for a broken GPS, so the
                                 crew can still scan in; the site check still holds. --}}
                            <div class="ss-row"><div class="lb"><b>{{ __('Reject scans out of range') }}</b><small>{{ __("On: a kiosk outside its site's radius, or with no GPS, refuses the scan. Turn it off if the kiosk's location is broken, so workers can still scan in.") }}</small></div>
                                <div class="ss-tgrow"><input type="hidden" name="kiosk_location_check" value="0"><label class="ss-tg"><input type="checkbox" name="kiosk_location_check" value="1" @checked($isOn('kiosk_location_check')) aria-label="{{ __('Reject scans out of range') }}" data-track data-saved="{{ $sw('kiosk_location_check') }}" data-label="{{ __('Reject scans out of range') }}"><span></span></label><small></small></div></div>
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
                        {{-- Whether each kiosk is on, and whether what was saved
                             here has reached it. Its screen, map and scans are on
                             Device Monitoring. --}}
                        <aside class="ss-side kn" aria-label="{{ __('Kiosks now') }}">
                            <h4>{!! $svg($ico['scan'], '2') !!}{{ __('Kiosks now') }}</h4>
                            @if($kiosks->isEmpty())
                                <p class="kn-none">{{ __('No kiosk is registered yet. A kiosk appears here once it has been added.') }}</p>
                            @else
                                <ul class="kn-list">
                                    @foreach($kiosks as $k)
                                        <li><i class="{{ $k['state'] }}"></i><b>{{ $k['name'] }}<small>{{ $k['site'] ?? __('Unassigned') }}</small></b>
                                            <em class="{{ $k['state'] }}">{{ ['ok' => __('ON'), 'late' => __('LATE'), 'off' => __('OFF')][$k['state']] }}</em>
                                            <span>{{ $k['code'] }} · {{ $k['seen_at'] ? __('last heard') . ' ' . $k['seen_at'] : __('never reported') }}</span>
                                            @php $m = $k['map']; @endphp
                                            <dl>
                                                <div><dt>{{ __('Heartbeat') }}</dt><dd class="{{ $k['state'] === 'ok' ? 'ok' : ($k['state'] === 'late' ? 'warn' : 'bad') }}">{{ $k['seen'] ?? __('never') }}</dd></div>
                                                <div><dt>{{ __('Settings reached it') }}</dt><dd>{{ $k['read'] ?? '—' }}</dd></div>
                                                <div><dt>{{ __('Geofence') }}</dt><dd class="{{ $m['inside'] === null ? 'warn' : ($m['inside'] ? 'ok' : 'bad') }}">{{ $m['inside'] === null ? ($m['site'] ? __('No GPS yet') : __('Site not pinned')) : ($m['inside'] ? __('Inside') : __('Outside')) . ' · ' . $m['distance'] . ' m' }}</dd></div>
                                            </dl></li>
                                    @endforeach
                                </ul>
                            @endif
                            <a class="kn-go" href="{{ route('devices.index') }}#dvConsole">{!! $svg('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>', '2') !!}
                                <span><b>{{ __('Watch the kiosks live') }}</b><small>{{ __('Their screens, where they are on the map, and every scan — on Device Monitoring.') }}</small></span></a>
                            <div class="kn-rules">
                                <h5>{{ __('How a kiosk decides') }}</h5>
                                <ol>
                                    <li><span><b>{{ __('The site is set here') }}</b>{{ __('The kiosk shows the site chosen under Kiosk site. It has no site buttons of its own.') }}</span></li>
                                    <li><span><b>{{ __("Only that site's workers") }}</b>{{ __('A worker assigned to another site, or to none, is refused by name.') }}</span></li>
                                    <li><span><b>{{ __("Only inside the site's radius") }}</b>{{ __('Its current GPS position counts. With no signal, its last known position does — a kiosk carried away without a fix is refused until the GPS finds it at the new site.') }}</span></li>
                                </ol>
                            </div>
                        </aside>
                    </div>
                </section>

                {{-- ── NOTIFICATIONS ───────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="notif" @if($section !== 'notif') hidden @endif>
                    <header class="ss-sec-h"><div><h3>{{ __('Notifications') }} <small>{{ __('System') }}</small></h3><p>{{ __('What admins get notified about.') }}</p></div></header>
                    <div class="ss-split">
                        <div class="ss-grp">
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
                        {{-- The bell as an admin would find it, one alert of each
                             kind; the ones switched off are greyed. --}}
                        <aside class="ss-side" aria-label="{{ __('What admins receive') }}">
                            <h4>{!! $svg($ico['bell'], '2') !!}{{ __('What reaches the bell') }}</h4>
                            <div class="ss-bell">
                                <header>{{ __('Notifications') }} <span data-bell-count></span></header>
                                <div class="it" data-bell="notify_missing_scans" style="--c:var(--warning);--c-soft:var(--warning-soft)"><span>{!! $svg($ico['clock'], '2') !!}</span><div><b>{{ __('Missing time out') }}<em data-bell-mail>{{ __('+ email') }}</em></b><small>{{ __('Jason Caridad timed in at 7:50 AM and has not timed out.') }}</small></div></div>
                                <div class="it" data-bell="notify_remittances" style="--c:var(--brand);--c-soft:var(--brand-subtle)"><span>{!! $svg($ico['cal'], '2') !!}</span><div><b>{{ __('Remittance due') }}<em data-bell-mail>{{ __('+ email') }}</em></b><small>{{ __('SSS for August is due by Sep 30 and is not marked paid.') }}</small></div></div>
                                <div class="it" data-bell="notify_payroll" style="--c:var(--success);--c-soft:var(--success-soft)"><span>{!! $svg($ico['peso'], '2') !!}</span><div><b>{{ __('Payroll ready') }}<em data-bell-mail>{{ __('+ email') }}</em></b><small>{{ __("This week's payroll is ready to review.") }}</small></div></div>
                                <div class="it" data-bell="kiosk_unknown_alert" style="--c:var(--danger);--c-soft:var(--danger-soft)"><span>{!! $svg($ico['scan'], '2') !!}</span><div><b>{{ __('Unknown fingerprint') }}<em data-bell-mail>{{ __('+ email') }}</em></b><small>{{ __('A finger that is not registered was scanned at Site A. Set under Kiosks.') }}</small></div></div>
                            </div>
                            <p class="note">{{ __('Examples only. Each admin gets these in the bell at the top of the page; with email on, a copy goes to their address too.') }}</p>
                        </aside>
                    </div>
                </section>

                <div class="ss-bar" id="ssBar" role="region" aria-label="{{ __('Unsaved changes') }}"><span><i></i>{{ __('You have unsaved changes') }}</span><button class="ss-btn ghost" type="button" data-discard>{{ __('Discard') }}</button><button class="ss-btn pri" type="submit" data-save>{{ __('Save changes') }}</button></div>
            </form>

            <form method="POST" action="{{ route('system-settings.sign-out-all') }}" id="ssSignOut" hidden>@csrf</form>
            <form method="POST" action="{{ route('system-settings.kiosk.store') }}" id="ksAddForm" hidden>@csrf</form>
            @foreach($kiosks as $k)
                <form method="POST" action="{{ route('system-settings.kiosk.destroy', $k['id']) }}" id="ksDel{{ $k['id'] }}" hidden
                      data-confirm="{{ __('This removes :name. Its attendance records are kept.', ['name' => $k['name']]) }}"
                      data-confirm-title="{{ __('Remove kiosk?') }}" data-confirm-label="{{ __('Remove') }}" data-confirm-tone="danger">@csrf @method('DELETE')</form>
            @endforeach

            {{-- ── AUDIT LOGS ──────────────────────────────────────────────── --}}
            <section class="ss-sec" data-sec="audit" @if($section !== 'audit') hidden @endif>
                <header class="ss-sec-h"><div><h3>{{ __('Audit logs') }} <small>{{ __('System') }}</small></h3><p>{{ __("Every change in the system. Logs can't be edited or deleted.") }}</p></div>
                    <a class="ss-btn" id="auditExport" href="{{ route('audit-logs.export', array_filter(request()->only(['q', 'module']) + $auditKeep, fn ($x) => $x !== null && $x !== '')) }}" download>{!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M12 11v6M9 14l3 3 3-3"/>') !!}{{ __('Export') }}</a></header>
                <div class="ss-audit">
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
                    <div class="ss-loglist">
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

    // ── Switches read On / Off; the lockout text follows its select ──────
    function tgLabels() { $$('.ss-tgrow').forEach(r => { const c = r.querySelector('input[type=checkbox]'); r.querySelector(':scope > small').textContent = c.checked ? @json(__('On')) : @json(__('Off')); }); }
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
        tgLabels(); lockText(); looks();
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

// ── The side panels: each follows its section's choices before they are saved ──
(function () {
    const form = document.getElementById('ssForm');
    if (!form) return;
    const $$ = s => [...document.querySelectorAll(s)];
    const picked = n => form.querySelector('[name="' + n + '"]:checked');
    const on = n => { const c = form.querySelector('input[type=checkbox][name="' + n + '"]'); return !!(c && c.checked); };
    const text = sel => { const o = form.elements[sel]?.selectedOptions?.[0]; return o ? o.textContent.trim() : ''; };

    // Appearance: the small copy of the app.
    const mock = document.getElementById('ssMock');
    const accents = {!! $accentHex !!};
    const look = (k, v) => { const e = document.querySelector('[data-look="' + k + '"]'); if (e) e.textContent = v; };
    function appearance() {
        if (!mock) return;
        const t = picked('default_theme')?.value || 'system';
        const dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        mock.classList.toggle('is-dark', dark);
        const a = picked('accent_color');
        mock.style.setProperty('--m-accent', accents[a?.value] || 'var(--accent)');
        const compact = picked('table_density')?.value === 'compact';
        mock.classList.toggle('is-compact', compact);
        look('theme', (picked('default_theme')?.nextElementSibling?.textContent || '') + (t === 'system' ? ' · ' + (dark ? @json(__('dark now')) : @json(__('light now'))) : ''));
        look('accent', a ? a.getAttribute('aria-label') : '');
        look('density', picked('table_density')?.nextElementSibling?.textContent || '');
    }

    // Security: each rule as a sentence.
    const rule = (k, html) => { const e = document.querySelector('[data-rule="' + k + '"]'); if (e) e.innerHTML = html; };
    const esc = v => String(v).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    function security() {
        if (!form.elements.session_timeout_minutes) return;
        rule('session',  @json(__('Signed out after')) + ' <b>' + esc(text('session_timeout_minutes')) + '</b> ' + @json(__('without activity.')));
        rule('password', @json(__('Passwords need at least')) + ' <b>' + esc(form.elements.password_min_length.value || '—') + ' ' + @json(__('characters')) + '</b>.');
        rule('lockout',  @json(__('After')) + ' <b>' + esc(form.elements.max_login_attempts.value || '—') + ' ' + @json(__('failed tries')) + '</b>, ' + @json(__('the account waits')) + ' <b>' + esc(text('lockout_seconds')) + '</b>.');
        rule('google',   on('google_sign_in') ? @json(__('Accounts can also')) + ' <b>' + @json(__('sign in with Google')) + '</b>.' : @json(__('Google sign-in is')) + ' <b>' + @json(__('off')) + '</b>; ' + @json(__('username and password only.')));
    }

    // Notifications: the bell.
    function bell() {
        let n = 0;
        $$('[data-bell]').forEach(it => { const lit = on(it.dataset.bell); it.classList.toggle('is-off', !lit); if (lit) n++; });
        $$('[data-bell-mail]').forEach(m => { m.hidden = !on('notify_email'); });
        const c = document.querySelector('[data-bell-count]'); if (c) c.textContent = n;
        const meta = document.querySelector('[data-notif-meta]');
        if (meta) meta.textContent = ['notify_missing_scans', 'notify_remittances', 'notify_payroll', 'notify_email'].filter(on).length + '/4 ' + @json(__('on'));
    }

    const all = () => { appearance(); security(); bell(); };
    form.addEventListener('input', all);
    form.addEventListener('change', all);
    form.addEventListener('reset', () => setTimeout(all, 0));
    all();
})();

// ── Kiosk site ───────────────────────────────────────────────────────────────
// A tap only picks the site; Save moves the kiosk (not through the page's
// save bar). The kiosk reads it on its next settings question, seconds later.
(function () {
    const list = document.querySelector('.ks-list');
    if (!list) return;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function geo(k) {
        const m = k.map || {};
        if (!k.site_id) return ['warn', 'No site yet'];
        if (!list.dataset.ksCheck) return ['warn', 'Location check is off'];
        if (!m.site) return ['out', 'No location on Sites — refusing scans'];
        if (m.lat === null) return ['warn', 'No GPS yet'];
        return m.inside ? ['in', 'Inside · ' + m.distance + ' m'] : ['out', 'Outside · ' + m.distance + ' m — refusing scans'];
    }

    function reset(card) {
        card.querySelectorAll('.ks-site').forEach(b => b.classList.remove('pick', 'was'));
        card.querySelector('[data-ks-save]').classList.remove('show');
        delete card.dataset.pick;
    }

    list.addEventListener('click', async e => {
        const card = e.target.closest('[data-ks]');
        if (!card) return;
        const note = card.querySelector('[data-ks-note]');

        // Pick: nothing is sent yet.
        const btn = e.target.closest('.ks-site');
        if (btn) {
            reset(card);
            if (btn.classList.contains('on')) return;          // back to where it is
            btn.classList.add('pick');
            card.querySelector('.ks-site.on')?.classList.add('was');
            card.dataset.pick = btn.dataset.site;
            const from = card.querySelector('.ks-site.on b');
            card.querySelector('[data-ks-ask]').innerHTML = 'Move to <b></b>' + (from ? ' from <b></b>' : '') + '?';
            const bs = card.querySelectorAll('[data-ks-ask] b');
            bs[0].textContent = btn.querySelector('b').textContent;
            if (from) bs[1].textContent = from.textContent;
            card.querySelector('[data-ks-save]').classList.add('show');
            note.className = 'ks-note'; note.textContent = '';
            return;
        }

        if (e.target.closest('[data-ks-cancel]')) { reset(card); return; }
        const go = e.target.closest('[data-ks-go]');
        if (!go || !card.dataset.pick) return;

        const buttons = card.querySelectorAll('.ks-site, [data-ks-save] button');
        buttons.forEach(b => b.disabled = true);
        card.classList.add('moving');
        go.textContent = 'Saving…';
        try {
            const res = await fetch(list.dataset.ksUrl + '/' + card.dataset.ks + '/site', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: JSON.stringify({ site_id: Number(card.dataset.pick) }),
            });
            if (!res.ok) throw new Error(res.status);
            const k = (await res.json()).kiosk;
            reset(card);
            card.querySelectorAll('.ks-site').forEach(b => { const on = Number(b.dataset.site) === k.site_id; b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); });
            const [cls, text] = geo(k);
            const g = card.querySelector('[data-ks-geo]');
            g.className = 'ks-geo ' + cls; g.textContent = text;
            note.className = 'ks-note ok';
            note.textContent = 'Saved. ' + k.name + ' is now at ' + k.site + '. The kiosk switches within a few seconds.'
                + (cls === 'out' ? ' Until its GPS is inside ' + k.site + "'s radius, it will refuse scans." : '');
        } catch (err) {
            note.className = 'ks-note bad';
            note.textContent = 'Could not save the site — check the connection and try again.';
        } finally {
            buttons.forEach(b => b.disabled = false);
            go.textContent = 'Save';
            card.classList.remove('moving');
        }
    });
})();
</script>
@endpush
