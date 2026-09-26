@extends('layouts')

@section('page_title', 'Security')

@php
    $v = fn ($key) => (int) old($key, $system->{$key});
    $rules = [
        [
            'key' => 'session_timeout_minutes', 'title' => 'Session timeout', 'unit' => 'min', 'step' => 5, 'min' => 5, 'max' => 1440,
            'label' => 'session timeout', 'range' => 'Allowed 5 min – 24 h',
            'desc' => 'How long someone can be idle before they’re signed out.',
            'presets' => [30 => '30 min', 60 => '1 h', 120 => '2 h', 480 => '8 h'],
        ],
        [
            'key' => 'password_min_length', 'title' => 'Minimum password length', 'unit' => 'characters', 'step' => 1, 'min' => 8, 'max' => 64,
            'label' => 'minimum password length', 'range' => 'Allowed 8 – 64',
            'desc' => 'Checked when a password is set or reset. Passwords already on file aren’t re-checked, so raising this locks nobody out.',
            'meter' => true,
        ],
        [
            'key' => 'max_login_attempts', 'title' => 'Failed sign-ins before lockout', 'unit' => 'tries', 'step' => 1, 'min' => 3, 'max' => 20,
            'label' => 'failed sign-ins', 'range' => 'Allowed 3 – 20', 'note' => '· fewer than 3 locks people out for a typo',
            'desc' => 'Counted per username and computer together, so one person’s typos don’t lock out everyone else.',
        ],
        [
            'key' => 'lockout_seconds', 'title' => 'Lockout length', 'unit' => 'sec', 'step' => 30, 'min' => 30, 'max' => 3600,
            'label' => 'lockout length', 'range' => 'Allowed 30 s – 1 h',
            'desc' => 'How long that username waits before it can try again from that computer.',
            'presets' => [30 => '30 s', 60 => '1 min', 300 => '5 min', 900 => '15 min'],
        ],
    ];
@endphp

@push('styles')
@include('system._kit')
<style>
.st-plain { display: flex; gap: 14px; padding: 14px 16px; border-radius: 12px; background: var(--brand-subtle); border: 1px solid color-mix(in srgb, var(--brand) 25%, transparent); margin-bottom: 14px; }
.st-plain-ic { width: 34px; height: 34px; border-radius: 9px; background: var(--brand); color: #fff; display: grid; place-items: center; flex: none; }
.st-plain-ic svg { width: 17px; height: 17px; }
.st-plain p { margin: 5px 0 0; font-size: 14px; line-height: 1.7; color: var(--text-primary); }
.st-plain em { font-style: normal; font-weight: 700; background: var(--surface); border: 1px solid color-mix(in srgb, var(--brand) 30%, transparent); border-radius: 5px; padding: 1px 6px; color: var(--brand); white-space: nowrap; }
.st-plain .sub { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
.presets button svg { display: none; }
.presets button.on svg { display: inline-block; }
.pwm { display: flex; gap: 3px; align-items: center; }
.pwm i { width: 13px; height: 18px; border-radius: 3px; background: var(--bg-subtle); border: 1px solid var(--border); }
.pwm i.on { background: var(--brand); border-color: var(--brand); }
.pwm i.rec { border-style: dashed; border-color: var(--success); }
.pwm-l { font-size: 11.5px; color: var(--text-muted); margin-left: 8px; }
.st-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }
@media (max-width: 1100px) { .st-two { grid-template-columns: 1fr; } }
.hy { display: flex; align-items: center; gap: 12px; padding: 11px 16px; border-bottom: 1px solid var(--border); }
.hy:last-child { border-bottom: none; }
.hy .i { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; flex: none; }
.hy .i svg { width: 15px; height: 15px; }
.hy .i.ok { background: var(--success-soft); color: var(--success); }
.hy .i.danger { background: var(--danger-soft); color: var(--danger); }
.hy .i.muted { background: var(--bg-subtle); color: var(--text-muted); border: 1px solid var(--border); }
.hy .i.warn { background: var(--warning-soft); color: var(--warning); }
.hy .t { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.hy .d { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
.hy .sx-link { margin-left: auto; font-size: 12px; }
.chg { display: grid; grid-template-columns: 104px 1fr; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-secondary); }
.chg:last-child { border-bottom: none; }
.chg .w { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); line-height: 1.45; }
.chg .who { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
.chg .from { text-decoration: line-through; color: var(--text-muted); }
.chg .to { font-weight: 700; color: var(--text-primary); }
</style>
@endpush

@section('content')
<div class="sx-page">
    @include('settings._head', [
        'title' => 'Security',
    ])

    <div class="st-wrap">
        @include('settings._side')

        <div>
            <form method="POST" action="{{ route('system-settings.security.update') }}" data-sx-form>
                @csrf
                @method('PUT')

                <div class="st-plain">
                    <span class="st-plain-ic"><i data-lucide="message-square-text"></i></span>
                    <div>
                        <span class="sx-label" style="color:var(--brand)">In plain words</span>
                        <p>Anyone idle for <em data-words="session"></em> is signed out. A new password needs at least <em data-words="password"></em>.
                           After <em data-words="attempts"></em>, that username is locked out <em>on that computer</em> for <em data-words="lockout"></em> — everyone else can still sign in.</p>
                        <div class="sub">This sentence rewrites itself as you change the values below.</div>
                    </div>
                </div>

                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">A</span><h2 class="sx-card-title">Sign-in rules</h2><span class="sx-card-note">Applied to every sign-in, and to the next password set or reset</span></div>

                    @foreach($rules as $r)
                        <div class="st-row">
                            <div class="st-row-l">
                                <label class="t" for="{{ $r['key'] }}">{{ $r['title'] }} <span class="edited" data-edited="{{ $r['key'] }}" hidden>Edited</span></label>
                                <div class="d">{{ $r['desc'] }}</div>
                            </div>
                            <div class="st-field">
                                <div class="st-inline">
                                    <span class="stepper" data-dirty-wrap="{{ $r['key'] }}">
                                        <button type="button" data-step="-{{ $r['step'] }}" aria-label="Less"><i data-lucide="minus"></i></button>
                                        <span class="v">
                                            <input type="number" id="{{ $r['key'] }}" name="{{ $r['key'] }}" value="{{ $v($r['key']) }}"
                                                   min="{{ $r['min'] }}" max="{{ $r['max'] }}" required
                                                   data-track data-saved="{{ (int) $system->{$r['key']} }}" data-label="{{ $r['label'] }}">
                                            <span class="u">{{ $r['unit'] }}</span>
                                        </span>
                                        <button type="button" data-step="{{ $r['step'] }}" aria-label="More"><i data-lucide="plus"></i></button>
                                    </span>
                                    @if(! empty($r['presets']))
                                        <span class="presets" data-presets="{{ $r['key'] }}">
                                            @foreach($r['presets'] as $value => $text)
                                                <button type="button" data-set="{{ $value }}"><i data-lucide="check"></i>{{ $text }}</button>
                                            @endforeach
                                        </span>
                                    @endif
                                    @if(! empty($r['meter']))
                                        <span class="pwm" data-meter></span><span class="pwm-l">12 or more is stronger</span>
                                    @endif
                                </div>
                                <div class="st-help"><span class="range">{{ $r['range'] }}</span>@if(! empty($r['note']))<span>{{ $r['note'] }}</span>@endif</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @include('settings._savebar')
            </form>

            <div class="st-two">
                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">B</span><h2 class="sx-card-title">Account hygiene</h2><span class="sx-card-note">from the accounts on file</span></div>
                    <div class="hy">
                        <span class="i {{ $hygiene['admins'] ? 'ok' : 'danger' }}"><i data-lucide="shield-check"></i></span>
                        <div><div class="t">{{ $hygiene['admins'] }} active {{ \Illuminate\Support\Str::plural('administrator', $hygiene['admins']) }}</div><div class="d">At least one is always kept</div></div>
                        <a class="sx-link" href="{{ route('users-roles.index', ['role' => 'admin']) }}">Show <i data-lucide="arrow-up-right"></i></a>
                    </div>
                    <div class="hy">
                        <span class="i muted"><i data-lucide="user-x"></i></span>
                        <div><div class="t">{{ $hygiene['disabled']->count() }} disabled {{ \Illuminate\Support\Str::plural('account', $hygiene['disabled']->count()) }}</div>
                            <div class="d">{{ $hygiene['disabled']->count() ? \Illuminate\Support\Str::limit($hygiene['disabled']->take(3)->implode(', '), 60) . ($hygiene['disabled']->count() > 3 ? ' and ' . ($hygiene['disabled']->count() - 3) . ' more' : '') : 'None — every account can sign in' }}</div></div>
                        @if($hygiene['disabled']->count())<a class="sx-link" href="{{ route('users-roles.index', ['status' => 'disabled']) }}">Show <i data-lucide="arrow-up-right"></i></a>@endif
                    </div>
                    <div class="hy">
                        <span class="i {{ $hygiene['idle']->count() ? 'warn' : 'ok' }}"><i data-lucide="clock-alert"></i></span>
                        <div><div class="t">{{ $hygiene['idle']->count() }} {{ \Illuminate\Support\Str::plural('account', $hygiene['idle']->count()) }} idle for 90+ days</div>
                            <div class="d">{{ $hygiene['idle']->count() ? $hygiene['idle']->first() . ($hygiene['idle']->count() > 1 ? ' and ' . ($hygiene['idle']->count() - 1) . ' ' . \Illuminate\Support\Str::plural('other', $hygiene['idle']->count() - 1) : '') . ' still ' . ($hygiene['idle']->count() > 1 ? 'have' : 'has') . ' a working password' : 'Everyone active has signed in lately' }}</div></div>
                        @if($hygiene['idle']->count())<a class="sx-link" href="{{ route('users-roles.index', ['status' => 'idle']) }}">Review <i data-lucide="arrow-up-right"></i></a>@endif
                    </div>
                </div>
                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">C</span><h2 class="sx-card-title">Recent changes to these rules</h2>
                        <div class="sx-card-tools"><a class="sx-link" style="font-size:12px" href="{{ route('audit-logs.index', ['module' => 'Settings', 'range' => 'all']) }}">Audit Logs <i data-lucide="arrow-up-right"></i></a></div>
                    </div>
                    @forelse($changes as $c)
                        <div class="chg">
                            <div class="w">{{ $c->created_at->format('M j, Y') }}<br>{{ $c->created_at->format('g:i A') }}</div>
                            <div>
                                @foreach(explode(', ', \Illuminate\Support\Str::after($c->description, 'Security: ')) as $item)
                                    @if(preg_match('/^(.*) (\S+) → (\S+)(.*)$/u', $item, $m))
                                        <div>{{ \Illuminate\Support\Str::ucfirst($m[1]) }} <span class="from">{{ $m[2] }}</span> → <span class="to">{{ $m[3] }}</span>{{ $m[4] }}</div>
                                    @else
                                        <div>{{ \Illuminate\Support\Str::ucfirst($item) }}</div>
                                    @endif
                                @endforeach
                                <div class="who">by {{ $c->user_name ?: 'System' }}</div>
                            </div>
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
    const form = document.querySelector('[data-sx-form]');
    if (!form) return;
    const num = n => parseInt(form.elements[n]?.value, 10) || 0;
    const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);

    function span(minutes) {
        if (minutes % 60 === 0) return plural(minutes / 60, 'hour', 'hours');
        if (minutes > 60) return plural(Math.floor(minutes / 60), 'hour', 'hours') + ' ' + plural(minutes % 60, 'minute', 'minutes');
        return plural(minutes, 'minute', 'minutes');
    }
    function seconds(s) {
        if (s % 60 === 0) return plural(s / 60, 'minute', 'minutes');
        if (s > 60) return plural(Math.floor(s / 60), 'minute', 'minutes') + ' ' + plural(s % 60, 'second', 'seconds');
        return plural(s, 'second', 'seconds');
    }

    function draw() {
        const words = {
            session:  span(num('session_timeout_minutes')),
            password: plural(num('password_min_length'), 'character', 'characters'),
            attempts: plural(num('max_login_attempts'), 'wrong try', 'wrong tries'),
            lockout:  seconds(num('lockout_seconds')),
        };
        document.querySelectorAll('[data-words]').forEach(el => { el.textContent = words[el.dataset.words]; });

        document.querySelectorAll('[data-presets]').forEach(p => {
            const current = form.elements[p.dataset.presets].value;
            p.querySelectorAll('[data-set]').forEach(b => b.classList.toggle('on', b.dataset.set === current));
        });

        const meter = document.querySelector('[data-meter]');
        if (meter) {
            const len = num('password_min_length');
            meter.innerHTML = Array.from({ length: 16 }, (_, i) => '<i class="' + (i < len ? 'on' : (i === 11 ? 'rec' : '')) + '"></i>').join('');
        }
    }

    form.addEventListener('click', e => {
        const step = e.target.closest('[data-step]');
        const set  = e.target.closest('[data-set]');
        if (!step && !set) return;
        const input = step ? step.closest('.stepper').querySelector('input') : form.elements[set.closest('[data-presets]').dataset.presets];
        const min = +input.min, max = +input.max;
        let next = set ? +set.dataset.set : (parseInt(input.value, 10) || 0) + (+step.dataset.step);
        input.value = Math.min(max, Math.max(min, next));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    form.addEventListener('sx:change', draw);
    form.addEventListener('sx:reset', () => setTimeout(draw, 0));
    draw();
})();
</script>
@endpush
