@extends('layouts')
@section('page_title', $employee->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
{{-- View Details is laid out like Register Employee on purpose: the same
     container, the same section cards, the same labels in the same order and
     the same column widths. Reading a record and filling one in should not
     feel like two different pages. What the form has as inputs, this has as
     answers — see _profile_view.blade.php — plus the two things a form has no
     reason to carry: this cutoff's payroll, and the recent scans. --}}
<div class="employee-container">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="page-title mb-1">{{ $employee->name }}</h2>
            <p class="text-muted mb-0" style="font-size:.875rem;">
                <span class="ep-mono">#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                · {{ $employee->position ?: ($employee->laborType->name ?? __('Worker')) }}
                · {{ $employee->employment_label }}
                @unless($employee->fingerprint_id)
                    · <span class="ep-warn"><i class="fas fa-hourglass-half"></i> {{ __('No fingerprint yet') }}</span>
                @endunless
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary shadow-sm px-4">
                <i class="fas fa-arrow-left me-2"></i>{{ __('Employee Directory') }}
            </a>
            <a href="{{ route('employees.edit', $employee->id) }}" class="btn btn-primary shadow-sm px-4">
                <i class="fas fa-pen me-2"></i>{{ __('Edit') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">

            {{-- ════════════════════ EMPLOYMENT & PAY ════════════════════
                 Mirrors the first section of Register Employee, field for
                 field. The photo sits where the picker sits there. --}}
            <section class="ep-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon"><i class="fas fa-helmet-safety"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                        <p class="ep-section-sub">{{ __('What the worker is paid and where they are assigned.') }}</p>
                    </div>
                </header>

                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('First Name') }}</span>
                        <span class="ep-value {{ $employee->first_name ? '' : 'is-empty' }}">{{ $employee->first_name ?: __('Not recorded') }}</span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Middle Name') }}</span>
                        <span class="ep-value {{ $employee->middle_name ? '' : 'is-empty' }}">{{ $employee->middle_name ?: __('Not recorded') }}</span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Last Name') }}</span>
                        <span class="ep-value {{ $employee->last_name ? '' : 'is-empty' }}">{{ $employee->last_name ?: __('Not recorded') }}</span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Employee Type') }}</span>
                        <span class="ep-value">{{ $employee->employment_label }}</span>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Labor Type') }}</span>
                        <span class="ep-value {{ $employee->laborType ? '' : 'is-empty' }}">{{ $employee->laborType->name ?? '—' }}</span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Shift') }}</span>
                        {{-- Not a dash: an untagged worker falls back to the
                             office default in payroll, and that is worth seeing
                             rather than reading as a blank. --}}
                        <span class="ep-value">
                            @if($employee->shift)
                                <i class="fas {{ $employee->shift->crosses_midnight ? 'fa-moon' : 'fa-sun' }} me-1"></i>
                                {{ $employee->shift->name }} — {{ \Carbon\Carbon::parse($employee->shift->starts_at)->format('g:i A') }}
                            @else
                                {{ __('Unassigned — office default applies') }}
                            @endif
                        </span>
                    </div>
                    @if($employee->isContractual())
                        <div class="col-md-6 col-lg-3">
                            <span class="ep-label">{{ __('Contract Amount') }}</span>
                            <span class="ep-value ep-mono">₱{{ number_format($employee->contract_rate ?? 0, 2) }}
                                <span class="ep-aside">{{ __('whole project') }}</span></span>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <span class="ep-label">{{ __('End of Contract') }}</span>
                            <span class="ep-value {{ $employee->end_of_contract ? '' : 'is-empty' }}">
                                {{ $employee->end_of_contract?->format('M d, Y') ?: __('Not recorded') }}
                            </span>
                        </div>
                    @else
                        <div class="col-md-6 col-lg-3">
                            <span class="ep-label">{{ __('Rate Per Hour') }}</span>
                            <span class="ep-value ep-mono">₱{{ number_format($employee->rate_per_hour, 2) }}</span>
                        </div>
                    @endif
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Position / Job Title') }}</span>
                        {{-- The form posts job_title; the controller also derives
                             `position` from the labor type. job_title is what was
                             typed, so it leads, and position covers the records
                             saved before that field existed. --}}
                        @php $jobTitle = $employee->job_title ?: $employee->position; @endphp
                        <span class="ep-value {{ $jobTitle ? '' : 'is-empty' }}">{{ $jobTitle ?: __('Not recorded') }}</span>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Date Hired') }}</span>
                        <span class="ep-value {{ $employee->date_hired ? '' : 'is-empty' }}">{{ $employee->date_hired?->format('M d, Y') ?: __('Not recorded') }}</span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Site Assignment') }}</span>
                        <span class="ep-value {{ $employee->site ? '' : 'is-empty' }}">
                            @if($employee->site)<i class="fas fa-map-marker-alt me-1"></i>@endif
                            {{ $employee->site->name ?? __('Not assigned') }}
                        </span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Vale Balance') }}</span>
                        <span class="ep-value ep-mono {{ ($employee->vale ?? 0) > 0 ? 'is-warn' : '' }}">₱{{ number_format($employee->vale ?? 0, 2) }}</span>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Fingerprint') }}</span>
                        <span class="ep-value {{ $employee->fingerprint_id ? 'ep-mono' : 'is-warn' }}">
                            @if($employee->fingerprint_id)
                                <i class="fas fa-fingerprint me-1"></i>{{ __('Enrolled') }} #{{ $employee->fingerprint_id }}
                            @else
                                <i class="fas fa-hourglass-half me-1"></i>{{ __('Not enrolled yet') }}
                            @endif
                        </span>
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <span class="ep-label">{{ __('Profile Photo') }}</span>
                        <div class="epv-photo">
                            @if($employee->photo)
                                <img src="{{ url('storage/' . $employee->photo) }}" alt="{{ $employee->name }}">
                            @else
                                <div class="epv-photo-none">
                                    <i class="fas fa-user"></i>
                                    <span>{{ __('No photo') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- The four personnel sections, laid out exactly as the form does. --}}
            @include('employees._profile_view', ['employee' => $employee])

            {{-- ════════════════════ PAYROLL THIS CUTOFF ════════════════════ --}}
            <section class="ep-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon"><i class="fas fa-money-check-dollar"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Payroll This Cutoff') }}</h3>
                        <p class="ep-section-sub">{{ $period }}</p>
                    </div>
                </header>

                @php $num = fn ($k) => (float) ($totals[$k] ?? 0); @endphp

                <div class="ep-pay">
                    <div class="ep-pay-cell">
                        <span class="ep-label">{{ __('Days Worked') }}</span>
                        <span class="ep-pay-v">{{ (int) ($totals['workdays'] ?? 0) }}</span>
                    </div>
                    <div class="ep-pay-cell">
                        <span class="ep-label">{{ __('Hours') }}</span>
                        <span class="ep-pay-v">{{ number_format($num('hours'), 2) }}</span>
                    </div>
                    <div class="ep-pay-cell">
                        <span class="ep-label">{{ __('Overtime') }}</span>
                        <span class="ep-pay-v {{ $num('overtime') > 0 ? 'is-ot' : '' }}">₱{{ number_format($num('overtime'), 2) }}</span>
                    </div>
                    <div class="ep-pay-cell">
                        <span class="ep-label">{{ __('Gross') }}</span>
                        <span class="ep-pay-v">₱{{ number_format($num('gross'), 2) }}</span>
                    </div>
                    <div class="ep-pay-cell">
                        <span class="ep-label">{{ __('Deductions') }}</span>
                        <span class="ep-pay-v {{ $num('totalDeductions') > 0 ? 'is-minus' : '' }}">₱{{ number_format($num('totalDeductions'), 2) }}</span>
                    </div>
                    <div class="ep-pay-cell is-net">
                        <span class="ep-label">{{ __('Net') }}</span>
                        <span class="ep-pay-v">₱{{ number_format($num('net'), 2) }}</span>
                    </div>
                </div>
            </section>

            {{-- ════════════════════ RECENT ATTENDANCE ════════════════════ --}}
            <section class="ep-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon"><i class="fas fa-clock"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Recent Attendance') }}</h3>
                        <p class="ep-section-sub">{{ __('The latest scans recorded at the kiosk.') }}</p>
                    </div>
                </header>

                @if($attendance->isEmpty())
                    <p class="ep-none">{{ __('No attendance recorded yet.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="ep-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Session') }}</th>
                                    <th>{{ __('Time In') }}</th>
                                    <th>{{ __('Time Out') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($attendance as $a)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($a->date)->format('M d, Y') }}</td>
                                    <td>{{ $a->session ?? '—' }}</td>
                                    <td class="ep-mono">{{ $a->time_in ? \Carbon\Carbon::parse($a->time_in)->format('g:i A') : '—' }}</td>
                                    {{-- An open row is not a missing time — it is
                                         someone who has not clocked out yet, and
                                         a dash would hide that. --}}
                                    <td class="{{ $a->time_out ? 'ep-mono' : '' }}">
                                        @if($a->time_out)
                                            {{ \Carbon\Carbon::parse($a->time_out)->format('g:i A') }}
                                        @else
                                            <span class="ep-live">{{ __('Still clocked in') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

        </div>
    </div>
</div>
@endsection

{{-- The section chrome the form uses, shared so the two pages cannot drift. --}}
@push('styles')
    @include('employees._profile_styles')
@endpush

@push('styles')
<style>
/* ── View-Details-only additions ───────────────────────────────────────────
   Everything else on this page is the form's own chrome from
   _profile_styles.blade.php. These are the few things a form has no use for:
   a payroll strip, an attendance table and a photo tile. Colours are theme
   tokens, so there is no dark-mode twin of this block. */
.ep-aside { color: var(--text-muted, #8a929b); font-size: .8em; margin-left: 4px; }
.ep-warn  { color: var(--warning, #b54708); font-weight: 600; }
.ep-value.is-warn { color: var(--warning, #b54708); font-weight: 600; }

/* Photo sits in the grid where the picker sits on the form, so the section
   keeps the same shape whether it is being filled in or read. */
.epv-photo {
    display: flex; align-items: center; justify-content: center;
    min-height: 120px; padding: 8px;
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-md, 6px);
    background: var(--bg-subtle, #f7f8fa);
}
.epv-photo img { max-height: 104px; border-radius: var(--radius-md, 6px); object-fit: cover; }
.epv-photo-none {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    color: var(--text-muted, #8a929b); font-size: .78rem;
}
.epv-photo-none i { font-size: 1.6rem; opacity: .45; }

/* ── Payroll strip ─────────────────────────────────────────────────────── */
.ep-pay { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
.ep-pay-cell {
    padding: 12px 14px;
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-md, 6px);
    background: var(--bg-subtle, #f7f8fa);
}
.ep-pay-v {
    display: block; margin-top: 2px;
    font-size: 1rem; font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--text-primary, #1b2430);
}
.ep-pay-v.is-ot    { color: var(--brand, #1668dc); }
.ep-pay-v.is-minus { color: var(--danger, #b3403a); }
.ep-pay-cell.is-net {
    background: var(--brand-subtle, #eaf2fd);
    border-color: transparent;
}
.ep-pay-cell.is-net .ep-pay-v { color: var(--brand, #1668dc); }

/* ── Attendance table ──────────────────────────────────────────────────── */
.ep-table { width: 100%; margin: 0; border-collapse: collapse; }
.ep-table thead th {
    padding: 0 0 9px; text-align: left; white-space: nowrap;
    font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--text-muted, #8a929b);
    border-bottom: 1px solid var(--border, #e3e6e9);
}
.ep-table tbody td {
    padding: 11px 0; font-size: .84rem;
    color: var(--text-primary, #1b2430);
    border-bottom: 1px solid var(--border, #e3e6e9);
}
.ep-table tbody tr:last-child td { border-bottom: none; padding-bottom: 0; }
.ep-table thead th + th,
.ep-table tbody td + td { padding-left: 16px; }

.ep-live {
    display: inline-block; padding: 2px 9px; border-radius: 999px;
    font-size: .74rem; font-weight: 600;
    color: var(--success, #027a48);
    background: var(--success-soft, #ecfdf3);
    border: 1px solid transparent;
}

.ep-none { margin: 0; font-size: .85rem; color: var(--text-muted, #8a929b); }

@media (max-width: 1100px) { .ep-pay { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 620px)  { .ep-pay { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush
