@extends('layouts')

@section('page_title', 'Kiosk')

@php
    $mode       = old('kiosk_attendance_mode', $system->kioskMode());
    $savedMode  = $system->kioskMode();
    $savedGuard = (int) ($system->kiosk_repeat_guard_seconds ?? 180);
    $savedIdle  = (int) ($system->kiosk_idle_return_seconds ?? 60);
    $guard      = (int) old('kiosk_repeat_guard_seconds', $savedGuard);
    $idle       = (int) old('kiosk_idle_return_seconds', $savedIdle);

    // The saved value is always one of the choices, even one set some other way.
    $guardOptions = collect([60, 120, 180, 300])->push($savedGuard)->unique()->sort()->values();
    $idleOptions  = collect([30, 60, 120])->push($savedIdle)->unique()->sort()->values();
    $span = fn (int $s) => $s % 60 === 0 ? ($s / 60) . ' min' : ($s < 60 ? $s . ' s' : intdiv($s, 60) . ' min ' . ($s % 60) . ' s');

    $cards = [
        'buttons' => ['Buttons — TIME IN / TIME OUT', 'The worker presses a button, then scans. A scan without a button records nothing. Best when workers often come and go mid-session.'],
        'auto'    => ['Automatic — scan only', 'No button. The kiosk decides IN or OUT from the worker’s open time in and the shift’s clock. Fastest at the gate: one touch per punch.'],
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

/* A · the two modes, each with a drawing of the kiosk in that mode */
.km { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 16px 18px 18px; }
@media (max-width: 900px) { .km { grid-template-columns: 1fr; } }
.km-card { border: 1px solid var(--border-md); border-radius: 12px; padding: 12px; background: var(--surface); cursor: pointer; margin: 0; transition: border-color .15s, box-shadow .15s; display: block; }
.km-card:hover { border-color: var(--brand); }
.km-card:has(input:checked) { border: 2px solid var(--brand); padding: 11px; box-shadow: 0 0 0 4px var(--brand-subtle); }
.km-mini { height: 150px; border-radius: 9px; background: #0a0e14; border: 1px solid #2b3644; display: grid; grid-template-rows: 18px 1fr; overflow: hidden; }
.km-top { background: #141a23; border-bottom: 1px solid #2b3644; display: flex; align-items: center; gap: 5px; padding: 0 8px; }
.km-top i { height: 4px; width: 22px; background: #2b3644; border-radius: 2px; }
.km-top i.on { background: #2f81f7; }
.km-body { display: grid; grid-template-columns: 44% 1fr; gap: 8px; padding: 9px; }
.km-scan { border: 1px solid #2b3644; border-radius: 7px; background: #141a23; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px; padding: 6px; }
.km-ring { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #3a4656; display: grid; place-items: center; color: #7a8797; }
.km-ring svg { width: 20px; height: 20px; }
.km-ring.auto { width: 50px; height: 50px; border-color: #2ea043; color: #3fb950; box-shadow: 0 0 0 5px rgba(46,160,67,.12); }
.km-ring.auto svg { width: 25px; height: 25px; }
.km-cap { font-size: 7.5px; font-weight: 800; letter-spacing: .8px; color: #b6c2d0; text-align: center; }
.km-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; width: 100%; }
.km-btns span { height: 22px; border-radius: 5px; font-size: 7.5px; font-weight: 800; letter-spacing: .6px; display: grid; place-items: center; }
.km-btns .in { background: rgba(46,160,67,.12); border: 1.5px solid rgba(46,160,67,.6); color: #3fb950; }
.km-btns .out { background: rgba(240,71,62,.1); border: 1.5px solid rgba(240,71,62,.55); color: #f0473e; }
.km-board { border: 1px solid #2b3644; border-radius: 7px; background: #141a23; padding: 6px; display: flex; flex-direction: column; gap: 5px; }
.km-board i { height: 7px; border-radius: 2px; background: #232d3b; }
.km-board i.h { background: #1b232f; height: 9px; }
.km-lbl { display: flex; align-items: center; gap: 9px; margin-top: 11px; font-size: 13.5px; font-weight: 700; color: var(--text-primary); }
.km-lbl .rd { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--border-md); flex: none; }
.km-card:has(input:checked) .rd { border: 5px solid var(--brand); }
.km-lbl small { margin-left: auto; font-size: 11px; font-weight: 600; color: var(--text-muted); }
.km-hint { font-size: 12px; color: var(--text-muted); margin: 3px 0 0 25px; line-height: 1.55; }

/* C · safeguards, chosen from a few sensible values */
.sg { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 28px; padding: 14px 18px; border-bottom: 1px solid var(--border); align-items: center; }
.sg:last-child { border-bottom: none; }
@media (max-width: 900px) { .sg { grid-template-columns: 1fr; gap: 10px; } }
.sg .t { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.sg .d { font-size: 12px; color: var(--text-muted); margin-top: 3px; line-height: 1.5; }
.kp { display: inline-flex; gap: 6px; flex-wrap: wrap; }
.kp label { height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--border); font-size: 12.5px; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 5px; background: var(--surface); cursor: pointer; margin: 0; white-space: nowrap; }
.kp label:hover { border-color: var(--brand); }
.kp label:has(input:checked) { border-color: var(--brand); color: var(--brand); background: var(--brand-subtle); }
.kp label svg { width: 12px; height: 12px; stroke-width: 2.6; display: none; }
.kp label:has(input:checked) svg { display: inline-block; }
.fx { display: flex; gap: 10px; padding: 11px 18px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; }
.fx:last-child { border-bottom: none; }
.fx svg { width: 15px; height: 15px; flex: none; margin-top: 2px; color: var(--success); }
.fx b { color: var(--text-primary); font-weight: 600; }

/* B · how Automatic decides */
.kd-wrap.is-off { opacity: .55; }
.kd-note { display: none; margin: 12px 18px 0; padding: 9px 12px; border-radius: 9px; background: var(--bg-subtle); border: 1px dashed var(--border-md); font-size: 12px; color: var(--text-muted); }
.kd-wrap.is-off .kd-note { display: block; }
.kr { padding: 14px 18px 4px; }
.kr-row { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 14px; align-items: center; margin-bottom: 4px; }
.kr-name { font-size: 12.5px; font-weight: 700; color: var(--text-primary); }
.kr-name small { display: block; font-size: 11px; font-weight: 500; color: var(--text-muted); margin-top: 1px; }
.kr-track { position: relative; height: 34px; border-radius: 7px; background: var(--bg-subtle); border: 1px solid var(--border); overflow: hidden; }
.kr-seg { position: absolute; top: 0; bottom: 0; display: grid; place-items: center; font-size: 10.5px; font-weight: 700; white-space: nowrap; overflow: hidden; }
.kr-seg.early { background: repeating-linear-gradient(90deg, transparent 0 6px, color-mix(in srgb, var(--text-muted) 12%, transparent) 6px 7px); color: var(--text-muted); font-weight: 600; }
.kr-seg.work { background: color-mix(in srgb, var(--brand) 16%, transparent); color: var(--brand); border-left: 1px solid color-mix(in srgb, var(--brand) 35%, transparent); border-right: 1px solid color-mix(in srgb, var(--brand) 35%, transparent); }
.kr-seg.lunch { background: repeating-linear-gradient(45deg, color-mix(in srgb, var(--warning) 22%, transparent) 0 4px, transparent 4px 8px); }
.kr-seg.ot { background: color-mix(in srgb, var(--warning) 10%, transparent); color: var(--warning); }
.kr-cut { position: absolute; top: -3px; bottom: -3px; border-left: 2px dashed var(--warning); }
.kr-ticks { position: relative; height: 16px; margin: 0 0 8px 126px; font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--text-muted); }
.kr-ticks span { position: absolute; transform: translateX(-50%); white-space: nowrap; }
.kr-ticks span.first { transform: none; }
.kr-ticks span.cut { color: var(--warning); font-weight: 700; }
.kr-legend { display: flex; gap: 8px; align-items: center; margin: -2px 0 12px 126px; font-size: 11.5px; color: var(--text-muted); }
.kr-legend i { width: 0; height: 14px; border-left: 2px dashed var(--warning); flex: none; }
@media (max-width: 700px) { .kr-row { grid-template-columns: 1fr; } .kr-ticks, .kr-legend { margin-left: 0; } }
.kd-scroll { overflow-x: auto; }
.kd { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 640px; }
.kd th { text-align: left; font-size: 10.5px; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted); font-weight: 600; padding: 8px 18px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); background: var(--bg-subtle); }
.kd td { padding: 9px 18px; border-bottom: 1px solid var(--border); color: var(--text-secondary); vertical-align: top; }
.kd tr:last-child td { border-bottom: none; }
.kd td.w { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-primary); white-space: nowrap; }
.kd .rec { display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .03em; margin-right: 6px; white-space: nowrap; }
.kd .rec.in { background: var(--success-soft); color: var(--success); }
.kd .rec.out { background: var(--danger-soft); color: var(--danger); }
.kd .rec.auto { background: var(--warning-soft); color: var(--warning); }
.kd .rec.none { background: var(--bg-subtle); color: var(--text-muted); border: 1px solid var(--border); }

/* D · kiosks, and recent changes */
.st-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }
@media (max-width: 1100px) { .st-two { grid-template-columns: 1fr; } }
.kk { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.kk:last-child { border-bottom: none; }
.kk .i { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; flex: none; background: var(--bg-subtle); border: 1px solid var(--border); color: var(--text-secondary); }
.kk .i.on { background: var(--success-soft); border-color: transparent; color: var(--success); }
.kk .i svg { width: 16px; height: 16px; }
.kk > div:nth-child(2) { flex: 1; min-width: 0; }
.kk .t { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kk .t code { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); font-weight: 500; margin-left: 6px; }
.kk .d { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
.kk .s { flex: none; max-width: 210px; text-align: right; font-size: 11.5px; color: var(--text-muted); line-height: 1.5; }
.kk .s b { color: var(--text-primary); font-weight: 600; }
.chg { display: grid; grid-template-columns: 104px 1fr; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-secondary); }
.chg:last-child { border-bottom: none; }
.chg .w { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); line-height: 1.45; }
.chg .who { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
</style>
@endpush

@section('content')
<div class="sx-page">
    @include('settings._head', [
        'title' => 'Kiosk',
        'sub'   => 'How the attendance kiosk records a scan. A saved change reaches the kiosk within a few seconds — nobody has to touch the Pi.',
    ])

    <div class="st-wrap">
        @include('settings._hub')

        <div>
            <form method="POST" action="{{ route('system-settings.kiosk.update') }}" data-sx-form>
                @csrf
                @method('PUT')

                <div class="st-plain">
                    <span class="st-plain-ic"><i data-lucide="message-square-text"></i></span>
                    <div>
                        <span class="sx-label" style="color:var(--brand)">In plain words</span>
                        <p data-plain="auto" @if($mode !== 'auto') hidden @endif>Workers <em>only scan their finger</em> — there is no button. A scan with no open time in is a <em>TIME IN</em>; a scan with one is a <em>TIME OUT</em>. In the lunch break, a scan before <em>{{ $cut }}</em> closes the morning and one from {{ $cut }} opens the afternoon. A second scan within <em data-words="guard">{{ $span($guard) }}</em> is ignored.</p>
                        <p data-plain="buttons" @if($mode === 'auto') hidden @endif>Workers <em>press TIME IN or TIME OUT</em>, then scan their finger. A scan without a button records nothing.</p>
                        <p>After <em data-words="idle">{{ $span($idle) }}</em> without a touch on another tab, the kiosk goes back to ATTENDANCE.</p>
                        <div class="sub">These sentences rewrite themselves as you change the values below.</div>
                    </div>
                </div>

                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">A</span><h2 class="sx-card-title">Attendance mode</h2><span class="sx-card-note">What a worker does at the kiosk to record a time</span></div>
                    <div class="km">
                        @foreach($cards as $value => [$label, $hint])
                            <label class="km-card">
                                <input type="radio" name="kiosk_attendance_mode" value="{{ $value }}" hidden @checked($mode === $value)
                                       data-track data-saved="{{ $savedMode }}" data-label="Attendance mode">
                                <div class="km-mini"><div class="km-top"><i class="on"></i><i></i><i></i><i></i></div>
                                    <div class="km-body">
                                        <div class="km-scan">
                                            @if($value === 'auto')
                                                <span class="km-ring auto"><i data-lucide="fingerprint"></i></span><span class="km-cap">JUST SCAN</span>
                                            @else
                                                <span class="km-ring"><i data-lucide="fingerprint"></i></span><span class="km-cap">PRESS, THEN SCAN</span>
                                                <div class="km-btns"><span class="in">TIME IN</span><span class="out">TIME OUT</span></div>
                                            @endif
                                        </div>
                                        <div class="km-board"><i class="h"></i><i></i><i></i><i></i><i></i><i></i></div>
                                    </div>
                                </div>
                                <div class="km-lbl"><span class="rd"></span>{{ $label }}@if($savedMode === $value)<small>current</small>@endif</div>
                                <div class="km-hint">{{ $hint }}</div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="sx-card" style="margin-top:14px">
                    <div class="sx-card-head"><span class="sx-idx">B</span><h2 class="sx-card-title">Safeguards</h2><span class="sx-card-note">The same on every kiosk</span></div>
                    <div class="sg">
                        <div><div class="t">Ignore a repeat scan within</div><div class="d">In Automatic, so a second touch never turns a TIME IN into a TIME OUT.</div></div>
                        <span class="kp">
                            @foreach($guardOptions as $s)
                                <label><input type="radio" name="kiosk_repeat_guard_seconds" value="{{ $s }}" hidden @checked($guard === $s)
                                              data-track data-saved="{{ $savedGuard }}" data-label="Repeat scan guard"><i data-lucide="check"></i>{{ $span($s) }}</label>
                            @endforeach
                        </span>
                    </div>
                    <div class="sg">
                        <div><div class="t">Back to ATTENDANCE after</div><div class="d">Idle time on another tab before the kiosk returns to the scanner. An open payroll is never cut short.</div></div>
                        <span class="kp">
                            @foreach($idleOptions as $s)
                                <label><input type="radio" name="kiosk_idle_return_seconds" value="{{ $s }}" hidden @checked($idle === $s)
                                              data-track data-saved="{{ $savedIdle }}" data-label="Back to Attendance"><i data-lucide="check"></i>{{ $span($s) }}</label>
                            @endforeach
                        </span>
                    </div>
                    <div class="fx"><i data-lucide="shield-check"></i><span><b>MY PAYROLL and SUMMARY never record.</b> A scan only counts on the ATTENDANCE tab.</span></div>
                    <div class="fx"><i data-lucide="shield-check"></i><span><b>Shift windows still apply.</b> TIME IN opens before a shift and closes when it ends, in both modes.</span></div>
                </div>

                @include('settings._savebar')
            </form>

            <div class="sx-card kd-wrap {{ $mode === 'auto' ? '' : 'is-off' }}" style="margin-top:14px" data-mode-only="auto">
                <div class="sx-card-head"><span class="sx-idx">C</span><h2 class="sx-card-title">How Automatic decides</h2><span class="sx-card-note">Read from each shift — the same AM in · AM out · PM in · PM out as today</span></div>
                <div class="kd-note">Shown for reference. With Buttons, the worker’s button decides instead.</div>
                <div class="kr">
                    @foreach($rulers as $r)
                        <div class="kr-row">
                            <div class="kr-name">{{ $r['name'] }} shift<small>{{ $r['workers'] }} {{ \Illuminate\Support\Str::plural('worker', $r['workers']) }}</small></div>
                            <div class="kr-track">
                                @foreach($r['segments'] as [$class, $from, $to, $text])
                                    <span class="kr-seg {{ $class }}" style="left:{{ $from }}%;width:{{ max(0, $to - $from) }}%">{{ $text }}</span>
                                @endforeach
                                <span class="kr-cut" style="left:{{ $r['cut'] }}%"></span>
                            </div>
                        </div>
                        <div class="kr-ticks">
                            @foreach($r['ticks'] as [$at, $text, $class])
                                <span class="{{ $class }}" style="left:{{ $at }}%">{{ $text }}</span>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="kr-legend"><i></i>Lunch cut-off — halfway through the break, worked out from each shift. Before it, a scan closes the first half; from it, a scan opens the second.</div>
                </div>
                @if($examples)
                    <div class="kd-scroll">
                        <table class="kd">
                            <thead><tr><th style="width:120px">A scan at</th><th style="width:240px">The worker has</th><th>The kiosk records</th></tr></thead>
                            <tbody>
                                @foreach($examples as [$at, $has, $recs, $note])
                                    <tr><td class="w">{{ $at }}</td><td>{{ $has }}</td>
                                        <td>@foreach($recs as [$class, $text])<span class="rec {{ $class }}">{{ $text }}</span>@endforeach{{ $note }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="st-two">
                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">D</span><h2 class="sx-card-title">Kiosks</h2><span class="sx-card-note">Each checks for changes every few seconds</span></div>
                    @forelse($kiosks as $k)
                        <div class="kk">
                            <span class="i {{ $k['online'] ? 'on' : '' }}"><i data-lucide="tablet-smartphone"></i></span>
                            <div>
                                <div class="t">{{ $k['name'] }}<code>{{ $k['code'] }}</code></div>
                                <div class="d">{{ $k['site'] ? 'At ' . $k['site'] : 'No site set' }} · {{ $k['online'] ? 'online' : ($k['heard'] ? 'last heard ' . $k['heard']->diffForHumans() : 'never heard from') }}</div>
                            </div>
                            <div class="s">
                                @if($k['read'])
                                    Read it <b>{{ $k['read']->diffForHumans() }}</b>
                                    <br>{{ $k['online'] ? 'A change reaches it within a few seconds' : 'Picks up changes when it is back online' }}
                                @else
                                    Has not read it yet<br>Needs the new kiosk files
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="sx-empty"><i data-lucide="tablet-smartphone"></i>No kiosks registered yet.</div>
                    @endforelse
                </div>
                <div class="sx-card">
                    <div class="sx-card-head"><span class="sx-idx">E</span><h2 class="sx-card-title">Recent changes</h2>
                        <div class="sx-card-tools"><a class="sx-link" style="font-size:12px" href="{{ route('audit-logs.index', ['module' => 'Settings', 'range' => 'all']) }}">Audit Logs <i data-lucide="arrow-up-right"></i></a></div>
                    </div>
                    @forelse($changes as $c)
                        <div class="chg">
                            <div class="w">{{ $c->created_at->format('M j, Y') }}<br>{{ $c->created_at->format('g:i A') }}</div>
                            <div>{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($c->description, 'Kiosk: ')) }}
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
    const form = document.querySelector('[data-sx-form]');
    if (!form) return;
    const val = name => (form.querySelector('[name="' + name + '"]:checked') || {}).value;
    const span = s => {
        s = parseInt(s, 10) || 0;
        if (s % 60 === 0) { const m = s / 60; return m + (m === 1 ? ' minute' : ' minutes'); }
        return s < 60 ? s + ' seconds' : Math.floor(s / 60) + ' min ' + (s % 60) + ' s';
    };

    function draw() {
        const mode = val('kiosk_attendance_mode') || 'buttons';
        document.querySelectorAll('[data-plain]').forEach(p => { p.hidden = p.dataset.plain !== mode; });
        document.querySelectorAll('[data-words="guard"]').forEach(e => { e.textContent = span(val('kiosk_repeat_guard_seconds')); });
        document.querySelectorAll('[data-words="idle"]').forEach(e => { e.textContent = span(val('kiosk_idle_return_seconds')); });
        document.querySelectorAll('[data-mode-only]').forEach(e => e.classList.toggle('is-off', e.dataset.modeOnly !== mode));
    }

    form.addEventListener('sx:change', draw);
    form.addEventListener('sx:reset', () => setTimeout(draw, 0));
    draw();
})();
</script>
@endpush
