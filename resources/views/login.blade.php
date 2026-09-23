@extends('auth.layout')

@section('title', 'Sign In')
@section('heading', __('Welcome back'))
@section('subheading', __('Sign in to the Jeyanco management dashboard.'))

@section('form')
    <form action="{{ route('login.post') }}" method="POST" id="login-form" novalidate>
        @csrf

        <div class="field rv" style="--jp-d:400">
            <label for="username">{{ __('Username or email') }}</label>
            <div class="control">
                <span class="lead"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span>
                {{-- text, not email: this box takes either one. --}}
                <input id="username" name="username" type="text" value="{{ old('username') }}"
                       class="{{ $errors->has('username') ? 'invalid' : '' }}"
                       autocomplete="username" spellcheck="false" autocapitalize="none"
                       placeholder="{{ __('you@jeyanco.com') }}" autofocus>
            </div>
            @error('username')
                <span class="field-error">
                    <svg class="i" width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    {{ $message }}
                </span>
            @enderror
        </div>

        <div class="field rv" style="--jp-d:470">
            <label for="password">{{ __('Password') }}</label>
            <div class="control">
                <span class="lead"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                <input id="password" name="password" type="password"
                       class="has-toggle {{ $errors->has('password') ? 'invalid' : '' }}"
                       autocomplete="current-password" placeholder="{{ __('Enter your password') }}">
                <button class="eye" id="toggle-pw" type="button"
                        aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg class="i on" width="18" height="18" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="i off" width="18" height="18" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/></svg>
                </button>
            </div>
            @error('password')
                <span class="field-error">
                    <svg class="i" width="14" height="14" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    {{ $message }}
                </span>
            @enderror
            <span class="caps" id="caps" role="status">
                <svg class="i" width="14" height="14" viewBox="0 0 24 24"><path d="M12 4l7 8h-4v4H9v-4H5zM9 20h6"/></svg>
                {{ __('Caps Lock is on') }}
            </span>
        </div>

        <div class="row rv" style="--jp-d:540">
            <label class="remember">
                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>{{ __('Remember me') }}
            </label>
            <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
        </div>

        <button class="submit rv" id="submit" type="submit" style="--jp-d:610">
            <span class="spin" aria-hidden="true"></span>
            <span class="label">{{ __('Sign in') }}</span>
            <svg class="i arrow" width="18" height="18" viewBox="0 0 24 24" style="stroke-width:2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>

        <div class="notice rv" style="--jp-d:690">
            <span><svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
            {{ __('For authorized Jeyanco personnel only. Every sign-in is recorded in the audit log.') }}
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
  var form   = document.getElementById('login-form');
  var user   = document.getElementById('username');
  var pw     = document.getElementById('password');
  var toggle = document.getElementById('toggle-pw');
  var caps   = document.getElementById('caps');
  var btn    = document.getElementById('submit');

  // ── Show / hide the password ───────────────────────────────────────────
  toggle.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
    pw.focus();
  });

  // ── Caps Lock ──────────────────────────────────────────────────────────
  // The commonest reason a correct password is refused, and the one thing
  // the server can never tell you afterwards.
  function checkCaps(e) {
    if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock'));
  }
  pw.addEventListener('keyup', checkCaps);
  pw.addEventListener('keydown', checkCaps);
  pw.addEventListener('blur', function () { caps.classList.remove('show'); });

  // ── Typing clears the mark the server put on the field ─────────────────
  [user, pw].forEach(function (el) {
    el.addEventListener('input', function () { el.classList.remove('invalid'); });
  });

  // A field the server refused starts focused, with the cursor at the end,
  // so the correction begins where the problem is.
  var firstBad = form.querySelector('input.invalid');
  if (firstBad) {
    firstBad.focus();
    var v = firstBad.value; firstBad.value = ''; firstBad.value = v;
  }

  // ── Submit ─────────────────────────────────────────────────────────────
  form.addEventListener('submit', function (e) {
    if (btn.classList.contains('loading')) { e.preventDefault(); return; }

    var missing = [];
    if (!user.value.trim()) missing.push(user);
    if (!pw.value) missing.push(pw);

    // Caught here only to save a round trip; the server checks the same two
    // things and is what actually decides.
    if (missing.length) {
      e.preventDefault();
      missing.forEach(function (el) { el.classList.add('invalid'); });
      missing[0].focus();
      return;
    }

    btn.classList.add('loading');
    btn.querySelector('.label').textContent = @json(__('Signing in…'));

    // The wait for the dashboard is the screen the site opened on, not a
    // form frozen mid-press. The overlay goes up first and the form is then
    // submitted for real — a short beat, only long enough for the fade to
    // start, because the page it is waiting for is already on its way.
    if (window.JeyancoLoader) {
      e.preventDefault();
      window.JeyancoLoader.show(@json(__('Signing you in')));
      setTimeout(function () { HTMLFormElement.prototype.submit.call(form); }, 220);
    }
  });

  // Coming back to this page from the browser's cache leaves the button
  // spinning on a form that is no longer being submitted. The loading screen
  // clears itself on the same event, in _loading.blade.php.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    btn.classList.remove('loading');
    btn.querySelector('.label').textContent = @json(__('Sign in'));
  });
})();
</script>
@endpush
