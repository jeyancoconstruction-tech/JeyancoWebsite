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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Jeyanco auth pages. One card, centred, over a navy header band. */
        :root {
            --navy:      #123566;
            --navy-dark: #0d2a4f;
            --blue:      #1668dc;
            --blue-dark: #1257bc;

            --ink:   #101828;
            --text:  #344054;
            --muted: #667085;
            --line:  #d0d5dd;

            --danger:  #b42318;
            --success: #027a48;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }

        body {
            position: relative;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 20px;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            font-size: 14px; line-height: 1.5;
            color: var(--text);
            background-color: var(--navy-dark);
            background-image:
                radial-gradient(950px 640px at 50% 44%, rgba(72, 132, 216, .22), transparent 72%),
                repeating-linear-gradient(0deg,  rgba(255,255,255,.055) 0 1px, transparent 1px 160px),
                repeating-linear-gradient(90deg, rgba(255,255,255,.055) 0 1px, transparent 1px 160px),
                repeating-linear-gradient(0deg,  rgba(255,255,255,.028) 0 1px, transparent 1px 32px),
                repeating-linear-gradient(90deg, rgba(255,255,255,.028) 0 1px, transparent 1px 32px),
                linear-gradient(165deg, #143a70 0%, var(--navy-dark) 55%, #071a33 100%);
        }

        .site-art { position: fixed; inset: 0; overflow: hidden; pointer-events: none; }
        /* One elevation across the foot of the page: everything stands on
           the same ground line, and the card covers the middle of it. */
        .site-art svg {
            position: absolute; left: 0; bottom: 0;
            width: 100%; height: auto;
            fill: none; stroke: rgba(255, 255, 255, .16);
            stroke-width: 2; stroke-linecap: square;
        }
        /* The drawing scales with the window; the pen width should not. */
        .site-art path, .site-art circle { vector-effect: non-scaling-stroke; }

        /* Below this the card is most of the window and the drawings only
           crowd it. */
        @media (max-width: 1080px) { .site-art { display: none; } }

        /* The site is working. The crane runs its trolley out, lowers a beam
           and takes the hook back up; the frame gains a floor every few
           seconds; the two figures on it keep at their work. Transform and
           opacity only, so none of it costs a layout pass. */
        .trolley { animation: travel 16s ease-in-out infinite; }
        .hoist   { transform-origin: 1248px 176px; animation: hoist 16s ease-in-out infinite; }
        .load    { animation: load 16s ease-in-out infinite; }

        @keyframes travel {
            0%, 14%   { transform: translateX(0); }
            38%, 72%  { transform: translateX(-40px); }
            96%, 100% { transform: translateX(0); }
        }
        @keyframes hoist {
            0%, 20%   { transform: scaleY(.34); }
            44%, 62%  { transform: scaleY(1); }
            86%, 100% { transform: scaleY(.34); }
        }
        @keyframes load {
            0%, 20%   { transform: translateY(-79px); }
            44%, 62%  { transform: translateY(0); }
            86%, 100% { transform: translateY(-79px); }
        }

        .f1 { animation: floor1 24s ease-out infinite; }
        .f2 { animation: floor2 24s ease-out infinite; }
        .f3 { animation: floor3 24s ease-out infinite; }
        .f4 { animation: floor4 24s ease-out infinite; }
        .f5 { animation: floor5 24s ease-out infinite; }
        .f6 { animation: floor6 24s ease-out infinite; }

        @keyframes floor1 {
            0%, 5%    { opacity: 0; transform: translateY(-9px); }
            11%, 86%  { opacity: 1; transform: translateY(0); }
            95%, 100% { opacity: 0; transform: translateY(-9px); }
        }
        @keyframes floor2 {
            0%, 17%   { opacity: 0; transform: translateY(-9px); }
            23%, 86%  { opacity: 1; transform: translateY(0); }
            95%, 100% { opacity: 0; transform: translateY(-9px); }
        }
        @keyframes floor3 {
            0%, 29%   { opacity: 0; transform: translateY(-9px); }
            35%, 86%  { opacity: 1; transform: translateY(0); }
            95%, 100% { opacity: 0; transform: translateY(-9px); }
        }
        @keyframes floor4 {
            0%, 41%   { opacity: 0; transform: translateY(-9px); }
            47%, 86%  { opacity: 1; transform: translateY(0); }
            95%, 100% { opacity: 0; transform: translateY(-9px); }
        }
        @keyframes floor5 {
            0%, 53%   { opacity: 0; transform: translateY(-9px); }
            59%, 86%  { opacity: 1; transform: translateY(0); }
            95%, 100% { opacity: 0; transform: translateY(-9px); }
        }
        @keyframes floor6 {
            0%, 65%   { opacity: 0; transform: translateY(-9px); }
            71%, 86%  { opacity: 1; transform: translateY(0); }
            95%, 100% { opacity: 0; transform: translateY(-9px); }
        }

        .worker .arm { transform-origin: 0 -18px; }
        .w-hammer .arm { animation: hammer .78s ease-in-out infinite alternate; }
        .w-signal .arm { animation: signal 2.6s ease-in-out infinite alternate; }

        @keyframes hammer { from { transform: rotate(-6deg); }  to { transform: rotate(-52deg); } }
        @keyframes signal { from { transform: rotate(-13deg); } to { transform: rotate(15deg); } }

        .boom { transform-origin: 46px -32px; animation: dig 3.4s ease-in-out infinite alternate; }
        .drum { transform-origin: 62px -38px; animation: mix 7s linear infinite; }

        @keyframes dig { from { transform: rotate(-13deg); } to { transform: rotate(7deg); } }
        @keyframes mix { to { transform: rotate(360deg); } }

        .auth-shell { position: relative; z-index: 1; width: 100%; max-width: 408px; }

        .auth-card {
            padding: 32px 32px 28px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(5, 20, 40, .30),
                        0 24px 56px rgba(5, 20, 40, .38);
        }

        /* Brand block */
        .brand { text-align: center; margin-bottom: 26px; }
        .brand-mark { width: 60px; height: 60px; display: block; margin: 0 auto 14px; }
        .brand-name {
            font-size: 11px; font-weight: 700; letter-spacing: .16em;
            text-transform: uppercase; color: var(--muted);
        }
        .brand h1 {
            margin-top: 14px;
            font-size: 21px; font-weight: 600; letter-spacing: -.01em;
            color: var(--ink);
        }
        .card-lede { margin-top: 6px; font-size: 13.5px; color: var(--muted); }

        /* Alerts */
        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            margin-bottom: 18px; padding: 11px 13px;
            border-radius: 8px; font-size: 13.5px;
        }
        .alert i { margin-top: 2px; flex: none; }
        .alert-success { background: #ecfdf3; color: var(--success); }
        .alert-error   { background: #fef3f2; color: var(--danger); }

        /* Fields */
        .form-group { margin-bottom: 16px; }
        .form-group > label {
            display: block; margin-bottom: 6px;
            font-size: 13px; font-weight: 500; color: var(--text);
        }

        .input-wrap { position: relative; display: flex; align-items: center; }

        .input-wrap input {
            width: 100%; height: 42px; padding: 0 14px;
            font-family: inherit; font-size: 14px; color: var(--ink);
            background: #fff;
            border: 1px solid var(--line); border-radius: 8px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .input-wrap input::placeholder { color: #98a2b3; }
        .input-wrap input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(22, 104, 220, .14);
        }
        .has-error .input-wrap input { border-color: var(--danger); }

        /* Room for the show/hide button. */
        .input-wrap:has(.toggle-pass) input { padding-right: 42px; }

        .toggle-pass {
            position: absolute; right: 5px;
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            background: none; border: none; border-radius: 6px;
            color: #98a2b3; font-size: 13px; cursor: pointer;
            transition: color .15s ease, background .15s ease;
        }
        .toggle-pass:hover { color: var(--blue); background: #f2f6fc; }
        .toggle-pass:focus-visible { outline: 2px solid var(--blue); outline-offset: 1px; }

        .field-hint { margin-top: 6px; font-size: 12.5px; color: var(--muted); }

        .caps-hint {
            display: none; align-items: center; gap: 6px;
            margin-top: 6px; font-size: 12.5px; color: #b54708;
        }
        .caps-hint.show { display: flex; }

        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }

        /* Remember / forgot row */
        .form-options {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin: 2px 0 20px;
            font-size: 13px;
        }

        .remember-me { display: flex; align-items: center; gap: 8px; }
        .remember-me input[type="checkbox"] {
            appearance: none; -webkit-appearance: none;
            width: 16px; height: 16px; flex: none;
            border: 1px solid var(--line); border-radius: 4px;
            background: #fff; cursor: pointer; position: relative;
            transition: background .15s ease, border-color .15s ease;
        }
        .remember-me input[type="checkbox"]:checked {
            background: var(--blue); border-color: var(--blue);
        }
        .remember-me input[type="checkbox"]:checked::after {
            content: ''; position: absolute;
            left: 5px; top: 1.5px; width: 4px; height: 9px;
            border: solid #fff; border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .remember-me input[type="checkbox"]:focus-visible {
            outline: 2px solid var(--blue); outline-offset: 2px;
        }
        .remember-me label { margin: 0; cursor: pointer; color: var(--text); }

        .forgot-link { font-weight: 500; color: var(--blue); text-decoration: none; white-space: nowrap; }
        .forgot-link:hover { text-decoration: underline; }

        /* Submit */
        .btn-login {
            width: 100%; height: 42px;
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            font-family: inherit; font-size: 14.5px; font-weight: 600; color: #fff;
            background: var(--blue); border: none; border-radius: 8px;
            cursor: pointer;
            transition: background .15s ease;
        }
        .btn-login:hover:not(:disabled) { background: var(--blue-dark); }
        .btn-login:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        .btn-login:disabled { opacity: .7; cursor: progress; }

        /* Back link, used by the password pages */
        .login-footer { margin-top: 18px; text-align: center; font-size: 13px; }
        .login-footer a { color: var(--blue); text-decoration: none; font-weight: 500; }
        .login-footer a:hover { text-decoration: underline; }

        @media (max-width: 420px) {
            body { padding: 28px 14px; }
            .auth-card { padding: 26px 20px 22px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition-duration: .01ms !important; }
            .site-art * { animation: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="site-art" aria-hidden="true">
        {{-- A site elevation, all of it on one ground line: a tower finished,
             two frames going up, and the plant working between them. The band
             is deep enough for the buildings to read as buildings; the plant
             and the figures keep their size because only the viewBox height
             changed, not its width. --}}
        <svg class="art-scene" viewBox="0 0 1440 560">
            <path d="M0 558h1440"/>

            {{-- Tower already standing: eighteen floors, parapet and mast. --}}
            <path d="M20 558V60M160 558V60M20 60h140"/>
            <path d="M14 52h152v8H14z"/>
            <path d="M90 52V24M84 34h12"/>
            <path d="M67 558V60M113 558V60"/>
            <path d="M20 532h140M20 506h140M20 480h140M20 454h140M20 428h140M20 402h140M20 376h140M20 350h140M20 324h140M20 298h140M20 272h140M20 246h140M20 220h140M20 194h140M20 168h140M20 142h140M20 116h140M20 90h140"/>
            <path d="M76 558v-30h28v30"/>

            {{-- Frame going up. Ground floor poured, the rest arrives a storey
                 at a time, starter bars left standing for the next pour. --}}
            <path d="M180 558V500M275 558V500M370 558V500M465 558V500"/>
            <path d="M180 558h285M180 500h285"/>
            <g class="f1">
                <path d="M180 500V442M275 500V442M370 500V442M465 500V442"/>
                <path d="M180 442h285M275 500 180 442"/>
            </g>
            <g class="f2">
                <path d="M180 442V384M275 442V384M370 442V384M465 442V384"/>
                <path d="M180 384h285M180 442 275 384"/>
            </g>
            <g class="f3">
                <path d="M180 384V326M275 384V326M370 384V326M465 384V326"/>
                <path d="M180 326h285"/>
            </g>
            <g class="f4">
                <path d="M180 326V268M275 326V268M370 326V268M465 326V268"/>
                <path d="M180 268h285M275 326 180 268"/>
            </g>
            <g class="f5">
                <path d="M180 268V210M275 268V210M370 268V210M465 268V210"/>
                <path d="M180 210h285"/>
            </g>
            <g class="f6">
                <path d="M174 210V190M186 210V190M269 210V190M281 210V190M364 210V190M376 210V190M459 210V190M471 210V190"/>
            </g>
            <g class="worker w-hammer" transform="translate(225 500)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0M0-18-7-13"/>
                <path class="arm" d="M0-18 8-21M6-24 11-19"/>
            </g>

            {{-- Ground hand carrying a plank across. --}}
            <g class="worker" transform="translate(515 558)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0"/>
                <path d="M0-18-8-22M0-18 8-22M-14-22h28"/>
            </g>

            {{-- Blocks on a pallet. --}}
            <g transform="translate(545 558)">
                <path d="M0-6h46v6H0z"/>
                <path d="M4-24h16v18H4zM26-24h16v18H26z"/>
            </g>

            {{-- Excavator, boom working. --}}
            <g transform="translate(610 558)">
                <path d="M2-18h72l-6 18H8z"/>
                <circle cx="16" cy="-9" r="4"/><circle cx="38" cy="-9" r="4"/><circle cx="60" cy="-9" r="4"/>
                <path d="M16-44h32v26H16z"/>
                <path d="M20-40h12v10H20z"/>
                <g class="boom">
                    <path d="M46-32 80-62 106-44"/>
                    <path d="M54-40 76-54"/>
                    <path d="M106-44 110-31 96-25 92-38z"/>
                </g>
            </g>

            {{-- Tipper. --}}
            <g transform="translate(750 558)">
                <circle cx="20" cy="-11" r="11"/><circle cx="76" cy="-11" r="11"/>
                <path d="M4-22h92"/>
                <path d="M4-56h54v34H4z"/>
                <path d="M62-44h22l10 16v6H62z"/>
            </g>

            {{-- Pipe stacked three high. --}}
            <g transform="translate(862 558)">
                <circle cx="10" cy="-10" r="10"/><circle cx="30" cy="-10" r="10"/><circle cx="50" cy="-10" r="10"/>
                <circle cx="20" cy="-27" r="10"/><circle cx="40" cy="-27" r="10"/>
            </g>

            {{-- Mixer, drum turning. --}}
            <g transform="translate(940 558)">
                <circle cx="20" cy="-11" r="11"/><circle cx="76" cy="-11" r="11"/>
                <path d="M4-22h94"/>
                <path d="M4-48h24l8 16v10H4z"/>
                <path d="M56-58h16l-4 8h-8z"/>
                <circle cx="62" cy="-38" r="20"/>
                <g class="drum">
                    <path d="M62-50a12 12 0 0 1 12 12"/>
                    <path d="M74-38a12 12 0 0 1-12 12"/>
                    <path d="M50-38a12 12 0 0 1 12-12"/>
                </g>
                <path d="M84-32 96-22 86-18z"/>
            </g>

            {{-- Second frame, two storeys behind the first. --}}
            <path d="M1060 558V500M1150 558V500M1240 558V500"/>
            <path d="M1060 558h180M1060 500h180"/>
            <g class="f2">
                <path d="M1060 500V442M1150 500V442M1240 500V442"/>
                <path d="M1060 442h180M1150 500 1060 442"/>
            </g>
            <g class="f3">
                <path d="M1060 442V384M1150 442V384M1240 442V384"/>
                <path d="M1060 384h180"/>
            </g>
            <g class="f4">
                <path d="M1060 384V326M1150 384V326M1240 384V326"/>
                <path d="M1060 326h180M1060 384 1150 326"/>
            </g>
            <g class="f5">
                <path d="M1054 326V306M1066 326V306M1144 326V306M1156 326V306M1234 326V306M1246 326V306"/>
            </g>
            <g class="worker w-signal" transform="translate(1100 500)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0M0-18-7-13"/>
                <path class="arm" d="M0-18 7-27"/>
            </g>

            {{-- Tower crane, jib out over the right frame. --}}
            <path d="M1330 558V150M1352 558V150"/>
            <path d="M1330 558h22M1330 500h22M1330 442h22M1330 384h22M1330 326h22M1330 268h22M1330 210h22M1330 152h22"/>
            <path d="M1330 558 1352 500 1330 442 1352 384 1330 326 1352 268 1330 210 1352 152"/>
            <path d="M1330 150 1341 122 1352 150"/>
            <path d="M1341 122 1200 150M1341 122 1415 150"/>
            <path d="M1330 150H1200M1330 176H1212l-12-26"/>
            <path d="M1330 176 1300 150M1300 176 1270 150M1270 176 1240 150M1240 176 1212 152"/>
            <path d="M1352 150H1415M1352 176H1405M1405 146h22v34h-22z"/>
            <path d="M1330 176h22v24h-22z"/>
            <g class="trolley">
                <path d="M1240 168h16v8h-16z"/>
                <path class="hoist" d="M1248 176v120"/>
                <g class="load">
                    <path d="M1240 296h16v10h-16z"/>
                    <path d="M1248 306 1234 314M1248 306 1262 314M1228 314h40"/>
                </g>
            </g>
        </svg>
    </div>

    <main class="auth-shell">
        <div class="auth-card">
            <header class="brand">
                {{-- The uploaded logo if Settings has one. The fallback is not
                     logoUrl()'s: that returns the full artwork, and this page
                     wants logo-mark.png, the same file trimmed to the disc. --}}
                <img class="brand-mark" alt=""
                     src="{{ $company?->logo_path ? $company->logoUrl() : asset('images/logo-mark.png') }}">
                <p class="brand-name">{{ $company?->company_name ?? 'Jeyanco Construction' }}</p>
                <h1>@yield('heading')</h1>
                <p class="card-lede">@yield('subheading')</p>
            </header>

            @if(session('success'))
                <div class="alert alert-success">
                    <i class="fas fa-circle-check"></i> {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i> {{ $errors->first() }}
                </div>
            @endif

            @yield('form')
        </div>
    </main>

    @yield('after')
    @stack('scripts')
</body>
</html>
