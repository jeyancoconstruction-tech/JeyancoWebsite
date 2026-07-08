<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Jeyanco | Admin Login</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-mid: #2563eb;
            --accent: #6366f1;
            --danger: #ef4444;
            --text-muted: #94a3b8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #e2e8f0;
            /* Brand gradient — no external image, works fully offline */
            background:
                radial-gradient(circle at 15% 20%, rgba(37, 99, 235, 0.18) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(99, 102, 241, 0.16) 0%, transparent 45%),
                linear-gradient(135deg, #0b1120 0%, #0f172a 55%, #111c3a 100%);
            position: relative;
            overflow: hidden;
        }
        /* subtle grid accent */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(148,163,184,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
            pointer-events: none;
        }

        .login-container { position: relative; z-index: 1; width: 100%; max-width: 420px; }

        .login-card {
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(99, 102, 241, 0.22);
            border-radius: 20px;
            padding: 44px 38px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.35s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

        .login-header { text-align: center; margin-bottom: 32px; }
        .logo-container { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 18px; }
        .logo-icon { width: 46px; height: 46px; flex-shrink: 0; border-radius: 50%; overflow: hidden; }
        .logo-icon img { width: 100%; height: 100%; object-fit: contain; }
        .logo-text { font-size: 16px; font-weight: 800; color: #fff; letter-spacing: 0.5px; line-height: 1.2; text-align: left; }
        .logo-text small { display: block; font-size: 10px; font-weight: 600; color: var(--accent); letter-spacing: 1.5px; text-transform: uppercase; }

        .login-header h1 { font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 6px; }
        .login-header p { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; font-size: 11px; font-weight: 700; color: #cbd5e1;
            text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px;
        }
        .input-wrap { position: relative; }
        .input-wrap > i.lead {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 14px; pointer-events: none;
        }
        .form-group input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 11px;
            padding: 13px 44px 13px 40px;
            font-size: 14px; color: #fff; font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
        }
        .form-group input::placeholder { color: rgba(148, 163, 184, 0.5); }
        .form-group input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-mid);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
        }
        .form-group.has-error input { border-color: rgba(239, 68, 68, 0.6); }

        .toggle-pass {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--text-muted); cursor: pointer;
            padding: 8px; font-size: 14px; border-radius: 8px; transition: color 0.2s;
        }
        .toggle-pass:hover { color: #fff; }

        .form-options { display: flex; align-items: center; justify-content: space-between; margin: 20px 0 24px; font-size: 13px; }
        .remember-me { display: flex; align-items: center; gap: 8px; color: var(--text-muted); }
        .remember-me input[type="checkbox"] { width: 15px; height: 15px; cursor: pointer; accent-color: var(--primary-mid); }
        .remember-me label { cursor: pointer; margin: 0; font-weight: 500; }
        .forgot-hint { color: var(--text-muted); font-weight: 500; cursor: default; }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            color: #fff; border: none; border-radius: 11px;
            padding: 13px 16px; font-size: 14px; font-weight: 700;
            letter-spacing: 0.4px; cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.35);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(30, 58, 138, 0.45); }
        .btn-login:active:not(:disabled) { transform: translateY(0); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }

        .login-footer { margin-top: 22px; text-align: center; }
        .login-footer p { font-size: 13px; color: var(--text-muted); }
        .login-footer a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .login-footer a:hover { text-decoration: underline; }

        .secure-note {
            margin-top: 18px; text-align: center; font-size: 11px; color: var(--text-muted);
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .secure-note i { color: #22c55e; }

        .alert {
            border-radius: 11px; padding: 12px 14px; margin-bottom: 22px; font-size: 12.5px;
            font-weight: 500; display: flex; align-items: center; gap: 9px;
            animation: slideDown 0.25s ease;
        }
        .alert i { font-size: 14px; flex-shrink: 0; }
        .alert-error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; }
        .alert-success { background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35); color: #86efac; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 640px) {
            .login-card { padding: 34px 24px; }
            .login-header h1 { font-size: 21px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <div class="logo-icon"><img src="{{ asset('images/JeyancoLogo.png') }}" alt="Jeyanco Logo"></div>
                    <div class="logo-text">JEYANCO<small>Construction</small></div>
                </div>
                <h1>Admin Login</h1>
                <p>Sign in to access the management dashboard</p>
            </div>

            <form action="{{ route('login.post') }}" method="POST" class="login-form" id="loginForm" autocomplete="on">
                @csrf

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

                <div class="form-group {{ $errors->has('username') ? 'has-error' : '' }}">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <i class="fas fa-user lead"></i>
                        <input type="text" id="username" name="username" value="{{ old('username') }}"
                               required autofocus autocomplete="username" placeholder="Enter your username">
                    </div>
                </div>

                <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock lead"></i>
                        <input type="password" id="password" name="password"
                               required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="toggle-pass" id="togglePass" aria-label="Show password" title="Show / hide password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label for="remember">Remember me</label>
                    </div>
                    <span class="forgot-hint" title="Please contact your system administrator to reset your password.">Forgot password?</span>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-right-to-bracket"></i>
                    <span class="btn-label">Sign In</span>
                </button>

                <div class="secure-note">
                    <i class="fas fa-shield-halved"></i> Secured administrator access &middot; authorized personnel only
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password show/hide
        (function () {
            const btn = document.getElementById('togglePass');
            const input = document.getElementById('password');
            btn.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                input.focus();
            });
        })();

        // Submit loading state (prevents double submit, gives feedback)
        (function () {
            const form = document.getElementById('loginForm');
            const btn = document.getElementById('loginBtn');
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.querySelector('i').className = 'fas fa-circle-notch fa-spin';
                btn.querySelector('.btn-label').textContent = 'Signing in...';
            });
        })();
    </script>
</body>
</html>
