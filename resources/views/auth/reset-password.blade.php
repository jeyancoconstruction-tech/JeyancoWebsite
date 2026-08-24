@extends('auth.layout')

@section('title', 'Reset Password')
@section('heading', 'Set a new password')
@section('subheading', 'Choose a password you have not used before')

@section('form')
    <form action="{{ route('password.update') }}" method="POST" id="resetForm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">Email</label>
            <div class="input-wrap">
                <i class="fas fa-envelope lead"></i>
                {{-- Carried from the emailed link. Kept editable rather than hidden so a
                     mistyped or truncated link can still be corrected here. --}}
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                       required autocomplete="email" spellcheck="false"
                       autocapitalize="none" placeholder="The email on your account">
            </div>
        </div>

        <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
            <label for="password">New password</label>
            <div class="input-wrap">
                <i class="fas fa-lock lead"></i>
                <input type="password" id="password" name="password" required autofocus
                       autocomplete="new-password" placeholder="At least 8 characters">
                <button type="button" class="toggle-pass" data-toggle="password" aria-label="Show password" title="Show / hide password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <p class="field-hint">Must be at least 8 characters and include both letters and numbers.</p>
            <div class="caps-hint" id="capsHint" role="status">
                <i class="fas fa-triangle-exclamation"></i> Caps Lock is on
            </div>
        </div>

        <div class="form-group {{ $errors->has('password_confirmation') ? 'has-error' : '' }}">
            <label for="password_confirmation">Confirm new password</label>
            <div class="input-wrap">
                <i class="fas fa-lock lead"></i>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       required autocomplete="new-password" placeholder="Type it again">
                <button type="button" class="toggle-pass" data-toggle="password_confirmation" aria-label="Show password" title="Show / hide password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <p class="field-hint" id="matchHint"></p>
        </div>

        <button type="submit" class="btn-login" id="resetBtn">
            <i class="fas fa-key"></i>
            <span class="btn-label">Reset password</span>
        </button>

        <div class="login-footer">
            <p><a href="{{ route('login') }}"><i class="fas fa-arrow-left"></i> Back to sign in</a></p>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Show/hide for both password boxes.
    document.querySelectorAll('.toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.toggle);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            input.focus();
        });
    });

    // Caps Lock warning.
    (function () {
        const input = document.getElementById('password');
        const hint = document.getElementById('capsHint');
        function check(e) {
            if (typeof e.getModifierState !== 'function') return;
            hint.classList.toggle('show', e.getModifierState('CapsLock'));
        }
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('blur', function () { hint.classList.remove('show'); });
    })();

    // Say straight away when the two boxes disagree, rather than after a round trip.
    (function () {
        const pw = document.getElementById('password');
        const confirm = document.getElementById('password_confirmation');
        const hint = document.getElementById('matchHint');

        function check() {
            if (confirm.value === '') { hint.textContent = ''; return; }
            const ok = pw.value === confirm.value;
            hint.textContent = ok ? 'Passwords match.' : 'Passwords do not match yet.';
            hint.style.color = ok ? '#86efac' : '#fbbf24';
        }
        pw.addEventListener('input', check);
        confirm.addEventListener('input', check);
    })();

    // Submit state.
    (function () {
        const form = document.getElementById('resetForm');
        const btn = document.getElementById('resetBtn');

        form.addEventListener('submit', function (e) {
            if (btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.querySelector('i').className = 'fas fa-circle-notch fa-spin';
            btn.querySelector('.btn-label').textContent = 'Saving...';
        });

        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            btn.disabled = false;
            btn.querySelector('i').className = 'fas fa-key';
            btn.querySelector('.btn-label').textContent = 'Reset password';
        });
    })();
</script>
@endpush
