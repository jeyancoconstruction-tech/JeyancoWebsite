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
        /* ─────────────────────────────────────────────────────────────────
           Jeyanco sign-in.

           One white card on a pale ground: a navy brand panel on the left,
           the form on the right. The corner shapes are drawn in CSS rather
           than placed as artwork, so there is no photograph anywhere on this
           page and nothing to download before it looks finished.
           ───────────────────────────────────────────────────────────────── */
        :root {
            --navy-900: #0d2a4f;
            --navy-800: #123566;
            --navy-700: #17417a;
            --navy-edge: #1e4a8a;

            --blue:      #1668dc;
            --blue-dark: #1257bc;
            --blue-soft: #a9c8ee;

            --ink:       #0f2544;
            --ink-soft:  #46586f;
            --muted:     #6b7a90;
            --line:      #dde3ec;
            --ground:    #f1f3f7;

            --danger:  #d0342c;
            --success: #12874a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            color: var(--ink);
            background: var(--ground);
            display: flex; align-items: center; justify-content: center;
            padding: 32px 20px;
            overflow-x: hidden;
        }

        /* ── Corner shapes ────────────────────────────────────────────────
           Two navy blocks with one curved edge each, and a hairline arc
           echoing the curve. Fixed, behind everything, and inert. */
        .corner { position: fixed; z-index: 0; pointer-events: none; }

        .corner-tl {
            top: 0; left: 0;
            width: clamp(180px, 22vw, 340px);
            height: clamp(120px, 17vh, 190px);
            background: linear-gradient(135deg, var(--navy-edge) 0%, var(--navy-800) 100%);
            border-bottom-right-radius: 100% 100%;
        }
        .corner-tl::after {
            content: ''; position: absolute;
            top: 0; left: 0; width: 116%; height: 140%;
            border-bottom-right-radius: 100% 100%;
            border-right: 2px solid rgba(30, 74, 138, 0.45);
            border-bottom: 2px solid rgba(30, 74, 138, 0.45);
        }

        .corner-br {
            bottom: 0; right: 0;
            width: clamp(180px, 22vw, 340px);
            height: clamp(120px, 17vh, 190px);
            background: linear-gradient(315deg, var(--navy-edge) 0%, var(--navy-800) 100%);
            border-top-left-radius: 100% 100%;
        }
        .corner-br::after {
            content: ''; position: absolute;
            bottom: 0; right: 0; width: 116%; height: 140%;
            border-top-left-radius: 100% 100%;
            border-left: 2px solid rgba(30, 74, 138, 0.45);
            border-top: 2px solid rgba(30, 74, 138, 0.45);
        }

        /* ── The card ─────────────────────────────────────────────────── */
        .auth-card {
            position: relative; z-index: 1;
            width: min(1120px, 100%);
            display: grid; grid-template-columns: 0.86fr 1fr;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 24px 70px rgba(13, 42, 79, 0.13),
                        0 2px 6px rgba(13, 42, 79, 0.05);
            overflow: hidden;
        }

        /* ── Brand panel ──────────────────────────────────────────────── */
        .auth-brand {
            background: linear-gradient(168deg, var(--navy-700) 0%, var(--navy-800) 46%, var(--navy-900) 100%);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 74px 44px;
            text-align: center;
            position: relative;
        }

        .brand-mark {
            width: 170px; height: 170px;
            border-radius: 50%;
            object-fit: contain;
            display: block;
            /* The mark is a filled blue disc and the panel behind it is navy.
               The ring is what separates them, and it is what the reference
               shows. */
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.85);
        }

        .brand-tagline {
            margin-top: 30px;
            font-size: 21px; font-weight: 400; letter-spacing: 0.01em;
            color: #eaf1fb;
            line-height: 1.35;
        }

        .brand-rule {
            display: block; width: 62px; height: 3px; border-radius: 3px;
            background: #2d7ff0; margin: 22px auto 0;
        }

        /* ── Form panel ───────────────────────────────────────────────── */
        .auth-panel {
            padding: 72px 60px 60px;
            display: flex; flex-direction: column; justify-content: center;
            background: #fff;
        }

        .panel-eyebrow {
            font-size: 11.5px; font-weight: 700; letter-spacing: 0.18em;
            text-transform: uppercase; color: var(--blue);
            margin-bottom: 14px;
        }

        .auth-panel h1 {
            font-size: 33px; font-weight: 700; letter-spacing: -0.025em;
            color: var(--ink); line-height: 1.15;
        }
        .auth-panel h1 .accent { color: var(--blue); }

        .card-lede {
            margin-top: 12px;
            font-size: 14.5px; line-height: 1.55; color: var(--muted);
            max-width: 42ch;
        }

        /* ── Alerts ───────────────────────────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            margin-top: 20px; padding: 11px 14px;
            border-radius: 9px; font-size: 13.5px; line-height: 1.45;
        }
        .alert i { margin-top: 2px; flex: none; }
        .alert-success { background: #e7f5ed; color: var(--success); }
        .alert-error   { background: #fcecec; color: var(--danger); }

        /* ── Fields ───────────────────────────────────────────────────── */
        .login-form { margin-top: 26px; }

        .form-group { margin-bottom: 18px; }
        .form-group > label {
            display: block; margin-bottom: 8px;
            font-size: 13.5px; font-weight: 600; color: #16283f;
        }

        .input-wrap { position: relative; display: flex; align-items: center; }

        .input-wrap > i.field-icon {
            position: absolute; left: 15px;
            color: #98a4b5; font-size: 14px; pointer-events: none;
        }

        .input-wrap input {
            width: 100%; height: 50px;
            padding: 0 15px 0 43px;
            font-family: inherit; font-size: 14.5px; color: var(--ink);
            background: #fff;
            border: 1px solid var(--line); border-radius: 9px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .input-wrap input::placeholder { color: #a3aebd; }
        .input-wrap input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(22, 104, 220, 0.10);
        }
        .input-wrap input:focus ~ i.field-icon { color: var(--blue); }

        /* A field with no icon (the reset form's confirmation box). */
        .input-wrap.no-icon input { padding-left: 15px; }

        .has-error .input-wrap input { border-color: var(--danger); }

        .toggle-pass {
            position: absolute; right: 6px;
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            background: none; border: none; border-radius: 7px;
            color: #98a4b5; cursor: pointer; font-size: 14px;
            transition: color .15s ease, background .15s ease;
        }
        .toggle-pass:hover { color: var(--blue); background: #f2f6fc; }
        .toggle-pass:focus-visible { outline: 2px solid var(--blue); outline-offset: 1px; }

        .field-hint { display: block; margin-top: 7px; font-size: 12.5px; color: var(--muted); }

        .caps-hint {
            display: none; align-items: center; gap: 6px;
            margin-top: 7px; font-size: 12.5px; color: #b7791f;
        }
        .caps-hint.show { display: flex; }

        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }

        /* ── Options row ──────────────────────────────────────────────── */
        .form-options {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; margin: 4px 0 24px;
        }

        .remember-me { display: flex; align-items: center; gap: 9px; }
        .remember-me input[type="checkbox"] {
            appearance: none; -webkit-appearance: none;
            width: 19px; height: 19px; flex: none;
            border: 1.5px solid #c6cfdb; border-radius: 5px;
            background: #fff; cursor: pointer; position: relative;
            transition: background .15s ease, border-color .15s ease;
        }
        .remember-me input[type="checkbox"]:checked {
            background: var(--blue); border-color: var(--blue);
        }
        .remember-me input[type="checkbox"]:checked::after {
            content: ''; position: absolute;
            left: 6px; top: 2px; width: 5px; height: 10px;
            border: solid #fff; border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .remember-me input[type="checkbox"]:focus-visible {
            outline: 2px solid var(--blue); outline-offset: 2px;
        }
        .remember-me label {
            margin: 0; cursor: pointer;
            font-size: 14px; font-weight: 400; color: #46586f;
        }

        .forgot-link {
            font-size: 14px; font-weight: 500; color: var(--blue);
            text-decoration: none; white-space: nowrap;
        }
        .forgot-link:hover { text-decoration: underline; }

        /* ── Button ───────────────────────────────────────────────────── */
        .btn-login {
            width: 100%; height: 52px;
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            font-family: inherit; font-size: 15.5px; font-weight: 600; color: #fff;
            background: var(--blue); border: none; border-radius: 9px;
            cursor: pointer;
            transition: background .15s ease, box-shadow .15s ease;
        }
        .btn-login:hover:not(:disabled) {
            background: var(--blue-dark);
            box-shadow: 0 6px 18px rgba(22, 104, 220, 0.28);
        }
        .btn-login:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        .btn-login:disabled { opacity: .72; cursor: progress; }

        /* ── Footer rule ──────────────────────────────────────────────── */
        .login-footer {
            display: flex; align-items: center; gap: 14px;
            margin-top: 26px;
            font-size: 13px; color: var(--muted);
        }
        .login-footer::before,
        .login-footer::after {
            content: ''; flex: 1 1 auto; height: 1px; background: var(--line);
        }
        .login-footer a { color: var(--blue); text-decoration: none; font-weight: 500; }
        .login-footer a:hover { text-decoration: underline; }

        /* ── Responsive ───────────────────────────────────────────────── */
        @media (max-width: 900px) {
            .auth-card { grid-template-columns: 1fr; max-width: 480px; }
            .auth-brand { padding: 34px 28px 30px; }
            .brand-mark { width: 96px; height: 96px; }
            .brand-tagline { margin-top: 16px; font-size: 17px; }
            .brand-rule { margin-top: 14px; }
            .auth-panel { padding: 32px 28px 28px; }
            .auth-panel h1 { font-size: 27px; }
        }

        @media (max-width: 480px) {
            body { padding: 16px 12px; }
            .auth-panel { padding: 26px 20px 22px; }
            .auth-panel h1 { font-size: 24px; }
            .form-options { flex-wrap: wrap; }
        }

        /* Short laptops: the card must not need the page to scroll. */
        @media (min-width: 901px) and (max-height: 760px) {
            body { padding: 18px 20px; }
            .auth-brand { padding: 34px 36px; }
            .brand-mark { width: 128px; height: 128px; }
            .brand-tagline { margin-top: 20px; font-size: 18px; }
            .auth-panel { padding: 34px 46px 30px; }
            .auth-panel h1 { font-size: 28px; }
            .card-lede { margin-top: 9px; font-size: 13.5px; }
            .login-form { margin-top: 20px; }
            .form-group { margin-bottom: 14px; }
            .input-wrap input { height: 46px; }
            .btn-login { height: 48px; }
            .login-footer { margin-top: 20px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="corner corner-tl" aria-hidden="true"></div>
    <div class="corner corner-br" aria-hidden="true"></div>

    <main class="auth-card">
        <section class="auth-brand">
            <img class="brand-mark" src="{{ asset('images/JeyancoLogo.png') }}" alt="Jeyanco Construction">
            <p class="brand-tagline">{{ __('Building a Better Tomorrow') }}</p>
            <span class="brand-rule" aria-hidden="true"></span>
        </section>

        <section class="auth-panel">
            <p class="panel-eyebrow">@yield('eyebrow', __('Welcome back'))</p>
            <h1>@yield('heading')</h1>
            <p class="card-lede">@yield('subheading')</p>

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
        </section>
    </main>

    @yield('after')
    @stack('scripts')
</body>
</html>
