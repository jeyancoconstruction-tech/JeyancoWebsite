@extends('auth.layout')

@section('title', 'Reset Password')
@section('heading', 'Set a new password')
@section('subheading', 'Choose a password you have not used before')

@php $min = $minLength ?? 8; @endphp

@push('styles')
<style>
    /* The same three steps shown on the request page, now at the last one. */
    .pw-flow {
        display: flex; list-style: none; margin: 0 0 22px; padding: 0;
    }
    .pw-flow li {
        flex: 1; position: relative; text-align: center;
        display: flex; flex-direction: column; align-items: center; gap: 6px;
    }
    .pw-flow li::before {
        content: ""; position: absolute; top: 13px; left: -50%;
        width: 100%; height: 2px; background: var(--line); z-index: 0;
    }
    .pw-flow li:first-child::before { display: none; }
    .pw-flow .n {
        position: relative; z-index: 1;
        width: 26px; height: 26px; border-radius: 50%;
        display: grid; place-items: center;
        background: var(--surface); border: 2px solid var(--line-strong);
        color: var(--muted); font-size: 12px; font-weight: 700;
    }
    .pw-flow .t { font-size: 11.5px; line-height: 1.3; color: var(--muted); }
    .pw-flow li.is-done::before,
    .pw-flow li.is-now::before  { background: var(--success); }
    .pw-flow li.is-done .n {
        background: var(--success); border-color: var(--success); color: #fff;
    }
    .pw-flow li.is-now .n {
        background: var(--brand); border-color: var(--brand); color: #fff;
    }
    .pw-flow li.is-now .t { color: var(--text-2); font-weight: 600; }

    /* The rules the server will apply, checked off while the person types, so
       a rejected password is caught here instead of after a round trip. */
    .pw-rules {
        list-style: none; margin: 10px 0 0; padding: 0;
        display: grid; gap: 7px;
    }
    .pw-rules li {
        display: flex; align-items: center; gap: 8px;
        font-size: 12.5px; line-height: 1.4; color: var(--muted);
    }
    .pw-rules li i { flex: 0 0 14px; text-align: center; font-size: 11px; }
    .pw-rules li.ok { color: var(--success); }
    .pw-rules li.bad { color: var(--warn); }
</style>
@endpush

@section('form')
    <ol class="pw-flow">
        <li class="is-done"><span class="n">1</span><span class="t">Ask for a link</span></li>
        <li class="is-done"><span class="n">2</span><span class="t">Open your email</span></li>
        <li class="is-now"><span class="n">3</span><span class="t">Set a new one</span></li>
    </ol>

    <form action="{{ route('password.update') }}" method="POST" id="resetForm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">{{ __('Email') }}</label>
            <div class="input-wrap">
                {{-- Carried from the emailed link. Kept editable rather than hidden so a
                     mistyped or truncated link can still be corrected here. --}}
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                       required autocomplete="email" spellcheck="false"
                       autocapitalize="none" placeholder="{{ __('The email on your account') }}">
            </div>
        </div>

        <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
            <label for="password">{{ __('New password') }}</label>
            <div class="input-wrap">
                <input type="password" id="password" name="password" required autofocus
                       autocomplete="new-password" placeholder="{{ __('At least :n characters', ['n' => $min]) }}">
                <button type="button" class="toggle-pass" data-toggle="password" aria-label="{{ __('Show password') }}" title="{{ __('Show / hide password') }}">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <ul class="pw-rules" id="pwRules" aria-live="polite">
                <li data-rule="len"><i class="fas fa-circle" style="font-size:6px"></i>
                    <span>{{ __('At least :n characters', ['n' => $min]) }}</span></li>
                <li data-rule="letter"><i class="fas fa-circle" style="font-size:6px"></i>
                    <span>{{ __('Contains a letter') }}</span></li>
                <li data-rule="number"><i class="fas fa-circle" style="font-size:6px"></i>
                    <span>{{ __('Contains a number') }}</span></li>
                <li data-rule="match"><i class="fas fa-circle" style="font-size:6px"></i>
                    <span>{{ __('Both boxes match') }}</span></li>
            </ul>

            <div class="caps-hint" id="capsHint" role="status">
                <i class="fas fa-triangle-exclamation"></i> {{ __('Caps Lock is on') }}
            </div>
        </div>

        <div class="form-group {{ $errors->has('password_confirmation') ? 'has-error' : '' }}">
            <label for="password_confirmation">{{ __('Confirm new password') }}</label>
            <div class="input-wrap">
                <input type="password" id="password_confirmation" name="password_confirmation"
                       required autocomplete="new-password" placeholder="{{ __('Type it again') }}">
                <button type="button" class="toggle-pass" data-toggle="password_confirmation" aria-label="{{ __('Show password') }}" title="{{ __('Show / hide password') }}">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login" id="resetBtn">
            <span class="btn-label">{{ __('Reset password') }}</span>
        </button>

        <div class="login-footer">
            <p><a href="{{ route('login') }}"><i class="fas fa-arrow-left"></i> {{ __('Back to sign in') }}</a></p>
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

    // Tick the rules off as they are met. These are the same rules the server
    // enforces, so nothing here is the only thing standing between a weak
    // password and the account.
    (function () {
        const MIN = {{ (int) $min }};
        const pw = document.getElementById('password');
        const confirm = document.getElementById('password_confirmation');
        const rules = document.getElementById('pwRules');
        if (!pw || !confirm || !rules) return;

        const items = {};
        rules.querySelectorAll('li[data-rule]').forEach(function (li) {
            items[li.dataset.rule] = li;
        });

        function mark(li, state) {
            li.classList.toggle('ok', state === 'ok');
            li.classList.toggle('bad', state === 'bad');
            const icon = li.querySelector('i');
            if (state === 'ok') {
                icon.className = 'fas fa-circle-check';
                icon.style.fontSize = '';
            } else if (state === 'bad') {
                icon.className = 'fas fa-circle-xmark';
                icon.style.fontSize = '';
            } else {
                icon.className = 'fas fa-circle';
                icon.style.fontSize = '6px';
            }
        }

        function check() {
            const v = pw.value;
            const c = confirm.value;

            mark(items.len,    v === '' ? 'idle' : (v.length >= MIN ? 'ok' : 'bad'));
            mark(items.letter, v === '' ? 'idle' : (/[A-Za-z]/.test(v) ? 'ok' : 'bad'));
            mark(items.number, v === '' ? 'idle' : (/[0-9]/.test(v) ? 'ok' : 'bad'));
            mark(items.match,  c === '' ? 'idle' : (v === c ? 'ok' : 'bad'));
        }

        pw.addEventListener('input', check);
        confirm.addEventListener('input', check);
        check();
    })();

    // Submit state.
    (function () {
        const form = document.getElementById('resetForm');
        const btn = document.getElementById('resetBtn');

        form.addEventListener('submit', function (e) {
            if (btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.querySelector('.btn-label').textContent = 'Saving...';
        });

        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            btn.disabled = false;
            btn.querySelector('.btn-label').textContent = 'Reset password';
        });
    })();
</script>
@endpush
