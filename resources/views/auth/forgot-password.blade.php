@extends('auth.layout')

@section('title', 'Forgot Password')
@section('heading', 'Forgot your password?')
@section('subheading', 'We will email you a link to set a new one')

@push('styles')
<style>
    /* Where the person is in a three-step errand. The numbers are the steps,
       so they are numbers; nothing here is decorative. */
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
        background: #fff; border: 2px solid var(--line);
        color: var(--muted); font-size: 12px; font-weight: 700;
    }
    .pw-flow .t { font-size: 11.5px; line-height: 1.3; color: var(--muted); }
    .pw-flow li.is-done::before,
    .pw-flow li.is-now::before  { background: var(--success); }
    .pw-flow li.is-done .n {
        background: var(--success); border-color: var(--success); color: #fff;
    }
    .pw-flow li.is-now .n {
        background: var(--blue); border-color: var(--blue); color: #fff;
    }
    .pw-flow li.is-now .t { color: var(--ink); font-weight: 600; }

    /* After the request goes in, the form has nothing left to ask, so it is
       replaced by what to do next rather than left sitting there. */
    .pw-next {
        border: 1px solid var(--line); border-radius: 10px;
        padding: 16px 18px; background: #f8fafc;
    }
    .pw-next h2 {
        font-size: 13.5px; font-weight: 700; color: var(--ink);
        margin: 0 0 12px; letter-spacing: .01em;
    }
    .pw-next ul {
        list-style: none; margin: 0; padding: 0;
        display: flex; flex-direction: column; gap: 11px;
    }
    .pw-next li {
        display: flex; gap: 10px; align-items: flex-start;
        font-size: 13px; line-height: 1.5; color: var(--text);
    }
    .pw-next li i { flex: 0 0 15px; margin-top: 3px; color: var(--blue); font-size: 12.5px; }
    .pw-next li b { color: var(--ink); }
    .pw-again {
        margin: 16px 0 0; padding-top: 14px; border-top: 1px solid var(--line);
        font-size: 12.5px; color: var(--muted);
    }
    .pw-again a { color: var(--blue); font-weight: 600; text-decoration: none; }
    .pw-again a:hover { text-decoration: underline; }
</style>
@endpush

@section('form')
    @php $sent = session('success'); @endphp

    <ol class="pw-flow">
        <li class="{{ $sent ? 'is-done' : 'is-now' }}">
            <span class="n">1</span><span class="t">Ask for a link</span>
        </li>
        <li class="{{ $sent ? 'is-now' : '' }}">
            <span class="n">2</span><span class="t">Open your email</span>
        </li>
        <li>
            <span class="n">3</span><span class="t">Set a new one</span>
        </li>
    </ol>

    @if($sent)
        {{-- The message itself is already shown by the layout's alert, so this
             panel carries only what the person has to do next. --}}
        <div class="pw-next">
            <h2>What happens now</h2>
            <ul>
                <li>
                    <i class="fas fa-inbox"></i>
                    <span>Open the inbox of the email on your account and look for a
                          <b>Reset Password</b> message.</span>
                </li>
                <li>
                    <i class="fas fa-clock"></i>
                    <span>The link works <b>once</b> and expires in <b>60 minutes</b>. After that,
                          come back here and ask for another.</span>
                </li>
                <li>
                    <i class="fas fa-folder-open"></i>
                    <span>Nothing after a few minutes? Check <b>Spam</b> and <b>Promotions</b> —
                          a first message from a new sender often lands there.</span>
                </li>
                <li>
                    <i class="fas fa-user-shield"></i>
                    <span>If your account has <b>no email on file</b>, no link was sent. Ask your
                          administrator to reset it for you.</span>
                </li>
            </ul>
            <p class="pw-again">
                Wrong account, or nothing arrived?
                <a href="{{ route('password.request') }}">Try again</a>
            </p>
        </div>

        <div class="login-footer">
            <p><a href="{{ route('login') }}"><i class="fas fa-arrow-left"></i> {{ __('Back to sign in') }}</a></p>
        </div>
    @else
        <form action="{{ route('password.email') }}" method="POST" id="forgotForm">
            @csrf

            <div class="form-group {{ $errors->has('login') ? 'has-error' : '' }}">
                <label for="login">{{ __('Username / Email') }}</label>
                <div class="input-wrap">
                    <input type="text" id="login" name="login" value="{{ old('login') }}"
                           required autofocus autocomplete="username" spellcheck="false"
                           autocapitalize="none" placeholder="{{ __('Enter your username or email') }}">
                </div>
                <p class="field-hint">
                    The link goes to the email on your account. If your account has no email on file,
                    ask your administrator to reset it for you.
                </p>
            </div>

            <button type="submit" class="btn-login" id="forgotBtn">
                <span class="btn-label">{{ __('Send reset link') }}</span>
            </button>

            <div class="login-footer">
                <p><a href="{{ route('login') }}"><i class="fas fa-arrow-left"></i> {{ __('Back to sign in') }}</a></p>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('forgotForm');
        const btn = document.getElementById('forgotBtn');

        // The form is gone once the request is in; there is nothing to guard.
        if (!form || !btn) return;

        form.addEventListener('submit', function (e) {
            if (btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.querySelector('.btn-label').textContent = 'Sending...';
        });

        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            btn.disabled = false;
            btn.querySelector('.btn-label').textContent = 'Send reset link';
        });
    })();
</script>
@endpush
