@extends('layouts')

@section('page_title', 'Appearance')

@push('styles')
@include('settings._system-styles')
@endpush

@section('content')
<div class="sy-page">

    <div class="sy-header mb-3">
        <h1>{{ __('Appearance') }}</h1>
        <p>{{ __('How the system looks before anybody has chosen for themselves.') }}</p>
    </div>

    <div class="hub">
    @include('settings._hub')
    <div>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="fas fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Nothing was saved.</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('system-settings.appearance.update') }}">
        @csrf
        @method('PUT')

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-palette"></i>
                <div>
                    <h6>{{ __('Display') }}</h6>
                    <p>The theme a screen opens on the first time it is used</p>
                </div>
            </div>
            <div class="sy-card-body">
                <div class="sy-grid">
                    <div class="sy-field">
                        <label for="default_theme">{{ __('Default theme') }}</label>
                        <select class="sy-input" id="default_theme" name="default_theme" required>
                            <option value="dark"  @selected(old('default_theme', $system->default_theme) === 'dark')>{{ __('Dark') }}</option>
                            <option value="light" @selected(old('default_theme', $system->default_theme) === 'light')>{{ __('Light') }}</option>
                        </select>
                    </div>
                    <div class="sy-field">
                        <label for="locale">{{ __('Language') }}</label>
                        <select class="sy-input" id="locale" name="locale" required>
                            <option value="en" @selected(old('locale', $system->locale) === 'en')>{{ __('English') }}</option>
                            <option value="tl" @selected(old('locale', $system->locale) === 'tl')>{{ __('Tagalog') }}</option>
                        </select>
                    </div>
                </div>

                <p class="sy-hint">
                    This is the starting point, not a lock. Anyone who uses the toggle in the top bar keeps
                    their own choice — it is saved in their browser and outranks this one. Changing it here
                    reaches a new phone, a fresh browser, and a kiosk nobody has touched.
                </p>
            </div>
        </div>

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-language"></i>
                <div>
                    <h6>Language and currency</h6>
                    <p>Not settings yet</p>
                </div>
            </div>
            <div class="sy-card-body">
                <p class="sy-hint" style="margin-top:0;">
                    There is no translation layer in the app — the English and the Tagalog on screen are both
                    written into the templates, so a language switch would have nothing to switch to. Amounts
                    are formatted as pesos to two decimals wherever they are printed, with the symbol written
                    in place rather than read from anywhere.
                    Both are real work rather than a control, so neither is offered here as one.
                </p>
            </div>
        </div>

        <div class="sy-foot">
            <span class="sy-updated">
                <i class="fas fa-clock-rotate-left"></i>
                @if($system->exists)
                    Last updated {{ $system->updated_at?->format('Y-m-d') }}
                @else
                    Never changed — showing the built-in defaults
                @endif
            </span>
            <button type="submit" class="sy-save"><i class="fas fa-floppy-disk me-1"></i> {{ __('Save') }}</button>
        </div>
    </form>

    </div>
    </div>
</div>
@endsection
