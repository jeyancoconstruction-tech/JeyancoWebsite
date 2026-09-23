<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $company?->company_name ?? 'Jeyanco' }} | @yield('title', 'Admin')</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon-64.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-180.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Forgot password and Set a new password draw their step markers and
         field icons from Font Awesome. The sign-in page uses inline SVG and
         needs none of it. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    {{-- Michael's design, from login.html. Two panels: what the system is on
         the left, the way in on the right. Below 1024px the left one goes and
         the form has the screen to itself, which is what a phone wants.

         Changed for the app rather than for the look: the mark and the name
         come from System Settings, the fields are the ones AuthController
         reads, and Laravel's own validation drives the error state so a
         refused sign-in comes back marked without JavaScript having to
         re-check anything. The two inline base64 logos are gone — the same
         file is served once from /images and cached.

         No theme switch here. The rest of the app follows the device; this
         page is the front door and is always the dark one, the same as the
         loading screen it hands over to. --}}
    <style>
        :root {
            --bg:          #0a0f1e;
            --panel:       #0C1A38;
            --surface:     #0F1629;
            --surface-2:   #131C33;
            --brand:       #1E5C9B;
            --brand-hover: #246AB0;
            --brand-light: #7FB0E6;
            --text:        #E6EDF7;
            --text-2:      #C9D4E6;
            --muted:       #8A98B3;
            --faint:       #6E7C97;
            --line:        rgba(255, 255, 255, .08);
            --line-strong: rgba(255, 255, 255, .14);
            --success:     #5FCB9F;
            --warn:        #E7C07A;
            --danger:      #F08A8A;

            /* One spacing scale, so the two panels breathe the same way. */
            --s-1: 6px;  --s-2: 10px; --s-3: 14px;
            --s-4: 20px; --s-5: 28px; --s-6: 40px;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Manrope", system-ui, -apple-system, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        a { color: var(--brand-light); text-decoration: none; transition: color .15s; }
        a:hover { color: #A9CBF0; }
        a:focus-visible, button:focus-visible, input:focus-visible {
            outline: 2px solid var(--brand-light); outline-offset: 2px; border-radius: 4px;
        }
        .i { fill: none; stroke: currentColor; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

        .page { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 53fr) minmax(0, 47fr); }

        /* ── Brand panel ───────────────────────────────────────────────── */
        .brand-panel {
            position: relative; overflow: hidden;
            padding: 44px clamp(36px, 5vw, 72px);
            display: flex; flex-direction: column;
            background-color: var(--panel);
            background-image:
                linear-gradient(rgba(127,176,230,.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(127,176,230,.07) 1px, transparent 1px),
                linear-gradient(rgba(127,176,230,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(127,176,230,.035) 1px, transparent 1px);
            background-size: 80px 80px, 80px 80px, 16px 16px, 16px 16px;
        }
        /* While the loader is up the panel is the loader's own ground; its
           colour and its glow come in with everything else, so the hand-over
           reads as one screen becoming another rather than a cut. */
        .brand-panel { transition: background-color .9s ease; }
        .jp-loading .brand-panel { background-color: var(--bg); }
        .brand-panel::before {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 70% 55% at 30% 35%, rgba(30,92,155,.45), transparent 70%),
                linear-gradient(180deg, transparent 55%, rgba(10,15,30,.85));
            transition: opacity 1.2s ease .1s;
        }
        .jp-loading .brand-panel::before { opacity: 0; }
        .brand-panel > * { position: relative; }

        .logo { display: flex; align-items: center; gap: var(--s-3); }
        .logo img {
            width: 48px; height: 48px; border-radius: 50%; object-fit: cover;
            box-shadow: 0 0 0 3px rgba(255,255,255,.08);
        }
        .logo-name { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .logo-name b { font-size: 15px; font-weight: 800; letter-spacing: .1em; }
        .logo-name span { font-size: 10px; font-weight: 600; letter-spacing: .28em; color: #9FB3D1; }

        /* The pitch and the list are one column: the gap does the spacing, so
           nothing carries a margin of its own to fall out of step. */
        .pitch { margin-top: clamp(40px, 9vh, 88px); max-width: 620px; display: flex; flex-direction: column; gap: var(--s-4); }
        .tag {
            align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
            height: 30px; padding: 0 13px; border-radius: 99px;
            background: rgba(79,143,209,.14); border: 1px solid rgba(127,176,230,.22);
            font-size: 11.5px; font-weight: 700; letter-spacing: .08em; color: #BFD6F0;
        }
        .tag i { width: 7px; height: 7px; border-radius: 50%; background: var(--success); box-shadow: 0 0 0 3px rgba(95,203,159,.2); }
        .pitch h2 {
            /* text-wrap: balance evens the two lines instead of leaving one
               word stranded on the second. */
            margin: 0; font-size: clamp(30px, 3vw, 42px); line-height: 1.14;
            font-weight: 800; letter-spacing: -.022em; text-wrap: balance;
        }
        .pitch p { margin: 0; font-size: 15.5px; line-height: 1.65; color: #A9B8D2; max-width: 46ch; }

        .features { list-style: none; margin: var(--s-5) 0 0; padding: 0; display: flex; flex-direction: column; gap: var(--s-3); }
        .features li { display: flex; align-items: center; gap: var(--s-3); font-size: 14px; font-weight: 500; color: #D5DFEE; }
        .features li span {
            width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center;
            background: rgba(255,255,255,.05); border: 1px solid var(--line); color: var(--brand-light);
        }

        .skyline { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: auto; max-height: 34%; pointer-events: none; }
        .sk  { fill: none; stroke: rgba(127,176,230,.28); stroke-width: 1.2; vector-effect: non-scaling-stroke; }
        .sk2 { fill: none; stroke: rgba(127,176,230,.14); stroke-width: 1;   vector-effect: non-scaling-stroke; }
        .hook { transform-origin: 470px 60px; transform-box: view-box; animation: sway 5s ease-in-out infinite; }
        @keyframes sway { 0%,100% { transform: rotate(-1.6deg); } 50% { transform: rotate(1.6deg); } }

        /* ── Form panel ────────────────────────────────────────────────── */
        .auth {
            position: relative; padding: 32px clamp(24px, 4vw, 56px);
            display: flex; flex-direction: column;
        }
        .auth::before {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(ellipse 60% 40% at 50% 30%, rgba(30,92,155,.12), transparent 70%);
        }
        .auth > * { position: relative; }
        .auth-top { display: flex; justify-content: space-between; align-items: center; gap: var(--s-3); font-size: 13px; color: var(--muted); }
        .auth-top .mobile-logo { display: none; }
        .auth-top .help { margin-left: auto; }
        .auth-top a { font-weight: 700; }

        .auth-center { flex: 1; display: flex; align-items: center; justify-content: center; padding: var(--s-5) 0; }

        /* Heading, alerts and form are one column of the same width: the
           layout owns them, because Forgot password and Set a new password
           sit here too and only hand over their own fields. */
        .auth-stack { width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: var(--s-4); }
        form { width: 100%; display: flex; flex-direction: column; gap: var(--s-4); }
        /* No entrance of its own: the loading screen hands over to the .rv
           choreography in _loading_head, which brings the page in piece by
           piece as the overlay lifts. Two entrances at once would fight. */

        .head h1 { margin: 0 0 var(--s-1); font-size: 29px; font-weight: 800; letter-spacing: -.02em; }
        .head p  { margin: 0; font-size: 14.5px; line-height: 1.55; color: var(--muted); }

        .alert {
            display: flex; align-items: flex-start; gap: var(--s-2);
            padding: 12px 14px; border-radius: 12px;
            font-size: 13.5px; font-weight: 600; line-height: 1.45;
            animation: alertIn .28s cubic-bezier(.2,.8,.2,1) both;
        }
        @keyframes alertIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
        .alert-error   { background: rgba(240,138,138,.1); border: 1px solid rgba(240,138,138,.28); color: var(--danger); }
        .alert-success { background: rgba(95,203,159,.1);  border: 1px solid rgba(95,203,159,.28);  color: var(--success); }
        .alert .i { margin-top: 1px; }

        .field { display: flex; flex-direction: column; gap: 7px; }
        .field label { font-size: 12.5px; font-weight: 600; letter-spacing: .01em; color: var(--text-2); }
        .control { position: relative; display: flex; align-items: center; }
        .control > .lead { position: absolute; left: 14px; color: var(--faint); display: flex; pointer-events: none; transition: color .15s; }
        .control:focus-within > .lead { color: var(--brand-light); }
        .control input {
            width: 100%; height: 48px; border-radius: 12px;
            border: 1px solid var(--line-strong); background: var(--surface);
            color: var(--text); font: 500 14.5px "Manrope", sans-serif;
            padding: 0 14px 0 44px;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .control input::placeholder { color: #66748F; }
        .control input:hover { border-color: rgba(255,255,255,.2); }
        .control input:focus {
            outline: none; border-color: #4F8FD1; background: var(--surface-2);
            box-shadow: 0 0 0 4px rgba(79,143,209,.18);
        }
        .control input.invalid { border-color: rgba(240,138,138,.6); }
        .control input.invalid:focus { box-shadow: 0 0 0 4px rgba(240,138,138,.16); }
        .control input.has-toggle { padding-right: 48px; }
        /* Chrome paints its own near-white block over an autofilled box. */
        .control input:-webkit-autofill,
        .control input:-webkit-autofill:hover,
        .control input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text);
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
            caret-color: var(--text);
        }
        /* Said under the field it belongs to, not only in the banner above. */
        .field-error { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--danger); }

        .eye {
            position: absolute; right: 6px; width: 36px; height: 36px;
            display: grid; place-items: center; border: 0; border-radius: 9px;
            background: transparent; color: var(--muted); cursor: pointer; transition: background .13s, color .13s;
        }
        .eye:hover { background: rgba(255,255,255,.06); color: var(--text); }
        .eye .off { display: none; }
        .eye[aria-pressed="true"] .on  { display: none; }
        .eye[aria-pressed="true"] .off { display: block; }

        .caps { display: none; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--warn); }
        .caps.show { display: flex; }

        .row { display: flex; align-items: center; justify-content: space-between; gap: var(--s-3); }
        .remember { display: flex; align-items: center; gap: var(--s-2); font-size: 13.5px; color: var(--text-2); cursor: pointer; user-select: none; }
        .remember input { width: 17px; height: 17px; margin: 0; accent-color: var(--brand); cursor: pointer; }
        .row a { font-size: 13.5px; font-weight: 700; }

        .submit {
            height: 50px; width: 100%; border-radius: 12px;
            border: 1px solid #2A6BAD; background: var(--brand); color: #fff;
            font: 700 15px "Manrope", sans-serif;
            display: flex; align-items: center; justify-content: center; gap: var(--s-2);
            cursor: pointer;
            box-shadow: 0 10px 28px -12px rgba(30,92,155,.9), inset 0 1px 0 rgba(255,255,255,.12);
            transition: background .15s, transform .08s, box-shadow .15s;
        }
        .submit:hover { background: var(--brand-hover); box-shadow: 0 12px 32px -12px rgba(30,92,155,1), inset 0 1px 0 rgba(255,255,255,.14); }
        .submit:active { transform: translateY(1px); }
        .submit .spin { display: none; width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; animation: sp .8s linear infinite; }
        .submit.loading { pointer-events: none; opacity: .92; }
        .submit.loading .spin { display: block; }
        .submit.loading .arrow { display: none; }
        /* The arrow leans into the press; it is the only decorative motion here. */
        .submit:hover .arrow { transform: translateX(2px); }
        .submit .arrow { transition: transform .18s cubic-bezier(.2,.8,.2,1); }
        @keyframes sp { to { transform: rotate(360deg); } }

        .notice {
            display: flex; align-items: center; gap: var(--s-3);
            padding: 13px 15px; border-radius: 12px;
            background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.06);
            font-size: 12px; line-height: 1.55; color: var(--muted);
        }
        .notice > span { color: var(--success); display: flex; }

        .auth-foot { display: flex; justify-content: space-between; gap: var(--s-3); font-size: 12px; color: var(--faint); }
        .auth-foot nav { display: flex; gap: var(--s-4); }
        .auth-foot a { color: var(--muted); font-weight: 500; }

        /* ── Forgot password, and Set a new password ────────────────────
           Those two were written against the light card this replaced, and
           they carry their own class names. Rather than rewrite two working
           forms, the names are given the same shapes the sign-in page uses,
           so all three read as one page with different questions on it.
           They have no leading icon in the box, hence the plain padding. */
        .form-group { display: flex; flex-direction: column; gap: 7px; }
        .form-group + .form-group { margin-top: var(--s-4); }
        .form-group label { font-size: 12.5px; font-weight: 600; color: var(--text-2); }
        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-wrap input {
            width: 100%; height: 48px; border-radius: 12px;
            border: 1px solid var(--line-strong); background: var(--surface);
            color: var(--text); font: 500 14.5px "Manrope", sans-serif; padding: 0 14px;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .input-wrap input::placeholder { color: #66748F; }
        .input-wrap input:hover { border-color: rgba(255,255,255,.2); }
        .input-wrap input:focus {
            outline: none; border-color: #4F8FD1; background: var(--surface-2);
            box-shadow: 0 0 0 4px rgba(79,143,209,.18);
        }
        .input-wrap input:-webkit-autofill {
            -webkit-text-fill-color: var(--text);
            -webkit-box-shadow: 0 0 0 1000px var(--surface) inset;
            caret-color: var(--text);
        }
        .form-group.has-error .input-wrap input { border-color: rgba(240,138,138,.6); }
        .input-wrap input[type="password"] { padding-right: 48px; }
        .toggle-pass {
            position: absolute; right: 6px; width: 36px; height: 36px;
            display: grid; place-items: center; border: 0; border-radius: 9px;
            background: transparent; color: var(--muted); cursor: pointer; transition: background .13s, color .13s;
        }
        .toggle-pass:hover { background: rgba(255,255,255,.06); color: var(--text); }
        .field-hint { font-size: 12.5px; line-height: 1.5; color: var(--muted); }
        .caps-hint { display: none; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--warn); }
        .caps-hint.show { display: flex; }
        .btn-login {
            height: 50px; width: 100%; border-radius: 12px; margin-top: var(--s-4);
            border: 1px solid #2A6BAD; background: var(--brand); color: #fff;
            font: 700 15px "Manrope", sans-serif;
            display: flex; align-items: center; justify-content: center; gap: var(--s-2);
            cursor: pointer;
            box-shadow: 0 10px 28px -12px rgba(30,92,155,.9), inset 0 1px 0 rgba(255,255,255,.12);
            transition: background .15s, transform .08s;
        }
        .btn-login:hover { background: var(--brand-hover); }
        .btn-login:active { transform: translateY(1px); }
        .btn-login:disabled { opacity: .7; cursor: progress; }
        .login-footer { margin-top: var(--s-4); text-align: center; font-size: 13px; color: var(--muted); }

        /* Their step markers were drawn on white. */
        .pw-flow .n { background: var(--surface) !important; border-color: var(--line-strong) !important; color: var(--muted) !important; }
        .pw-flow li.is-now .n   { background: var(--brand) !important; border-color: var(--brand) !important; color: #fff !important; }
        .pw-flow li.is-done .n  { background: rgba(95,203,159,.16) !important; border-color: rgba(95,203,159,.5) !important; color: var(--success) !important; }
        .pw-flow li.is-now .t   { color: var(--text-2); }
        .pw-rules, .pw-again { color: var(--muted); }

        /* ── Responsive ────────────────────────────────────────────────── */
        @media (max-width: 1024px) {
            .page { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .auth-top .mobile-logo { display: flex; }
        }
        @media (max-width: 480px) {
            .auth { padding: 20px 16px; }
            .auth-top .help { display: none; }
            .head h1 { font-size: 25px; }
            .auth-foot { flex-direction: column; gap: var(--s-1); align-items: center; }
        }
        @media (prefers-reduced-motion: reduce) {
            .hook, .auth-stack, .alert, .submit .spin { animation: none; }
            .submit, .submit .arrow, .control input, .eye { transition: none; }
            .submit:active { transform: none; }
            .submit:hover .arrow { transform: none; }
        }
    </style>

    @stack('styles')

    {{-- The entry loader. Signing in is where most people arrive — the
         address goes to the login page when nobody is signed in — so the
         overlay belongs here as much as on the dashboard layout. The check
         inside stamps <html> before these styles are read. --}}
    @include('_loading_head')
</head>
<body>

@include('_loading')

@php
    $mark = $company?->logo_path ? $company->logoUrl() : asset('images/logo-mark.png');
    $name = $company?->company_name ?? 'Jeyanco Construction';
@endphp

<div class="page">

    {{-- ── What the system is ───────────────────────────────────────── --}}
    <aside class="brand-panel">
        <div class="logo rv rv-left" style="--jp-d:150">
            <img src="{{ $mark }}" alt="">
            <div class="logo-name"><b>{{ __('JEYANCO') }}</b><span>{{ __('CONSTRUCTION') }}</span></div>
        </div>

        <div class="pitch">
            <span class="tag rv" style="--jp-d:300"><i></i>{{ __('JEYANCO PAYROLL') }}</span>
            <h2 class="rv" style="--jp-d:380">{{ __('Every shift clocked.') }}<br>{{ __('Every peso accounted for.') }}</h2>
            <p class="rv" style="--jp-d:460">{{ __('Attendance from the site kiosks flows straight into payroll, so the crew gets paid right and on time.') }}</p>

            <ul class="features">
                <li class="rv" style="--jp-d:560">
                    <span><svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M6.5 7.5A7 7 0 0 1 19 11v1.5M5 12a7 7 0 0 1 .4-2.4M8.5 20a14 14 0 0 1-1-5.5v-2.5a4.5 4.5 0 0 1 9 0v1.5M12 12v2.5a11 11 0 0 0 1.5 5.5M16.5 17.5a18 18 0 0 0 .5-3"/></svg></span>
                    {{ __('Biometric time-in and time-out from every site') }}
                </li>
                <li class="rv" style="--jp-d:620">
                    <span><svg class="i" width="18" height="18" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/></svg></span>
                    {{ __('DOLE-compliant pay, deductions and contributions') }}
                </li>
                <li class="rv" style="--jp-d:680">
                    <span><svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                    {{ __('GPS monitoring across project sites') }}
                </li>
            </ul>
        </div>

        {{-- Blueprint skyline: a tower, a frame going up, the crane between
             them. Drawn, not photographed, so it stays a background. --}}
        <svg class="skyline" viewBox="0 0 760 260" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
            <path class="sk draw" pathLength="1" style="--jp-d:200" d="M0 259.5H760"/>
            <rect class="sk draw" pathLength="1" style="--jp-d:300" x="40" y="70" width="110" height="190"/>
            <path class="sk2 fade" style="--jp-d:1300" d="M40 95H150M40 120H150M40 145H150M40 170H150M40 195H150M40 220H150M40 245H150M77 70V260M113 70V260"/>
            <path class="sk draw" pathLength="1" style="--jp-d:700" d="M60 70V56H130V70M95 56V30"/>
            <rect class="sk draw" pathLength="1" style="--jp-d:400" x="168" y="140" width="92" height="120"/>
            <path class="sk2 fade" style="--jp-d:1400" d="M168 164H260M168 188H260M168 212H260M168 236H260M199 140V260M229 140V260"/>
            <path class="sk draw" pathLength="1" style="--jp-d:450" d="M322 260V48M338 260V48M322 48H338"/>
            <path class="sk2 fade" style="--jp-d:1350" d="M322 70L338 90L322 110L338 130L322 150L338 170L322 190L338 210L322 230L338 250"/>
            <path class="sk draw" pathLength="1" style="--jp-d:750" d="M250 48H540M250 60H540M250 48V60M540 48V60M330 48L330 22L250 48M330 22L540 48"/>
            <path class="sk2 fade" style="--jp-d:1500" d="M270 48L282 60L294 48L306 60M360 48L372 60L384 48L396 60L408 48L420 60L432 48L444 60L456 48L468 60L480 48L492 60L504 48L516 60L528 48"/>
            <rect class="sk fade" style="--jp-d:1200" x="256" y="60" width="26" height="14"/>
            <g class="hook fade" style="--jp-d:1600">
                <path class="sk" d="M470 60V150"/>
                <rect class="sk" x="452" y="150" width="36" height="22"/>
                <path class="sk2" d="M452 150L488 172M488 150L452 172"/>
            </g>
            <path class="sk fade" style="--jp-d:1100" d="M390 260V170H530V260" stroke-dasharray="5 5"/>
            <path class="sk2 fade" style="--jp-d:1450" d="M390 200H530M390 230H530M425 170V260M460 170V260M495 170V260M390 170L425 200M425 200L390 230M495 170L530 200M530 200L495 230"/>
            <rect class="sk draw" pathLength="1" style="--jp-d:500" x="560" y="100" width="78" height="160"/>
            <path class="sk2 fade" style="--jp-d:1400" d="M560 124H638M560 148H638M560 172H638M560 196H638M560 220H638M560 244H638M586 100V260M612 100V260"/>
            <rect class="sk draw" pathLength="1" style="--jp-d:550" x="656" y="170" width="84" height="90"/>
            <path class="sk2 fade" style="--jp-d:1500" d="M656 192H740M656 214H740M656 236H740M684 170V260M712 170V260"/>
        </svg>
    </aside>

    {{-- ── The way in ───────────────────────────────────────────────── --}}
    <main class="auth">
        <div class="auth-top rv" style="--jp-d:250">
            <div class="logo mobile-logo">
                <img src="{{ $mark }}" alt="" style="width:40px;height:40px">
                <div class="logo-name"><b>{{ __('JEYANCO') }}</b><span>{{ __('CONSTRUCTION') }}</span></div>
            </div>
            <span class="help">{{ __('Need an account?') }} <a href="{{ route('password.request') }}">{{ __('Contact your administrator') }}</a></span>
        </div>

        <div class="auth-center">
            <div class="auth-stack">
                <div class="head rv" style="--jp-d:320">
                    <h1>@yield('heading')</h1>
                    <p>@yield('subheading')</p>
                </div>

                @if(session('success'))
                    <div class="alert alert-success" role="status">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                {{-- Laravel's own errors, so a refused sign-in comes back
                     marked whether or not any script ran. --}}
                @if($errors->any())
                    <div class="alert alert-error" role="alert">
                        <svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('form')
            </div>
        </div>

        <footer class="auth-foot rv" style="--jp-d:760">
            <span>&copy; {{ now()->year }} {{ $name }}</span>
            <nav>
                <a href="{{ route('password.request') }}">{{ __('Trouble signing in?') }}</a>
            </nav>
        </footer>
    </main>
</div>

@yield('after')
@stack('scripts')
</body>
</html>
