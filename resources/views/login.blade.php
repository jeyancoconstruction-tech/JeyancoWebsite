@extends('auth.layout')

@section('title', 'Sign In')
@section('heading', __('Sign in'))
@section('subheading', __('Access the management dashboard'))

@section('form')
    <form action="{{ route('login.post') }}" method="POST" class="login-form" id="loginForm">
        @csrf

        <div class="form-group {{ $errors->has('username') ? 'has-error' : '' }}">
            <label for="username">{{ __('Username or email') }}</label>
            <div class="input-wrap">
                {{-- text, not email: this box takes either one. --}}
                <input type="text" id="username" name="username" value="{{ old('username') }}"
                       required autofocus autocomplete="username" spellcheck="false" autocapitalize="none"
                       placeholder="{{ __('you@jeyanco.com') }}">
            </div>
        </div>

        <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
            <label for="password">{{ __('Password') }}</label>
            <div class="input-wrap">
                <input type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="{{ __('Enter your password') }}">
                <button type="button" class="toggle-pass" id="togglePass"
                        aria-label="{{ __('Show password') }}" title="{{ __('Show / hide password') }}">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="caps-hint" id="capsHint" role="status">
                <i class="fas fa-triangle-exclamation"></i> {{ __('Caps Lock is on') }}
            </div>
        </div>

        <div class="form-options">
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">{{ __('Remember me') }}</label>
            </div>
            <a class="forgot-link" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            <span class="btn-label">{{ __('Sign in') }}</span>
        </button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const form   = document.getElementById('loginForm');
    const user   = document.getElementById('username');
    const pass   = document.getElementById('password');
    const toggle = document.getElementById('togglePass');
    const caps   = document.getElementById('capsHint');
    const btn    = document.getElementById('loginBtn');
    const label  = btn.querySelector('.btn-label');

    toggle.addEventListener('click', function () {
        const show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        toggle.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        pass.focus();
    });

    function capsLock(e) {
        if (typeof e.getModifierState !== 'function') return;
        caps.classList.toggle('show', e.getModifierState('CapsLock'));
    }
    pass.addEventListener('keydown', capsLock);
    pass.addEventListener('keyup', capsLock);
    pass.addEventListener('blur', function () { caps.classList.remove('show'); });

    form.addEventListener('submit', function (e) {
        if (btn.disabled) { e.preventDefault(); return; }
        btn.disabled = true;
        label.textContent = 'Signing in...';
    });

    // bfcache hands the page back with the button still disabled.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        btn.disabled = false;
        label.textContent = 'Sign in';
    });

    // Failed attempt: username comes back filled, password does not.
    if (user.value !== '') pass.focus();
})();
</script>
@endpush
