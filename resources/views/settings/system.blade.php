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

/* ── Kiosk monitor ─────────────────────────────────────────────────────── */
.km { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
.km-bar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.km-pick { display: inline-flex; align-items: center; gap: 8px; height: 32px; padding: 0 11px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel); color: var(--text); font-size: 12.5px; font-weight: 600; cursor: pointer; }
.km-pick i { width: 8px; height: 8px; border-radius: 50%; background: var(--danger); flex: none; }
.km-pick i.ok { background: var(--success); }
.km-pick i.late { background: var(--warning); }
.km-pick small { color: var(--faint); font-weight: 600; font-size: 11px; }
.km-pick.on { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent) inset; }
.km-bar .sp { flex: 1; }
.km-bar .chk { font-size: 11.5px; color: var(--faint); font-family: var(--mono); }
.km-bar .ss-btn { height: 32px; }
/* The screen is drawn at the kiosk's own 1280 × 800 and scaled to fit, so
   it reads exactly as the one on site does. */
.km-frame { position: relative; width: 100%; aspect-ratio: 16 / 10; border-radius: 12px; background: #05080d; padding: 10px; box-shadow: 0 0 0 1px #1b2433 inset; }
.km-glass { position: relative; width: 100%; height: 100%; overflow: hidden; border-radius: 4px; background: #0b111a; }
/* Full screen: the kiosk at the size of the office's screen. */
.km-frame:fullscreen { border-radius: 0; padding: 0; display: grid; place-items: center; background: #000; }
.km-frame:fullscreen .km-glass { width: min(100vw, 160vh); height: auto; aspect-ratio: 16 / 10; border-radius: 0; }
.km-screen { position: absolute; left: 0; top: 0; width: 1280px; height: 800px; transform-origin: 0 0; }
.km-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border: 1px solid var(--line-soft); border-radius: 10px; background: var(--panel); overflow: hidden; }
.km-facts > div { padding: 9px 12px; border-right: 1px solid var(--line-soft); min-width: 0; }
.km-facts > div:last-child { border-right: 0; }
.km-facts span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--faint); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Today's scans at the kiosk shown, newest first: the rest of the column. */
.km-log { flex: 1; min-height: 120px; display: flex; flex-direction: column; border: 1px solid var(--line-soft); border-radius: 10px; background: var(--panel); overflow: hidden; }
.km-log > header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid var(--line-soft); font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--faint); }
.km-log ol { list-style: none; margin: 0; padding: 0; overflow-y: auto; flex: 1; }
.km-log li { display: grid; grid-template-columns: 74px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 7px 12px; border-bottom: 1px solid var(--line-soft); font-size: 12.5px; }
.km-log li:last-child { border-bottom: 0; }
.km-log time { font-family: var(--mono); font-size: 11.5px; color: var(--muted); }
.km-log li b { font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.km-log li span { font-size: 10.5px; font-weight: 800; letter-spacing: .05em; padding: 2px 7px; border-radius: 5px; white-space: nowrap; }
.km-log li span.in { color: var(--success); background: var(--success-soft); }
.km-log li span.out { color: var(--danger); background: var(--danger-soft); }
.km-log .none { padding: 18px 12px; text-align: center; color: var(--muted); font-size: 12.5px; }
.km-facts b { display: block; font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.km-facts b.ok { color: var(--success); } .km-facts b.off { color: var(--danger); } .km-facts b.late { color: var(--warning); }
.km-none { padding: 40px 16px; text-align: center; color: var(--muted); border: 1px dashed var(--line); border-radius: 12px; }

/* The kiosk's own look (kiosk-screen.css on the Pi), at its own size. */
.kx { --kx-bg: #0d131c; --kx-panel: #161e2a; --kx-line: #263245; --kx-text: #eef2f8; --kx-muted: #8b98ab; --kx-blue: #2f7bf6; --kx-green: #34c05a; --kx-red: #ef4444; --kx-amber: #f5b223;
    width: 1280px; height: 800px; background: var(--kx-bg); color: var(--kx-text); font-family: Inter, system-ui, sans-serif; display: flex; flex-direction: column; }
.kx-top { height: 78px; display: flex; align-items: center; gap: 16px; padding: 0 26px; background: #111823; border-bottom: 1px solid var(--kx-line); }
.kx-logo { width: 50px; height: 50px; border-radius: 10px; background: #fff; display: grid; place-items: center; overflow: hidden; }
.kx-logo img { width: 44px; height: 44px; object-fit: contain; }
.kx-co b { display: block; font-size: 20px; font-weight: 800; letter-spacing: .06em; }
.kx-co small { display: block; font-size: 13px; color: var(--kx-muted); margin-top: 2px; font-weight: 500; }
.kx-top .sp { flex: 1; }
.kx-lang { display: flex; gap: 4px; padding: 4px; border: 1px solid var(--kx-line); border-radius: 10px; }
.kx-lang span { padding: 6px 12px; border-radius: 7px; font-size: 13px; font-weight: 800; color: var(--kx-muted); }
.kx-lang span.on { background: var(--kx-blue); color: #fff; }
.kx-sess { height: 34px; padding: 0 18px; border-radius: 999px; border: 1.5px solid var(--kx-blue); color: #7fb0ff; display: flex; align-items: center; font-weight: 800; letter-spacing: .06em; font-size: 14px; }
.kx-clock { text-align: right; }
.kx-clock b { display: block; font-size: 34px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
.kx-clock small { display: block; font-size: 13px; color: var(--kx-muted); letter-spacing: .06em; margin-top: 4px; }
.kx-strip { height: 60px; display: flex; align-items: center; gap: 12px; padding: 0 20px; background: #141c28; border-bottom: 1px solid var(--kx-line); }
.kx-strip .lbl { font-size: 12px; font-weight: 800; letter-spacing: .08em; color: #c9d3e2; }
.kx-site { height: 36px; padding: 0 16px; border-radius: 9px; background: var(--kx-blue); color: #fff; font-weight: 800; font-size: 15px; display: flex; align-items: center; }
.kx-strip .sp { flex: 1; }
.kx-strip .ok { color: var(--kx-green); font-weight: 700; font-size: 13px; }
.kx-strip .saved { font-size: 11.5px; font-weight: 800; color: var(--kx-green); border: 1px solid color-mix(in srgb, var(--kx-green) 60%, transparent); border-radius: 999px; padding: 3px 10px; background: color-mix(in srgb, var(--kx-green) 12%, transparent); }
.kx-tabs { height: 48px; display: flex; padding: 0 18px; border-bottom: 1px solid var(--kx-line); }
.kx-tabs button { width: 308px; border: 0; background: none; color: var(--kx-muted); font: 800 14px Inter, sans-serif; letter-spacing: .08em; border-bottom: 3px solid transparent; cursor: pointer; }
.kx-tabs button.on { color: #7fb0ff; border-bottom-color: var(--kx-blue); background: linear-gradient(to bottom, transparent, rgba(47,123,246,.12)); }
.kx-body { flex: 1; display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 18px; padding: 18px; min-height: 0; }
.kx-card { background: var(--kx-panel); border: 1px solid var(--kx-line); border-radius: 12px; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.kx-card > .kx-h { margin: 0; height: 44px; padding: 0 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--kx-line); font-size: 13px; font-weight: 800; letter-spacing: .1em; color: #c9d3e2; }
.kx-card > .kx-h .sp { flex: 1; }
.kx-card > .kx-h small { font-size: 11.5px; letter-spacing: 0; color: var(--kx-muted); font-weight: 700; }
.kx-scan { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 18px; text-align: center; }
.kx-fp { width: 116px; height: 116px; border-radius: 50%; border: 2px solid var(--kx-line); display: grid; place-items: center; color: #9aa6b8; background: #1c2533; }
.kx-fp svg { width: 56px; height: 56px; }
.kx-fp.hit { border-color: var(--kx-green); color: var(--kx-green); box-shadow: 0 0 0 8px rgba(52,192,90,.08); }
.kx-fp.hit.out { border-color: var(--kx-red); color: var(--kx-red); box-shadow: 0 0 0 8px rgba(239,68,68,.08); }
.kx-scan .big { font-size: 18px; font-weight: 800; letter-spacing: .04em; }
.kx-scan .sub { font-size: 14px; color: var(--kx-muted); }
.kx .kx-who { color: #eef2f8 !important; font-weight: 800; }
.kx-inout { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
.kx-inout span { height: 74px; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; }
.kx-inout span small { font-size: 11px; letter-spacing: .08em; margin-top: 3px; }
.kx-inout .in { border: 1.5px solid #2c7a45; background: #13261c; color: var(--kx-green); }
.kx-inout .out { border: 1.5px solid #8a2d2d; background: #2a1618; color: var(--kx-red); }
.kx-inout.auto { grid-template-columns: 1fr; }
.kx-inout.auto span { border: 1.5px solid #274b86; background: #122036; color: #7fb0ff; }
.kx-foot { height: 42px; border-top: 1px solid var(--kx-line); display: flex; align-items: center; justify-content: space-between; padding: 0 18px; font-size: 13px; color: var(--kx-muted); }
.kx-foot .ok { color: var(--kx-muted); } .kx-foot .ok::before { content: "● "; color: var(--kx-green); }
.kx-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--kx-line); }
.kx-kpis > div { border: 1px solid var(--kx-line); border-left: 3px solid var(--c); border-radius: 10px; padding: 10px 14px; background: #1b2431; }
.kx-kpis b { display: block; font-size: 32px; font-weight: 800; color: var(--c); line-height: 1.1; }
.kx-kpis span { font-size: 13px; color: #b7c2d3; font-weight: 600; }
.kx-tbl { width: 100%; border-collapse: collapse; }
.kx-tbl th { height: 40px; font-size: 12px; font-weight: 800; letter-spacing: .06em; color: var(--kx-muted); text-align: right; padding: 0 12px; border-bottom: 1px solid var(--kx-line); background: #141c28; }
.kx-tbl th:first-child, .kx-tbl td:first-child { text-align: left; padding-left: 18px; }
.kx-tbl td { height: 64px; padding: 0 12px; text-align: right; border-bottom: 1px solid var(--kx-line); font-size: 16px; font-weight: 700; font-variant-numeric: tabular-nums; }
.kx-tbl td small { font-size: 11px; color: var(--kx-muted); margin-left: 2px; font-weight: 700; }
.kx-tbl td.nm b { display: block; font-size: 16px; }
.kx-tbl td.nm span { font-size: 12.5px; color: var(--kx-muted); font-weight: 600; }
.kx-tbl td.nm em { font-style: normal; font-size: 10px; font-weight: 800; letter-spacing: .06em; padding: 2px 6px; border-radius: 4px; margin-left: 6px; vertical-align: 2px; background: rgba(245,178,35,.14); color: var(--kx-amber); }
.kx-tbl td.nm em.n { background: rgba(127,176,255,.14); color: #7fb0ff; }
.kx-tbl td.brk { width: 54px; padding: 0; background: repeating-linear-gradient(135deg, #2a2a20 0 6px, #1b1c18 6px 12px); }
.kx-tbl td.dim { color: #4d5a6d; }
.kx-tbl td.ot { color: #5b9dff; }
.kx-tbl tr.w td { background: rgba(52,192,90,.045); }
.kx-tbl tr.w td.brk { background: repeating-linear-gradient(135deg, #2a2a20 0 6px, #1b1c18 6px 12px); }
.kx-st { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px; font-size: 12.5px; font-weight: 800; letter-spacing: .06em; white-space: nowrap; }
.kx-st.working { color: var(--kx-green); border: 1.5px solid #2c7a45; background: #13261c; }
.kx-st.working::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.kx-st.ot { color: #7fb0ff; border: 1.5px solid #274b86; background: #122036; }
.kx-st.notback, .kx-st.lunch { color: var(--kx-amber); border: 1.5px dashed #8a6a1c; }
.kx-st.done { color: var(--kx-muted); border: 1.5px solid var(--kx-line); }
.kx-empty { flex: 1; display: grid; place-items: center; color: var(--kx-muted); font-size: 15px; text-align: center; padding: 20px; }
.kx-scroll { flex: 1; overflow: hidden; }
.kx-roster { display: flex; flex-direction: column; gap: 8px; padding: 12px 16px; }
.kx-roster > div { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 14px; border: 1px solid var(--kx-line); border-radius: 10px; background: #1b2431; }
.kx-roster b { font-size: 16px; }
.kx-roster b em { font-style: normal; font-size: 10.5px; font-weight: 800; padding: 2px 7px; border-radius: 4px; background: var(--kx-blue); color: #fff; margin-left: 8px; vertical-align: 2px; }
.kx-roster small { display: block; color: var(--kx-muted); font-size: 12.5px; margin-top: 2px; }
.kx-badge { font-size: 11px; font-weight: 800; letter-spacing: .06em; padding: 5px 10px; border-radius: 999px; white-space: nowrap; }
.kx-badge.need { color: var(--kx-amber); border: 1.5px solid #8a6a1c; }
.kx-badge.have { color: var(--kx-green); border: 1.5px solid #2c7a45; }
.kx-lock { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; color: var(--kx-muted); text-align: center; font-size: 15px; padding: 30px; }
.kx-lock svg { width: 64px; height: 64px; color: #3b4a60; }
.kx-lock b { color: var(--kx-text); font-size: 20px; letter-spacing: .04em; }
.kx-viewonly { position: absolute; right: 18px; bottom: 16px; font: 800 11px Inter, sans-serif; letter-spacing: .12em; color: #8b98ab; background: rgba(13,19,28,.85); border: 1px solid var(--kx-line); border-radius: 6px; padding: 5px 9px; }
/* Off: the screen is dark, and says so. */
.kx-res { justify-content: center; gap: 12px; }
.kx-fp.read { border-color: var(--kx-blue); color: #7fb0ff; box-shadow: 0 0 0 8px rgba(47,123,246,.1); }
.kx-verb { width: 100%; min-height: 66px; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 6px 12px; text-align: center; font-size: 26px; font-weight: 900; letter-spacing: 2px; color: #fff; line-height: 1.1; }
.kx-verb.in { background: #2ea043; box-shadow: 0 0 0 4px rgba(46,160,67,.2); }
.kx-verb.out { background: #f0473e; box-shadow: 0 0 0 4px rgba(240,71,62,.2); }
.kx-verb.scan { background: #122036; color: #7fb0ff; border: 1.5px solid #274b86; font-size: 22px; }
.kx-verb.warn { background: rgba(242,176,36,.14); color: #f2b024; border: 1.5px solid #8a6a1c; font-size: 20px; }
.kx-verb.rej, .kx-verb.unknown { background: rgba(240,71,62,.14); color: #ff8a82; border: 1.5px solid #8a2d2d; font-size: 20px; }
.kx-rwho { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border-radius: 10px; background: #1b2431; border: 1px solid var(--kx-line); text-align: left; }
.kx-rwho b { display: block; font-size: 19px; }
.kx-rwho small { display: block; font-size: 13px; color: var(--kx-muted); margin-top: 2px; }
.kx-rwho > span { font-size: 24px; font-weight: 800; font-variant-numeric: tabular-nums; white-space: nowrap; }
.kx-rnote { width: 100%; padding: 9px 12px; border-radius: 8px; font-size: 14px; background: #1b2431; border: 1px solid var(--kx-line); color: #b7c2d3; }
.kx-rnote.warn { color: #f2b024; border-color: #8a6a1c; }
.kx-rnote.rej, .kx-rnote.unknown { color: #ff8a82; border-color: #8a2d2d; }
.kx-rbar { width: 100%; height: 5px; border-radius: 99px; background: #232d3b; overflow: hidden; }
.kx-rbar i { display: block; height: 100%; background: #7fb0ff; animation: kx-drain linear forwards; }
@keyframes kx-drain { from { width: 100%; } to { width: 0; } }
.kx .kx-live { color: #34c05a !important; letter-spacing: .08em !important; animation: kx-blink 1.2s steps(2) infinite; }
.kx-strip .kx-onair { color: #34c05a; border-color: rgba(52,192,90,.6); animation: kx-blink 1.6s ease-in-out infinite; }
@keyframes kx-blink { 50% { opacity: .45; } }
.km-log li.fresh span, .km-log li span.rej { color: var(--warning); background: var(--warning-soft); }
@media (prefers-reduced-motion: reduce) { .kx .kx-live, .kx-strip .kx-onair { animation: none; } }
.kx-off { width: 1280px; height: 800px; background: #030507; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px; color: #5d6a7d; font-family: Inter, system-ui, sans-serif; text-align: center; }
.kx-off svg { width: 120px; height: 120px; color: #3a4658; }
.kx-off b { font-size: 48px; letter-spacing: .2em; color: #c9d3e2; }
.kx-off p { margin: 0; font-size: 24px; max-width: 900px; line-height: 1.5; }
.kx-off p strong { color: #c9d3e2; }

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
    .km-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .km-facts > div:nth-child(2) { border-right: 0; }
    .km-facts > div:nth-child(-n+2) { border-bottom: 1px solid var(--line-soft); }
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
                    <div class="ss-split wide-side">
                        <div class="ss-grp stack">
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
                        {{-- The kiosk monitor: what each kiosk's screen is
                             showing, drawn from what the kiosk reads from us.
                             Filled and refreshed by the script below. --}}
                        <aside class="ss-side km" id="kioskMonitor" data-url="{{ route('system-settings.kiosk.monitor') }}" aria-label="{{ __('Kiosk monitor') }}">
                            <div class="km-bar">
                                <h4>{!! $svg($ico['scan'], '2') !!}{{ __('Kiosk monitor') }}</h4>
                                <span class="sp"></span>
                                <span class="chk" data-km-checked></span>
                                <button type="button" class="ss-btn" data-km-full>{!! $svg('<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>', '2') !!}{{ __('Full screen') }}</button>
                                <button type="button" class="ss-btn" data-km-refresh>{!! $svg('<path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/>', '2') !!}{{ __('Refresh') }}</button>
                            </div>
                            <div class="km-bar" data-km-picks>
                                @foreach($kiosks as $k)
                                    <button type="button" class="km-pick" data-km-kiosk="{{ $k['id'] }}"><i class="{{ $k['state'] }}"></i>{{ $k['name'] }} <small>{{ $k['site'] }}</small></button>
                                @endforeach
                            </div>
                            @if($kiosks->isEmpty())
                                <p class="km-none">{{ __('No kiosk is registered yet. A kiosk appears here once it has been added.') }}</p>
                            @else
                                <div class="km-frame"><div class="km-glass"><div class="km-screen" data-km-screen></div></div></div>
                                <div class="km-facts" data-km-facts></div>
                                <div class="km-log"><header><span>{{ __('Scans today at this kiosk') }}</span><span data-km-count></span></header><ol data-km-log></ol></div>
                            @endif
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

// ── The kiosk monitor ────────────────────────────────────────────────────────
// Each kiosk's screen as the site sees it, drawn at the kiosk's own 1280 × 800
// from what it reads from us, refreshed every ten seconds while the section is
// open. A kiosk that has stopped its heartbeat shows as switched off.
(function () {
    const box = document.getElementById('kioskMonitor');
    const screen = box && box.querySelector('[data-km-screen]');
    if (!screen) return;

    const facts   = box.querySelector('[data-km-facts]');
    const checked = box.querySelector('[data-km-checked]');
    const glass   = screen.parentElement;
    const logo    = @json($s->logoUrl());
    const company = @json(mb_strtoupper($name ?: 'Company'));
    const T = {
        attendance: @json(__('ATTENDANCE')), enroll: @json(__('ENROLL FINGERPRINT')), payroll: @json(__('MY PAYROLL')),
        youAre: @json(__('YOU ARE AT')), saved: @json(__('Saved to web')), scan: @json(__('BIOMETRIC SCAN')),
        onSite: @json(__('WHO IS ON SITE TODAY')), working: @json(__('Working now')), today: @json(__('On site today')), ot: @json(__('Overtime')),
        none: @json(__('Nobody has scanned in yet today')), capture: @json(__('CAPTURE FINGERPRINT')), workers: @json(__('WORKERS AT THIS SITE')),
        noFp: @json(__('No fingerprint yet')), fpOn: @json(__('Fingerprint on file')), need: @json(__('NEEDS FINGERPRINT')), have: @json(__('ENROLLED')),
        viewOnly: @json(__('VIEW ONLY · MONITOR')), off: @json(__('KIOSK IS OFF')), lastScan: @json(__('LAST SCAN')), noScan: @json(__('NO SCANS YET TODAY')),
    };
    let current = null, tab = 'attendance', timer = null, data = null;
    // Live: the last event number read, the scan on screen now, and the
    // scans turned away since the monitor opened (the board has no row for them).
    let seq = null, flash = null, flashTimer = null, quick = null, recent = [];
    const FLASH_MS = 5000;
    const WARN = { already_in: 'ALREADY TIMED IN', no_open: 'NO OPEN TIME IN', just_timed_in: 'JUST TIMED IN',
                   just_timed_out: 'JUST TIMED OUT', session_done: 'SESSION DONE', wrong_shift: 'REJECTED',
                   not_registered: 'NOT REGISTERED YET', mode_buttons: 'PRESS A BUTTON FIRST',
                   no_gps: 'LOCATION NOT CONFIRMED', outside_location: 'LOCATION NOT CONFIRMED' };

    const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fp = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M12 11v3a8 8 0 01-1.5 4.7M8.5 7.2A5 5 0 0117 11v1.5M7 11a5 5 0 01.3-1.8M16.9 16a13 13 0 01-.9 3M5 16.5A12 12 0 006 11a6 6 0 011.2-3.6M12 3a8 8 0 018 8v1M4 11a8 8 0 012-5.3"/></svg>';
    const t12 = v => { if (!v) return '—'; const m = String(v).match(/^(\d{1,2}:\d{2})\s*([AP]M)$/i); return m ? m[1] + '<small>' + m[2].toUpperCase() + '</small>' : esc(v); };
    const hrs = v => (v || v === 0) && Number(v) > 0 ? Number(v).toFixed(2) : '—';
    const stLabel = { working: 'WORKING', ot: 'OVERTIME', lunch: 'ON LUNCH', notback: 'NOT BACK', done: 'DONE' };

    function fit() { const w = glass.clientWidth; screen.style.transform = 'scale(' + (w / 1280) + ')'; }
    new ResizeObserver(fit).observe(glass);
    document.addEventListener('fullscreenchange', () => setTimeout(fit, 50));

    function clock() {
        const n = new Date();
        const c = screen.querySelector('[data-kx-clock]'); if (c) c.textContent = n.toLocaleTimeString('en-GB', { hour12: false });
        const d = screen.querySelector('[data-kx-date]'); if (d) d.textContent = n.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).toUpperCase();
    }
    setInterval(clock, 1000);

    function attendanceView(s) {
        const b = s.board || {}, rows = b.records || [], last = s.last;
        const auto = s.mode === 'auto';
        const left = flash ? flashCard(s, flash) : `
            <div class="kx-card"><div class="kx-h">${fp.replace('<svg', '<svg width="18" height="18"')} ${T.scan}</div>
                <div class="kx-scan">
                    <div class="kx-fp ${last ? 'hit' + (last.type === 'out' ? ' out' : '') : ''}">${fp}</div>
                    ${last ? `<div class="big">${T.lastScan}: ${last.type === 'out' ? 'TIME OUT' : 'TIME IN'}</div>
                               <div class="sub"><span class="kx-who">${esc(last.name)}</span> · ${esc(last.at)} · ${esc(last.ago)}</div>`
                           : `<div class="big">${T.noScan}</div><div class="sub">${esc(s.site || '')}</div>`}
                    <div class="kx-inout ${auto ? 'auto' : ''}">${auto
                        ? `<span>SCAN<small>AUTOMATIC · ${esc(s.session)}</small></span>`
                        : `<span class="in">TIME IN<small>${esc(s.session)}</small></span><span class="out">TIME OUT<small>${esc(s.session)}</small></span>`}</div>
                </div>
                <div class="kx-foot"><span>${esc(s.site || '')}</span><span class="ok">System online</span></div>
            </div>`;
        const body = rows.length ? `<div class="kx-scroll"><table class="kx-tbl"><thead><tr><th>EMPLOYEE</th><th>AM IN</th><th>AM OUT</th><th></th><th>PM IN</th><th>PM OUT</th><th>PAID HRS</th><th>OT</th><th>STATUS</th></tr></thead><tbody>
            ${rows.slice(0, 7).map(r => `<tr class="${r.working ? 'w' : ''}">
                <td class="nm"><b>${esc(r.name)}<em class="${r.night ? 'n' : ''}">${r.night ? 'NIGHT' : 'DAY'}</em></b><span>${esc(r.position)}</span></td>
                <td class="${r.am_in ? '' : 'dim'}">${t12(r.am_in)}</td><td class="${r.am_out ? '' : 'dim'}">${t12(r.am_out)}</td><td class="brk"></td>
                <td class="${r.pm_in ? '' : 'dim'}">${t12(r.pm_in)}</td><td class="${r.pm_out ? '' : 'dim'}">${t12(r.pm_out)}</td>
                <td class="ot">${hrs(r.total_hours)}</td><td class="${r.overtime_hours > 0 ? 'ot' : 'dim'}">${hrs(r.overtime_hours)}</td>
                <td><span class="kx-st ${esc(r.state || r.status)}">${stLabel[r.state || r.status] || esc(r.status)}</span></td></tr>`).join('')}
            </tbody></table></div>` : `<div class="kx-empty">${T.none}</div>`;
        const sum = b.summary || {};
        return left + `
            <div class="kx-card"><div class="kx-h">${T.onSite}<span class="sp"></span><small>${esc(b.kiosk || '')}</small></div>
                <div class="kx-kpis">
                    <div style="--c:#34c05a"><b>${b.working ?? 0}</b><span>${T.working}</span></div>
                    <div style="--c:#eef2f8"><b>${b.total ?? 0}</b><span>${T.today}</span></div>
                    <div style="--c:#f5b223"><b>${sum.overtime ?? b.overtime ?? 0}</b><span>${T.ot}</span></div>
                </div>${body}
            </div>`;
    }

    // A scan as the kiosk shows it the moment it happens: the verb in its
    // colour, who, when, and why when it was turned away.
    function flashCard(s, e) {
        const verb = e.kind === 'in' ? 'TIME IN' : e.kind === 'out' ? 'TIME OUT'
                   : e.kind === 'scan' ? 'FINGER READ' : e.kind === 'unknown' ? 'FINGERPRINT NOT RECOGNISED'
                   : (WARN[e.code] || 'REJECTED');
        const sub = e.kind === 'in' || e.kind === 'out'
                  ? [e.session ? e.session + ' SESSION' : '', e.auto ? 'AUTOMATIC' : ''].filter(Boolean).join(' · ')
                  : e.kind === 'scan' ? 'Waiting for TIME IN or TIME OUT' : (e.message || '');
        const at = e.time ? String(e.time).replace(/:\d{2}(\s*[AP]M)$/i, '$1') : '';
        return `<div class="kx-card"><div class="kx-h">${fp.replace('<svg', '<svg width="18" height="18"')} ${T.scan}<span class="sp"></span><small class="kx-live">● LIVE</small></div>
            <div class="kx-scan kx-res">
                <div class="kx-fp hit ${e.kind === 'in' ? '' : e.kind === 'scan' ? 'read' : 'out'}">${fp}</div>
                <div class="kx-verb ${esc(e.kind)}">${esc(verb)}</div>
                ${e.name ? `<div class="kx-rwho"><div><b class="kx-who">${esc(e.name)}</b><small>${esc(e.position || '')}</small></div><span>${esc(at)}</span></div>` : ''}
                ${sub ? `<div class="kx-rnote ${esc(e.kind)}">${esc(sub)}</div>` : ''}
                <div class="kx-rbar"><i style="animation-duration:${FLASH_MS}ms"></i></div>
            </div>
            <div class="kx-foot"><span>${esc(s.site || '')}</span><span class="ok">System online</span></div></div>`;
    }

    function enrollView(s) {
        const r = s.roster || {}, list = r.employees || [], c = r.counts || {};
        return `
            <div class="kx-card"><div class="kx-h">${fp.replace('<svg', '<svg width="18" height="18"')} ${T.capture}</div>
                <div class="kx-scan"><div class="kx-fp">${fp}</div><div class="big">CHOOSE A NAME FIRST</div>
                <div class="sub">Details come from the web — only the finger is needed here</div></div></div>
            <div class="kx-card"><div class="kx-h">${T.workers}</div>
                <div class="kx-kpis" style="grid-template-columns:1fr 1fr">
                    <div style="--c:#f5b223"><b>${c.pending ?? 0}</b><span>${T.noFp}</span></div>
                    <div style="--c:#34c05a"><b>${c.enrolled ?? 0}</b><span>${T.fpOn}</span></div>
                </div>
                <div class="kx-scroll"><div class="kx-roster">${list.slice(0, 6).map(e => `<div><div><b>${esc(e.name)}${e.is_new ? '<em>NEW</em>' : ''}</b>
                    <small>${esc(e.position)} · ${esc(e.employment_label || '')}${e.shift?.name ? ' · ' + esc(e.shift.name) : ''}</small></div>
                    <span class="kx-badge ${e.enrolled ? 'have' : 'need'}">${e.enrolled ? T.have : T.need}</span></div>`).join('') || `<div class="kx-empty">No workers at this site</div>`}</div></div>
            </div>`;
    }

    function payrollView() {
        return `<div class="kx-card" style="grid-column:1/-1"><div class="kx-lock">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>
            <b>MY PAYROLL</b><span>Each worker sees their own pay here after scanning their finger.<br>It is not shown in the monitor.</span></div></div>`;
    }

    function render() {
        const s = data && data.screen;
        if (!s) { screen.innerHTML = ''; return; }
        if (!s.on) {
            const k = s.kiosk;
            screen.innerHTML = `<div class="kx-off">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M12 3v8M6.3 6.3a8 8 0 1011.4 0"/></svg>
                <b>${T.off}</b>
                <p><strong>${esc(k.name)}</strong> ${k.seen_at ? 'has sent no heartbeat since <strong>' + esc(k.seen_at) + '</strong> (' + esc(k.seen) + ').' : 'has never reported to the web.'}</p>
                <p>Its screen shows here again on its own once it is switched on and online.</p></div>`;
            return;
        }
        screen.innerHTML = `<div class="kx">
            <div class="kx-top"><span class="kx-logo"><img src="${esc(logo)}" alt=""></span>
                <div class="kx-co"><b>${esc(company)}</b><small>${esc(s.site || '')} — Attendance Kiosk</small></div>
                <span class="sp"></span><span class="kx-lang"><span class="on">EN</span><span>TL</span></span>
                <span class="kx-sess">${esc(s.session)}</span>
                <span class="kx-clock"><b data-kx-clock></b><small data-kx-date></small></span></div>
            <div class="kx-strip"><span class="lbl">${T.youAre}</span><span class="kx-site">${esc(s.site || '—')}</span><span class="sp"></span>
                <span class="ok">✓ ${esc(s.site || '')}</span><span class="saved">${T.saved}</span><span class="saved kx-onair">● LIVE</span></div>
            <div class="kx-tabs">${[['attendance', T.attendance], ['enroll', T.enroll], ['payroll', T.payroll]].map(([k, l]) =>
                `<button type="button" data-kx-tab="${k}" class="${tab === k ? 'on' : ''}">${l}</button>`).join('')}</div>
            <div class="kx-body">${tab === 'enroll' ? enrollView(s) : tab === 'payroll' ? payrollView() : attendanceView(s)}</div>
            <span class="kx-viewonly">${T.viewOnly}</span></div>`;
        clock();
    }

    function renderFacts() {
        const s = data && data.screen, k = s && s.kiosk;
        if (!k) { facts.innerHTML = ''; return; }
        const b = s.board || {};
        const cell = (label, value, cls) => `<div><span>${label}</span><b class="${cls || ''}">${value}</b></div>`;
        facts.innerHTML =
            cell(@json(__('Status')), k.state === 'ok' ? @json(__('Online')) : k.state === 'late' ? @json(__('Online · late')) : @json(__('Off')), k.state) +
            cell(@json(__('Last heartbeat')), k.seen ? esc(k.seen) : @json(__('Never'))) +
            cell(@json(__('Settings reached it')), k.read ? esc(k.read) : '—') +
            cell(@json(__('Scanned today')), s.on ? (b.total ?? 0) + ' ' + @json(__('workers')) : '—');
    }

    // Every time in and time out on today's board, newest first.
    const logBox = box.querySelector('[data-km-log]'), logCount = box.querySelector('[data-km-count]');
    const minutes = v => { const m = String(v || '').match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AP]M)$/i); if (!m) return -1; let h = +m[1] % 12; if (m[3].toUpperCase() === 'PM') h += 12; return h * 60 + +m[2]; };
    function renderLog() {
        if (!logBox) return;
        const s = data && data.screen;
        if (!s || !s.on) { logBox.innerHTML = '<li class="none">' + @json(__('Nothing to show while the kiosk is off.')) + '</li>'; logCount.textContent = ''; return; }
        const ev = [];
        ((s.board || {}).records || []).forEach(r => (r.entries || []).forEach(e => {
            if (e.in) ev.push({ t: e.in, who: r.name, type: 'in', ses: e.session });
            if (e.out) ev.push({ t: e.out, who: r.name, type: 'out', ses: e.session, auto: e.auto });
        }));
        recent.forEach(e => ev.push({ t: String(e.time || '').replace(/:\d{2}(\s*[AP]M)$/i, '$1'), who: e.name || @json(__('Unknown finger')), type: 'rej', label: e.kind === 'unknown' ? 'NOT RECOGNISED' : (WARN[e.code] || 'REJECTED'), fresh: true }));
        ev.sort((a, b) => minutes(b.t) - minutes(a.t));
        logCount.textContent = ev.length;
        logBox.innerHTML = ev.length ? ev.map(e => `<li class="${e.fresh ? 'fresh' : ''}"><time>${esc(e.t)}</time><b>${esc(e.who)}</b><span class="${e.type}">${e.label ? esc(e.label) : (e.type === 'in' ? 'TIME IN' : 'TIME OUT') + ' · ' + esc(e.ses) + (e.auto ? ' · AUTO' : '')}</span></li>`).join('')
                                     : '<li class="none">' + @json(__('No scans yet today.')) + '</li>';
    }

    function renderPicks() {
        (data.kiosks || []).forEach(k => {
            const b = box.querySelector('[data-km-kiosk="' + k.id + '"]');
            if (!b) return;
            b.classList.toggle('on', data.screen && data.screen.kiosk && data.screen.kiosk.id === k.id);
            b.querySelector('i').className = k.state;
        });
    }

    async function load() {
        if (!data) {
            checked.textContent = @json(__('Connecting…'));
            screen.innerHTML = '<div class="kx-off"><b style="font-size:22px">' + @json(__('CONNECTING TO THE KIOSK…')) + '</b></div>';
        }
        try {
            const next = await ask(false);
            data = next;
            current = data.screen && data.screen.kiosk ? data.screen.kiosk.id : current;
            checked.textContent = '● ' + @json(__('Live')) + ' · ' + data.checked;
            take(next);
            render(); renderFacts(); renderLog(); renderPicks(); fit();
        } catch (e) {
            checked.textContent = @json(__('Could not reach the server — retrying'));
        }
    }

    async function ask(light) {
        const url = new URL(box.dataset.url, location.href);
        if (current) url.searchParams.set('kiosk', current);
        url.searchParams.set('since', seq === null ? -1 : seq);
        if (light) url.searchParams.set('light', 1);
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(res.status);
        return res.json();
    }

    // New scans from the feed: the newest one is shown; a recorded one means
    // the board has changed, so it is read again at once.
    function take(r) {
        const events = r.events || [];
        if (r.seq !== undefined) seq = r.seq;
        if (!events.length) return false;
        events.filter(e => !['in', 'out', 'scan'].includes(e.kind)).forEach(e => recent.push(e));
        recent = recent.slice(-20);
        flash = events[events.length - 1];
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => { flash = null; render(); }, FLASH_MS);
        return events.some(e => e.kind === 'in' || e.kind === 'out');
    }

    // The quick poll, every two seconds: on or off, and anybody scanning.
    async function tick() {
        if (visible() && data) {
            try {
                const r = await ask(true);
                const wasOn = !!(data.screen && data.screen.on);
                data.kiosks = r.kiosks;
                checked.textContent = '● ' + @json(__('Live')) + ' · ' + r.checked;
                const recorded = take(r);
                if (recorded || !!(r.screen && r.screen.on) !== wasOn) { await load(); }
                else if ((r.events || []).length) { render(); renderLog(); renderPicks(); }
                else { renderPicks(); }
            } catch (e) { /* the slow poll says so */ }
        }
        quick = setTimeout(tick, 2000);
    }

    const visible = () => !box.closest('.ss-sec').hidden && !document.hidden;
    function schedule() { clearTimeout(timer); timer = setTimeout(async () => { if (visible()) await load(); schedule(); }, 10000); }

    box.addEventListener('click', e => {
        const k = e.target.closest('[data-km-kiosk]');
        if (k) { current = Number(k.dataset.kmKiosk); tab = 'attendance'; seq = null; flash = null; recent = []; load(); return; }
        const t = e.target.closest('[data-kx-tab]');
        if (t) { tab = t.dataset.kxTab; render(); return; }
        if (e.target.closest('[data-km-refresh]')) load();
        if (e.target.closest('[data-km-full]')) {
            const f = box.querySelector('.km-frame');
            if (document.fullscreenElement) document.exitFullscreen();
            else if (f && f.requestFullscreen) f.requestFullscreen().then(() => setTimeout(fit, 50)).catch(() => {});
        }
    });
    document.querySelectorAll('.ss-si[data-s="kiosk"]').forEach(b => b.addEventListener('click', () => setTimeout(() => { fit(); load(); }, 0)));

    if (visible()) load();
    schedule();
    tick();
})();
</script>
@endpush
