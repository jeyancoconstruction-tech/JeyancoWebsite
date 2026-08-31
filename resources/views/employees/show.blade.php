@extends('layouts')
@section('page_title', $employee->name)

@section('content')
<div class="emp-page">

    {{-- ── Back + actions ──────────────────────────────────────────────────── --}}
    <div class="dir-header">
        <div class="dir-header-text">
            <a href="{{ route('employees.index') }}" class="prof-back">
                <i class="fas fa-arrow-left"></i> Employee Directory
            </a>
            <h1 class="dir-title" style="margin-top:10px;">{{ $employee->name }}</h1>
            <p class="dir-sub" style="margin-left:0;">
                ID #{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}
                &middot; {{ $employee->position ?: ($employee->laborType->name ?? 'Worker') }}
            </p>
        </div>
        <div class="dir-header-actions">
            <a href="{{ route('employees.edit', $employee->id) }}" class="dir-btn-primary">
                <i class="fas fa-pen"></i> Edit
            </a>
        </div>
    </div>

    <div class="prof-grid">

        {{-- ── Left: who they are ──────────────────────────────────────────── --}}
        <div class="emp-card prof-card">
            <div class="prof-id">
                @if($employee->photo)
                    <img src="{{ url('storage/' . $employee->photo) }}"
                         alt="{{ $employee->name }}" class="prof-photo">
                @else
                    <div class="prof-initials">{{ strtoupper(substr($employee->name, 0, 1)) }}</div>
                @endif
                <div>
                    <div class="prof-name">{{ $employee->name }}</div>
                    <div class="prof-role">{{ $employee->position ?: ($employee->laborType->name ?? 'Worker') }}</div>
                </div>
            </div>

            <dl class="prof-facts">
                <div class="prof-fact">
                    <dt>Site</dt>
                    <dd>
                        @if($employee->site)
                            <span class="emp-badge-site"><i class="fas fa-map-marker-alt"></i> {{ $employee->site->name }}</span>
                        @else
                            <span class="emp-dash">Hindi pa naka-assign</span>
                        @endif
                    </dd>
                </div>
                <div class="prof-fact">
                    <dt>Labor type</dt>
                    <dd>
                        @if($employee->laborType)
                            <span class="emp-badge-labor"><i class="fas fa-briefcase"></i> {{ $employee->laborType->name }}</span>
                        @else
                            <span class="emp-dash">—</span>
                        @endif
                    </dd>
                </div>
                <div class="prof-fact">
                    <dt>Employment</dt>
                    <dd><span class="prof-chip">{{ $employee->employment_label }}</span></dd>
                </div>
                @if($employee->isContractual() && $employee->contract_rate)
                <div class="prof-fact">
                    <dt>Contract rate</dt>
                    <dd class="prof-money">₱{{ number_format($employee->contract_rate, 2) }} <small>kada araw</small></dd>
                </div>
                @endif
                <div class="prof-fact">
                    <dt>Rate / hour</dt>
                    <dd class="prof-money">₱{{ number_format($employee->rate_per_hour, 2) }}</dd>
                </div>
                <div class="prof-fact">
                    <dt>Vale balance</dt>
                    <dd class="prof-money {{ ($employee->vale ?? 0) > 0 ? 'warn' : '' }}">
                        ₱{{ number_format($employee->vale ?? 0, 2) }}
                    </dd>
                </div>
                <div class="prof-fact">
                    <dt>Fingerprint</dt>
                    <dd>
                        @if($employee->fingerprint_id)
                            <span class="emp-badge-fp"><i class="fas fa-fingerprint"></i> Enrolled #{{ $employee->fingerprint_id }}</span>
                        @else
                            <span class="prof-chip warn"><i class="fas fa-hourglass-half"></i> Wala pang daliri</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- ── Right: this week, then recent scans ─────────────────────────── --}}
        <div class="prof-col">

            <div class="emp-card prof-card">
                <div class="prof-card-head">
                    <span>Payroll ngayong cutoff</span>
                    <span class="prof-period">{{ $period }}</span>
                </div>

                @php
                    $num = fn ($k) => (float) ($totals[$k] ?? 0);
                @endphp

                <div class="prof-pay">
                    <div class="prof-pay-cell">
                        <span class="prof-pay-k">Araw na pumasok</span>
                        <span class="prof-pay-v">{{ (int) ($totals['workdays'] ?? 0) }}</span>
                    </div>
                    <div class="prof-pay-cell">
                        <span class="prof-pay-k">Oras</span>
                        <span class="prof-pay-v">{{ number_format($num('hours'), 2) }}</span>
                    </div>
                    <div class="prof-pay-cell">
                        <span class="prof-pay-k">Overtime</span>
                        <span class="prof-pay-v {{ $num('overtime') > 0 ? 'ot' : '' }}">₱{{ number_format($num('overtime'), 2) }}</span>
                    </div>
                    <div class="prof-pay-cell">
                        <span class="prof-pay-k">Gross</span>
                        <span class="prof-pay-v">₱{{ number_format($num('gross'), 2) }}</span>
                    </div>
                    <div class="prof-pay-cell">
                        <span class="prof-pay-k">Deductions</span>
                        <span class="prof-pay-v {{ $num('totalDeductions') > 0 ? 'minus' : '' }}">₱{{ number_format($num('totalDeductions'), 2) }}</span>
                    </div>
                    <div class="prof-pay-cell net">
                        <span class="prof-pay-k">Net</span>
                        <span class="prof-pay-v">₱{{ number_format($num('net'), 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="emp-card prof-card">
                <div class="prof-card-head"><span>Huling mga pasok</span></div>

                @if($attendance->isEmpty())
                    <p class="prof-none">Wala pang naitalang attendance.</p>
                @else
                <div class="table-responsive">
                    <table class="emp-table prof-table">
                        <thead>
                            <tr><th>Petsa</th><th>Session</th><th>Time in</th><th>Time out</th></tr>
                        </thead>
                        <tbody>
                        @foreach($attendance as $a)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($a->date)->format('M d, Y') }}</td>
                                <td><span class="prof-chip">{{ $a->session }}</span></td>
                                <td>{{ $a->time_in ? \Carbon\Carbon::parse($a->time_in)->format('g:i A') : '—' }}</td>
                                <td>
                                    @if($a->time_out)
                                        {{ \Carbon\Carbon::parse($a->time_out)->format('g:i A') }}
                                    @else
                                        <span class="prof-chip warn">Nasa loob pa</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

        </div>
    </div>
</div>

<style>
.prof-back {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 0.82rem; font-weight: 600; text-decoration: none;
    color: var(--text-muted, #8fa2bd);
}
.prof-back:hover { color: var(--accent, #2f7fd1); }

.prof-grid { display: grid; grid-template-columns: 360px 1fr; gap: 16px; align-items: start; }
.prof-col  { display: flex; flex-direction: column; gap: 16px; }
.prof-card { padding: 20px; }

.prof-id { display: flex; align-items: center; gap: 14px; padding-bottom: 18px;
           border-bottom: 1px solid var(--border, #2a3856); }
.prof-photo, .prof-initials {
    width: 58px; height: 58px; border-radius: 50%; flex-shrink: 0;
    object-fit: cover;
}
.prof-initials {
    display: flex; align-items: center; justify-content: center;
    background: rgba(47,127,209,0.18); color: #6fa8dc;
    font-size: 1.5rem; font-weight: 800;
}
.prof-name { font-size: 1.1rem; font-weight: 800; color: var(--text, #e8eef7); }
.prof-role { font-size: 0.8rem; color: var(--text-muted, #8fa2bd); margin-top: 2px; }

.prof-facts { margin: 0; padding: 4px 0 0; }
.prof-fact {
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 12px 0; border-bottom: 1px solid var(--border-soft, #223049);
}
.prof-fact:last-child { border-bottom: none; }
.prof-fact dt { font-size: 0.78rem; color: var(--text-muted, #8fa2bd); margin: 0; font-weight: 500; }
.prof-fact dd { margin: 0; text-align: right; }
.prof-money { font-size: 0.95rem; font-weight: 700; color: var(--text, #e8eef7);
              font-variant-numeric: tabular-nums; }
.prof-money.warn { color: var(--warning, #e8a33d); }
.prof-money small { font-size: 0.68rem; font-weight: 500; color: var(--text-dim, #5a6b86); }

.prof-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 8px; font-size: 0.74rem; font-weight: 600;
    background: var(--surface-3, #212d44); color: var(--text, #e8eef7);
    border: 1px solid var(--border, #2a3856);
}
.prof-chip.warn {
    background: rgba(232,163,61,0.14); color: var(--warning, #e8a33d);
    border-color: var(--warning, #e8a33d);
}

.prof-card-head {
    display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
    font-size: 0.78rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
    color: var(--text-muted, #8fa2bd);
    padding-bottom: 14px; margin-bottom: 14px;
    border-bottom: 1px solid var(--border, #2a3856);
}
.prof-period { font-size: 0.72rem; letter-spacing: 0; text-transform: none;
               color: var(--accent, #2f7fd1); font-weight: 600; }

.prof-pay { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; }
.prof-pay-cell {
    background: var(--surface-2, #1a2438); border: 1px solid var(--border, #2a3856);
    border-radius: 10px; padding: 13px 14px;
    display: flex; flex-direction: column; gap: 5px;
}
.prof-pay-k { font-size: 0.7rem; color: var(--text-muted, #8fa2bd); }
.prof-pay-v { font-size: 1.05rem; font-weight: 800; color: var(--text, #e8eef7);
              font-variant-numeric: tabular-nums; }
.prof-pay-v.ot    { color: var(--warning, #e8a33d); }
.prof-pay-v.minus { color: var(--danger, #e5484d); }
.prof-pay-cell.net {
    background: rgba(43,182,115,0.10); border-color: var(--success, #2bb673);
}
.prof-pay-cell.net .prof-pay-k,
.prof-pay-cell.net .prof-pay-v { color: var(--success, #2bb673); }

.prof-table { margin: 0; }
.prof-none { color: var(--text-muted, #8fa2bd); font-size: 0.85rem; margin: 0; padding: 8px 0; }

@media (max-width: 1100px) {
    .prof-grid { grid-template-columns: 1fr; }
}
@media (max-width: 620px) {
    .prof-pay { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
</style>
@endsection
