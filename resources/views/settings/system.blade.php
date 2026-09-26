@extends('layouts')

@section('page_title', 'System Settings')

{{-- System Settings as one page, laid out after Michael's jeyanco-settings.html
     (2026-09-26): a section nav on the left; Company with a live preview;
     Appearance, Security and Kiosks; and the Audit Log, which used to be a
     page of its own. Every field saves through the section's own action, as
     before (SystemSettingsController::updateAll), from one save bar that
     shows only while something is unsaved. --}}

@php
    use Illuminate\Support\Str;

    $name    = old('company_name', $system->company_name);
    $tagline = old('company_tagline', $system->company_tagline);
    $address = old('company_address', $system->company_address);
    $parts   = preg_split('/\s+/', trim((string) $name), 2);
    // The sign-in page keeps the built-in mark until a logo is uploaded.
    $signinMark = $system->logo_path ? $system->logoUrl() : asset('images/logo-mark.png');

    $savedTheme = $system->default_theme ?: 'dark';
    $theme      = old('default_theme', $savedTheme);
    $themeNow   = match ($savedTheme) { 'system' => 'Follows the device setting', 'light' => 'Opens in the light theme', default => 'Opens in the dark theme' };

    $num = fn ($key) => (int) old($key, $system->{$key});
    $security = [
        ['session_timeout_minutes', 'Session timeout', 'How long someone can be idle before they’re signed out.', 'min', 5, 1440, 'Allowed 5 min – 24 h', [30 => '30 min', 60 => '1 h', 120 => '2 h', 480 => '8 h']],
        ['password_min_length', 'Minimum password length', 'Checked when a password is set or reset. Passwords already on file aren’t re-checked, so raising this locks nobody out.', 'characters', 8, 64, 'Allowed 8 – 64 · 12 or more is stronger', []],
        ['max_login_attempts', 'Failed sign-ins before lockout', 'Counted per username and computer together, so one person’s typos don’t lock out everyone else.', 'tries', 3, 20, 'Allowed 3 – 20 · fewer than 3 locks people out for a typo', []],
        ['lockout_seconds', 'Lockout length', 'How long that username waits before it can try again from that computer.', 'sec', 30, 3600, 'Allowed 30 s – 1 h', [30 => '30 s', 60 => '1 min', 300 => '5 min', 900 => '15 min']],
    ];

    $savedMode  = $system->kioskMode();
    $mode       = old('kiosk_attendance_mode', $savedMode);
    $savedGuard = (int) ($system->kiosk_repeat_guard_seconds ?? 180);
    $savedIdle  = (int) ($system->kiosk_idle_return_seconds ?? 60);
    $guard      = (int) old('kiosk_repeat_guard_seconds', $savedGuard);
    $idle       = (int) old('kiosk_idle_return_seconds', $savedIdle);
    // The saved value is always one of the choices, even one set some other way.
    $guardOptions = collect([60, 120, 180, 300])->push($savedGuard)->unique()->sort()->values();
    $idleOptions  = collect([30, 60, 120])->push($savedIdle)->unique()->sort()->values();
    $span = fn (int $s) => $s % 60 === 0 ? ($s / 60) . ' min' : ($s < 60 ? $s . ' s' : intdiv($s, 60) . ' min ' . ($s % 60) . ' s');

    $savedAt = $lastSaved?->created_at ?? ($system->exists ? $system->updated_at : null);

    // After a refused save, open the section the first problem is in.
    $fieldSection = [
        'company_name' => 'company', 'company_tagline' => 'company', 'company_address' => 'company', 'logo' => 'company',
        'default_theme' => 'appearance',
        'session_timeout_minutes' => 'security', 'password_min_length' => 'security', 'max_login_attempts' => 'security', 'lockout_seconds' => 'security',
        'kiosk_attendance_mode' => 'kiosk', 'kiosk_repeat_guard_seconds' => 'kiosk', 'kiosk_idle_return_seconds' => 'kiosk',
    ];
    foreach ($errors->keys() as $k) {
        if (isset($fieldSection[$k])) { $section = $fieldSection[$k]; break; }
    }

    $nav = [
        'ORGANIZATION' => [
            'company' => ['Company', '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>'],
        ],
        'SYSTEM' => [
            'appearance' => ['Appearance', '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 000 18z" fill="currentColor"/>'],
            'security'   => ['Security', '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>'],
            'kiosk'      => ['Kiosks', '<path d="M12 11v3a8 8 0 01-1.5 4.7M8.5 7.2A5 5 0 0117 11v1.5M7 11a5 5 0 01.3-1.8M16.9 16a13 13 0 01-.9 3M5 16.5A12 12 0 006 11a6 6 0 011.2-3.6M12 3a8 8 0 018 8v1M4 11a8 8 0 012-5.3"/>'],
            'audit'      => ['Audit logs', '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>'],
        ],
    ];
    $svg = fn (string $path, string $w = '1.8') => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" stroke-linecap="round" aria-hidden="true">' . $path . '</svg>';

    $A = $audit;
    $auditQuery = array_filter([
        'q' => $A['q'] !== '' ? $A['q'] : null, 'module' => $A['selected']['module'] ?: null,
        'action' => $A['selected']['action'] ?: null, 'person' => $A['selected']['person'] ?: null,
        'range' => in_array($A['range'], ['today', '30', 'all'], true) ? $A['range'] : null, 'quick' => $A['quick'] !== '' ? $A['quick'] : null,
        'subject_type' => request('subject_type'), 'subject_id' => request('subject_id'),
        'from' => $A['range'] === 'custom' ? request('from') : null, 'to' => $A['range'] === 'custom' ? request('to') : null,
    ]);
@endphp

@push('styles')
<style>
.ss { display: flex; flex-direction: column; gap: 16px; }
html[data-bs-theme] .main-content .ss > .page-head { margin-bottom: -4px !important; }
.ss-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-xs); }
.ss-saved { display: flex; gap: 8px; align-items: center; font-size: 12.5px; color: var(--text-secondary); }
.ss-saved svg { width: 15px; height: 15px; }
.ss-saved b { color: var(--text-primary); font-weight: 600; }
.ss-btn { border: 1px solid var(--border-md); background: var(--surface); border-radius: 10px; height: 38px; padding: 0 14px; font-weight: 600; font-size: 13px; display: inline-flex; gap: 8px; align-items: center; cursor: pointer; color: var(--text-primary); white-space: nowrap; text-decoration: none; }
.ss-btn svg { width: 16px; height: 16px; flex: none; }
.ss-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.ss-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.ss-btn.pri:hover { color: #fff; filter: brightness(1.06); }
.ss-btn:disabled { opacity: .6; cursor: default; }
.ss-alert { display: flex; gap: 10px; padding: 12px 14px; border-radius: 12px; background: var(--danger-soft); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 30%, transparent); font-size: 13px; }
.ss-alert ul { margin: 4px 0 0; padding-left: 18px; }

.ss-set { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 18px; align-items: start; }

/* Section nav */
.ss-nav { position: sticky; top: calc(var(--topbar-height, 60px) + 16px); padding: 10px; display: flex; flex-direction: column; gap: 2px; }
.ss-nav h6 { margin: 10px 10px 6px; font-size: 10px; letter-spacing: .14em; color: var(--text-muted); font-weight: 700; }
.ss-nav h6:first-child { margin-top: 4px; }
.ss-si { display: flex; gap: 10px; align-items: center; padding: 0 10px; height: 40px; border-radius: 9px; border: 0; background: none; color: var(--text-secondary); font-size: 13.5px; font-weight: 500; cursor: pointer; text-align: left; width: 100%; }
.ss-si svg { width: 17px; height: 17px; flex: none; }
.ss-si:hover { background: var(--bg-subtle); color: var(--text-primary); }
.ss-si.on { background: var(--brand-subtle); color: var(--brand); font-weight: 700; }
.ss-si .dot { margin-left: auto; width: 8px; height: 8px; border-radius: 50%; background: var(--warning); }
.ss-si .bd { margin-left: auto; font-size: 10.5px; font-weight: 700; background: var(--bg-subtle); color: var(--text-muted); border-radius: 999px; padding: 1px 7px; }
.ss-si .dot:not([hidden]) + .bd { margin-left: 6px; }
.ss-si:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }
.ss-nav .hint { margin: 10px 6px 2px; padding: 10px 10px 4px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
.ss-nav .hint a { color: var(--brand); font-weight: 600; text-decoration: none; }

/* Sections */
.ss-sec { display: flex; flex-direction: column; gap: 14px; }
.ss-sec[hidden] { display: none; }
.ss-sec-h { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.ss-sec-h h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--text-primary); }
.ss-sec-h p { margin: 3px 0 0; color: var(--text-secondary); font-size: 13px; }
.ss-split { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 14px; align-items: start; }
@media (max-width: 1300px) { .ss-split { grid-template-columns: minmax(0, 1fr); } }
.ss-grp { padding: 4px 18px; }
.ss-grp > h4 { margin: 14px 0 2px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; }
.ss-row { display: grid; grid-template-columns: minmax(0, 210px) minmax(0, 1fr); gap: 16px; align-items: start; padding: 14px 0; border-bottom: 1px solid var(--border); }
.ss-row:last-child { border-bottom: 0; }
.ss-row .lb b { display: block; font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
.ss-row .lb small { display: block; color: var(--text-secondary); font-size: 12px; margin-top: 3px; line-height: 1.45; }
.ss-opt { font-size: 10.5px; font-weight: 700; color: var(--text-muted); border: 1px solid var(--border-md); border-radius: 6px; padding: 1px 6px; margin-left: 6px; vertical-align: 1px; }
.ss-inp { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
html[data-bs-theme] .ss .ss-inp input[type=text],
html[data-bs-theme] .ss .ss-inp select,
html[data-bs-theme] .ss .ss-num input {
    border: 1px solid var(--border-md) !important; background: var(--bg-subtle) !important; border-radius: 10px !important;
    height: 40px; padding: 0 12px; outline: none; color: var(--text-primary) !important; font-size: 13.5px; width: 100%; box-shadow: none !important;
}
html[data-bs-theme] .ss .ss-inp input:focus,
html[data-bs-theme] .ss .ss-inp select:focus,
html[data-bs-theme] .ss .ss-num input:focus { border-color: var(--brand) !important; box-shadow: 0 0 0 3px var(--brand-subtle) !important; }
html[data-bs-theme] .ss .dirty input, html[data-bs-theme] .ss input.dirty, html[data-bs-theme] .ss select.dirty { border-color: var(--warning) !important; }
.ss-inp .cnt { align-self: flex-end; font-size: 11px; color: var(--text-muted); font-family: 'JetBrains Mono', ui-monospace, monospace; }
.ss-inp.short { max-width: 240px; }
.ss-err { font-size: 12px; color: var(--danger); font-weight: 600; }
.ss-help { font-size: 11.5px; color: var(--text-muted); }

/* Numbers with a unit, and quick picks beside them */
.ss-numrow { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ss-num { display: inline-flex; align-items: center; gap: 8px; }
html[data-bs-theme] .ss .ss-num input { width: 110px; font-family: 'JetBrains Mono', ui-monospace, monospace; }
.ss-num span { font-size: 12.5px; color: var(--text-secondary); }
.ss-picks { display: inline-flex; gap: 4px; flex-wrap: wrap; }
.ss-picks button { border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); font-size: 12px; font-weight: 600; height: 28px; padding: 0 9px; border-radius: 7px; cursor: pointer; }
.ss-picks button:hover { border-color: var(--brand); color: var(--text-primary); }
.ss-picks button.on { background: var(--brand-subtle); border-color: transparent; color: var(--brand); }

/* Segmented choices (radio buttons underneath) */
.ss-seg { align-self: flex-start; max-width: 100%; display: inline-flex; border: 1px solid var(--border-md); border-radius: 10px; overflow: hidden; background: var(--bg-subtle); flex-wrap: wrap; }
.ss-seg label { margin: 0; cursor: pointer; }
.ss-seg input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
.ss-seg span { padding: 0 14px; height: 38px; color: var(--text-secondary); font-size: 13px; font-weight: 600; display: flex; gap: 7px; align-items: center; }
.ss-seg span svg { width: 15px; height: 15px; }
.ss-seg input:checked + span { background: var(--brand); color: #fff; }
.ss-seg input:focus-visible + span { outline: 2px solid var(--brand); outline-offset: -2px; }
.ss-seg.dirty { border-color: var(--warning); }

/* Switch */
.ss-tg { position: relative; width: 42px; height: 24px; flex: none; margin: 0; }
.ss-tg input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; z-index: 1; }
.ss-tg span { position: absolute; inset: 0; border-radius: 999px; background: var(--bg-subtle); border: 1px solid var(--border-md); transition: background .2s; }
.ss-tg span::after { content: ""; position: absolute; top: 2px; left: 2px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .2s; }
.ss-tg input:checked + span { background: var(--brand); border-color: var(--brand); }
.ss-tg input:checked + span::after { transform: translateX(18px); }
.ss-tg input:focus-visible + span { outline: 2px solid var(--brand); outline-offset: 2px; }
.ss-tgrow { display: flex; gap: 10px; align-items: center; font-size: 12.5px; color: var(--text-secondary); }

/* Logo */
.ss-logo { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
.ss-logo-prev { width: 64px; height: 64px; border-radius: 50%; background: var(--bg-subtle); border: 2px solid var(--border-md); display: grid; place-items: center; flex: none; overflow: hidden; position: relative; }
.ss-logo-prev img { width: 100%; height: 100%; object-fit: contain; }
.ss-drop { flex: 1; min-width: 220px; border: 1.5px dashed var(--border-md); border-radius: 12px; padding: 12px 14px; display: flex; gap: 10px; align-items: center; cursor: pointer; color: var(--text-secondary); font-size: 12.5px; margin: 0; transition: border-color .15s, background .15s; }
.ss-drop:hover, .ss-drop.over { border-color: var(--brand); background: var(--brand-subtle); }
.ss-drop.dirty { border-color: var(--warning); }
.ss-drop b { color: var(--text-primary); }
.ss-drop u { color: var(--brand); text-decoration: none; font-weight: 600; }
.ss-drop svg { width: 20px; height: 20px; flex: none; }

/* Live preview */
.ss-prev { position: sticky; top: calc(var(--topbar-height, 60px) + 16px); padding: 14px; display: flex; flex-direction: column; gap: 12px; }
@media (max-width: 1300px) { .ss-prev { position: static; } }
.ss-prev h4 { margin: 0; font-size: 13px; font-weight: 700; display: flex; gap: 8px; align-items: center; color: var(--text-primary); }
.ss-prev h4 svg { width: 16px; height: 16px; color: var(--brand); }
.ss-prev h4 .st { margin-left: auto; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px; background: var(--success-soft); color: var(--success); }
.ss-prev h4 .st.warn { background: var(--warning-soft); color: var(--warning); }
.ss-ptabs { display: flex; gap: 4px; background: var(--bg-subtle); border-radius: 9px; padding: 3px; }
.ss-ptabs button { flex: 1; border: 0; background: none; color: var(--text-secondary); font-size: 12px; font-weight: 600; height: 28px; border-radius: 7px; cursor: pointer; }
.ss-ptabs button.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.15); }
.ss-prev .note { font-size: 11.5px; color: var(--text-muted); margin: 0; }
.paper { background: #fff; border-radius: 8px; border: 1px solid #EAECF0; box-shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 20px rgba(16,24,40,.10); padding: 14px 16px 16px; color: #101828; }
.paper-h { display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 2px solid #101828; }
.paper-h img { width: 38px; height: 38px; object-fit: contain; flex: none; }
.paper-co { font-size: 13px; font-weight: 800; letter-spacing: .02em; line-height: 1.15; word-break: break-word; }
.paper-sub { font-size: 9.5px; color: #475467; margin-top: 2px; }
.paper-meta { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; font-size: 9px; color: #475467; }
.paper-meta b { font-size: 10.5px; letter-spacing: .12em; color: #101828; }
.paper-l { height: 6px; border-radius: 3px; background: #F2F4F7; margin-top: 8px; }
.paper-tot { display: flex; justify-content: space-between; margin-top: 11px; padding-top: 8px; border-top: 1px solid #EAECF0; font-size: 10px; font-weight: 700; }
.rail { border-radius: 10px; background: linear-gradient(135deg, #123566, #0d2a4f); padding: 14px; display: flex; align-items: center; gap: 10px; }
.rail img { width: 34px; height: 34px; object-fit: contain; filter: drop-shadow(0 1px 3px rgba(0,0,0,.45)); flex: none; }
.rail .l1 { font-size: 15px; font-weight: 800; color: #fff; letter-spacing: .06em; line-height: 1.1; text-transform: uppercase; }
.rail .l2 { font-size: 9px; font-weight: 600; letter-spacing: .3em; color: #a9bbd9; margin-top: 3px; text-transform: uppercase; }
.signin { border-radius: 10px; border: 1px solid var(--border); overflow: hidden; background: #0a1421; }
.signin-tab { display: flex; align-items: center; gap: 7px; padding: 8px 10px; background: #141a23; border-bottom: 1px solid #223049; font-size: 11px; color: #c9d4e3; min-width: 0; }
.signin-tab img { width: 14px; height: 14px; object-fit: contain; flex: none; }
.signin-tab span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.signin-body { display: flex; align-items: center; gap: 12px; padding: 14px; background: radial-gradient(120% 120% at 0% 0%, #123566 0%, #0a1421 75%); }
.signin-body img { width: 36px; height: 36px; object-fit: contain; flex: none; filter: drop-shadow(0 1px 3px rgba(0,0,0,.45)); }
.signin-body .f { flex: 1; display: flex; flex-direction: column; gap: 5px; }
.signin-body i { display: block; height: 12px; border-radius: 3px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); }
.signin-body i.b { background: #1668DC; border-color: #1668DC; width: 55%; }

/* Lists: accounts check, kiosks, the log */
.ss-list-h { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.ss-list-h h4 { margin: 0; font-size: 13.5px; font-weight: 700; color: var(--text-primary); }
.ss-list-h small { color: var(--text-muted); font-size: 12px; }
.ss-li { display: flex; gap: 12px; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.ss-li:last-child { border-bottom: 0; }
.ss-li .ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none; background: var(--bg-subtle); color: var(--text-secondary); }
.ss-li .ic svg { width: 17px; height: 17px; }
.ss-li .ic.ok { background: var(--success-soft); color: var(--success); }
.ss-li .ic.warn { background: var(--warning-soft); color: var(--warning); }
.ss-li .ic.danger { background: var(--danger-soft); color: var(--danger); }
.ss-li > div { flex: 1; min-width: 0; }
.ss-li b { display: block; font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
.ss-li small { display: block; color: var(--text-secondary); font-size: 12px; }
.ss-li code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: var(--text-muted); font-weight: 500; margin-left: 6px; }
.ss-li .rt { flex: none; text-align: right; font-size: 12px; color: var(--text-muted); max-width: 240px; }
.ss-li .rt b { display: inline; font-size: 12px; }
.ss-lnk { color: var(--brand); font-size: 12.5px; font-weight: 600; text-decoration: none; white-space: nowrap; }
.ss-lnk:hover { text-decoration: underline; }
.ss-empty { padding: 28px 16px; text-align: center; color: var(--text-muted); font-size: 13px; margin: 0; }

.ss-more > summary { list-style: none; cursor: pointer; padding: 12px 18px; display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 700; color: var(--text-primary); }
.ss-more > summary::-webkit-details-marker { display: none; }
.ss-more > summary small { color: var(--text-muted); font-weight: 500; font-size: 12px; }
.ss-more > summary svg { width: 16px; height: 16px; margin-left: auto; color: var(--text-muted); transition: transform .2s; }
.ss-more[open] > summary svg { transform: rotate(180deg); }
.ss-more[open] > summary { border-bottom: 1px solid var(--border); }
.kr { padding: 14px 18px 4px; }
.kr-row { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 14px; align-items: center; margin-bottom: 4px; }
.kr-name { font-size: 12.5px; font-weight: 700; color: var(--text-primary); }
.kr-name small { display: block; font-size: 11px; font-weight: 500; color: var(--text-muted); margin-top: 1px; }
.kr-track { position: relative; height: 34px; border-radius: 7px; background: var(--bg-subtle); border: 1px solid var(--border); overflow: hidden; }
.kr-seg { position: absolute; top: 0; bottom: 0; display: grid; place-items: center; font-size: 10.5px; font-weight: 700; white-space: nowrap; overflow: hidden; }
.kr-seg.early { background: repeating-linear-gradient(90deg, transparent 0 6px, color-mix(in srgb, var(--text-muted) 12%, transparent) 6px 7px); color: var(--text-muted); font-weight: 600; }
.kr-seg.work { background: color-mix(in srgb, var(--brand) 16%, transparent); color: var(--brand); border-left: 1px solid color-mix(in srgb, var(--brand) 35%, transparent); border-right: 1px solid color-mix(in srgb, var(--brand) 35%, transparent); }
.kr-seg.lunch { background: repeating-linear-gradient(45deg, color-mix(in srgb, var(--warning) 22%, transparent) 0 4px, transparent 4px 8px); }
.kr-seg.ot { background: color-mix(in srgb, var(--warning) 10%, transparent); color: var(--warning); }
.kr-cut { position: absolute; top: -3px; bottom: -3px; border-left: 2px dashed var(--warning); }
.kr-ticks { position: relative; height: 16px; margin: 0 0 8px 126px; font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--text-muted); }
.kr-ticks span { position: absolute; transform: translateX(-50%); white-space: nowrap; }
.kr-ticks span.first { transform: none; }
.kr-ticks span.cut { color: var(--warning); font-weight: 700; }
.kr-legend { display: flex; gap: 8px; align-items: center; margin: -2px 0 12px 126px; font-size: 11.5px; color: var(--text-muted); }
.kr-legend i { width: 0; height: 14px; border-left: 2px dashed var(--warning); flex: none; }
.kd-scroll { overflow-x: auto; }
.kd { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 640px; }
.kd th { text-align: left; font-size: 10.5px; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted); font-weight: 600; padding: 8px 18px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); background: var(--bg-subtle); }
.kd td { padding: 9px 18px; border-bottom: 1px solid var(--border); color: var(--text-secondary); vertical-align: top; }
.kd tr:last-child td { border-bottom: none; }
.kd td.w { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-primary); white-space: nowrap; }
.kd .rec { display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .03em; margin-right: 6px; white-space: nowrap; }
.kd .rec.in { background: var(--success-soft); color: var(--success); }
.kd .rec.out { background: var(--danger-soft); color: var(--danger); }
.kd .rec.auto { background: var(--warning-soft); color: var(--warning); }
.kd .rec.none { background: var(--bg-subtle); color: var(--text-muted); border: 1px solid var(--border); }

/* The log */
.ss-tools { display: flex; gap: 8px 6px; flex-wrap: wrap; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.ss-sel { display: flex; align-items: center; gap: 8px; border: 1px solid var(--border-md); background: var(--surface); border-radius: 10px; padding: 0 10px; height: 38px; margin: 0; }
.ss-sel svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
html[data-bs-theme] .ss .ss-sel input, html[data-bs-theme] .ss .ss-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none; color: var(--text-primary); font-size: 13px; height: 36px; padding: 0; min-width: 0;
}
html[data-bs-theme] .ss .ss-sel input.q { width: 160px; }
html[data-bs-theme] .ss .ss-sel select { max-width: 132px; text-overflow: ellipsis; }
.ss-tools .sp { flex: 1; }
.ss-chip { display: inline-flex; gap: 8px; align-items: center; font-size: 12px; font-weight: 600; background: var(--brand-subtle); color: var(--brand); border-radius: 999px; padding: 4px 10px; }
.ss-chip a { color: inherit; text-decoration: none; font-weight: 800; }
.ss-log { display: grid; grid-template-columns: 136px minmax(0, 1fr) auto; gap: 12px; padding: 11px 18px; border-bottom: 1px solid var(--border); font-size: 13px; align-items: center; color: var(--text-primary); }
.ss-log:last-child { border-bottom: 0; }
.ss-log time { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12px; color: var(--text-muted); }
.ss-log .ds { min-width: 0; overflow-wrap: anywhere; }
.ss-log small { display: block; color: var(--text-muted); font-size: 12px; margin-top: 1px; }
.ss-log small .ok { color: var(--success); } .ss-log small .warn { color: var(--warning); } .ss-log small .danger { color: var(--danger); }
.ss-log small a { color: var(--brand); text-decoration: none; font-weight: 600; }
.ss-pill { font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 3px 9px; background: var(--bg-subtle); color: var(--text-secondary); white-space: nowrap; }
.ss-pager { padding: 10px 18px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: 12.5px; color: var(--text-muted); }
.ss-pager nav { margin: 0; flex: 1; }
.ss-pager p { margin: 0; }
.ss-pager .pagination { margin: 0; }

/* The save bar: out of sight until something is unsaved. */
.ss-bar { position: fixed; left: 50%; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); transform: translate(-50%, 160%); display: flex; gap: 10px; align-items: center; background: var(--text-primary); color: var(--surface); border-radius: 14px; padding: 8px 8px 8px 16px; box-shadow: 0 20px 50px rgba(0,0,0,.35); z-index: 1050; transition: transform .35s cubic-bezier(.2, .8, .2, 1), visibility 0s linear .35s; max-width: calc(100% - 32px); visibility: hidden; }
.ss-bar.show { transform: translate(-50%, 0); visibility: visible; transition: transform .35s cubic-bezier(.2, .8, .2, 1), visibility 0s; }
.ss-bar > span { font-size: 13px; font-weight: 600; display: flex; gap: 8px; align-items: center; min-width: 0; }
.ss-bar > span i { width: 8px; height: 8px; border-radius: 50%; background: var(--warning); flex: none; }
.ss-bar > span small { font-weight: 500; opacity: .75; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ss-bar .ss-btn { height: 34px; }
.ss-bar .ss-btn.ghost { background: transparent; border-color: transparent; color: var(--surface); opacity: .75; }
.ss-bar .ss-btn.ghost:hover { opacity: 1; }
@media (prefers-reduced-motion: reduce) { .ss-bar, .ss-bar.show { transition: none; } }
</style>
@endpush

@section('content')
<div class="ss">
    <x-page-header :title="__('System Settings')">
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
                    <button type="button" class="ss-si {{ $section === $key ? 'on' : '' }}" data-s="{{ $key }}" @if($section === $key) aria-current="page" @endif>
                        {!! $svg($path) !!}{{ __($label) }}
                        <i class="dot" data-dirty-dot="{{ $key }}" title="{{ __('Unsaved changes') }}" hidden></i>
                        @if($key === 'audit')<span class="bd" title="{{ __('Entries in this view') }}">{{ number_format($A['logs']->total()) }}</span>@endif
                    </button>
                @endforeach
            @endforeach
            <p class="hint">{{ __('Pay rates, shifts and holidays live in') }} <a href="{{ route('settings.index') }}">{{ __('Payroll Settings') }}</a>. {{ __('Accounts and roles are in') }} <a href="{{ route('users-roles.index') }}">{{ __('Users & Roles') }}</a>.</p>
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
                            <div class="ss-row">
                                <div class="lb"><b>{{ __('Company name') }}</b><small>{{ __('The first word is the big line in the sidebar; the rest goes under it. Printed exactly as typed — capitals included.') }}</small></div>
                                <div class="ss-inp">
                                    <input type="text" id="company_name" name="company_name" value="{{ $name }}" maxlength="120" required
                                           data-track data-saved="{{ $system->company_name }}" data-label="{{ __('Company name') }}">
                                    <span class="cnt" data-cnt-for="company_name">{{ mb_strlen((string) $name) }}/120</span>
                                    @error('company_name')<span class="ss-err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="ss-row">
                                <div class="lb"><b>{{ __('Line under the name') }}</b><small>{{ __('Printed under the name on the batch payslip and the payroll receipt.') }}</small></div>
                                <div class="ss-inp">
                                    <input type="text" id="company_tagline" name="company_tagline" value="{{ $tagline }}" maxlength="160" required
                                           data-track data-saved="{{ $system->company_tagline }}" data-label="{{ __('Line under the name') }}">
                                    <span class="cnt" data-cnt-for="company_tagline">{{ mb_strlen((string) $tagline) }}/160</span>
                                    @error('company_tagline')<span class="ss-err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="ss-row">
                                <div class="lb"><b>{{ __('Address') }}<span class="ss-opt">{{ __('Optional') }}</span></b><small>{{ __('Printed on the batch payslip only, and only once it’s set.') }}</small></div>
                                <div class="ss-inp">
                                    <input type="text" id="company_address" name="company_address" value="{{ $address }}" maxlength="255" placeholder="{{ __('Street, barangay, city') }}"
                                           data-track data-saved="{{ $system->company_address }}" data-label="{{ __('Address') }}">
                                    <span class="cnt" data-cnt-for="company_address">{{ mb_strlen((string) $address) }}/255</span>
                                    @error('company_address')<span class="ss-err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <h4>{{ __('Logo') }}</h4>
                            <div class="ss-row">
                                <div class="lb"><b>{{ __('Company logo') }}</b><small>{{ __('Replaces the logo on payslips, the receipt, the sidebar and the sign-in page. Up to 2 MB; a square image with a transparent background looks best.') }}</small></div>
                                <div class="ss-inp">
                                    <div class="ss-logo">
                                        <div class="ss-logo-prev"><img src="{{ $system->logoUrl() }}" alt="{{ __('Current logo') }}" data-logo></div>
                                        <label class="ss-drop" for="logo" data-drop>
                                            {!! $svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>') !!}
                                            <span data-drop-text><b>{{ __('Drop an image here') }}</b> {{ __('or') }} <u>{{ __('browse') }}</u></span>
                                        </label>
                                        <input type="file" id="logo" name="logo" accept="image/*" hidden data-track data-saved="" data-label="{{ __('Logo') }}">
                                    </div>
                                    @error('logo')<span class="ss-err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>

                        <aside class="ss-card ss-prev" aria-label="{{ __('Live preview') }}">
                            <h4>{!! $svg('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>', '2') !!}{{ __('Live preview') }}<span class="st" data-pv-badge>{{ __('Saved') }}</span></h4>
                            <div class="ss-ptabs" role="group" aria-label="{{ __('Preview') }}">
                                <button type="button" class="on" data-p="slip">{{ __('Payslip') }}</button>
                                <button type="button" data-p="side">{{ __('Sidebar') }}</button>
                                <button type="button" data-p="signin">{{ __('Sign-in') }}</button>
                            </div>
                            <div data-pv-pane="slip">
                                <div class="paper">
                                    <div class="paper-h">
                                        <img src="{{ $system->logoUrl() }}" alt="" data-logo>
                                        <div style="min-width:0">
                                            <div class="paper-co" data-pv="company_name">{{ $name }}</div>
                                            <div class="paper-sub" data-pv="company_tagline">{{ $tagline }}</div>
                                            <div class="paper-sub" data-pv="company_address" @if(! $address) hidden @endif>{{ $address }}</div>
                                        </div>
                                    </div>
                                    <div class="paper-meta"><b>PAYSLIP</b><span>{{ now()->startOfWeek()->format('M j') }} – {{ now()->startOfWeek()->addDays(6)->format('M j, Y') }}</span></div>
                                    <div class="paper-l" style="width:90%"></div><div class="paper-l" style="width:70%"></div><div class="paper-l" style="width:80%"></div>
                                    <div class="paper-tot"><span>NET PAY</span><span>₱4,860.00</span></div>
                                </div>
                            </div>
                            <div data-pv-pane="side" hidden>
                                <div class="rail">
                                    <img src="{{ $system->logoUrl() }}" alt="" data-logo>
                                    <div style="min-width:0"><div class="l1" data-rail="1">{{ $parts[0] ?? '' }}</div><div class="l2" data-rail="2">{{ $parts[1] ?? '' }}</div></div>
                                </div>
                            </div>
                            <div data-pv-pane="signin" hidden>
                                <div class="signin">
                                    <div class="signin-tab"><img src="{{ $signinMark }}" alt="" data-logo-signin><span><span data-pv="signin_name">{{ $name }}</span> | Sign In</span></div>
                                    <div class="signin-body"><img src="{{ $signinMark }}" alt="" data-logo-signin><div class="f"><i></i><i></i><i class="b"></i></div></div>
                                </div>
                            </div>
                            <p class="note">{{ __('Updates as you type. Nothing changes for other users until you save.') }}</p>
                        </aside>
                    </div>
                </section>

                {{-- ── APPEARANCE ──────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="appearance" @if($section !== 'appearance') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Appearance') }}</h3><p>{{ __('Default look for everyone. Each user can still switch from the top bar.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        <div class="ss-row">
                            <div class="lb"><b>{{ __('Default theme') }}</b><small>{{ __('The theme a screen opens on the first time it is used — a new phone, a fresh browser, a kiosk nobody has touched. Anyone who uses the toggle in the top bar keeps their own choice.') }}</small></div>
                            <div class="ss-inp">
                                <div class="ss-seg" role="radiogroup" aria-label="{{ __('Default theme') }}" data-dirty-wrap="default_theme">
                                    @foreach(['system' => ['System', '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>'], 'light' => ['Light', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>'], 'dark' => ['Dark', '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>']] as $value => [$label, $icon])
                                        <label><input type="radio" name="default_theme" value="{{ $value }}" @checked($theme === $value)
                                                      data-track data-saved="{{ $savedTheme }}" data-label="{{ __('Default theme') }}"><span>{!! $svg($icon) !!}{{ __($label) }}</span></label>
                                    @endforeach
                                </div>
                                <span class="ss-help">{{ __('Now:') }} {{ __($themeNow) }}. {{ __('System follows each device’s light or dark setting — this device is') }} <b data-device-mode>{{ __('light') }}</b> {{ __('right now.') }}</span>
                                @error('default_theme')<span class="ss-err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ── SECURITY ────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="security" @if($section !== 'security') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Security') }}</h3><p>{{ __('Sign-in rules for every account, applied to every sign-in and to the next password set or reset.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        @foreach($security as [$key, $title, $desc, $unit, $min, $max, $range, $picks])
                            <div class="ss-row">
                                <div class="lb"><b>{{ __($title) }}</b><small>{{ __($desc) }}</small></div>
                                <div class="ss-inp">
                                    <div class="ss-numrow">
                                        <span class="ss-num"><input type="number" id="{{ $key }}" name="{{ $key }}" value="{{ $num($key) }}" min="{{ $min }}" max="{{ $max }}" required
                                                                    data-track data-saved="{{ (int) $system->{$key} }}" data-label="{{ __($title) }}"><span>{{ __($unit) }}</span></span>
                                        @if($picks)
                                            <span class="ss-picks" data-picks="{{ $key }}">
                                                @foreach($picks as $value => $text)<button type="button" data-set="{{ $value }}">{{ $text }}</button>@endforeach
                                            </span>
                                        @endif
                                    </div>
                                    <span class="ss-help">{{ __($range) }}</span>
                                    @error($key)<span class="ss-err">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="ss-card">
                        <div class="ss-list-h"><h4>{{ __('Account check') }}</h4><small>{{ __('from the accounts on file') }}</small></div>
                        <div class="ss-li">
                            <span class="ic {{ $hygiene['admins'] ? 'ok' : 'danger' }}">{!! $svg('<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>') !!}</span>
                            <div><b>{{ $hygiene['admins'] }} {{ __('active') }} {{ Str::plural('administrator', $hygiene['admins']) }}</b><small>{{ __('At least one is always kept') }}</small></div>
                            <a class="ss-lnk" href="{{ route('users-roles.index', ['role' => 'admin']) }}">{{ __('Show') }}</a>
                        </div>
                        <div class="ss-li">
                            <span class="ic">{!! $svg('<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6 1.5 0 2.9.3 4 .9M17 17l4 4M21 17l-4 4"/>') !!}</span>
                            <div><b>{{ $hygiene['disabled']->count() }} {{ __('disabled') }} {{ Str::plural('account', $hygiene['disabled']->count()) }}</b>
                                <small>{{ $hygiene['disabled']->count() ? Str::limit($hygiene['disabled']->take(3)->implode(', '), 60) . ($hygiene['disabled']->count() > 3 ? ' and ' . ($hygiene['disabled']->count() - 3) . ' more' : '') : __('None — every account can sign in') }}</small></div>
                            @if($hygiene['disabled']->count())<a class="ss-lnk" href="{{ route('users-roles.index', ['status' => 'disabled']) }}">{{ __('Show') }}</a>@endif
                        </div>
                        <div class="ss-li">
                            <span class="ic {{ $hygiene['idle']->count() ? 'warn' : 'ok' }}">{!! $svg('<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9 2h6"/>') !!}</span>
                            <div><b>{{ $hygiene['idle']->count() }} {{ Str::plural('account', $hygiene['idle']->count()) }} {{ __('idle for 90+ days') }}</b>
                                <small>{{ $hygiene['idle']->count() ? $hygiene['idle']->first() . ($hygiene['idle']->count() > 1 ? ' and ' . ($hygiene['idle']->count() - 1) . ' ' . Str::plural('other', $hygiene['idle']->count() - 1) : '') . ' still ' . ($hygiene['idle']->count() > 1 ? 'have' : 'has') . ' a working password' : __('Everyone active has signed in lately') }}</small></div>
                            @if($hygiene['idle']->count())<a class="ss-lnk" href="{{ route('users-roles.index', ['status' => 'idle']) }}">{{ __('Review') }}</a>@endif
                        </div>
                    </div>
                </section>

                {{-- ── KIOSKS ──────────────────────────────────────────────── --}}
                <section class="ss-sec" data-sec="kiosk" @if($section !== 'kiosk') hidden @endif>
                    <div class="ss-sec-h"><div><h3>{{ __('Kiosks') }}</h3><p>{{ __('How fingerprint scans are recorded at the sites. The same on every kiosk; each picks a change up within a few seconds.') }}</p></div></div>
                    <div class="ss-card ss-grp">
                        <div class="ss-row">
                            <div class="lb"><b>{{ __('Attendance mode') }}</b>
                                <small data-mode-hint="auto" @if($mode !== 'auto') hidden @endif>{{ __('Automatic — scan only. No button: a scan with no open time in is a TIME IN; one with an open time in is a TIME OUT. In the lunch break, a scan before') }} {{ $cut }} {{ __('closes the morning and one from') }} {{ $cut }} {{ __('opens the afternoon.') }}</small>
                                <small data-mode-hint="buttons" @if($mode === 'auto') hidden @endif>{{ __('Buttons — the worker presses TIME IN or TIME OUT, then scans. A scan without a button records nothing. Best when workers often come and go mid-session.') }}</small>
                            </div>
                            <div class="ss-inp">
                                <div class="ss-seg" role="radiogroup" aria-label="{{ __('Attendance mode') }}" data-dirty-wrap="kiosk_attendance_mode">
                                    @foreach(['auto' => 'Automatic', 'buttons' => 'Buttons'] as $value => $label)
                                        <label><input type="radio" name="kiosk_attendance_mode" value="{{ $value }}" @checked($mode === $value)
                                                      data-track data-saved="{{ $savedMode }}" data-label="{{ __('Attendance mode') }}"><span>{{ __($label) }}</span></label>
                                    @endforeach
                                </div>
                                <span class="ss-help">{{ __('Now:') }} {{ $savedMode === 'auto' ? __('Automatic · scan only') : __('Buttons · press, then scan') }}</span>
                                @error('kiosk_attendance_mode')<span class="ss-err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="ss-row">
                            <div class="lb"><b>{{ __('Ignore a repeat scan within') }}</b><small>{{ __('So a second touch never turns a TIME IN into a TIME OUT.') }}</small></div>
                            <div class="ss-inp short">
                                <select name="kiosk_repeat_guard_seconds" data-track data-saved="{{ $savedGuard }}" data-label="{{ __('Repeat scan guard') }}">
                                    @foreach($guardOptions as $s)<option value="{{ $s }}" @selected($guard === $s)>{{ $span($s) }}</option>@endforeach
                                </select>
                                @error('kiosk_repeat_guard_seconds')<span class="ss-err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="ss-row">
                            <div class="lb"><b>{{ __('Back to ATTENDANCE after') }}</b><small>{{ __('Idle time on another tab before the kiosk returns to the scanner. An open payroll is never cut short; MY PAYROLL and SUMMARY never record.') }}</small></div>
                            <div class="ss-inp short">
                                <select name="kiosk_idle_return_seconds" data-track data-saved="{{ $savedIdle }}" data-label="{{ __('Back to Attendance') }}">
                                    @foreach($idleOptions as $s)<option value="{{ $s }}" @selected($idle === $s)>{{ $span($s) }}</option>@endforeach
                                </select>
                                @error('kiosk_idle_return_seconds')<span class="ss-err">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="ss-card">
                        <div class="ss-list-h"><h4>{{ __('Kiosks') }}</h4><small>{{ __('each checks for changes every few seconds') }}</small></div>
                        @forelse($kiosks as $k)
                            <div class="ss-li">
                                <span class="ic {{ $k['online'] ? 'ok' : '' }}">{!! $svg('<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M11 18h2"/>') !!}</span>
                                <div><b>{{ $k['name'] }}<code>{{ $k['code'] }}</code></b>
                                    <small>{{ $k['site'] ? 'At ' . $k['site'] : 'No site set' }} · {{ $k['online'] ? 'online' : ($k['heard'] ? 'last heard ' . $k['heard']->diffForHumans() : 'never heard from') }}</small></div>
                                <div class="rt">
                                    @if($k['read'])
                                        {{ __('Read the settings') }} <b>{{ $k['read']->diffForHumans() }}</b>
                                    @else
                                        {{ __('Has not read the settings yet') }}
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="ss-empty">{{ __('No kiosks registered yet.') }}</p>
                        @endforelse
                    </div>

                    <details class="ss-card ss-more">
                        <summary>{{ __('How Automatic decides') }} <small>{{ __('read from each shift') }}</small>{!! $svg('<path d="M6 9l6 6 6-6"/>', '2') !!}</summary>
                        <div class="kr">
                            @foreach($rulers as $r)
                                <div class="kr-row">
                                    <div class="kr-name">{{ $r['name'] }} {{ __('shift') }}<small>{{ $r['workers'] }} {{ Str::plural('worker', $r['workers']) }}</small></div>
                                    <div class="kr-track">
                                        @foreach($r['segments'] as [$class, $from, $to, $text])
                                            <span class="kr-seg {{ $class }}" style="left:{{ $from }}%;width:{{ max(0, $to - $from) }}%">{{ $text }}</span>
                                        @endforeach
                                        <span class="kr-cut" style="left:{{ $r['cut'] }}%"></span>
                                    </div>
                                </div>
                                <div class="kr-ticks">@foreach($r['ticks'] as [$at, $text, $class])<span class="{{ $class }}" style="left:{{ $at }}%">{{ $text }}</span>@endforeach</div>
                            @endforeach
                            <div class="kr-legend"><i></i>{{ __('Lunch cut-off — halfway through the break, worked out from each shift. Before it, a scan closes the first half; from it, a scan opens the second.') }}</div>
                        </div>
                        @if($examples)
                            <div class="kd-scroll">
                                <table class="kd">
                                    <thead><tr><th style="width:120px">{{ __('A scan at') }}</th><th style="width:240px">{{ __('The worker has') }}</th><th>{{ __('The kiosk records') }}</th></tr></thead>
                                    <tbody>
                                        @foreach($examples as [$at, $has, $recs, $note])
                                            <tr><td class="w">{{ $at }}</td><td>{{ $has }}</td><td>@foreach($recs as [$class, $text])<span class="rec {{ $class }}">{{ $text }}</span>@endforeach{{ $note }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </details>
                </section>

                <div class="ss-bar" id="ssBar" role="region" aria-label="{{ __('Unsaved changes') }}">
                    <span><i></i>{{ __('You have unsaved changes') }}<small data-bar-names></small></span>
                    <button type="button" class="ss-btn ghost" data-discard>{{ __('Discard') }}</button>
                    <button type="submit" class="ss-btn pri" data-save>{{ __('Save changes') }}</button>
                </div>
            </form>

            {{-- ── AUDIT LOGS ──────────────────────────────────────────────── --}}
            <section class="ss-sec" data-sec="audit" @if($section !== 'audit') hidden @endif>
                <div class="ss-sec-h">
                    <div><h3>{{ __('Audit logs') }}</h3><p>{{ __('Every change in the system. Logs can’t be edited or deleted.') }}</p></div>
                    <a class="ss-btn" href="{{ route('audit-logs.export', $auditQuery) }}" download>{!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M12 11v6M9 14l3 3 3-3"/>') !!}{{ __('Export') }}</a>
                </div>
                <div class="ss-card">
                    <form class="ss-tools" method="GET" action="{{ route('system-settings.about') }}" id="ssAuditTools">
                        <input type="hidden" name="section" value="audit">
                        @if(request('subject_type'))<input type="hidden" name="subject_type" value="{{ request('subject_type') }}"><input type="hidden" name="subject_id" value="{{ request('subject_id') }}">@endif
                        <label class="ss-sel">{!! $svg('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>', '2') !!}<input class="q" type="search" name="q" value="{{ $A['q'] }}" placeholder="{{ __('Search activity') }}" aria-label="{{ __('Search activity') }}"></label>
                        <label class="ss-sel"><select name="module" aria-label="{{ __('Area') }}">
                            <option value="">{{ __('All areas') }}</option>
                            @foreach($A['modules'] as $m => $n)<option value="{{ $m }}" @selected(in_array($m, $A['selected']['module'], true))>{{ $m }} ({{ $n }})</option>@endforeach
                        </select></label>
                        <label class="ss-sel"><select name="action" aria-label="{{ __('Action') }}">
                            <option value="">{{ __('Any action') }}</option>
                            @foreach($A['actions'] as $m => $n)<option value="{{ $m }}" @selected(in_array($m, $A['selected']['action'], true))>{{ Str::ucfirst($m) }} ({{ $n }})</option>@endforeach
                        </select></label>
                        <label class="ss-sel"><select name="person" aria-label="{{ __('Person') }}">
                            <option value="">{{ __('Everyone') }}</option>
                            @foreach($A['people'] as $m => $n)<option value="{{ $m }}" @selected(in_array($m, $A['selected']['person'], true))>{{ $m }} ({{ $n }})</option>@endforeach
                        </select></label>
                        <label class="ss-sel"><select name="range" aria-label="{{ __('Period') }}">
                            @foreach(['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'all' => 'All time'] as $v => $l)<option value="{{ $v }}" @selected($A['range'] === (string) $v)>{{ __($l) }}</option>@endforeach
                            @if($A['range'] === 'custom')<option value="" selected>{{ $A['from']->format('M j') }} – {{ $A['to']->format('M j, Y') }}</option>@endif
                        </select></label>
                        <label class="ss-tgrow"><span class="ss-tg"><input type="checkbox" name="quick" value="sensitive" @checked($A['quick'] === 'sensitive') aria-label="{{ __('Sensitive only') }}"><span></span></span>{{ __('Sensitive only') }}</label>
                        <span class="sp"></span>
                        @if($A['subject'])<span class="ss-chip">{{ __('One record:') }} {{ $A['subject'] }} <a href="{{ route('system-settings.about', ['section' => 'audit']) }}" aria-label="{{ __('Clear') }}">×</a></span>@endif
                        @if($auditQuery)<a class="ss-lnk" href="{{ route('system-settings.about', ['section' => 'audit']) }}">{{ __('Reset') }}</a>@endif
                    </form>
                    <div id="auditEntries" data-live="audit">
                        @forelse($A['logs'] as $log)
                            @php
                                // A settings entry's record is this page; no link back to it.
                                $key  = $log->subject_type && $log->subject_type !== 'SystemSetting' ? $log->subject_type . '#' . $log->subject_id : null;
                                $subj = $key ? ($A['subjects'][$key] ?? null) : null;
                            @endphp
                            <div class="ss-log">
                                <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y · g:i:s A') }}">{{ $log->created_at->format('M j · g:i A') }}</time>
                                <div class="ds">{{ $log->description ?: '—' }}
                                    <small>{{ $log->user_name ?: 'System' }} · <span class="{{ \App\Models\AuditLog::toneFor($log->action) }}">{{ Str::ucfirst($log->action) }}</span>@if($subj) · @if($subj['url'])<a href="{{ $subj['url'] }}">{{ $subj['label'] }}</a>@else{{ $subj['label'] }}{{ $subj['gone'] ? ' · no longer exists' : '' }}@endif @endif</small>
                                </div>
                                <span class="ss-pill">{{ $log->module }}</span>
                            </div>
                        @empty
                            <p class="ss-empty">{{ __('No activity matches.') }}</p>
                        @endforelse
                    </div>
                    @if($A['logs']->hasPages())
                        <div class="ss-pager">{{ $A['logs']->onEachSide(1)->links() }}</div>
                    @elseif($A['logs']->total())
                        <div class="ss-pager"><span>{{ number_format($A['logs']->total()) }} {{ Str::plural('entry', $A['logs']->total()) }}</span></div>
                    @endif
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
    const bar   = document.getElementById('ssBar');
    const secs  = [...document.querySelectorAll('.ss-sec')];
    const navs  = [...document.querySelectorAll('.ss-si')];
    let current = @json($section);

    // ── Sections: one at a time, the address follows ─────────────────────
    function show(key) {
        current = key;
        secs.forEach(s => { s.hidden = s.dataset.sec !== key; });
        navs.forEach(b => {
            const on = b.dataset.s === key;
            b.classList.toggle('on', on);
            if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
        });
        const url = new URL(location.href);
        url.searchParams.set('section', key);
        history.replaceState(null, '', url);
    }
    navs.forEach(b => b.addEventListener('click', () => show(b.dataset.s)));

    // ── Unsaved changes ──────────────────────────────────────────────────
    const fields = [...form.querySelectorAll('[data-track]')];
    const valueOf = el => {
        if (el.type === 'radio') return form.querySelector('[name="' + el.name + '"]:checked')?.value ?? '';
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
        const changed = dirty();
        const names = new Set(changed.map(el => el.name));
        const bySec = new Set(changed.map(sectionOf));
        bar.classList.toggle('show', changed.length > 0);
        bar.querySelector('[data-bar-names]').textContent = changed.length
            ? ' · ' + [...new Set(changed.map(el => el.dataset.label || el.name))].join(', ') : '';
        document.querySelectorAll('[data-dirty-dot]').forEach(d => { d.hidden = !bySec.has(d.dataset.dirtyDot); });
        fields.forEach(el => {
            const on = names.has(el.name);
            const wrap = form.querySelector('[data-dirty-wrap="' + el.name + '"]');
            if (wrap) wrap.classList.toggle('dirty', on);
            else if (el.type !== 'file' && el.type !== 'radio') el.classList.toggle('dirty', on);
            const c = form.querySelector('[data-cnt-for="' + el.name + '"]');
            if (c && el.maxLength > 0) c.textContent = el.value.length + '/' + el.maxLength;
        });
        form.dispatchEvent(new CustomEvent('ss:change'));
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);

    bar.querySelector('[data-discard]').addEventListener('click', () => {
        form.reset();
        setTimeout(() => { form.dispatchEvent(new CustomEvent('ss:reset')); update(); }, 0);
    });

    // Saving: the edited sections, each through its own save, on this section.
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
    // The audit filters reload the page too.
    window.ssMayLeave = mayLeave;

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

    // ── Company: the live preview ────────────────────────────────────────
    const val = n => form.elements[n]?.value ?? '';
    const saved = {};
    ['company_name', 'company_tagline', 'company_address'].forEach(n => { saved[n] = form.elements[n].dataset.saved || ''; });
    const logoImgs   = [...document.querySelectorAll('[data-logo]')];
    const signinImgs = [...document.querySelectorAll('[data-logo-signin]')];
    const originalLogo = logoImgs[0]?.getAttribute('src');
    const signinLogo   = signinImgs[0]?.getAttribute('src');
    const file = document.getElementById('logo');
    const drop = document.querySelector('[data-drop]');
    const dropText = document.querySelector('[data-drop-text]');
    const dropDefault = dropText.innerHTML;

    function draw() {
        document.querySelector('[data-pv="company_name"]').textContent = val('company_name');
        document.querySelector('[data-pv="signin_name"]').textContent = val('company_name');
        document.querySelector('[data-pv="company_tagline"]').textContent = val('company_tagline');
        const addr = val('company_address').trim();
        const a = document.querySelector('[data-pv="company_address"]');
        a.textContent = addr; a.hidden = !addr;
        const parts = val('company_name').trim().split(/\s+/);
        document.querySelector('[data-rail="1"]').textContent = parts.shift() || '';
        document.querySelector('[data-rail="2"]').textContent = parts.join(' ');
        const changed = Object.keys(saved).some(n => val(n) !== saved[n]) || (file.files && file.files.length > 0);
        const badge = document.querySelector('[data-pv-badge]');
        badge.classList.toggle('warn', changed);
        badge.textContent = changed ? @json(__('Unsaved')) : @json(__('Saved'));
    }
    function showFile() {
        const f = file.files && file.files[0];
        const src = f ? URL.createObjectURL(f) : null;
        logoImgs.forEach(img => { img.src = src || originalLogo; });
        signinImgs.forEach(img => { img.src = src || signinLogo; });
        dropText.innerHTML = f ? @json(__('Chosen:')) + ' <u>' + f.name.replace(/[<>&"]/g, '') + '</u>' : dropDefault;
    }
    file.addEventListener('change', showFile);
    ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('over')));
    drop.addEventListener('drop', e => {
        e.preventDefault();
        if (e.dataTransfer.files.length) {
            file.files = e.dataTransfer.files;
            file.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    document.querySelectorAll('.ss-ptabs button').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('.ss-ptabs button').forEach(x => x.classList.toggle('on', x === b));
        document.querySelectorAll('[data-pv-pane]').forEach(p => { p.hidden = p.dataset.pvPane !== b.dataset.p; });
    }));
    form.addEventListener('ss:change', draw);
    form.addEventListener('ss:reset', () => { showFile(); draw(); });

    // ── Appearance: what this device is set to ───────────────────────────
    const deviceMode = document.querySelector('[data-device-mode]');
    if (deviceMode && window.matchMedia) {
        const dm = window.matchMedia('(prefers-color-scheme: dark)');
        const say = () => { deviceMode.textContent = dm.matches ? @json(__('dark')) : @json(__('light')); };
        say();
        dm.addEventListener && dm.addEventListener('change', say);
    }

    // ── Security: quick picks set the number ─────────────────────────────
    document.querySelectorAll('[data-picks]').forEach(p => {
        const input = form.elements[p.dataset.picks];
        const mark = () => p.querySelectorAll('[data-set]').forEach(b => b.classList.toggle('on', b.dataset.set === input.value));
        p.addEventListener('click', e => {
            const b = e.target.closest('[data-set]');
            if (!b) return;
            input.value = b.dataset.set;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        form.addEventListener('ss:change', mark);
        mark();
    });

    // ── Kiosks: the mode's own explanation ───────────────────────────────
    form.addEventListener('ss:change', () => {
        const m = val('kiosk_attendance_mode') || form.querySelector('[name="kiosk_attendance_mode"]:checked')?.value;
        document.querySelectorAll('[data-mode-hint]').forEach(h => { h.hidden = h.dataset.modeHint !== m; });
    });

    update();
})();

// ── Audit logs: the filters apply as they change ─────────────────────────
(function () {
    const tools = document.getElementById('ssAuditTools');
    if (!tools) return;
    const go = async () => {
        if (window.ssMayLeave && !(await window.ssMayLeave())) return;
        // Only the filters in use go in the address.
        tools.querySelectorAll('input, select').forEach(el => { if (el.name && el.type !== 'hidden' && !el.value) el.disabled = true; });
        tools.submit();
    };
    tools.querySelectorAll('select, input[type=checkbox]').forEach(el => el.addEventListener('change', go));
    tools.addEventListener('submit', e => { e.preventDefault(); go(); });
    const q = tools.querySelector('input[name=q]');
    let t;
    q && q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(go, 600); });
})();
</script>
@endpush
