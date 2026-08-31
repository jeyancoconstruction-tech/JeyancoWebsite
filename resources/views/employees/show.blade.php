@extends('layouts')
@section('page_title', $employee->name)

@section('content')
<div class="prof-page">

    {{-- ── Back + actions ────────────────────────────────────────────────────
         Sariling klase ang pahinang ito. Humihiram ito dati ng .dir-header at
         .dir-btn-primary sa index.blade.php, na nasa loob ng <style> nito at
         hindi naman umaabot dito. --}}
    <a href="{{ route('employees.index') }}" class="prof-back">
        <i class="fas fa-arrow-left"></i> Employee Directory
    </a>

    <div class="prof-head">
        <div class="prof-head-main">
            <h1 class="prof-title">{{ $employee->name }}</h1>
            <div class="prof-meta">
                <span class="prof-id">#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                <span class="prof-sep"></span>
                <span>{{ $employee->position ?: ($employee->laborType->name ?? 'Worker') }}</span>
                <span class="prof-sep"></span>
                {{-- Ang dalawang bagay na nagbabago ng pakikitungo sa tao:
                     paano siya binabayaran, at nakakapasok ba siya. --}}
                <span class="prof-tag {{ $employee->isContractual() ? 'is-contract' : '' }}">
                    {{ $employee->employment_label }}
                </span>
                @unless($employee->fingerprint_id)
                    <span class="prof-tag is-warn">
                        <i class="fas fa-hourglass-half"></i> Walang daliri
                    </span>
                @endunless
            </div>
        </div>

        <a href="{{ route('employees.edit', $employee->id) }}" class="prof-edit">
            <i class="fas fa-pen"></i> Edit
        </a>
    </div>

    <div class="prof-grid">

        {{-- ── Left: who they are ──────────────────────────────────────────── --}}
        <div class="prof-col">

        <div class="prof-card">
            <div class="prof-id-block">
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
                            <span class="prof-tag"><i class="fas fa-map-marker-alt"></i> {{ $employee->site->name }}</span>
                        @else
                            <span class="prof-dash">Hindi pa naka-assign</span>
                        @endif
                    </dd>
                </div>
                <div class="prof-fact">
                    <dt>Labor type</dt>
                    <dd>
                        @if($employee->laborType)
                            <span class="prof-tag"><i class="fas fa-briefcase"></i> {{ $employee->laborType->name }}</span>
                        @else
                            <span class="prof-dash">—</span>
                        @endif
                    </dd>
                </div>
                {{-- Nasa header na ang Employment; hindi na inuulit dito. --}}
                @if($employee->isContractual())
                <div class="prof-fact">
                    <dt>Contract amount</dt>
                    {{-- Kabuuan para sa buong proyekto, hindi kada araw — iyon
                         ang hinihingi ng form ("Total for the whole project").
                         Ang dating "kada araw" dito ay nagsasabi ng ibang halaga
                         nang tahimik. --}}
                    <dd class="prof-money">
                        ₱{{ number_format($employee->contract_rate ?? 0, 2) }}
                        <small>buong proyekto</small>
                    </dd>
                </div>
                @else
                <div class="prof-fact">
                    <dt>Rate / hour</dt>
                    <dd class="prof-money">₱{{ number_format($employee->rate_per_hour, 2) }}</dd>
                </div>
                @endif
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
                            <span class="prof-tag mono"><i class="fas fa-fingerprint"></i> Enrolled #{{ $employee->fingerprint_id }}</span>
                        @else
                            <span class="prof-tag is-warn"><i class="fas fa-hourglass-half"></i> Wala pang daliri</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- ── Sino siya sa labas ng trabaho ────────────────────────────────
             Ang tinatanong lang ng opisina — hindi ang buong form. Ang walang
             laman ay hindi ipinapakita: mas mabuti ang maikling listahan kaysa
             hanay ng mga gitling na walang sinasabi. --}}
        @php
            $personal = array_filter([
                'Gender'       => $employee->gender,
                'Age'          => $employee->birth_date?->age,
                'Birthplace'   => $employee->birth_place,
                'Address'      => implode(', ', array_filter([
                                      $employee->address_city,
                                      $employee->address_province,
                                  ])),
                'Civil status' => $employee->civil_status,
                'Contact'      => $employee->phone,
            ], fn ($v) => $v !== null && $v !== '');
        @endphp

        <div class="prof-card">
            <div class="prof-card-head"><span>Personal na impormasyon</span></div>

            @if(count($personal))
                <dl class="prof-facts">
                    @foreach($personal as $label => $value)
                        <div class="prof-fact">
                            <dt>{{ $label }}</dt>
                            <dd class="prof-val">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="prof-none">
                    Wala pang naitatalang personal na detalye. Idadagdag ito sa <strong>Edit</strong>.
                </p>
            @endif
        </div>

        </div>

        {{-- ── Right: this week, then recent scans ─────────────────────────── --}}
        <div class="prof-col">

            <div class="prof-card">
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

            <div class="prof-card">
                <div class="prof-card-head"><span>Huling mga pasok</span></div>

                @if($attendance->isEmpty())
                    <p class="prof-none">Wala pang naitalang attendance.</p>
                @else
                <div class="table-responsive">
                    <table class="prof-table">
                        <thead>
                            <tr><th>Petsa</th><th>Session</th><th>Time in</th><th>Time out</th></tr>
                        </thead>
                        <tbody>
                        @foreach($attendance as $a)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($a->date)->format('M d, Y') }}</td>
                                <td><span class="prof-tag">{{ $a->session }}</span></td>
                                <td>{{ $a->time_in ? \Carbon\Carbon::parse($a->time_in)->format('g:i A') : '—' }}</td>
                                <td>
                                    @if($a->time_out)
                                        {{ \Carbon\Carbon::parse($a->time_out)->format('g:i A') }}
                                    @else
                                        <span class="prof-tag is-live">Nasa loob pa</span>
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
/* =============================================================================
   EMPLOYEE PROFILE
   -----------------------------------------------------------------------------
   Sarili ng pahinang ito ang lahat ng klase rito. Humihiram ito dati ng
   .dir-header, .dir-btn-primary, .emp-card at .emp-table sa index.blade.php —
   nasa loob iyon ng <style> ng ibang pahina at hindi umaabot dito, kaya walang
   anyo ang header, ang Edit at ang talahanayan.

   Tokens lang ang kulay: --surface, --text-primary, --border at kapatid nito,
   na muling binibigyang-halaga sa ilalim ng html[data-bs-theme]. Kaya sumusunod
   ito sa light at dark nang walang hiwalay na panuntunan. (Ang dating code ay
   tumatawag ng --text, --surface-2, --surface-3, --border-soft at --text-dim,
   na wala sa alinmang stylesheet — kaya laging ang madilim na fallback ang
   ginagamit, at nabubura ang pahina sa light mode.)

   Sumusunod sa sistema ng app: 6px radius, patag na ibabaw, isang brand blue.
   ========================================================================== */

.prof-page { width: 100%; max-width: none; margin: 0; }

/* --- Balik sa listahan --------------------------------------------------- */
.prof-back {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 0.8rem; font-weight: 600; text-decoration: none;
    color: var(--text-muted); transition: var(--transition);
}
.prof-back:hover { color: var(--brand); }
.prof-back i { font-size: 0.72rem; }

/* --- Pamagat + Edit ------------------------------------------------------ */
.prof-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 20px; flex-wrap: wrap;
    margin: 12px 0 20px;
    padding-bottom: 18px; border-bottom: 1px solid var(--border);
}
.prof-head-main { min-width: 0; }
.prof-title {
    margin: 0; font-size: 1.6rem; font-weight: 700;
    letter-spacing: -0.01em; color: var(--text-primary);
}
.prof-meta {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-top: 9px; font-size: 0.82rem; color: var(--text-secondary);
}
.prof-id {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    color: var(--text-muted);
}
/* Tuldok na naghihiwalay - mas tahimik kaysa tunay na bantas. */
.prof-sep {
    width: 3px; height: 3px; border-radius: 50%;
    background: var(--border-md); flex-shrink: 0;
}

.prof-edit {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 16px; border-radius: var(--radius-sm);
    font-size: 0.83rem; font-weight: 600; text-decoration: none;
    white-space: nowrap; cursor: pointer;
    background: var(--brand); border: 1px solid var(--brand);
    color: #fff; transition: var(--transition);
}
.prof-edit:hover { background: var(--brand-strong); border-color: var(--brand-strong); color: #fff; }
.prof-edit:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

/* --- Tatak ---------------------------------------------------------------
   Isang hugis para sa lahat ng maliit na pananda sa pahina, kaya hindi
   nagmumukhang iba't ibang bagay ang magkakatulad na impormasyon. */
.prof-tag {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 3px 9px; border-radius: var(--radius-sm);
    font-size: 0.72rem; font-weight: 600; white-space: nowrap;
    color: var(--text-secondary);
    background: transparent; border: 1px solid var(--border);
}
.prof-tag i { font-size: 0.62rem; color: var(--text-muted); }
.prof-tag.mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-weight: 500;
}
.prof-tag.is-contract {
    color: var(--brand); border-color: var(--brand); background: var(--brand-subtle);
}
.prof-tag.is-contract i { color: var(--brand); }
.prof-tag.is-warn   { color: var(--warning); border-color: var(--warning); }
.prof-tag.is-warn i { color: var(--warning); }
.prof-tag.is-live   { color: var(--success); border-color: var(--success); }

.prof-dash { color: var(--text-muted); font-size: 0.82rem; }

/* --- Layout -------------------------------------------------------------- */
.prof-grid { display: grid; grid-template-columns: 340px 1fr; gap: 16px; align-items: start; }
.prof-col  { display: flex; flex-direction: column; gap: 16px; }

.prof-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
    padding: 18px;
}

/* --- Pagkakakilanlan ----------------------------------------------------- */
.prof-id-block {
    display: flex; align-items: center; gap: 14px;
    padding-bottom: 16px; margin-bottom: 4px;
    border-bottom: 1px solid var(--border);
}
.prof-photo, .prof-initials {
    width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0; object-fit: cover;
}
.prof-initials {
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-subtle); color: var(--brand);
    font-size: 1.35rem; font-weight: 700;
}
.prof-name { font-size: 1rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
.prof-role { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }

/* --- Listahan ng datos --------------------------------------------------- */
.prof-facts { margin: 0; padding: 0; }
.prof-fact {
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 11px 0; border-bottom: 1px solid var(--border);
}
.prof-fact:last-child { border-bottom: none; padding-bottom: 0; }
.prof-fact dt {
    margin: 0; font-size: 0.76rem; font-weight: 500;
    color: var(--text-muted); flex-shrink: 0;
}
.prof-fact dd { margin: 0; text-align: right; min-width: 0; }
.prof-val   { font-size: 0.83rem; color: var(--text-primary); }
.prof-money {
    font-size: 0.9rem; font-weight: 700; color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}
.prof-money.warn { color: var(--warning); }
.prof-money small {
    display: block; margin-top: 2px;
    font-size: 0.64rem; font-weight: 500; color: var(--text-muted);
}

/* --- Ulo ng card --------------------------------------------------------- */
.prof-card-head {
    display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--text-muted);
    padding-bottom: 13px; margin-bottom: 15px;
    border-bottom: 1px solid var(--border);
}
.prof-period {
    font-size: 0.7rem; letter-spacing: 0; text-transform: none;
    color: var(--brand); font-weight: 600; white-space: nowrap;
}

/* --- Buod ng payroll ----------------------------------------------------- */
.prof-pay { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; }
.prof-pay-cell {
    display: flex; flex-direction: column; gap: 6px;
    background: var(--bg-subtle); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 12px 13px;
}
.prof-pay-k { font-size: 0.68rem; font-weight: 500; color: var(--text-muted); }
.prof-pay-v {
    font-size: 1.02rem; font-weight: 700; color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}
.prof-pay-v.ot    { color: var(--warning); }
.prof-pay-v.minus { color: var(--danger); }
/* Ang Net ang sagot sa tanong ng pahina, kaya isang guhit ang naghihiwalay
   dito - hindi buong kahon na kulay, na kumakain ng pansin sa katabi. */
.prof-pay-cell.net {
    border-left: 3px solid var(--success);
    background: var(--surface);
}
.prof-pay-cell.net .prof-pay-v { color: var(--success); }

/* --- Talahanayan ng pasok ------------------------------------------------ */
.prof-table { width: 100%; margin: 0; border-collapse: collapse; }
.prof-table thead th {
    padding: 0 0 9px; text-align: left;
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--text-muted); border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.prof-table tbody td {
    padding: 11px 0; font-size: 0.82rem; color: var(--text-primary);
    border-bottom: 1px solid var(--border);
}
.prof-table tbody tr:last-child td { border-bottom: none; padding-bottom: 0; }
.prof-table tbody td + td { padding-left: 14px; }
.prof-table thead th + th { padding-left: 14px; }

.prof-none { margin: 0; padding: 6px 0; font-size: 0.83rem; color: var(--text-muted); }

/* --- Responsive ---------------------------------------------------------- */
@media (max-width: 1100px) {
    .prof-grid { grid-template-columns: 1fr; }
}
@media (max-width: 620px) {
    .prof-pay { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .prof-head { gap: 14px; }
    .prof-edit { width: 100%; justify-content: center; }
    .prof-title { font-size: 1.35rem; }
}
</style>
@endsection
