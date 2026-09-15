@extends('layouts')

@section('page_title', 'Appearance')

@php $theme = old('default_theme', $system->default_theme ?: 'dark'); @endphp

@push('styles')
@include('system._kit')
<style>
.ap { display: grid; grid-template-columns: repeat(2, minmax(0, 260px)); gap: 16px; padding: 16px 18px 6px; }
@media (max-width: 700px) { .ap { grid-template-columns: 1fr; } }
.ap-card { border: 1px solid var(--border-md); border-radius: 12px; padding: 10px; background: var(--surface); cursor: pointer; margin: 0; transition: border-color .15s, box-shadow .15s; }
.ap-card:hover { border-color: var(--brand); }
.ap-card:has(input:checked) { border: 2px solid var(--brand); padding: 9px; box-shadow: 0 0 0 4px var(--brand-subtle); }
.ap-mini { height: 128px; border-radius: 8px; overflow: hidden; display: grid; grid-template-columns: 42px 1fr; border: 1px solid var(--border); }
.ap-mini .r { background: linear-gradient(180deg, #123566, #0d2a4f); padding: 9px 6px; display: flex; flex-direction: column; gap: 5px; }
.ap-mini .r i { height: 5px; border-radius: 2px; background: rgba(255,255,255,.25); }
.ap-mini .r i.on { background: #4F97F5; }
.ap-mini .c { padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.ap-mini .c i { height: 8px; border-radius: 2px; }
.ap-mini .c .k { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.ap-mini .c .k i { height: 28px; border-radius: 4px; }
.ap-mini.light .c { background: #F2F4F7; } .ap-mini.light .c i { background: #fff; border: 1px solid #E4E7EC; }
.ap-mini.dark .c { background: #0C1522; } .ap-mini.dark .c i { background: #131E2D; border: 1px solid #223049; }
.ap-mini.dark .r { background: linear-gradient(180deg, #0e1826, #0a1421); }
.ap-lbl { display: flex; align-items: center; gap: 9px; margin-top: 10px; font-size: 13px; font-weight: 700; color: var(--text-primary); }
.ap-lbl .rd { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--border-md); flex: none; }
.ap-card:has(input:checked) .rd { border: 5px solid var(--brand); }
.ap-lbl small { margin-left: auto; font-size: 11px; font-weight: 600; color: var(--text-muted); }
.ap-note { display: flex; gap: 8px; padding: 12px 18px 16px; font-size: 12px; color: var(--text-muted); line-height: 1.55; }
.ap-note svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
</style>
@endpush

@section('content')
<div class="sx-page">
    @include('settings._head', [
        'title' => 'Appearance',
        'sub'   => 'How the system looks before anybody has chosen for themselves.',
    ])

    <div class="st-wrap">
        @include('settings._hub')

        <form method="POST" action="{{ route('system-settings.appearance.update') }}" data-sx-form>
            @csrf
            @method('PUT')

            <div class="sx-card">
                <div class="sx-card-head"><span class="sx-idx">A</span><h2 class="sx-card-title">Default theme</h2><span class="sx-card-note">The theme a screen opens on the first time it is used</span></div>
                <div class="ap">
                    @foreach(['light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                        <label class="ap-card">
                            <input type="radio" name="default_theme" value="{{ $value }}" hidden @checked($theme === $value)
                                   data-track data-saved="{{ $system->default_theme }}" data-label="Default theme">
                            <div class="ap-mini {{ $value }}">
                                <div class="r"><i></i><i class="on"></i><i></i><i></i><i></i></div>
                                <div class="c"><i style="width:60%"></i><div class="k"><i></i><i></i><i></i></div><i></i><i style="width:80%"></i></div>
                            </div>
                            <div class="ap-lbl"><span class="rd"></span>{{ $label }}@if($system->default_theme === $value)<small>current default</small>@endif</div>
                        </label>
                    @endforeach
                </div>
                <div class="ap-note"><i data-lucide="info"></i><span>This is the starting point, not a lock. Anyone who uses the toggle in the top bar keeps their own choice — it is saved in their browser and outranks this one. Changing it here reaches a new phone, a fresh browser, and a kiosk nobody has touched.</span></div>
                <div class="ap-note" style="border-top:1px solid var(--border);padding-top:12px"><i data-lucide="languages"></i><span>The system is English only, and amounts print as pesos to two decimals. Both are decisions rather than controls, so neither is offered here as one.</span></div>
            </div>

            @include('settings._savebar')
        </form>
    </div>
</div>
@endsection

@include('settings._form-script')
