<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Jeyanco | @yield('title', 'Admin')</title>

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
        .site-art svg {
            position: absolute; bottom: 0;
            fill: none; stroke: rgba(255, 255, 255, .16);
            stroke-width: 2; stroke-linecap: square;
        }
        .art-frame { left: 5vw;  width: 420px; }
        .art-crane { right: 6vw; width: 330px; }

        /* Below this the card is most of the window and the drawings only
           crowd it. */
        @media (max-width: 1080px) { .site-art { display: none; } }

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
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="site-art" aria-hidden="true">
        {{-- Frame under construction: four columns, five slabs, starter bars
             left standing for the next pour. --}}
        <svg class="art-frame" viewBox="0 0 420 320">
            <path d="M40 320V60M150 320V60M260 320V60M370 320V60"/>
            <path d="M40 320h330M40 260h330M40 200h330M40 140h330M40 80h330"/>
            <path d="M40 260l110-60M150 200L40 140"/>
            <path d="M34 60V38M46 60V38M144 60V38M156 60V38M254 60V38M266 60V38M364 60V38M376 60V38"/>
        </svg>

        {{-- Tower crane: mast, A-frame, jib and counter-jib on their tie bars. --}}
        <svg class="art-crane" viewBox="0 0 330 470">
            <path d="M150 470V90M180 470V90"/>
            <path d="M150 470h30M150 407h30M150 344h30M150 281h30M150 218h30M150 155h30M150 90h30"/>
            <path d="M150 470l30-63L150 344l30-63L150 218l30-63L150 90"/>
            <path d="M150 90l15-34 15 34"/>
            <path d="M165 56l145 34M165 56 70 90"/>
            <path d="M180 90h130M180 120h118l12-30"/>
            <path d="M180 120 210 90M210 120 240 90M240 120 270 90M270 120 298 92"/>
            <path d="M150 90H70M150 120H84"/>
            <path d="M58 86h26v38H58z"/>
            <path d="M150 120h30v26h-30z"/>
            <path d="M266 112h16v10h-16z"/>
            <path d="M274 122v168M266 290h16v13h-16z"/>
        </svg>
    </div>

    <main class="auth-shell">
        <div class="auth-card">
            <header class="brand">
                {{-- logo-mark.png is the logo trimmed to the disc and re-centred. --}}
                <img class="brand-mark" src="{{ asset('images/logo-mark.png') }}" alt="">
                <p class="brand-name">Jeyanco Construction</p>
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
