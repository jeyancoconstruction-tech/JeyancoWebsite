@extends('auth.layout')

@section('title', 'Forgot Password')
@section('heading', 'Forgot your password?')
@section('subheading', 'We will email you a link to set a new one')

@section('form')
    <form action="{{ route('password.email') }}" method="POST" id="forgotForm">
        @csrf

        <div class="form-group {{ $errors->has('login') ? 'has-error' : '' }}">
            <label for="login">Username / Email</label>
            <div class="input-wrap">
                <i class="fas fa-user lead"></i>
                <input type="text" id="login" name="login" value="{{ old('login') }}"
                       required autofocus autocomplete="username" spellcheck="false"
                       autocapitalize="none" placeholder="Enter your username or email">
            </div>
            <p class="field-hint">
                The link goes to the email on your account. If your account has no email on file,
                ask your administrator to reset it for you.
            </p>
        </div>

        <button type="submit" class="btn-login" id="forgotBtn">
            <i class="fas fa-paper-plane"></i>
            <span class="btn-label">Send reset link</span>
        </button>

        <div class="login-footer">
            <p><a href="{{ route('login') }}"><i class="fas fa-arrow-left"></i> Back to sign in</a></p>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('forgotForm');
        const btn = document.getElementById('forgotBtn');

        form.addEventListener('submit', function (e) {
            if (btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.querySelector('i').className = 'fas fa-circle-notch fa-spin';
            btn.querySelector('.btn-label').textContent = 'Sending...';
        });

        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            btn.disabled = false;
            btn.querySelector('i').className = 'fas fa-paper-plane';
            btn.querySelector('.btn-label').textContent = 'Send reset link';
        });
    })();
</script>
@endpush
