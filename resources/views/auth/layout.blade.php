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
            fill: none; stroke: rgba(255, 255, 255, .155);
            stroke-width: 1.6; stroke-linecap: square;
        }
        .site-art .heavy { stroke: rgba(255, 255, 255, .27); stroke-width: 2.4; }
        .site-art .fine  { stroke: rgba(255, 255, 255, .10); stroke-width: 1; }
        /* Distance, not detail: light enough to sit behind the crane, solid
           enough to read as a building rather than disappear. */
        .site-art .far   { stroke: rgba(255, 255, 255, .135); stroke-width: 1.3; }
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

            {{-- A taller block standing behind the tower. Its floors are drawn
                 only down to 190 -- the height the frame in front of it reaches.
                 Below that it is two walls and nothing else, because a full face
                 of floor lines crossing the tower and the frame turns all three
                 into mush. What a skyline actually does: you draw the part you
                 can see. --}}
            <g class="far">
                <path d="M152 558V34M292 558V34M152 34h140"/>
                <path d="M146 26h152v8h-152z"/>
                <path d="M222 34V200"/>
                <path d="M152 60h140M152 86h140M152 112h140M152 138h140M152 164h140M152 190h140"/>
            </g>

            {{-- Tower already standing. Drawn the way an elevation is: a podium
                 wider than the shaft it carries, a setback crown, floors read
                 as a slab edge with its spandrel under it, and a curtain wall
                 on a mullion rhythm rather than two lines down the middle. --}}
            <path class="heavy" d="M8 558V470h164v88"/>
            <path d="M8 514h164"/>
            <path class="fine" d="M24 470V558M40 470V558M124 470V558M140 470V558M156 470V558"/>
            <path d="M70 558V516h32v42"/>
            <path d="M54 508h64"/>
            <path class="fine" d="M58 508v10M114 508v10"/>

            <path class="heavy" d="M20 470V120M160 470V120M20 120h140"/>
            <path class="heavy" d="M90 470V120"/>
            <path class="fine" d="M34 470V120M48 470V120M62 470V120M76 470V120M104 470V120M118 470V120M132 470V120M146 470V120"/>
            <path d="M20 444h140M20 418h140M20 392h140M20 366h140M20 340h140M20 314h140M20 288h140M20 262h140M20 236h140M20 210h140M20 184h140M20 158h140"/>
            <path class="fine" d="M20 438h140M20 412h140M20 386h140M20 360h140M20 334h140M20 308h140M20 282h140M20 256h140M20 230h140M20 204h140M20 178h140M20 152h140"/>

            <path class="heavy" d="M34 120V78M146 120V78M34 78h112"/>
            <path class="fine" d="M62 120V78M90 120V78M118 120V78M34 98h112"/>
            <path d="M34 104h112"/>
            <path class="heavy" d="M28 78h124v-8H28z"/>

            <path d="M56 70v-16h28v16"/>
            <path class="heavy" d="M118 70V22"/>
            <path d="M112 34h12"/>
            <path class="fine" d="M118 24 98 70M118 24 138 70"/>

            {{-- Frame going up, six storeys. Starter bars wait for the next pour. --}}
            <path class="heavy" d="M180 558V500M256 558V500M332 558V500M408 558V500"/>
            <path d="M180 558h228M180 500h228"/>
            <path class="fine" d="M180 506h228"/>
            <g class="f1">
                <path class="heavy" d="M180 500V442M256 500V442M332 500V442M408 500V442"/>
                <path d="M180 442h228"/>
                <path class="fine" d="M180 448h228M256 500 180 442"/>
            </g>
            <g class="f2">
                <path class="heavy" d="M180 442V384M256 442V384M332 442V384M408 442V384"/>
                <path d="M180 384h228"/>
                <path class="fine" d="M180 390h228M180 442 256 384"/>
            </g>
            <g class="f3">
                <path class="heavy" d="M180 384V326M256 384V326M332 384V326M408 384V326"/>
                <path d="M180 326h228"/>
                <path class="fine" d="M180 332h228"/>
            </g>
            <g class="f4">
                <path class="heavy" d="M180 326V268M256 326V268M332 326V268M408 326V268"/>
                <path d="M180 268h228"/>
                <path class="fine" d="M180 274h228M256 326 180 268"/>
            </g>
            <g class="f5">
                <path class="heavy" d="M180 268V210M256 268V210M332 268V210M408 268V210"/>
                <path d="M180 210h228"/>
                <path class="fine" d="M180 216h228"/>
            </g>
            <g class="f6">
                <path class="fine" d="M174 210V190M186 210V190M250 210V190M262 210V190M326 210V190M338 210V190M402 210V190M414 210V190"/>
            </g>

            <path d="M413 558V442M423 558V442"/>
            <path class="fine" d="M413 558h10M413 520h10M413 482h10M413 444h10"/>
            <path class="fine" d="M413 558 423 520M423 558 413 520M413 520 423 482M423 520 413 482M413 482 423 444M423 482 413 444"/>

            <g class="worker w-hammer" transform="translate(218 500)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0M0-18-7-13"/>
                <path class="arm" d="M0-18 8-21M6-24 11-19"/>
            </g>

            {{-- Blocks on a pallet, inside the ground bay. --}}
            <g transform="translate(268 558)">
                <path d="M0-6h46v6H0z"/>
                <path class="fine" d="M4-24h16v18H4zM26-24h16v18H26z"/>
            </g>

            {{-- Second frame, three storeys and only just out of the ground. --}}
            <path class="heavy" d="M448 558V500M524 558V500M600 558V500"/>
            <path d="M448 558h152M448 500h152"/>
            <path class="fine" d="M448 506h152"/>
            <g class="f1">
                <path class="heavy" d="M448 500V442M524 500V442M600 500V442"/>
                <path d="M448 442h152"/>
                <path class="fine" d="M448 448h152M524 500 448 442"/>
            </g>
            <g class="f2">
                <path class="heavy" d="M448 442V384M524 442V384M600 442V384"/>
                <path d="M448 384h152"/>
                <path class="fine" d="M448 390h152"/>
            </g>
            <g class="f3">
                <path class="fine" d="M442 384V364M454 384V364M518 384V364M530 384V364M594 384V364M606 384V364"/>
            </g>

            <g class="worker worker" transform="translate(486 558)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0M0-18-7-13"/>
                <path class="arm" d="M0-18 7-27"/>
            </g>

            {{-- Excavator, boom working. --}}
            <g transform="translate(626 558)">
                <path d="M2-18h72l-6 18H8z"/>
                <circle cx="16" cy="-9" r="4"/><circle cx="38" cy="-9" r="4"/><circle cx="60" cy="-9" r="4"/>
                <path d="M16-44h32v26H16z"/>
                <path class="fine" d="M20-40h12v10H20z"/>
                <g class="boom">
                    <path d="M46-32 80-62 106-44"/>
                    <path class="fine" d="M54-40 76-54"/>
                    <path d="M106-44 110-31 96-25 92-38z"/>
                </g>
            </g>

            {{-- Mixer, drum turning. --}}
            <g transform="translate(756 558)">
                <circle cx="20" cy="-11" r="11"/><circle cx="76" cy="-11" r="11"/>
                <path d="M4-22h94"/>
                <path d="M4-48h24l8 16v10H4z"/>
                <path class="fine" d="M56-58h16l-4 8h-8z"/>
                <circle cx="62" cy="-38" r="20"/>
                <g class="drum fine">
                    <path d="M62-50a12 12 0 0 1 12 12"/>
                    <path d="M74-38a12 12 0 0 1-12 12"/>
                    <path d="M50-38a12 12 0 0 1 12-12"/>
                </g>
                <path class="fine" d="M84-32 96-22 86-18z"/>
            </g>

            {{-- Third frame, five storeys. --}}
            <path class="heavy" d="M878 558V500M954 558V500M1030 558V500"/>
            <path d="M878 558h152M878 500h152"/>
            <path class="fine" d="M878 506h152"/>
            <g class="f1">
                <path class="heavy" d="M878 500V442M954 500V442M1030 500V442"/>
                <path d="M878 442h152"/>
                <path class="fine" d="M878 448h152"/>
            </g>
            <g class="f2">
                <path class="heavy" d="M878 442V384M954 442V384M1030 442V384"/>
                <path d="M878 384h152"/>
                <path class="fine" d="M878 390h152M878 442 954 384"/>
            </g>
            <g class="f3">
                <path class="heavy" d="M878 384V326M954 384V326M1030 384V326"/>
                <path d="M878 326h152"/>
                <path class="fine" d="M878 332h152"/>
            </g>
            <g class="f4">
                <path class="heavy" d="M878 326V268M954 326V268M1030 326V268"/>
                <path d="M878 268h152"/>
                <path class="fine" d="M878 274h152M954 326 878 268"/>
            </g>
            <g class="f5">
                <path class="fine" d="M872 268V248M884 268V248M948 268V248M960 268V248M1024 268V248M1036 268V248"/>
            </g>

            <g class="worker w-hammer" transform="translate(916 500)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0M0-18-7-13"/>
                <path class="arm" d="M0-18 8-21M6-24 11-19"/>
            </g>

            {{-- Pipe stacked three high, inside the ground bay. --}}
            <g class="fine" transform="translate(890 558)">
                <circle cx="10" cy="-10" r="10"/><circle cx="30" cy="-10" r="10"/><circle cx="50" cy="-10" r="10"/>
                <circle cx="20" cy="-27" r="10"/><circle cx="40" cy="-27" r="10"/>
            </g>

            {{-- Tipper. --}}
            <g transform="translate(1052 558)">
                <circle cx="20" cy="-11" r="11"/><circle cx="76" cy="-11" r="11"/>
                <path d="M4-22h92"/>
                <path d="M4-56h54v34H4z"/>
                <path d="M62-44h22l10 16v6H62z"/>
            </g>

            <path d="M1152 558V442M1162 558V442"/>
            <path class="fine" d="M1152 558h10M1152 520h10M1152 482h10M1152 444h10"/>
            <path class="fine" d="M1152 558 1162 520M1162 558 1152 520M1152 520 1162 482M1162 520 1152 482M1152 482 1162 444M1162 482 1152 444"/>

            {{-- Fourth frame, four storeys, the one the crane is serving. --}}
            <path class="heavy" d="M1168 558V500M1244 558V500M1320 558V500"/>
            <path d="M1168 558h152M1168 500h152"/>
            <path class="fine" d="M1168 506h152"/>
            <g class="f2">
                <path class="heavy" d="M1168 500V442M1244 500V442M1320 500V442"/>
                <path d="M1168 442h152"/>
                <path class="fine" d="M1168 448h152M1244 500 1168 442"/>
            </g>
            <g class="f3">
                <path class="heavy" d="M1168 442V384M1244 442V384M1320 442V384"/>
                <path d="M1168 384h152"/>
                <path class="fine" d="M1168 390h152"/>
            </g>
            <g class="f4">
                <path class="heavy" d="M1168 384V326M1244 384V326M1320 384V326"/>
                <path d="M1168 326h152"/>
                <path class="fine" d="M1168 332h152"/>
            </g>
            <g class="f5">
                <path class="fine" d="M1162 326V306M1174 326V306M1238 326V306M1250 326V306M1314 326V306M1326 326V306"/>
            </g>

            <g class="worker w-signal" transform="translate(1206 500)">
                <path d="M-8.5-29h17M-6-29a6 6 0 0 1 12 0"/>
                <circle cx="0" cy="-26" r="4.5"/>
                <path d="M0-21v12M0-9-6 0M0-9 6 0M0-18-7-13"/>
                <path class="arm" d="M0-18 7-27"/>
            </g>

            {{-- A finished block standing behind the crane. Drawn entirely on
                 the fine pen: the crane in front of it is on the heavy one, so
                 the weight alone puts this one further away. It comes before
                 the crane in the markup because SVG paints in order, and the
                 crane has to cross it rather than the other way round. --}}
            <g class="far">
                <path d="M1318 558V56M1440 558V56M1318 56h122"/>
                <path d="M1312 48h134v8h-134z"/>
                <path d="M1344 48v-14h22v14"/>
                <path d="M1358 558V56M1400 558V56"/>
                <path d="M1318 534h122M1318 510h122M1318 486h122M1318 462h122M1318 438h122M1318 414h122M1318 390h122M1318 366h122M1318 342h122M1318 318h122M1318 294h122M1318 270h122M1318 246h122M1318 222h122M1318 198h122M1318 174h122M1318 150h122M1318 126h122M1318 102h122M1318 78h122"/>
            </g>

            {{-- A second block behind the right-hand frame, so the mass at
                 this end answers the pair at the other. Floors stop at 320,
                 the height the frame in front reaches; below that it is two
                 walls, for the same reason as the one on the left. --}}
            <g class="far">
                <path d="M1096 558V100M1246 558V100M1096 100h150"/>
                <path d="M1090 92h162v8h-162z"/>
                <path d="M1171 100V300"/>
                <path d="M1096 126h150M1096 152h150M1096 178h150M1096 204h150M1096 230h150M1096 256h150M1096 282h150M1096 308h150"/>
            </g>

            {{-- Tower crane, jib out over the right frame. --}}
            <path class="heavy" d="M1330 558V150M1352 558V150"/>
            <path d="M1330 558h22M1330 500h22M1330 442h22M1330 384h22M1330 326h22M1330 268h22M1330 210h22M1330 152h22"/>
            <path d="M1330 558 1352 500 1330 442 1352 384 1330 326 1352 268 1330 210 1352 152"/>
            <path d="M1330 150 1341 122 1352 150"/>
            <path class="fine" d="M1341 122 1200 150M1341 122 1415 150"/>
            <path d="M1330 150H1200M1330 176H1212l-12-26"/>
            <path d="M1330 176 1300 150M1300 176 1270 150M1270 176 1240 150M1240 176 1212 152"/>
            <path d="M1352 150H1415M1352 176H1405M1405 146h22v34h-22z"/>
            <path d="M1330 176h22v24h-22z"/>
            <g class="trolley">
                <path d="M1240 168h16v8h-16z"/>
                <path class="hoist fine" d="M1248 176v120"/>
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
