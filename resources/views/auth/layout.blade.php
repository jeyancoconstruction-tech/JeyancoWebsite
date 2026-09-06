<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Jeyanco | @yield('title', 'Admin')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            /* Jeyanco corporate palette — navy shell, single corporate blue accent. */
            --navy-900: #071a33;
            --navy-800: #0c2447;
            --navy-700: #12305a;
            --navy-600: #17406f;
            --blue: #1a73d8;
            --blue-dark: #1560bd;
            --blue-soft: #a9c8ee;
            --ink: #10294a;
            --ink-soft: #44566f;
            --muted: #8c9aae;
            --line: #dfe5ee;
            --danger: #dc2626;
            --success: #15803d;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            color: var(--ink);
            background: var(--navy-800);
            /* Only sideways overflow is unwanted — the card must stay scrollable
               on short screens once an error alert and the hints appear. */
            overflow-x: hidden;
        }

        /* ---------- Site backdrop ---------- */
        .auth-bg,
        .auth-scrim { position: fixed; inset: 0; z-index: 0; pointer-events: none; }

        .auth-bg {
            background: #0d2340 url("{{ asset('images/login-background.png') }}") center / cover no-repeat;
            /* Breathes between 1 and 1.06 rather than looping back to the start,
               so there is never a jump. Scaling up only ever hides edges. */
            animation: siteDrift 24s ease-in-out infinite alternate;
            will-change: transform;
        }
        @keyframes siteDrift { from { transform: scale(1); } to { transform: scale(1.06); } }
        /* The photograph is already graded and carries its own angled panels, so
           this only has to hold contrast under the branding copy. */
        .auth-scrim {
            background:
                linear-gradient(90deg,
                    rgba(6, 20, 42, 0.52) 0%,
                    rgba(7, 22, 45, 0.26) 28%,
                    rgba(8, 24, 48, 0.08) 52%,
                    rgba(7, 20, 42, 0.18) 78%,
                    rgba(5, 16, 36, 0.38) 100%),
                linear-gradient(180deg,
                    rgba(5, 16, 34, 0.34) 0%,
                    rgba(5, 16, 34, 0.00) 46%,
                    rgba(4, 12, 28, 0.22) 100%);
        }
        /* ---------- Split shell ---------- */
        .auth-shell {
            position: relative; z-index: 1;
            display: flex; align-items: stretch;
            min-height: 100vh;
        }

        /* ---------- Left: branding ---------- */
        .auth-brand {
            flex: 1 1 56%;
            display: flex; flex-direction: column; justify-content: flex-start;
            padding: 14vh 40px 40px 7.2vw;
            min-width: 0;
        }
        .brand-lockup { display: flex; align-items: center; gap: 26px; }
        .brand-mark {
            width: 108px; height: 108px; flex-shrink: 0;
            border-radius: 50%;
            object-fit: contain;
            box-shadow: 0 10px 30px rgba(3, 12, 28, 0.45);
        }
        .brand-words { line-height: 1; }
        .brand-name {
            display: block;
            font-size: clamp(34px, 3.3vw, 54px);
            font-weight: 800; color: #fff; letter-spacing: 0.5px;
        }
        .brand-sub {
            display: block; margin-top: 12px;
            font-size: clamp(15px, 1.55vw, 25px);
            font-weight: 400; color: var(--blue-soft);
            letter-spacing: 0.42em; text-transform: uppercase;
        }
        .brand-tagline {
            margin-top: 44px;
            font-size: clamp(17px, 1.5vw, 24px);
            font-weight: 400; color: #d5e4f6; letter-spacing: 0.2px;
        }
        .brand-tagline strong { font-weight: 700; color: #fff; }
        .brand-rule {
            display: block; width: 108px; height: 5px; margin-top: 24px;
            border-radius: 3px;
            background: linear-gradient(90deg, #2f86e6 0%, #1a73d8 100%);
        }

        /* ---------- Right: login card ---------- */
        .auth-panel {
            flex: 0 1 46%;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 5vw 40px 20px;
            min-width: 0;
        }
        .login-card {
            width: 100%; max-width: 610px;
            background: #f7f9fc;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 34px 70px -18px rgba(2, 10, 24, 0.62), 0 4px 14px rgba(2, 10, 24, 0.3);
            animation: cardIn 0.5s cubic-bezier(0.22, 0.61, 0.36, 1);
        }
        @keyframes cardIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

        .card-head {
            position: relative;
            display: flex; align-items: center; gap: 22px;
            padding: 34px 46px;
            background: #0e2a50 url("{{ asset('images/login-background.png') }}") 62% 38% / 240% auto no-repeat;
        }
        .card-head::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(100deg, rgba(9, 30, 60, 0.90) 0%, rgba(11, 35, 68, 0.80) 100%);
        }
        .card-head > * { position: relative; z-index: 1; }
        .card-mark {
            width: 88px; height: 88px; flex-shrink: 0;
            border-radius: 50%; object-fit: contain;
        }
        .card-words { line-height: 1; min-width: 0; }
        .card-name { display: block; font-size: 33px; font-weight: 800; color: #fff; letter-spacing: 0.4px; }
        .card-sub-name {
            display: block; margin-top: 9px;
            font-size: 15px; font-weight: 400; color: var(--blue-soft);
            letter-spacing: 0.4em; text-transform: uppercase;
        }

        .card-body { padding: 36px 46px 34px; background: #f7f9fc; }

        .card-body h1 {
            font-size: 33px; font-weight: 800; color: #10294a; letter-spacing: -0.3px;
        }
        .card-body h1 .accent { color: var(--blue); }
        .card-lede { margin-top: 8px; font-size: 15px; color: #6b7a90; font-weight: 400; }

        /* ---------- Form ---------- */
        /* All three auth views post a bare <form>; the gap belongs to any of them. */
        .card-body form { margin-top: 26px; }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: flex; align-items: center; gap: 10px;
            font-size: 15px; font-weight: 600; color: #16304f;
            margin-bottom: 10px;
        }
        .form-group label i { font-size: 15px; color: #16304f; width: 16px; text-align: center; }

        .input-wrap { position: relative; }
        .form-group input {
            width: 100%; height: 52px;
            background: #fff;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            padding: 0 16px;
            font-size: 15px; color: var(--ink); font-family: inherit;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }
        .form-group input::placeholder { color: #9dabbe; }
        .form-group input:hover:not(:focus) { border-color: #c6d1e0; }
        .form-group input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(26, 115, 216, 0.16);
        }
        /* Room for the show/hide button. Kept as its own rule so browsers that
           do not know :has() still apply the type-based half below. */
        .form-group input[type="password"] { padding-right: 50px; }
        .input-wrap:has(.toggle-pass) input { padding-right: 50px; }

        /* Chrome paints autofilled fields pale yellow; repaint them white so a
           remembered login still matches the card. */
        .form-group input:-webkit-autofill,
        .form-group input:-webkit-autofill:hover,
        .form-group input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--ink);
            -webkit-box-shadow: 0 0 0 1000px #fff inset;
            caret-color: var(--ink);
            transition: background-color 9999s ease-out 0s;
        }
        .form-group.has-error input { border-color: rgba(220, 38, 38, 0.65); background: #fff7f7; }
        .form-group.has-error input:focus { box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.14); }

        .toggle-pass {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #7d8ca1; cursor: pointer;
            padding: 9px 10px; font-size: 16px; border-radius: 8px;
            transition: color 0.18s ease;
        }
        .toggle-pass:hover { color: var(--blue); }
        .toggle-pass:focus-visible { outline: 2px solid var(--blue); outline-offset: 1px; }

        .field-hint { margin-top: 8px; font-size: 12.5px; color: #7e8ca0; font-weight: 400; }
        /* Guidance kept for screen readers where the reference layout shows none. */
        .field-hint.sr-only {
            position: absolute; width: 1px; height: 1px; margin: -1px;
            padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
        }

        .caps-hint {
            display: none; align-items: center; gap: 7px;
            margin-top: 8px; font-size: 12.5px; font-weight: 600; color: #b45309;
        }
        .caps-hint.show { display: flex; }

        .form-options {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; margin: 18px 0 26px;
        }
        .remember-me { display: flex; align-items: center; gap: 11px; }
        .remember-me input[type="checkbox"] {
            appearance: none; -webkit-appearance: none;
            width: 20px; height: 20px; flex-shrink: 0; margin: 0; cursor: pointer;
            background: #fff; border: 1.5px solid #c3cddb; border-radius: 5px;
            position: relative;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .remember-me input[type="checkbox"]:hover { border-color: #93a5bd; }
        .remember-me input[type="checkbox"]:checked { background: var(--blue); border-color: var(--blue); }
        .remember-me input[type="checkbox"]:checked::after {
            content: ''; position: absolute;
            left: 6px; top: 2px; width: 5px; height: 10px;
            border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
        }
        .remember-me input[type="checkbox"]:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        .remember-me label { margin: 0; cursor: pointer; font-size: 14.5px; font-weight: 400; color: #46586f; }

        .forgot-link {
            font-size: 14.5px; font-weight: 600; color: var(--blue);
            text-decoration: none; white-space: nowrap;
        }
        .forgot-link:hover { text-decoration: underline; }
        .forgot-link:focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; border-radius: 4px; }

        .btn-login {
            width: 100%; height: 56px;
            display: flex; align-items: center; justify-content: center; gap: 11px;
            background: var(--blue); color: #fff;
            border: none; border-radius: 10px;
            font-family: inherit; font-size: 16.5px; font-weight: 700; letter-spacing: 0.2px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(26, 115, 216, 0.26);
            transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .btn-login i { font-size: 17px; }
        .btn-login:hover:not(:disabled) { background: var(--blue-dark); transform: translateY(-1px); box-shadow: 0 12px 24px rgba(21, 96, 189, 0.36); }
        .btn-login:active:not(:disabled) { transform: translateY(0); box-shadow: 0 5px 12px rgba(21, 96, 189, 0.3); }
        .btn-login:focus-visible { outline: 3px solid rgba(26, 115, 216, 0.45); outline-offset: 2px; }
        .btn-login:disabled { opacity: 0.72; cursor: not-allowed; }

        .secure-note {
            margin-top: 26px; padding-top: 20px;
            border-top: 1px solid #e7ecf3;
            display: flex; align-items: center; justify-content: center; gap: 11px;
        }
        .secure-note i { font-size: 19px; color: #14355f; }
        .secure-note .secure-text { line-height: 1.45; }
        .secure-note .secure-title { display: block; font-size: 13px; font-weight: 600; color: #3f5169; }
        .secure-note .secure-meta { display: block; font-size: 12px; font-weight: 400; color: #94a3b8; }

        .login-footer { margin-top: 22px; text-align: center; }
        .login-footer p { font-size: 14px; color: #6b7a90; }
        .login-footer a { color: var(--blue); text-decoration: none; font-weight: 600; }
        .login-footer a:hover { text-decoration: underline; }
        .login-footer a:focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; border-radius: 4px; }

        /* ---------- Alerts ---------- */
        .alert {
            border-radius: 10px; padding: 13px 15px; margin-top: 22px;
            font-size: 13.5px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
            animation: slideDown 0.25s ease;
        }
        .alert i { font-size: 15px; flex-shrink: 0; }
        .alert-error { background: #fdf0f0; border: 1px solid #f4cccc; color: #a92222; }
        .alert-success { background: #eef8f1; border: 1px solid #c6e6d1; color: #15683a; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        /* ---------- Responsive ---------- */
        @media (max-width: 1400px) {
            .auth-brand { padding-left: 5.5vw; }
            .auth-panel { padding-right: 4vw; }
            .login-card { max-width: 560px; }
            .card-head { padding: 30px 40px; }
            .card-mark { width: 80px; height: 80px; }
            .card-name { font-size: 30px; }
            .card-body { padding: 32px 40px 30px; }
            .card-body h1 { font-size: 30px; }
        }

        @media (max-width: 1180px) {
            .auth-brand { flex-basis: 46%; padding-top: 12vh; }
            .brand-mark { width: 84px; height: 84px; }
            .brand-lockup { gap: 18px; }
            .brand-tagline { margin-top: 34px; }
            .login-card { max-width: 500px; }
        }

        /* Tablet and below: one column, branding stacked above the card. */
        @media (max-width: 900px) {
            .auth-shell { flex-direction: column; align-items: center; }
            .auth-brand {
                flex: 0 0 auto; width: 100%;
                align-items: center; text-align: center;
                padding: 48px 24px 12px;
            }
            .brand-lockup { justify-content: center; gap: 16px; }
            .brand-mark { width: 74px; height: 74px; }
            .brand-name { font-size: 34px; }
            .brand-sub { font-size: 14px; margin-top: 9px; }
            .brand-tagline { margin-top: 22px; font-size: 17px; }
            .brand-rule { margin: 18px auto 0; }
            .auth-panel {
                flex: 1 1 auto; width: 100%;
                padding: 28px 24px 48px;
            }
            .login-card { max-width: 480px; }
        }

        @media (max-width: 560px) {
            .auth-brand { padding: 36px 20px 8px; }
            .brand-lockup { flex-direction: column; gap: 14px; }
            .brand-name { font-size: 28px; }
            .brand-sub { font-size: 12px; letter-spacing: 0.34em; }
            .brand-tagline { font-size: 15.5px; margin-top: 18px; }
            .auth-panel { padding: 24px 16px 40px; }
            .card-head { padding: 24px 24px; gap: 16px; }
            .card-mark { width: 62px; height: 62px; }
            .card-name { font-size: 24px; }
            .card-sub-name { font-size: 12px; letter-spacing: 0.3em; }
            .card-body { padding: 26px 24px 26px; }
            .card-body h1 { font-size: 25px; }
            .card-lede { font-size: 14px; }
            .form-options { flex-wrap: wrap; }
        }

        @media (max-height: 720px) and (min-width: 901px) {
            .auth-brand { padding-top: 10vh; }
            .auth-panel { padding-top: 28px; padding-bottom: 28px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
            .auth-bg { animation: none; transform: none; }
            .btn-login:hover:not(:disabled) { transform: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="auth-bg" aria-hidden="true"></div>
    <div class="auth-scrim" aria-hidden="true"></div>

    <div class="auth-shell">
        <section class="auth-brand">
            <div class="brand-lockup">
                <img class="brand-mark" src="{{ asset('images/JeyancoLogo.png') }}" alt="Jeyanco Construction">
                <div class="brand-words">
                    <span class="brand-name">{{ __('JEYANCO') }}</span>
                    <span class="brand-sub">{{ __('Construction') }}</span>
                </div>
            </div>
            <p class="brand-tagline"><strong>{{ __('Build Today.') }}</strong> {{ __('Stronger Tomorrow.') }}</p>
            <span class="brand-rule" aria-hidden="true"></span>
        </section>

        <section class="auth-panel">
            <div class="login-card">
                <header class="card-head">
                    <img class="card-mark" src="{{ asset('images/JeyancoLogo.png') }}" alt="" aria-hidden="true">
                    <div class="card-words">
                        <span class="card-name">{{ __('JEYANCO') }}</span>
                        <span class="card-sub-name">{{ __('Construction') }}</span>
                    </div>
                </header>

                <div class="card-body">
                    <h1>@yield('heading')</h1>
                    <p class="card-lede">@yield('subheading')</p>

                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
                        </div>
                    @endif

                    @yield('form')
                </div>
            </div>
        </section>
    </div>

    @yield('after')
    @stack('scripts')
</body>
</html>
