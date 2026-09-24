@extends('auth.layout')

@section('title', 'Choose Your Password')
@section('heading', __('Choose your own password'))
@section('subheading', __('Your administrator set the one you signed in with. Pick one only you know before you continue.'))

@php $min = $minLength ?? 8; @endphp

@section('form')
    <form action="{{ route('account.password.update') }}" method="POST" id="choose-form" novalidate>
        @csrf

        {{-- For the browser's password manager: whose password this is. --}}
        <input type="text" name="username" value="{{ auth()->user()->username }}" autocomplete="username" hidden>

        <div class="field rv" style="--jp-d:400">
            <label for="password">{{ __('New password') }}</label>
            <div class="control">
                <span class="lead"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                <input id="password" name="password" type="password" autofocus
                       class="has-toggle {{ $errors->has('password') ? 'invalid' : '' }}"
                       autocomplete="new-password" placeholder="{{ __('At least :n characters', ['n' => $min]) }}">
                <button class="eye" type="button" data-toggle="password" aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg class="i on" width="18" height="18" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="i off" width="18" height="18" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/></svg>
                </button>
            </div>
            <span class="field-hint">{{ __('At least :n characters, with a letter and a number.', ['n' => $min]) }}</span>
        </div>

        <div class="field rv" style="--jp-d:470">
            <label for="password_confirmation">{{ __('Confirm new password') }}</label>
            <div class="control">
                <span class="lead"><svg class="i" width="18" height="18" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       class="has-toggle" autocomplete="new-password" placeholder="{{ __('Type it again') }}">
                <button class="eye" type="button" data-toggle="password_confirmation" aria-label="{{ __('Show password') }}" aria-pressed="false">
                    <svg class="i on" width="18" height="18" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="i off" width="18" height="18" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/></svg>
                </button>
            </div>
        </div>

        <button class="submit rv" id="submit" type="submit" style="--jp-d:540">
            <span class="spin" aria-hidden="true"></span>
            <span class="label">{{ __('Save and continue') }}</span>
            <svg class="i arrow" width="18" height="18" viewBox="0 0 24 24" style="stroke-width:2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    {{-- Its own form: logout is a POST, and nesting it would submit the password. --}}
    <form action="{{ route('logout') }}" method="POST" class="rv" style="--jp-d:600;text-align:center">
        @csrf
        <button type="submit" class="signout">{{ __('Not you? Sign out') }}</button>
    </form>
@endsection

@push('styles')
<style>
    .signout { background: none; border: 0; padding: 0; font: 700 13.5px "Manrope", sans-serif; color: var(--brand-light); cursor: pointer; }
    .signout:hover { color: #A9CBF0; }
</style>
@endpush

@push('scripts')
<script>
(function () {
  document.querySelectorAll('[data-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.toggle);
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
      input.focus();
    });
  });

  var form = document.getElementById('choose-form'), btn = document.getElementById('submit');
  form.addEventListener('submit', function () {
    btn.classList.add('loading');
    btn.querySelector('.label').textContent = @json(__('Saving…'));
  });
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    btn.classList.remove('loading');
    btn.querySelector('.label').textContent = @json(__('Save and continue'));
  });
})();
</script>
@endpush
