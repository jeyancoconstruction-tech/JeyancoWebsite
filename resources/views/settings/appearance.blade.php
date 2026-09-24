@extends('layouts')

@section('page_title', 'Appearance')

@php
    $theme = old('default_theme', $system->default_theme ?: 'dark');
    $cards = [
        'light'  => ['Light', 'Always light'],
        'dark'   => ['Dark', 'Always dark'],
        'system' => ['System', 'Follows each device’s setting'],
    ];
@endphp

@push('styles')
@include('system._kit')
<style>
.ap { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; padding: 16px 18px 6px; }
@media (max-width: 900px) { .ap { grid-template-columns: 1fr; } }
.ap-card { border: 1px solid var(--border-md); border-radius: 12px; padding: 10px; background: var(--surface); cursor: pointer; margin: 0; transition: border-color .15s, box-shadow .15s; }
.ap-card:hover { border-color: var(--brand); }
.ap-card:has(input:checked) { border: 2px solid var(--brand); padding: 9px; box-shadow: 0 0 0 4px var(--brand-subtle); }
.ap-mini { height: 136px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); position: relative; }
.ap-face { position: absolute; inset: 0; display: grid; grid-template-columns: 38px 1fr; }
.ap-face .r { background: linear-gradient(180deg, #123566, #0d2a4f); padding: 9px 6px; display: flex; flex-direction: column; gap: 5px; }
.ap-face .r i { height: 5px; border-radius: 2px; background: rgba(255,255,255,.25); }
.ap-face .r i.on { background: #4F97F5; }
.ap-face .c { padding: 10px; display: flex; flex-direction: column; gap: 6px; }
.ap-face .c i { height: 8px; border-radius: 2px; }
.ap-face .c .k { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.ap-face .c .k i { height: 26px; border-radius: 4px; }
.ap-face.light .c { background: #F2F4F7; } .ap-face.light .c i { background: #fff; border: 1px solid #E4E7EC; }
.ap-face.dark .c { background: #0C1522; } .ap-face.dark .c i { background: #131E2D; border: 1px solid #223049; }
.ap-face.dark .r { background: linear-gradient(180deg, #0e1826, #0a1421); }
/* System: the dark face laid over the light one, cut on the diagonal. */
.ap-mini.split .ap-face.dark { clip-path: polygon(62% 0, 100% 0, 100% 100%, 38% 100%); }
.ap-mini.split::after { content: ""; position: absolute; top: -10%; bottom: -10%; left: 50%; width: 2px; background: rgba(79,151,245,.85); transform: rotate(19deg); }
.ap-lbl { display: flex; align-items: center; gap: 9px; margin-top: 10px; font-size: 13px; font-weight: 700; color: var(--text-primary); }
.ap-lbl .rd { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--border-md); flex: none; }
.ap-card:has(input:checked) .rd { border: 5px solid var(--brand); }
.ap-lbl small { margin-left: auto; font-size: 11px; font-weight: 600; color: var(--text-muted); }
.ap-hint { font-size: 11.5px; color: var(--text-muted); margin: 2px 0 0 25px; }
.ap-note { display: flex; gap: 8px; padding: 12px 18px 16px; font-size: 12px; color: var(--text-muted); line-height: 1.55; }
.ap-note svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
.ap-note b { color: var(--text-primary); font-weight: 600; }

/* B · the order a screen settles its theme in; C · recent changes */
.st-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }
@media (max-width: 1100px) { .st-two { grid-template-columns: 1fr; } }
.ord { display: flex; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--border); align-items: center; }
.ord:last-child { border-bottom: none; }
.ord .n { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; flex: none; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 700; background: var(--bg-subtle); border: 1px solid var(--border); color: var(--text-secondary); }
.ord.here .n { background: var(--brand); border-color: var(--brand); color: #fff; }
.ord .t { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.ord .d { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; line-height: 1.5; }
.chg { display: grid; grid-template-columns: 104px 1fr; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-secondary); }
.chg:last-child { border-bottom: none; }
.chg .w { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); line-height: 1.45; }
.chg .who { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
</style>
@endpush

@section('content')
<div class="sx-page">
    @include('settings._head', [
        'title' => 'Appearance',
        'sub'   => 'How the system looks before anybody has chosen for themselves.',
    ])

    <div class="st-wrap">
        @include('settings._side')

        <div>
            <form method="POST" action="{{ route('system-settings.appearance.update') }}" data-sx-form>
                @csrf
                @method('PUT')

                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">A</span><h2 class="sx-card-title">Default theme</h2><span class="sx-card-note">The theme a screen opens on the first time it is used</span></div>
                    <div class="ap">
                        @foreach($cards as $value => [$label, $hint])
                            <label class="ap-card">
                                <input type="radio" name="default_theme" value="{{ $value }}" hidden @checked($theme === $value)
                                       data-track data-saved="{{ $system->default_theme }}" data-label="Default theme">
                                <div class="ap-mini {{ $value === 'system' ? 'split' : '' }}">
                                    @foreach($value === 'system' ? ['light', 'dark'] : [$value] as $face)
                                        <div class="ap-face {{ $face }}">
                                            <div class="r"><i></i><i class="on"></i><i></i><i></i><i></i></div>
                                            <div class="c"><i style="width:60%"></i><div class="k"><i></i><i></i><i></i></div><i></i><i style="width:80%"></i></div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="ap-lbl"><span class="rd"></span>{{ $label }}@if($system->default_theme === $value)<small>current default</small>@endif</div>
                                <div class="ap-hint">{{ $hint }}</div>
                            </label>
                        @endforeach
                    </div>
                    <div class="ap-note"><i data-lucide="monitor"></i><span>System follows the light or dark setting of each device — Windows, macOS, Android or iPhone — and switches when the device does. This device is set to <b data-device-mode>light</b> right now.</span></div>
                    <div class="ap-note" style="border-top:1px solid var(--border);padding-top:12px"><i data-lucide="info"></i><span>This is the starting point, not a lock. Anyone who uses the toggle in the top bar keeps their own choice — it is saved in their browser and outranks this one. Changing it here reaches a new phone, a fresh browser, and a kiosk nobody has touched.</span></div>
                    <div class="ap-note" style="border-top:1px solid var(--border);padding-top:12px"><i data-lucide="languages"></i><span>The system is English only, and amounts print as pesos to two decimals. Both are decisions rather than controls, so neither is offered here as one.</span></div>
                </div>

                @include('settings._savebar')
            </form>

            <div class="st-two">
                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">B</span><h2 class="sx-card-title">How a screen picks its theme</h2><span class="sx-card-note">first match wins</span></div>
                    <div class="ord">
                        <span class="n">1</span>
                        <div><div class="t">The person’s own choice</div><div class="d">Set with the toggle in the top bar and kept in that browser.</div></div>
                    </div>
                    <div class="ord here">
                        <span class="n">2</span>
                        <div><div class="t">This default — <span data-theme-name>{{ $cards[$theme][0] }}</span></div><div class="d">For a new phone, a fresh browser, or a kiosk nobody has touched.</div></div>
                    </div>
                    <div class="ord">
                        <span class="n">3</span>
                        <div><div class="t">The device’s light or dark setting</div><div class="d">Only when the default is System — it switches when the device does.</div></div>
                    </div>
                </div>
                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">C</span><h2 class="sx-card-title">Recent changes</h2>
                        <div class="sx-card-tools"><a class="sx-link" style="font-size:12px" href="{{ route('audit-logs.index', ['module' => 'Settings', 'range' => 'all']) }}">Audit Logs <i data-lucide="arrow-up-right"></i></a></div>
                    </div>
                    @forelse($changes ?? [] as $c)
                        <div class="chg">
                            <div class="w">{{ $c->created_at->format('M j, Y') }}<br>{{ $c->created_at->format('g:i A') }}</div>
                            <div>{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($c->description, 'Appearance: ')) }}
                                <div class="who">by {{ $c->user_name ?: 'System' }}</div></div>
                        </div>
                    @empty
                        <div class="sx-empty"><i data-lucide="history"></i>No changes recorded yet. Saves from now on are listed here.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('settings._form-script')

@push('scripts')
<script>
(function () {
    const label = document.querySelector('[data-device-mode]');
    const names = { light: 'Light', dark: 'Dark', system: 'System' };
    const form  = document.querySelector('[data-sx-form]');
    const named = () => {
        const v = (form?.querySelector('[name="default_theme"]:checked') || {}).value;
        document.querySelectorAll('[data-theme-name]').forEach(e => { e.textContent = names[v] || v || ''; });
    };
    form?.addEventListener('change', named);
    form?.addEventListener('sx:reset', () => setTimeout(named, 0));
    if (!label || !window.matchMedia) return;
    const dark = window.matchMedia('(prefers-color-scheme: dark)');
    const show = () => { label.textContent = dark.matches ? 'dark' : 'light'; };
    show();
    if (dark.addEventListener) dark.addEventListener('change', show);
})();
</script>
@endpush
