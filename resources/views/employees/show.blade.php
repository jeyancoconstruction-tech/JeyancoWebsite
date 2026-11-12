@extends('layouts')
@section('page_title', $employee->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
{{-- A worker's full profile (2026-11-12 revision, "calm"). Who they are in
     one line; this week's pay as plain figures; then only what has been
     recorded, with one note naming what has not; and down the right their
     employment, how complete the record is, and the latest kiosk scans. --}}
@php
    $show = fn ($v) => is_string($v) ? (trim($v) !== '' ? trim($v) : null) : $v;
    $count = fn (array $vals) => collect($vals)->filter(fn ($v) => filled($v))->count();

    $slots   = $employee->fingerprintSlots();
    $needFp  = \App\Models\Employee::fingersTarget();
    $jobTitle = $employee->job_title ?: $employee->position;

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
        __('Emergency contact') => collect([$show($employee->emergency_contact_name), $employee->emergency_contact_relation ? '(' . $employee->emergency_contact_relation . ')' : null])->filter()->implode(' ') ?: null,
        __('Emergency number')  => $show($employee->emergency_contact_phone),
    ];
    $address = [
        __('House no. / street') => $show($employee->address_street),
        __('Barangay')           => $show($employee->address_barangay),
        __('City / municipality') => $show($employee->address_city),
        __('Province')           => $show($employee->address_province),
        __('ZIP')                => $show($employee->address_postal),
    ];
    $ids = [
        'SSS'        => $show($employee->sss_number),
        'PhilHealth' => $show($employee->philhealth_number),
        'Pag-IBIG'   => $show($employee->pagibig_number),
        'TIN'        => $show($employee->tin_number),
    ];
    // Emergency relationship is counted as its own field, as on the form.
    $recorded = $count($personal) + $count([$employee->phone, $employee->email, $employee->emergency_contact_name,
                 $employee->emergency_contact_relation, $employee->emergency_contact_phone]) + $count($address) + $count($ids);
    $fields   = count($personal) + 5 + count($address) + count($ids);

    $num = fn ($k) => (float) ($totals[$k] ?? 0);
    $statusLabel = ['active' => __('Active'), 'pending' => __('Pending'), 'archived' => __('Archived'), 'deleted' => __('Removed')][$employee->status] ?? ucfirst((string) $employee->status);
@endphp

@php
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
    $filledRows = fn (array $rows) => collect($rows)->filter(fn ($v) => filled($v));
    $canDtr = \Illuminate\Support\Facades\Route::has('payroll-reports.index') && auth()->user()?->canAccessModule('payroll-reports');
@endphp

<div class="employee-container epf">

    <header class="epf-hero">
        <span class="epf-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
        <div class="epf-who">
            <h1>{{ $employee->name }}</h1>
            <p>
                <span>#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                @if($jobTitle)<span>{{ $jobTitle }}</span>@endif
                @if($employee->site)<span>{{ $employee->site->name }}</span>@endif
                <span class="epf-st st-{{ $employee->status }}"><i></i>{{ $statusLabel }}</span>
            </p>
        </div>
        <div class="epf-acts">
            <a href="{{ route('employees.register') }}" class="epf-btn"><i class="fas fa-arrow-left"></i>{{ __('Employees') }}</a>
            <a href="{{ route('employees.edit', $employee->id) }}" class="epf-btn pri"><i class="fas fa-pen"></i>{{ __('Edit') }}</a>
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
        {{-- The record: only what has been recorded. Whatever is blank is
             named once, in the note at the end, with the way to fill it in. --}}
        <section class="epf-box epf-rec">
            @foreach([[__('Personal'), $personal], [__('Contact & emergency'), $contact]] as [$title, $rows])
                @if(($have = $filledRows($rows))->isNotEmpty())
                    <div class="epf-blk">
                        <h3>{{ $title }}</h3>
                        @foreach($have as $label => $value)
                            <div class="epf-r"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if(($have = $filledRows($address))->isNotEmpty())
                <div class="epf-blk full">
                    <h3>{{ __('Address') }}</h3>
                    <div class="epf-r"><span>{{ __('Home') }}</span><b>{{ collect([$show($employee->address_street), $show($employee->address_barangay), $show($employee->address_city), trim(($show($employee->address_province) ?? '') . ' ' . ($show($employee->address_postal) ?? ''))])->filter()->implode(', ') }}</b></div>
                </div>
            @endif

            @if(($have = $filledRows($ids))->isNotEmpty())
                <div class="epf-blk full">
                    <h3>{{ __('Government IDs') }}</h3>
                    <div class="epf-ids">
                        @foreach($have as $label => $value)
                            <div><span>{{ $label }}</span><b>{{ $value }}</b></div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($missing->isNotEmpty())
                <div class="epf-todo">
                    <div>
                        <strong>{{ $fields - $recorded }} {{ $fields - $recorded === 1 ? __('detail not recorded yet') : __('details not recorded yet') }}</strong>
                        <p>{{ $missing->implode(' · ') }}</p>
                    </div>
                    <a href="{{ route('employees.edit', $employee->id) }}">{{ __('Complete profile') }}</a>
                </div>
            @endif
        </section>

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
                    <dt>{{ __('Hired') }}</dt><dd>{{ $employee->date_hired?->format('M j, Y') ?: __('Not recorded') }}</dd>
                    <dt>{{ __('Employment') }}</dt><dd>{{ $employee->isContractual() ? __('Contractual') . ($employee->end_of_contract ? ' · ' . __('until') . ' ' . $employee->end_of_contract->format('M d, Y') : '') : __('Regular') }}</dd>
                    <dt>{{ __('Site') }}</dt><dd>{{ $employee->site->name ?? __('Not assigned') }}</dd>
                    @if(($others = $employee->otherSiteList())->isNotEmpty())<dt>{{ __('Other sites') }}</dt><dd>{{ $others->pluck('name')->implode(', ') }}</dd>@endif
                    <dt>{{ __('Vale balance') }}</dt><dd class="{{ ($employee->vale ?? 0) > 0 ? 'warn' : '' }}">₱{{ number_format($employee->vale ?? 0, 2) }}</dd>
                </dl>
            </section>

            <section class="epf-box epf-pad">
                <h3>{{ __('Profile') }}
                    @if($recorded >= $fields)<span class="ok"><i class="fas fa-check"></i> {{ __('Complete') }}</span>@else<span>{{ $recorded }} {{ __('of') }} {{ $fields }}</span>@endif
                </h3>
                @if($recorded < $fields)<div class="epf-meter"><i style="width: {{ round($recorded / max(1, $fields) * 100) }}%"></i></div>@endif
                @php $fpAsked = $employee->extraFingerRequested(); @endphp
                <div class="epf-r"><span>{{ __('Fingerprints') }}</span>
                    <b class="{{ count($slots) === 0 || $fpAsked ? 'warn' : '' }}">{{ count($slots) ? (count($slots) === 1 ? __('Slot') : __('Slots')) . ' ' . implode(', ', $slots) . ' · ' : '' }}{{ count($slots) }} {{ __('of') }} {{ $needFp }}</b></div>
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
/* ── Full profile (2026-11-12 revision, "calm"): one line of who, this week's
   pay as plain figures, only what has been recorded with one note naming
   what has not, and the employment, profile and scans down the right.
   Navy is the one accent, as on the worker card and Edit Employee. ──────── */
.epf { --ef-navy: #1f3a5f; --ef-navy-ink: #1f3a5f; --ef-green: #2f7a4f; --ef-amber: #9a6418; --ef-amber-bg: #fbf4e6; --ef-line: var(--border, #e6e8ec);
       display: flex; flex-direction: column; gap: 20px; }
[data-bs-theme="dark"] .epf { --ef-navy: #2c5282; --ef-navy-ink: #8fb3df; --ef-green: #6cc08e; --ef-amber: #e3b467; --ef-amber-bg: rgba(227, 180, 103, .09); }

.epf-hero { display: flex; align-items: center; gap: 16px; }
.epf-ico { flex: none; width: 58px; height: 58px; border-radius: 12px; display: grid; place-items: center; }
.epf-who { flex: 1; min-width: 0; }
html[data-bs-theme] .epf-who h1 { margin: 0; font-size: 24px; font-weight: 600; letter-spacing: -.015em; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.epf-who p { margin: 4px 0 0; display: flex; flex-wrap: wrap; align-items: center; font-size: 13px; color: var(--text-muted); }
.epf-who p > span + span::before { content: '/'; margin: 0 9px; opacity: .45; font-weight: 400; color: var(--text-muted); }
.epf-st { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text-secondary); }
.epf-st i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.epf-st.st-active { color: var(--ef-green); }
.epf-st.st-pending { color: var(--ef-amber); }
.epf-acts { display: flex; gap: 8px; }
.epf-btn { height: 36px; padding: 0 15px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; text-decoration: none;
           border: 1px solid var(--border-md); background: transparent; color: var(--text-primary); }
.epf-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
.epf-btn.pri { background: var(--ef-navy); border-color: var(--ef-navy); color: #fff; }
.epf-btn.pri:hover { filter: brightness(1.12); color: #fff; }
.epf-btn i { font-size: 12px; opacity: .8; }

.epf-box { background: var(--surface); border: 1px solid var(--ef-line); border-radius: 12px; min-width: 0; }

/* This week, in one strip. */
.epf-week { display: grid; grid-template-columns: 1.3fr repeat(6, minmax(0, 1fr)); }
.epf-week > div { padding: 15px 20px; min-width: 0; }
.epf-week > div + div { border-left: 1px solid var(--ef-line); }
.epf-week-t { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.epf-week-t small { display: block; margin-top: 3px; font-size: 12px; font-weight: 400; color: var(--text-muted); }
.epf-week span { display: block; font-size: 11.5px; color: var(--text-muted); }
.epf-week b { display: block; margin-top: 3px; font-size: 19px; font-weight: 600; letter-spacing: -.01em; font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text-primary); }
.epf-week .net b { color: var(--ef-navy-ink); }

.epf-cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }

/* The record. */
.epf-rec { display: grid; grid-template-columns: 1fr 1fr; column-gap: 40px; padding: 4px 24px 22px; }
.epf-blk { padding: 18px 0 2px; min-width: 0; }
.epf-blk.full { grid-column: 1 / -1; }
html[data-bs-theme] .epf-blk h3, html[data-bs-theme] .epf-pad > h3 { margin: 0 0 6px; font-size: 12px; font-weight: 600; color: var(--text-muted); display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.epf-pad > h3 a { font-weight: 600; color: var(--ef-navy-ink); text-decoration: none; }
.epf-pad > h3 .ok { color: var(--ef-green); }
.epf-pad > h3 span { color: var(--text-primary); font-variant-numeric: tabular-nums; }
.epf-spec { display: grid; grid-template-columns: auto 1fr; margin: 0; font-size: 13px; }
.epf-spec dt, .epf-spec dd { padding: 7px 0; border-bottom: 1px solid var(--ef-line); }
.epf-spec dt:nth-last-child(2), .epf-spec dd:last-child { border-bottom: 0; }
.epf-spec dt { font-weight: 400; color: var(--text-muted); white-space: nowrap; padding-right: 14px; }
.epf-spec dd { margin: 0; text-align: right; font-weight: 500; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-spec dd.warn { color: var(--ef-amber); }
.epf-r { display: flex; justify-content: space-between; gap: 14px; padding: 7px 0; border-bottom: 1px solid var(--ef-line); font-size: 13px; }
.epf-r:last-child { border-bottom: 0; }
.epf-r span { color: var(--text-muted); white-space: nowrap; }
.epf-r b { font-weight: 500; text-align: right; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-r b.warn { color: var(--ef-amber); }
.epf-ids { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.epf-ids span { display: block; font-size: 11.5px; color: var(--text-muted); }
.epf-ids b { display: block; margin-top: 3px; font: 500 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-todo { grid-column: 1 / -1; display: flex; align-items: center; gap: 18px; margin-top: 18px; padding: 16px 18px; border-radius: 10px; background: var(--ef-amber-bg); }
.epf-todo > div { flex: 1; min-width: 0; }
.epf-todo strong { display: block; font-size: 13.5px; font-weight: 600; color: var(--ef-amber); }
.epf-todo p { margin: 4px 0 0; font-size: 12.5px; line-height: 1.55; color: var(--text-muted); }
.epf-todo a { flex: none; height: 34px; padding: 0 14px; border-radius: 8px; display: inline-flex; align-items: center; font-size: 13px; font-weight: 600; text-decoration: none;
              color: var(--ef-amber); background: var(--surface); border: 1px solid color-mix(in srgb, var(--ef-amber) 35%, transparent); }
.epf-todo a:hover { color: var(--ef-amber); filter: brightness(.98); }

/* Down the right. */
.epf-side { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
.epf-pad { padding: 16px 20px; }
.epf-meter { height: 4px; margin: 6px 0 8px; border-radius: 2px; overflow: hidden; background: var(--ef-line); }
.epf-meter i { display: block; height: 100%; background: var(--ef-navy-ink); }
.epf-note { margin-top: 4px; font-size: 12px; color: var(--ef-amber); }
.epf-fp { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 10px; }
.epf-fp form { margin: 0; }
.epf-fp .epf-note { margin: 0; }
.epf-btn.sm { width: 100%; justify-content: center; height: 34px; font-size: 12.5px; }
.epf-link { padding: 0; border: 0; background: none; font-size: 12.5px; font-weight: 600; color: var(--ef-navy-ink); cursor: pointer; }
.epf-link:hover { text-decoration: underline; }
.epf-scroll { max-height: 360px; overflow-y: auto; }
.epf-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; font-variant-numeric: tabular-nums; }
.epf-tbl td { padding: 8px 0; border-bottom: 1px solid var(--ef-line); white-space: nowrap; color: var(--text-primary); font-weight: 500; text-align: right; }
.epf-tbl td:first-child { text-align: left; color: var(--text-muted); font-weight: 400; }
.epf-tbl td + td { padding-left: 12px; }
.epf-tbl tr:last-child td { border-bottom: 0; }
.epf-tbl small { margin-left: 6px; font-size: 11px; color: var(--text-muted); opacity: .8; }
.epf-live { font-weight: 600; color: var(--ef-green); }
.epf-empty { margin: 4px 0 0; font-size: 13px; color: var(--text-muted); }

@media (max-width: 1200px) {
    .epf-cols { grid-template-columns: 1fr; }
    .epf-week { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .epf-week > div:nth-child(5) { border-left: 0; }
    .epf-week > div:nth-child(n+5) { border-top: 1px solid var(--ef-line); }
}
@media (max-width: 760px) {
    .epf-hero { flex-wrap: wrap; } .epf-acts { width: 100%; } .epf-btn { flex: 1; justify-content: center; }
    .epf-week { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .epf-week > div:nth-child(odd) { border-left: 0; }
    .epf-week > div:nth-child(n+3) { border-top: 1px solid var(--ef-line); }
    .epf-rec { grid-template-columns: 1fr; }
    .epf-ids { grid-template-columns: 1fr 1fr; }
    .epf-todo { flex-direction: column; align-items: stretch; }
}
</style>
@endpush
