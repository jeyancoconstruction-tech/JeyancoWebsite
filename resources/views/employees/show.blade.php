@extends('layouts')
@section('page_title', $employee->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
{{-- A worker's full profile (2026-11-13 revision, "sheet"). Every field of the
     record is shown whether or not it has been filled in, so a new worker's
     page already has its full shape: a blank reads "Not recorded" in soft grey
     and leads to Edit. Who they are and how complete the record is sit on top,
     this week's pay under it, the record on the left and employment,
     fingerprints and the latest scans down the right. Black and greys only. --}}
@php
    $show = fn ($v) => is_string($v) ? (trim($v) !== '' ? trim($v) : null) : $v;

    $slots    = $employee->fingerprintSlots();
    $needFp   = \App\Models\Employee::fingersTarget();
    $jobTitle = $employee->job_title ?: $employee->position;
    $editUrl  = route('employees.edit', $employee->id);

    $personal = [
        __('Date of birth')  => $employee->birth_date ? $employee->birth_date->format('M d, Y') . ' · ' . $employee->birth_date->age . ' ' . __('yrs') : null,
        __('Place of birth') => $show($employee->birth_place),
        __('Gender')         => $show($employee->gender),
        __('Civil status')   => $show($employee->civil_status),
        __('Nationality')    => $show($employee->nationality),
        __('Blood type')     => $show($employee->blood_type),
    ];
    $contact = [
        __('Mobile number')     => $show($employee->phone),
        __('Email')             => $show($employee->email),
        __('Emergency contact') => $show($employee->emergency_contact_name),
        __('Relationship')      => $show($employee->emergency_contact_relation),
        __('Emergency number')  => $show($employee->emergency_contact_phone),
    ];
    $address = [
        __('House no. / street')  => $show($employee->address_street),
        __('Barangay')            => $show($employee->address_barangay),
        __('City / municipality') => $show($employee->address_city),
        __('Province')            => $show($employee->address_province),
        __('ZIP')                 => $show($employee->address_postal),
    ];
    $ids = [
        'SSS'        => $show($employee->sss_number),
        'PhilHealth' => $show($employee->philhealth_number),
        'Pag-IBIG'   => $show($employee->pagibig_number),
        'TIN'        => $show($employee->tin_number),
    ];
    $sections = [
        ['title' => __('Personal'), 'icon' => 'user', 'rows' => $personal, 'cols' => 3],
        ['title' => __('Contact & emergency'), 'icon' => 'phone', 'rows' => $contact, 'cols' => 3],
        ['title' => __('Address'), 'icon' => 'location-dot', 'rows' => $address, 'cols' => 3],
        ['title' => __('Government IDs'), 'icon' => 'id-card', 'rows' => $ids, 'cols' => 4, 'ids' => true],
    ];
    $have     = fn (array $rows) => collect($rows)->filter(fn ($v) => filled($v))->count();
    $fields   = collect($sections)->sum(fn ($s) => count($s['rows']));
    $recorded = collect($sections)->sum(fn ($s) => $have($s['rows']));
    $pct      = (int) round($recorded / max(1, $fields) * 100);

    // What is still blank, named once: a group's name when all of it is
    // blank, otherwise the fields that are.
    $missing = collect([
        [__('Personal details'), $personal],
        [__('Contact & emergency'), $contact],
        [__('Full address'), $address],
        ['SSS, PhilHealth, Pag-IBIG, TIN', $ids],
    ])->map(function ($g) {
        $gone = collect($g[1])->filter(fn ($v) => blank($v))->keys();
        if ($gone->isEmpty()) return null;
        return $gone->count() === count($g[1]) ? $g[0] : $gone->implode(', ');
    })->filter()->values();

    $num = fn ($k) => (float) ($totals[$k] ?? 0);
    $statusLabel = ['active' => __('Active'), 'pending' => __('Pending'), 'archived' => __('Archived'), 'deleted' => __('Removed')][$employee->status] ?? ucfirst((string) $employee->status);
    $canDtr = \Illuminate\Support\Facades\Route::has('payroll-reports.index') && auth()->user()?->canAccessModule('payroll-reports');
    $ring = 2 * M_PI * 21;
@endphp

<div class="employee-container epf">

    <header class="epf-hero">
        <span class="epf-ico">@include('partials.worker-icon', ['size' => '62%'])</span>
        <div class="epf-who">
            <h1>{{ $employee->name }}</h1>
            <p>
                <span class="epf-chip num">#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                @if($jobTitle)<span class="epf-chip"><i class="fas fa-helmet-safety"></i>{{ $jobTitle }}</span>@endif
                @if($employee->site)<span class="epf-chip"><i class="fas fa-location-dot"></i>{{ $employee->site->name }}</span>@endif
                <span class="epf-chip epf-st st-{{ $employee->status }}"><i></i>{{ $statusLabel }}</span>
            </p>
        </div>
        <div class="epf-done" title="{{ $recorded }} {{ __('of') }} {{ $fields }} {{ __('details recorded') }}">
            <svg viewBox="0 0 50 50" aria-hidden="true">
                <circle cx="25" cy="25" r="21" class="bg"/>
                <circle cx="25" cy="25" r="21" class="fg" stroke-dasharray="{{ round($ring, 2) }}" stroke-dashoffset="{{ round($ring * (1 - $recorded / max(1, $fields)), 2) }}"/>
            </svg>
            <div><b>{{ $pct }}%</b><span>{{ $recorded >= $fields ? __('Profile complete') : $recorded . ' ' . __('of') . ' ' . $fields . ' ' . __('details') }}</span></div>
        </div>
        <div class="epf-acts">
            <a href="{{ route('employees.register') }}" class="epf-btn"><i class="fas fa-arrow-left"></i>{{ __('Employees') }}</a>
            <a href="{{ $editUrl }}" class="epf-btn pri"><i class="fas fa-pen"></i>{{ __('Edit') }}</a>
        </div>
    </header>

    {{-- This week's payroll for the worker, as Payroll works it out. --}}
    <section class="epf-box epf-week">
        <div class="epf-week-t">{{ __('This week') }}<small>{{ $period }}</small></div>
        <div><span>{{ __('Days') }}</span><b>{{ (int) ($totals['workdays'] ?? 0) }}</b></div>
        <div><span>{{ __('Hours') }}</span><b>{{ number_format($num('hours'), 2) }}</b></div>
        <div><span>{{ __('Overtime') }}</span><b>₱{{ number_format($num('overtime'), 2) }}</b></div>
        <div><span>{{ __('Gross') }}</span><b>₱{{ number_format($num('gross'), 2) }}</b></div>
        <div><span>{{ __('Deductions') }}</span><b>₱{{ number_format($num('totalDeductions'), 2) }}</b></div>
        <div class="net"><span>{{ __('Net pay') }}</span><b>₱{{ number_format($num('net'), 2) }}</b></div>
    </section>

    <div class="epf-cols">
        {{-- The record: every field, filled or not. --}}
        <div class="epf-main">
            @if($missing->isNotEmpty())
                <div class="epf-todo">
                    <i class="fas fa-circle-info"></i>
                    <div>
                        <strong>{{ $fields - $recorded }} {{ $fields - $recorded === 1 ? __('detail not recorded yet') : __('details not recorded yet') }}</strong>
                        <p>{{ $missing->implode(' · ') }}</p>
                    </div>
                    <a href="{{ $editUrl }}">{{ __('Complete profile') }}</a>
                </div>
            @endif

            @foreach($sections as $s)
                @php $n = $have($s['rows']); $all = count($s['rows']); @endphp
                <section class="epf-box epf-sec">
                    <div class="epf-sec-h">
                        <span class="epf-sec-i"><i class="fas fa-{{ $s['icon'] }}"></i></span>
                        <h3>{{ $s['title'] }}</h3>
                        <span class="epf-cnt {{ $n === $all ? 'full' : '' }}">@if($n === $all)<i class="fas fa-check"></i> {{ __('Complete') }}@else{{ $n }} {{ __('of') }} {{ $all }}@endif</span>
                    </div>
                    <div class="epf-grid c{{ $s['cols'] }} {{ ! empty($s['ids']) ? 'ids' : '' }}">
                        @foreach($s['rows'] as $label => $value)
                            @if(filled($value))
                                <div class="epf-f"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                            @else
                                <a class="epf-f blank" href="{{ $editUrl }}" title="{{ __('Add in Edit') }}"><span>{{ $label }}</span><b>{{ __('Not recorded') }}</b></a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <aside class="epf-side">
            <section class="epf-box epf-pad">
                <h3>{{ __('Employment') }}</h3>
                <dl class="epf-spec">
                    <dt>{{ __('Labor type') }}</dt><dd>{{ $employee->laborType->name ?? __('Not set') }}</dd>
                    @if($jobTitle && $jobTitle !== ($employee->laborType->name ?? null))<dt>{{ __('Position') }}</dt><dd>{{ $jobTitle }}</dd>@endif
                    <dt>{{ __('Rate') }}</dt><dd>₱{{ number_format((float) $employee->rate_per_hour, 2) }} / {{ __('hr') }}</dd>
                    @if($employee->laborType)<dt>{{ __('Daily') }}</dt><dd>{{ $employee->laborType->getFormattedDailyRate() }}</dd>@endif
                    {{-- An untagged worker falls back to the office default in
                         payroll: worth saying rather than leaving blank. --}}
                    <dt>{{ __('Shift') }}</dt><dd>{{ $employee->shift ? $employee->shift->name . ' · ' . \Carbon\Carbon::parse($employee->shift->starts_at)->format('g:i A') : __('Office default') }}</dd>
                    <dt>{{ __('Hired') }}</dt><dd class="{{ $employee->date_hired ? '' : 'soft' }}">{{ $employee->date_hired?->format('M j, Y') ?: __('Not recorded') }}</dd>
                    <dt>{{ __('Employment') }}</dt><dd>{{ $employee->isContractual() ? __('Contractual') . ($employee->end_of_contract ? ' · ' . __('until') . ' ' . $employee->end_of_contract->format('M d, Y') : '') : __('Regular') }}</dd>
                    <dt>{{ __('Site') }}</dt><dd class="{{ $employee->site ? '' : 'soft' }}">{{ $employee->site->name ?? __('Not assigned') }}</dd>
                    @if(($others = $employee->otherSiteList())->isNotEmpty())<dt>{{ __('Other sites') }}</dt><dd>{{ $others->pluck('name')->implode(', ') }}</dd>@endif
                    <dt>{{ __('Vale balance') }}</dt><dd class="{{ ($employee->vale ?? 0) > 0 ? 'strong' : '' }}">₱{{ number_format($employee->vale ?? 0, 2) }}</dd>
                </dl>
            </section>

            <section class="epf-box epf-pad">
                @php $fpAsked = $employee->extraFingerRequested(); @endphp
                <h3>{{ __('Fingerprints') }}<span>{{ count($slots) }} {{ __('of') }} {{ $needFp }}</span></h3>
                <div class="epf-dots" aria-hidden="true">
                    @for($i = 0; $i < max($needFp, count($slots)); $i++)<i class="{{ $i < count($slots) ? 'on' : '' }}"></i>@endfor
                </div>
                @if(count($slots))
                    <div class="epf-note">{{ count($slots) === 1 ? __('Slot') : __('Slots') }} {{ implode(', ', $slots) }} {{ __('on the sensor') }}</div>
                @endif
                {{-- One finger is enough to work. Another is asked of the
                     kiosk from here, and the worker adds it under Enroll. --}}
                @if(count($slots) === 0)
                    <div class="epf-note">{{ __('First finger: at the kiosk, under Enroll.') }}</div>
                @elseif(count($slots) < $needFp && $fpAsked)
                    <div class="epf-fp">
                        <span class="epf-note">{{ __('Waiting at the kiosk, under Enroll.') }}</span>
                        <form method="POST" action="{{ route('employees.finger-request.cancel', $employee->id) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="epf-link">{{ __('Cancel') }}</button>
                        </form>
                    </div>
                @elseif(count($slots) < $needFp)
                    <form method="POST" action="{{ route('employees.finger-request', $employee->id) }}" class="epf-fp">
                        @csrf
                        <button type="submit" class="epf-btn sm"><i class="fas fa-fingerprint"></i>{{ count($slots) === 1 ? __('Add second fingerprint') : __('Add another fingerprint') }}</button>
                    </form>
                @endif
            </section>

            {{-- The latest scans. An open row is someone not clocked out yet,
                 not a missing time, and says so. --}}
            <section class="epf-box epf-pad epf-scans">
                <h3>{{ __('Recent scans') }}
                    @if($canDtr)<a href="{{ route('payroll-reports.index', ['report' => 'dtr', 'q' => $employee->name]) }}">{{ __('Open DTR') }}</a>@endif
                </h3>
                @if($attendance->isEmpty())
                    <p class="epf-empty">{{ __('No attendance recorded yet.') }}</p>
                @else
                    <div class="epf-scroll">
                        <table class="epf-tbl">
                            @foreach($attendance as $a)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($a->date)->format('D, M j') }}@if($a->session)<small>{{ ucfirst($a->session) }}</small>@endif</td>
                                    <td>{{ $a->time_in ? \Carbon\Carbon::parse($a->time_in)->format('g:i A') : '—' }}</td>
                                    <td>
                                        @if($a->time_out)
                                            {{ \Carbon\Carbon::parse($a->time_out)->format('g:i A') }}
                                        @else
                                            <span class="epf-live">{{ __('Clocked in') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection

@push('styles')
<style>
/* ── Full profile (2026-11-13 revision, "sheet"): every field shown, filled or
   not; black and greys only, soft lines and plenty of room, so the page is
   easy on the eyes. A blank is a quiet "Not recorded", never a warning. ──── */
.epf { --ef-ink: #111827; --ef-on-ink: #fff; --ef-line: var(--border, #e6e8ec); --ef-soft: var(--bg-subtle, #f8f9fb); --ef-mute: var(--text-muted, #667085);
       display: flex; flex-direction: column; gap: 16px; }
[data-bs-theme="dark"] .epf { --ef-ink: #e8edf5; --ef-on-ink: #0f172a; }

.epf-box, .epf-hero { background: var(--surface); border: 1px solid var(--ef-line); border-radius: 14px; min-width: 0; }

/* Who. */
.epf-hero { display: flex; align-items: center; gap: 18px; padding: 18px 22px; }
.epf-ico { flex: none; width: 64px; height: 64px; border-radius: 16px; display: grid; place-items: center; background: var(--ef-soft); border: 1px solid var(--ef-line); }
.epf-who { flex: 1; min-width: 0; }
html[data-bs-theme] .epf-who h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.epf-who p { margin: 8px 0 0; display: flex; flex-wrap: wrap; gap: 6px; }
.epf-chip { display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 9px; border-radius: 7px; background: var(--ef-soft); border: 1px solid var(--ef-line);
            font-size: 12px; font-weight: 500; color: var(--text-secondary, var(--text-primary)); }
.epf-chip i { font-size: 10.5px; color: var(--ef-mute); }
.epf-chip.num { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--ef-mute); }
.epf-st { font-weight: 600; }
.epf-st i { width: 7px; height: 7px; border-radius: 50%; background: var(--ef-mute); }
.epf-st.st-active { color: var(--text-primary); }
.epf-st.st-active i { background: var(--ef-ink); }
.epf-st.st-pending i { background: transparent; border: 1.5px solid var(--ef-mute); }

.epf-done { flex: none; display: flex; align-items: center; gap: 10px; padding: 0 18px; border-left: 1px solid var(--ef-line); border-right: 1px solid var(--ef-line); }
.epf-done svg { width: 50px; height: 50px; transform: rotate(-90deg); }
.epf-done circle { fill: none; stroke-width: 5; }
.epf-done .bg { stroke: var(--ef-line); }
.epf-done .fg { stroke: var(--ef-ink); stroke-linecap: round; }
.epf-done b { display: block; font-size: 17px; font-weight: 700; line-height: 1.1; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.epf-done span { display: block; margin-top: 2px; font-size: 11.5px; color: var(--ef-mute); white-space: nowrap; }

.epf-acts { display: flex; gap: 8px; }
.epf-btn { height: 36px; padding: 0 15px; border-radius: 9px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
           border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary); }
.epf-btn:hover { background: var(--ef-soft); color: var(--text-primary); }
.epf-btn.pri { background: var(--ef-ink); border-color: var(--ef-ink); color: var(--ef-on-ink); }
.epf-btn.pri:hover { opacity: .86; color: var(--ef-on-ink); }
.epf-btn i { font-size: 12px; opacity: .8; }

/* This week, in one strip. */
.epf-week { display: grid; grid-template-columns: 1.3fr repeat(6, minmax(0, 1fr)); }
.epf-week > div { padding: 14px 20px; min-width: 0; }
.epf-week > div + div { border-left: 1px solid var(--ef-line); }
.epf-week-t { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.epf-week-t small { display: block; margin-top: 3px; font-size: 12px; font-weight: 400; color: var(--ef-mute); }
.epf-week span { display: block; font-size: 11.5px; color: var(--ef-mute); }
.epf-week b { display: block; margin-top: 3px; font-size: 19px; font-weight: 600; letter-spacing: -.01em; font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text-primary); }
.epf-week .net { background: var(--ef-soft); border-radius: 0 14px 14px 0; }
.epf-week .net b { font-weight: 700; }

.epf-cols { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 16px; align-items: start; }
.epf-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

/* The record: a card per section, every field in it. */
.epf-sec { padding: 16px 22px 18px; }
.epf-sec-h { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.epf-sec-i { flex: none; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; background: var(--ef-soft); color: var(--ef-mute); font-size: 12px; }
html[data-bs-theme] .epf-sec-h h3 { flex: 1; margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.epf-cnt { font-size: 12px; font-weight: 500; color: var(--ef-mute); font-variant-numeric: tabular-nums; }
.epf-cnt.full { color: var(--text-primary); font-weight: 600; }
.epf-cnt i { font-size: 10.5px; }
.epf-grid { display: grid; gap: 4px 14px; }
.epf-grid.c3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.epf-grid.c4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.epf-f { display: block; min-width: 0; padding: 9px 12px; border-radius: 9px; text-decoration: none; color: inherit; }
.epf-f span { display: block; font-size: 11.5px; color: var(--ef-mute); }
.epf-f b { display: block; margin-top: 3px; font-size: 13.5px; font-weight: 500; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-grid.ids .epf-f:not(.blank) b { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
.epf-f.blank { background: var(--ef-soft); }
.epf-f.blank b { font-weight: 400; color: var(--ef-mute); opacity: .75; }
.epf-f.blank:hover { background: color-mix(in srgb, var(--ef-ink) 6%, var(--surface)); color: inherit; }
.epf-f.blank:hover b { opacity: 1; text-decoration: underline; text-underline-offset: 3px; }

/* What is left to fill in: a quiet line, not an alarm. */
.epf-todo { display: flex; align-items: center; gap: 14px; padding: 13px 16px 13px 18px; border-radius: 12px; border: 1px dashed var(--border-md); background: var(--surface); }
.epf-todo > i { color: var(--ef-mute); font-size: 15px; }
.epf-todo > div { flex: 1; min-width: 0; }
.epf-todo strong { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); }
.epf-todo p { margin: 2px 0 0; font-size: 12px; line-height: 1.5; color: var(--ef-mute); }
.epf-todo a { flex: none; height: 32px; padding: 0 13px; border-radius: 8px; display: inline-flex; align-items: center; font-size: 12.5px; font-weight: 600; text-decoration: none;
              color: var(--ef-on-ink); background: var(--ef-ink); }
.epf-todo a:hover { opacity: .86; color: var(--ef-on-ink); }

/* Down the right. */
.epf-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.epf-pad { padding: 16px 20px; }
html[data-bs-theme] .epf-pad > h3 { margin: 0 0 8px; font-size: 14px; font-weight: 700; color: var(--text-primary); display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.epf-pad > h3 a { font-size: 12.5px; font-weight: 600; color: var(--text-primary); text-decoration: underline; text-underline-offset: 3px; text-decoration-color: var(--border-md); }
.epf-pad > h3 span { font-size: 12.5px; font-weight: 600; color: var(--ef-mute); font-variant-numeric: tabular-nums; }
.epf-spec { display: grid; grid-template-columns: auto 1fr; margin: 0; font-size: 13px; }
.epf-spec dt, .epf-spec dd { padding: 8px 0; border-bottom: 1px solid var(--ef-line); }
.epf-spec dt:nth-last-child(2), .epf-spec dd:last-child { border-bottom: 0; }
.epf-spec dt { font-weight: 400; color: var(--ef-mute); white-space: nowrap; padding-right: 14px; }
.epf-spec dd { margin: 0; text-align: right; font-weight: 500; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-spec dd.soft { color: var(--ef-mute); font-weight: 400; }
.epf-spec dd.strong { font-weight: 700; }
.epf-dots { display: flex; gap: 6px; margin: 2px 0 8px; }
.epf-dots i { flex: 1; height: 6px; border-radius: 3px; background: var(--ef-line); }
.epf-dots i.on { background: var(--ef-ink); }
.epf-note { font-size: 12px; color: var(--ef-mute); }
.epf-fp { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 10px; }
.epf-fp form { margin: 0; }
.epf-btn.sm { width: 100%; justify-content: center; height: 34px; font-size: 12.5px; }
.epf-link { padding: 0; border: 0; background: none; font-size: 12.5px; font-weight: 600; color: var(--text-primary); text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
.epf-scroll { max-height: 360px; overflow-y: auto; }
.epf-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; font-variant-numeric: tabular-nums; }
.epf-tbl td { padding: 8px 0; border-bottom: 1px solid var(--ef-line); white-space: nowrap; color: var(--text-primary); font-weight: 500; text-align: right; }
.epf-tbl td:first-child { text-align: left; color: var(--ef-mute); font-weight: 400; }
.epf-tbl td + td { padding-left: 12px; }
.epf-tbl tr:last-child td { border-bottom: 0; }
.epf-tbl small { margin-left: 6px; font-size: 11px; color: var(--ef-mute); opacity: .8; }
.epf-live { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text-primary); }
.epf-live::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--ef-ink); }
.epf-empty { margin: 4px 0 0; font-size: 13px; color: var(--ef-mute); }

@media (max-width: 1200px) {
    .epf-cols { grid-template-columns: 1fr; }
    .epf-week { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .epf-week > div:nth-child(5) { border-left: 0; }
    .epf-week > div:nth-child(n+5) { border-top: 1px solid var(--ef-line); }
    .epf-week .net { border-radius: 0 0 14px 0; }
}
@media (max-width: 900px) {
    .epf-hero { flex-wrap: wrap; }
    .epf-done { order: 3; border: 0; padding: 0; }
    .epf-acts { order: 4; margin-left: auto; }
    .epf-grid.c3, .epf-grid.c4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .epf-hero, .epf-sec { padding: 14px 16px; }
    .epf-acts { width: 100%; } .epf-btn { flex: 1; justify-content: center; }
    .epf-week { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .epf-week > div:nth-child(odd) { border-left: 0; }
    .epf-week > div:nth-child(n+3) { border-top: 1px solid var(--ef-line); }
    .epf-week .net { border-radius: 0 0 14px 0; }
    .epf-grid.c3, .epf-grid.c4 { grid-template-columns: 1fr; }
    .epf-todo { flex-wrap: wrap; } .epf-todo a { width: 100%; justify-content: center; }
}
</style>
@endpush
