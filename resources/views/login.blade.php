@extends('auth.layout')

@section('title', 'Admin Login')
@section('heading', 'Admin Login')
@section('subheading', 'Sign in to access the management dashboard')

@section('form')
    <form action="{{ route('login.post') }}" method="POST" class="login-form" id="loginForm" autocomplete="on">
        @csrf

        <div class="form-group {{ $errors->has('username') ? 'has-error' : '' }}">
            <label for="username">Username / Email</label>
            <div class="input-wrap">
                <i class="fas fa-user lead"></i>
                {{-- type="text", not "email": this box takes either form, and
                     type="email" would make the browser reject a plain username. --}}
                <input type="text" id="username" name="username" value="{{ old('username') }}"
                       required autofocus autocomplete="username" spellcheck="false"
                       autocapitalize="none" placeholder="Enter your username or email">
            </div>
            <p class="field-hint">Sign in with either your username or the email on your account.</p>
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
            <div class="caps-hint" id="capsHint" role="status">
                <i class="fas fa-triangle-exclamation"></i> Caps Lock is on
            </div>
        </div>

        <div class="form-options">
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">Remember me</label>
            </div>
            <a class="forgot-link" href="{{ route('password.request') }}">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            <i class="fas fa-right-to-bracket"></i>
            <span class="btn-label">Sign In</span>
        </button>

        <div class="secure-note">
            <i class="fas fa-lock"></i> Secured administrator access &middot; authorized personnel only
        </div>
    </form>
@endsection

@push('scripts')
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

    // Caps Lock warning on the password field.
    (function () {
        const input = document.getElementById('password');
        const hint = document.getElementById('capsHint');
        function check(e) {
            // getModifierState is unavailable on some virtual keyboards.
            if (typeof e.getModifierState !== 'function') return;
            hint.classList.toggle('show', e.getModifierState('CapsLock'));
        }
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('blur', function () { hint.classList.remove('show'); });
    })();

    // Submit loading state (prevents double submit, gives feedback).
    (function () {
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');

        function reset() {
            btn.disabled = false;
            btn.querySelector('i').className = 'fas fa-right-to-bracket';
            btn.querySelector('.btn-label').textContent = 'Sign In';
        }

        form.addEventListener('submit', function (e) {
            if (btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.querySelector('i').className = 'fas fa-circle-notch fa-spin';
            btn.querySelector('.btn-label').textContent = 'Signing in...';
        });

        // Coming Back to this page restores it from the browser's cache with
        // the button still spinning and disabled — which looks like a frozen
        // login. Put it back to a usable state.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) reset();
        });
    })();

    // Keep focus where the visitor needs to type: after a rejected attempt
    // the username is refilled, so the password box is the next step.
    (function () {
        const username = document.getElementById('username');
        if (username.value.trim() !== '') document.getElementById('password').focus();
    })();
</script>
@endpush
