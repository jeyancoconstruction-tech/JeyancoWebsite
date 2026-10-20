@extends('layouts')
@section('page_title', $employee->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
{{-- A worker's full profile (2026-10-20 revision). One screen, filled: who
     they are across the top; this week's payroll, then the record in four
     cards that share the height; and down the right their employment, their
     fingers and how complete the record is, and the latest kiosk scans. --}}
@php
    $show = fn ($v) => is_string($v) ? (trim($v) !== '' ? trim($v) : null) : $v;
    $count = fn (array $vals) => collect($vals)->filter(fn ($v) => filled($v))->count();

    $slots   = $employee->fingerprintSlots();
    $needFp  = (int) config('kiosk.fingers_required', 2);
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

<div class="employee-container epf">

    <header class="epf-hero">
        <span class="epf-ico">@include('partials.worker-icon', ['size' => '66%'])</span>
        <div class="epf-who">
            <h1>{{ $employee->name }}</h1>
            <p>
                <span class="epf-id">#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                @if($jobTitle)<span class="epf-dot"></span>{{ $jobTitle }}@endif
                @if($employee->site)<span class="epf-dot"></span>{{ $employee->site->name }}@endif
            </p>
            <div class="epf-tags">
                <span class="epf-tag st-{{ $employee->status }}">{{ $statusLabel }}</span>
                <span class="epf-tag">{{ $employee->isContractual() ? __('Contractual') : __('Regular') }}</span>
                @if($employee->shift)<span class="epf-tag">{{ $employee->shift->name }}</span>@endif
                <span class="epf-tag {{ count($slots) < $needFp ? 'warn' : '' }}"><i class="fas fa-fingerprint"></i>{{ count($slots) }} {{ __('of') }} {{ $needFp }}</span>
            </div>
        </div>
        <div class="epf-acts">
            <a href="{{ route('employees.register') }}" class="epf-btn"><i class="fas fa-arrow-left"></i>{{ __('Employees') }}</a>
            <a href="{{ route('employees.edit', $employee->id) }}" class="epf-btn pri"><i class="fas fa-pen"></i>{{ __('Edit') }}</a>
        </div>
    </header>

    <div class="epf-cols">
        <div class="epf-main">
            {{-- This week's payroll for the worker, as Payroll works it out. --}}
            <section class="epf-box">
                <div class="epf-box-h"><h3>{{ __('Payroll this week') }}</h3><span>{{ $period }}</span></div>
                <div class="epf-pay">
                    <div><span>{{ __('Days') }}</span><b>{{ (int) ($totals['workdays'] ?? 0) }}</b></div>
                    <div><span>{{ __('Hours') }}</span><b>{{ number_format($num('hours'), 2) }}</b></div>
                    <div><span>{{ __('Overtime') }}</span><b class="{{ $num('overtime') > 0 ? 'is-ot' : '' }}">₱{{ number_format($num('overtime'), 2) }}</b></div>
                    <div><span>{{ __('Gross') }}</span><b>₱{{ number_format($num('gross'), 2) }}</b></div>
                    <div><span>{{ __('Deductions') }}</span><b class="{{ $num('totalDeductions') > 0 ? 'is-minus' : '' }}">₱{{ number_format($num('totalDeductions'), 2) }}</b></div>
                    <div class="net"><span>{{ __('Net') }}</span><b>₱{{ number_format($num('net'), 2) }}</b></div>
                </div>
            </section>

            {{-- The record, in four cards sharing what height is left. --}}
            <div class="epf-quad">
                @foreach([
                    [__('Personal'), $personal, 'g3', __('Not recorded')],
                    [__('Contact & emergency'), $contact, 'g2', __('Not recorded')],
                    [__('Address'), $address, 'g3', __('Not recorded')],
                    [__('Government IDs'), $ids, 'g2', __('Not issued yet')],
                ] as [$title, $rows, $cols, $blank])
                    <section class="epf-box">
                        <div class="epf-box-h"><h3>{{ $title }}</h3><span>{{ $count($rows) }} {{ __('of') }} {{ count($rows) }}</span></div>
                        <dl class="epf-grid {{ $cols }}">
                            @foreach($rows as $label => $value)
                                <div><dt>{{ $label }}</dt><dd class="{{ filled($value) ? '' : 'none' }}">{{ filled($value) ? $value : $blank }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        </div>

        <aside class="epf-side">
            <section class="epf-box">
                <div class="epf-box-h"><h3>{{ __('Employment') }}</h3></div>
                <dl class="epf-spec">
                    <dt>{{ __('Labor type') }}</dt><dd>{{ $employee->laborType->name ?? __('Not set') }}</dd>
                    <dt>{{ __('Position') }}</dt><dd>{{ $jobTitle ?: __('Not recorded') }}</dd>
                    <dt>{{ __('Rate') }}</dt><dd>₱{{ number_format((float) $employee->rate_per_hour, 2) }} / {{ __('hr') }}</dd>
                    @if($employee->laborType)<dt>{{ __('Daily') }}</dt><dd>{{ $employee->laborType->getFormattedDailyRate() }}</dd>@endif
                    {{-- An untagged worker falls back to the office default in
                         payroll: worth saying rather than leaving blank. --}}
                    <dt>{{ __('Shift') }}</dt><dd>{{ $employee->shift ? $employee->shift->name . ' · ' . \Carbon\Carbon::parse($employee->shift->starts_at)->format('g:i A') : __('Office default') }}</dd>
                    <dt>{{ __('Hired') }}</dt><dd>{{ $employee->date_hired?->format('M d, Y') ?: __('Not recorded') }}</dd>
                    <dt>{{ __('Employment') }}</dt><dd>{{ $employee->isContractual() ? __('Contractual') . ($employee->end_of_contract ? ' · ' . __('until') . ' ' . $employee->end_of_contract->format('M d, Y') : '') : __('Regular') }}</dd>
                    <dt>{{ __('Site') }}</dt><dd>{{ $employee->site->name ?? __('Not assigned') }}</dd>
                    <dt>{{ __('Vale balance') }}</dt><dd class="{{ ($employee->vale ?? 0) > 0 ? 'warn' : '' }}">₱{{ number_format($employee->vale ?? 0, 2) }}</dd>
                </dl>
            </section>

            <section class="epf-box epf-pad">
                <div class="epf-row"><h3>{{ __('Fingerprints') }}</h3><span>{{ count($slots) }} {{ __('of') }} {{ $needFp }}</span></div>
                <div class="epf-slots">
                    @for($i = 0; $i < max($needFp, count($slots)); $i++)
                        @if(isset($slots[$i]))
                            <span class="epf-slot on"><i class="fas fa-fingerprint"></i>{{ __('Slot') }} {{ $slots[$i] }}</span>
                        @else
                            <span class="epf-slot">{{ __('Empty') }}</span>
                        @endif
                    @endfor
                </div>
                @if(count($slots) < $needFp)
                    <div class="epf-note"><i class="fas fa-circle-exclamation"></i>{{ $needFp - count($slots) === 1 ? __('One more at the kiosk') : __(':n more at the kiosk', ['n' => $needFp - count($slots)]) }}</div>
                @endif
                <div class="epf-row" style="margin-top:16px"><h3>{{ __('Profile') }}</h3><span>{{ $recorded }} {{ __('of') }} {{ $fields }}</span></div>
                <div class="epf-meter"><i style="width: {{ round($recorded / max(1, $fields) * 100) }}%"></i></div>
            </section>

            {{-- The latest scans. An open row is someone not clocked out yet,
                 not a missing time, and says so. --}}
            <section class="epf-box epf-scans">
                <div class="epf-box-h"><h3>{{ __('Recent scans') }}</h3><span>{{ __('Kiosk') }}</span></div>
                @if($attendance->isEmpty())
                    <p class="epf-empty">{{ __('No attendance recorded yet.') }}</p>
                @else
                    <div class="epf-scroll">
                        <table class="epf-tbl">
                            @foreach($attendance as $a)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($a->date)->format('M d') }}<small>{{ $a->session ? ucfirst($a->session) : '' }}</small></td>
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
/* ── Full profile. Theme tokens throughout, so light and dark come from the
   theme; the worker icon is black on safety yellow in both. ───────────────── */
.epf { display: flex; flex-direction: column; gap: 14px; min-height: calc(100vh - 120px); }
.epf-hero { position: relative; display: flex; align-items: center; gap: 16px; padding: 18px 20px 18px 24px; overflow: hidden;
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
.epf-hero::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px;
                    background: repeating-linear-gradient(-45deg, #f5b73b 0 10px, #1a1a1a 10px 20px); }
.epf-ico { flex: none; width: 64px; height: 64px; border-radius: 14px; display: grid; place-items: center;
           background: #f5b73b; color: #111; box-shadow: inset 0 0 0 1px rgba(0,0,0,.14); }
.epf-who { flex: 1; min-width: 0; }
html[data-bs-theme] .epf-who h1 { margin: 0; font-size: 23px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.epf-who p { margin: 4px 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; font-size: 13px; color: var(--text-secondary); }
.epf-id { font: 500 12px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--text-muted); }
.epf-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--text-muted); }
.epf-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
.epf-tag { display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px; border: 1px solid var(--border-md); border-radius: 5px; font-size: 11.5px; font-weight: 600; color: var(--text-secondary); }
.epf-tag.st-active::before, .epf-tag.st-pending::before, .epf-tag.st-archived::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted); }
.epf-tag.st-active::before { background: var(--success, #12b76a); }
.epf-tag.st-pending::before { background: var(--warning, #d97706); }
.epf-tag.warn { color: var(--warning, #d97706); }
.epf-tag .fas { font-size: 10px; }
.epf-acts { display: flex; gap: 8px; align-self: flex-start; }
.epf-btn { height: 36px; padding: 0 15px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; text-decoration: none;
           border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary); }
.epf-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
.epf-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.epf-btn.pri:hover { filter: brightness(1.08); color: #fff; }
.epf-btn i { font-size: 12px; opacity: .85; }

/* The two columns run to the bottom of the screen; the cards share the height. */
.epf-cols { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 14px; }
.epf-main { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.epf-quad { flex: 1; display: grid; grid-template-columns: 1fr 1fr; grid-auto-rows: 1fr; gap: 14px; }
.epf-side { display: flex; flex-direction: column; gap: 14px; min-width: 0; }

.epf-box { display: flex; flex-direction: column; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; min-width: 0; }
.epf-box-h { display: flex; align-items: baseline; gap: 10px; padding: 13px 18px; border-bottom: 1px solid var(--border); }
html[data-bs-theme] .epf-box-h h3, html[data-bs-theme] .epf-row h3 { margin: 0; font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
.epf-box-h span { margin-left: auto; font-size: 12px; color: var(--text-muted); font-variant-numeric: tabular-nums; }

.epf-pay { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); }
.epf-pay > div { padding: 14px 16px; min-width: 0; }
.epf-pay > div + div { border-left: 1px solid var(--border); }
.epf-pay span { display: block; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); }
.epf-pay b { display: block; margin-top: 4px; font-size: 18px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text-primary); }
.epf-pay b.is-ot { color: var(--brand); }
.epf-pay b.is-minus { color: var(--danger); }
.epf-pay .net { background: color-mix(in srgb, var(--brand) 9%, transparent); border-radius: 0 0 11px 0; }
.epf-pay .net b { color: var(--brand); }

.epf-grid { flex: 1; display: grid; align-content: space-evenly; gap: 14px 22px; margin: 0; padding: 14px 18px 16px; }
.epf-grid.g3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.epf-grid.g2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.epf-grid > div { min-width: 0; }
.epf-grid dt { margin-bottom: 4px; font-size: 12px; font-weight: 400; color: var(--text-muted); }
.epf-grid dd { margin: 0; font-size: 14px; font-weight: 500; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-grid dd.none, .epf-spec dd.none { font-weight: 400; color: var(--text-muted); opacity: .75; }

.epf-spec { display: grid; grid-template-columns: auto 1fr; gap: 9px 14px; margin: 0; padding: 13px 18px 15px; font-size: 13px; }
.epf-spec dt { font-weight: 400; color: var(--text-muted); white-space: nowrap; }
.epf-spec dd { margin: 0; text-align: right; font-weight: 500; color: var(--text-primary); overflow-wrap: anywhere; }
.epf-spec dd.warn { color: var(--warning, #d97706); }

.epf-pad { padding: 14px 18px; }
.epf-row { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
.epf-row span { font-size: 12px; color: var(--text-secondary); font-variant-numeric: tabular-nums; }
.epf-slots { display: flex; gap: 6px; }
.epf-slot { flex: 1; min-width: 0; height: 36px; border-radius: 7px; display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 12px; color: var(--text-muted); border: 1px dashed var(--border-md); }
.epf-slot.on { border: 1px solid rgba(18,183,106,.45); background: rgba(18,183,106,.10); color: var(--success, #12b76a); font-weight: 600; }
.epf-note { margin-top: 9px; display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--warning, #d97706); }
.epf-meter { height: 5px; border-radius: 3px; overflow: hidden; background: var(--border); }
.epf-meter i { display: block; height: 100%; background: var(--brand); }

/* The scans take what is left of the column and scroll inside it. */
.epf-scans { flex: 1; min-height: 180px; }
.epf-scroll { flex: 1; min-height: 0; overflow-y: auto; max-height: 360px; }
.epf-tbl { width: 100%; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
.epf-tbl td { padding: 9px 18px; border-bottom: 1px solid var(--border); color: var(--text-primary); white-space: nowrap; }
.epf-tbl td + td { padding-left: 4px; }
.epf-tbl tr:last-child td { border-bottom: 0; }
.epf-tbl small { display: block; font-size: 11px; color: var(--text-muted); }
.epf-live { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600;
            color: var(--success, #12b76a); background: rgba(18,183,106,.12); }
.epf-empty { margin: 0; padding: 16px 18px; font-size: 13px; color: var(--text-muted); }

@media (max-width: 1200px) {
    .epf-cols { grid-template-columns: 1fr; }
    .epf-pay { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .epf-pay > div:nth-child(4) { border-left: 0; }
    .epf-pay > div:nth-child(n+4) { border-top: 1px solid var(--border); }
    .epf-pay .net { border-radius: 0 0 11px 0; }
}
@media (max-width: 760px) {
    .epf-hero { flex-wrap: wrap; } .epf-acts { width: 100%; } .epf-btn { flex: 1; justify-content: center; }
    .epf-quad { grid-template-columns: 1fr; grid-auto-rows: auto; }
    .epf-grid.g3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
@endpush
