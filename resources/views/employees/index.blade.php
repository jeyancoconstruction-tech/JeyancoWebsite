@extends('layouts')
@section('page_title', 'Employees')

@section('content')
<div class="emp-page">

    {{-- ── Flash ──────────────────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="emp-flash">
        <i class="fas fa-check-circle"></i>
        {{ session('success') }}
    </div>
    @endif

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="dir-header">
        <div class="dir-header-text">
            <h1 class="dir-title"><i class="fas fa-users dir-title-icon"></i> {{ __('Employee Directory') }}</h1>
            <p class="dir-sub">{{ __('Manage and view all employees in the company.') }}</p>
        </div>
        <div class="dir-header-actions">
            <a href="{{ route('employees.export') }}" class="dir-btn-ghost">
                <i class="fas fa-download"></i> {{ __('Export') }}
            </a>
            <a href="{{ route('employees.create') }}" class="dir-btn-primary">
                <i class="fas fa-plus"></i> {{ __('Add Employee') }}
            </a>
        </div>
    </div>

    {{-- ── Summary card ────────────────────────────────────────────────────────
         Ang bilang ay galing sa parehong koleksyon na ipinapakita ng talahanayan
         sa ibaba, kaya imposibleng magkasalungat ang card at ang mga row. --}}
    <div class="dir-stats">
        <div class="dir-stat">
            <span class="dir-stat-icon blue"><i class="fas fa-users"></i></span>
            <div class="dir-stat-body">
                <span class="dir-stat-label">{{ __('Total Employees') }}</span>
                <span class="dir-stat-value">{{ $stats['total'] }}</span>
            </div>
            <span class="dir-stat-foot">{{ __('Active workforce') }}</span>
        </div>
    </div>

    {{-- ── Table card ──────────────────────────────────────────────────────── --}}
    <div class="emp-card">

        {{-- Toolbar: tabs, search, filter --}}
        <div class="dir-toolbar">
            <div class="dir-tabs">
                <button type="button" class="dir-tab active" data-scope="all">
                    All Employees <span class="dir-tab-count" id="countAll">{{ $stats['total'] }}</span>
                </button>
                <button type="button" class="dir-tab" data-scope="regular"
                        title="{{ __('Oras-oras ang bayad at kasama sa payroll') }}">
                    Regular <span class="dir-tab-count">{{ $stats['regular'] }}</span>
                </button>
                <button type="button" class="dir-tab" data-scope="contractual"
                        title="{{ __('Bayad ayon sa kontrata — attendance lang ang sinusubaybayan, hindi kasama sa payroll') }}">
                    Contractual <span class="dir-tab-count">{{ $stats['contractual'] }}</span>
                </button>
            </div>

            <div class="dir-toolbar-right">
                <div class="emp-search-wrap">
                    <i class="fas fa-search emp-search-icon"></i>
                    <input type="text" id="empSearch" class="emp-search" placeholder="{{ __('Search by name, position…') }}">
                </div>

                <div class="emp-select-wrap">
                    <select id="siteFilter" class="emp-select">
                        <option value="">{{ __('All Sites') }}</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </select>
                    <i class="fas fa-filter emp-select-icon"></i>
                </div>

                <div class="emp-select-wrap">
                    <select id="shiftFilter" class="emp-select">
                        <option value="">{{ __('All shifts') }}</option>
                        @foreach($shifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                        @endforeach
                        <option value="none">{{ __('Unassigned') }}</option>
                    </select>
                    <i class="fas fa-user-clock emp-select-icon"></i>
                </div>

                {{-- Paano nakahanay ang listahan. Iisang <table> lang ang
                     pinagmumulan ng dalawang anyo — CSS lang ang nagbabago —
                     kaya hindi sila puwedeng magkaiba ng laman, at patuloy na
                     gumagana ang search, salain at checkbox sa dalawa. --}}
                <div class="dir-viewswitch" role="group" aria-label="{{ __('Ayos ng listahan') }}">
                    <button type="button" class="dir-view-opt active" data-view="table"
                            title="{{ __('Talahanayan') }}" aria-pressed="true">
                        <i class="fas fa-list"></i>
                    </button>
                    <button type="button" class="dir-view-opt" data-view="grid"
                            title="{{ __('Grid') }}" aria-pressed="false">
                        <i class="fas fa-th-large"></i>
                    </button>
                </div>

            </div>
        </div>

        <div class="table-responsive">
            <table class="emp-table" id="empTable">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Site') }}</th>
                        <th>{{ __('Labor Type') }}</th>
                        <th>{{ __('Shift') }}</th>
                        {{-- "Rate" lang, hindi "Rate / hr": may kada-oras (regular) at
                             may kabuuan ng kontrata (contractual) sa hanay na ito. --}}
                        <th class="text-center">{{ __('Rate') }}</th>
                        <th class="text-center">{{ __('Vale Balance') }}</th>
                        <th>{{ __('Fingerprint') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr data-site="{{ $emp->site_id ?? '' }}"
                        data-shift="{{ $emp->shift_id ?? '' }}"
                        data-name="{{ strtolower($emp->name) }}"
                        data-position="{{ strtolower($emp->position ?: ($emp->laborType->name ?? '')) }}"
                        data-type="{{ $emp->isContractual() ? 'contractual' : 'regular' }}">

                        {{-- Employee (avatar + name + ID) --}}
                        <td>
                            <div class="emp-cell">
                                @if($emp->photo)
                                    <img src="{{ url('storage/' . $emp->photo) }}"
                                         alt="{{ $emp->name }}"
                                         class="emp-avatar-img">
                                @else
                                    <div class="emp-avatar-initials emp-av-{{ substr(strtolower($emp->name), 0, 1) }}">
                                        {{ strtoupper(substr($emp->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="emp-info">
                                    <span class="emp-name">{{ $emp->name }}</span>
                                    <span class="emp-meta">
                                        <span class="emp-id-badge">#{{ str_pad($emp->id, 4, '0', STR_PAD_LEFT) }}</span>
                                        {{-- Nakikita agad kung sino ang wala sa payroll kahit
                                             nasa "All Employees" tab ka. --}}
                                        @if($emp->isContractual())
                                            <span class="emp-type-badge">{{ __('Contractual') }}</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </td>

                        {{-- Site. Ang data-label ay walang epekto sa talahanayan;
                             sa grid ito ang nagiging pangalan ng linya sa card. --}}
                        <td data-label="Site">
                            @if($emp->site)
                                <span class="emp-badge-site">
                                    <i class="fas fa-map-marker-alt"></i>
                                    {{ $emp->site->name }}
                                </span>
                            @else
                                <span class="emp-dash">—</span>
                            @endif
                        </td>

                        {{-- Labor Type --}}
                        <td data-label="Labor Type">
                            @if($emp->laborType)
                                <span class="emp-badge-labor">
                                    <i class="fas fa-briefcase"></i>
                                    {{ $emp->laborType->name }}
                                </span>
                            @else
                                <span class="emp-dash">—</span>
                            @endif
                        </td>

                        {{-- Shift. Isang dropdown na kusang nagse-save — para
                             mabilis tatakan ang buong crew nang hindi binubuksan
                             at muling sinasagutan ang buong edit form kada tao.
                             Ang bagong shift ay para sa susunod na araw na
                             papasukan; hawak ng bawat naitalang araw ang shift
                             na pinagtrabahuhan nito. --}}
                        <td data-label="Shift">
                            <div class="emp-shift-wrap {{ $emp->shift_id ? '' : 'is-unset' }}">
                                <select class="emp-shift" data-id="{{ $emp->id }}"
                                        aria-label="{{ __('Shift') }} — {{ $emp->name }}">
                                    <option value="">{{ __('Unassigned') }}</option>
                                    @foreach($shifts as $sh)
                                        <option value="{{ $sh->id }}" @selected($emp->shift_id === $sh->id)>
                                            {{ $sh->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <i class="fas fa-check emp-shift-ok" aria-hidden="true"></i>
                            </div>
                        </td>

                        {{-- Rate --}}
                        <td class="emp-rate" data-label="Rate">
                            @if($emp->isContractual())
                                {{-- Walang oras-oras na rate ang kontrata. Ang ipapakita ay
                                     ang kabuuan ng kontrata; kung ilalagay dito ang
                                     rate_per_hour ay ₱0.00 ang lalabas at mukhang walang
                                     bayad ang tao, gayong bayad siya sa labas ng payroll. --}}
                                <span class="emp-rate-contract">₱{{ number_format($emp->contract_rate ?? 0, 2) }}</span>
                                <span class="emp-rate-note">{{ __('contract') }}</span>
                            @else
                                ₱{{ number_format($emp->rate_per_hour, 2) }}
                            @endif
                        </td>

                        {{-- Vale balance --}}
                        <td class="emp-vale {{ ($emp->vale ?? 0) > 0 ? 'has-vale' : '' }}"
                            data-vale-cell="{{ $emp->id }}" data-label="Vale">
                            ₱{{ number_format($emp->vale ?? 0, 2) }}
                        </td>

                        {{-- Fingerprint --}}
                        <td data-label="Fingerprint">
                            @if($emp->fingerprint_id)
                                <span class="emp-badge-fp">
                                    <i class="fas fa-fingerprint"></i>
                                    {{ $emp->fingerprint_id }}
                                </span>
                            @else
                                <span class="emp-dash">{{ __('Not set') }}</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="emp-actions-cell">
                            {{-- Nakasulat, hindi mata: ang icon ay kailangan pang
                                 hulaan, at ang pagbubukas ng buong rekord ay
                                 hindi dapat pahulaan. --}}
                            <a href="{{ route('employees.show', $emp->id) }}"
                               class="emp-view-btn" title="Buksan ang rekord ni {{ $emp->name }}">
                                View Details
                            </a>
                            <div class="emp-more-wrap">
                                <button type="button" class="emp-more-btn" aria-label="{{ __('More options') }}">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                {{-- Walang Edit dito. Ang pagbabago ng rekord ay
                                     nagsisimula sa View Details, kung saan
                                     nakikita mo muna ang buong laman bago mo
                                     ito baguhin. --}}
                                <div class="emp-more-menu">
                                    <button type="button" class="emp-more-item js-set-vale"
                                            data-id="{{ $emp->id }}"
                                            data-name="{{ $emp->name }}"
                                            data-vale="{{ $emp->vale ?? 0 }}">
                                        <i class="fas fa-coins"></i> {{ __('Set Vale') }}
                                    </button>
                                    <button type="button" class="emp-more-item emp-more-delete js-emp-delete"
                                            data-id="{{ $emp->id }}"
                                            data-name="{{ $emp->name }}">
                                        <i class="fas fa-trash"></i> {{ __('Delete') }}
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="emp-empty-row">
                        <td colspan="8">
                            <div class="emp-empty">
                                <div class="emp-empty-icon"><i class="fas fa-users"></i></div>
                                <p class="emp-empty-title">{{ __('No employees yet') }}</p>
                                <p class="emp-empty-sub">{{ __('Register employees from the kiosk to get started.') }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Ilang ang ipinapakita ngayon. Nagbabago ito habang naghahanap o
             nagsasala, kaya hindi ito nagsisinungaling tungkol sa nakikita mo. --}}
        <div class="dir-foot">
            <span class="dir-foot-text">
                Showing <strong id="dirShown">{{ $stats['total'] }}</strong>
                of <strong>{{ $stats['total'] }}</strong> {{ __('employees') }}
            </span>
        </div>

        {{-- Filter / search empty state --}}
        <div id="noMatch" class="emp-empty" style="display:none;padding:48px 0;">
            <div class="emp-empty-icon"><i class="fas fa-filter"></i></div>
            <p class="emp-empty-title">{{ __('No results') }}</p>
            <p class="emp-empty-sub">{{ __('Try a different name or site filter.') }}</p>
        </div>
    </div>
</div>

{{-- ── Delete confirmation ─────────────────────────────────────────────────
     Ang pagbura ay tinatanong sa isang modal na nagsasabi ng PANGALAN, hindi
     sa isang browser prompt na madaling mapindot nang hindi nababasa. --}}
<div class="modal fade" id="empDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content emp-modal-content">
            <div class="emp-modal-header">
                <div>
                    <h3 class="emp-modal-title">{{ __('Remove employee') }}</h3>
                    <p class="emp-modal-sub" id="deleteModalName">—</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-3">
                <p class="emp-modal-sub" style="color:var(--text-muted);">
                    Mapupunta siya sa <strong>{{ __('Removed') }}</strong> sa Register &amp; Manage at
                    maibabalik mula roon. Mananatili ang attendance at payroll niya.
                </p>
                <form id="empDeleteForm" method="POST" class="d-flex justify-content-end gap-2 mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="emp-btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="emp-bulk-delete">
                        <i class="fas fa-trash"></i> {{ __('Remove') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ── Set Vale modal ─────────────────────────────────────────────────────── --}}
<div class="modal fade" id="empValeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content emp-modal-content">
            <div class="emp-modal-header">
                <div>
                    <h3 class="emp-modal-title">{{ __('Set Vale Balance') }}</h3>
                    <p class="emp-modal-sub" id="valeModalName">—</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-3">
                <label class="emp-site-add-label" for="valeInput"><i class="fas fa-coins"></i> {{ __('Vale amount (₱)') }}</label>
                <input type="number" step="0.01" min="0" id="valeInput" class="emp-modal-input" style="width:100%;" placeholder="0.00">
                <p class="emp-modal-sub mt-2" style="color:var(--text-muted);">{{ __('Manual running balance per employee. Payroll deductions are still entered per period on the Payroll page.') }}</p>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="emp-btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="emp-site-add-btn" id="valeSaveBtn">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
/* ═══════════════════════════════════════════════════════════════════════
   EMPLOYEE DIRECTORY — header, summary cards, toolbar
   Ang mga kulay ay galing sa enterprise.css tokens, hindi hardcoded,
   kaya sumusunod ito sa tema ng buong sistema.
   ═══════════════════════════════════════════════════════════════════════ */

/* ── Header ── */
.dir-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 20px; flex-wrap: wrap; margin-bottom: 22px;
}
.dir-title {
    display: flex; align-items: center; gap: 12px;
    font-size: 1.85rem; font-weight: 800; letter-spacing: -0.02em;
    color: var(--text-primary); margin: 0;
}
.dir-title-icon {
    font-size: 1.4rem; color: var(--accent, #2f7fd1);
}
.dir-sub {
    margin: 6px 0 0 40px; font-size: 0.88rem;
    color: var(--text-muted, #8fa2bd);
}
.dir-header-actions { display: flex; align-items: center; gap: 10px; }

.dir-btn-ghost, .dir-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 18px; border-radius: 10px;
    font-size: 0.86rem; font-weight: 600; text-decoration: none;
    cursor: pointer; transition: filter .15s, transform .1s, background .15s;
    white-space: nowrap;
}
.dir-btn-ghost {
    background: var(--bg-subtle);
    border: 1px solid var(--border, #2a3856);
    color: var(--text-primary);
}
.dir-btn-ghost:hover { background: var(--border); color: var(--text-primary); }
.dir-btn-primary {
    background: var(--accent, #2f7fd1); border: 1px solid var(--accent, #2f7fd1);
    color: #fff; box-shadow: 0 2px 10px rgba(47,127,209,0.28);
}
.dir-btn-primary:hover { filter: brightness(1.08); color: #fff; }
.dir-btn-ghost:active, .dir-btn-primary:active { transform: translateY(1px); }
.dir-btn-ghost:focus-visible, .dir-btn-primary:focus-visible {
    outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px;
}

/* ── Summary card ──
   Iisang card na lang, kaya hindi na grid: hinahabaan ito sa buong lapad para
   pantay ang gilid nito at ng talahanayan sa ibaba. Pahiga ang laman — icon,
   bilang, tapos ang caption sa kanan — para hindi mukhang kulang ang card. */
.dir-stats { margin-bottom: 20px; }
.dir-stat {
    display: flex; align-items: center; gap: 18px;
    background: var(--surface, #131c2e);
    border: 1px solid var(--border, #2a3856);
    border-radius: 14px; padding: 18px 22px;
    transition: border-color .18s;
}
.dir-stat:hover { border-color: var(--accent, #2f7fd1); }
.dir-stat-body { display: flex; flex-direction: column; min-width: 0; }
.dir-stat-label {
    font-size: 0.74rem; font-weight: 600; color: var(--text-muted, #8fa2bd);
    letter-spacing: 0.4px; text-transform: uppercase;
}
.dir-stat-value {
    font-size: 1.9rem; font-weight: 800; line-height: 1.1; margin-top: 4px;
    color: var(--text-primary); font-variant-numeric: tabular-nums;
}
.dir-stat-foot {
    margin-left: auto; font-size: 0.78rem; color: var(--text-muted);
}
.dir-stat-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 52px; height: 52px; border-radius: 13px; flex-shrink: 0;
    font-size: 1.25rem;
}
.dir-stat-icon.blue   { background: rgba(47,127,209,0.16);  color: #6fa8dc; }

/* ── Toolbar: tabs + search + filter ── */
.dir-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; flex-wrap: wrap;
    padding: 14px 18px; border-bottom: 1px solid var(--border, #2a3856);
}
.dir-tabs { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.dir-tab {
    display: inline-flex; align-items: center; gap: 8px;
    background: none;
    padding: 9px 14px;
    font-size: 0.85rem; font-weight: 600; color: var(--text-muted, #8fa2bd);
    cursor: pointer; text-decoration: none; transition: color .15s, background .15s, border-color .15s;
}
.dir-tab:hover { color: var(--text-primary); }
.dir-tab:focus-visible { outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px; }
.dir-tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 7px; border-radius: 11px;
    background: var(--border); color: var(--text-primary);
    font-size: 0.72rem; font-weight: 700; font-variant-numeric: tabular-nums;
}
.dir-tab.active .dir-tab-count { background: var(--brand-subtle); color: var(--brand); }
.dir-tab-count.warn { background: rgba(232,163,61,0.18); color: #e8a33d; }

.dir-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Ang pasukan sa buong rekord. Nakasulat ang gagawin nito, hindi icon lang,
   dahil dito nagsisimula ang lahat ng pagbabago sa isang empleyado. */
.emp-view-btn {
    display: inline-flex; align-items: center; justify-content: center;
    height: 32px; padding: 0 12px; margin-right: 6px; vertical-align: middle;
    font-size: 12px; font-weight: 600; white-space: nowrap;
    color: var(--text-secondary, #8fa2bd); text-decoration: none;
    background: var(--bg-subtle);
    border: 1px solid var(--border, #2a3856);
    border-radius: 8px;
    transition: color .15s, border-color .15s;
}
.emp-view-btn:hover { color: var(--accent, #2f7fd1); border-color: var(--accent, #2f7fd1); }
.emp-view-btn:focus-visible { outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px; }

/* ── Footer count ── */
.dir-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-top: 1px solid var(--border, #2a3856);
}
.dir-foot-text { font-size: 0.8rem; color: var(--text-muted, #8fa2bd); }
.dir-foot-text strong { color: var(--text-primary); font-variant-numeric: tabular-nums; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .dir-title { font-size: 1.5rem; }
    .dir-sub { margin-left: 0; }
    .dir-header-actions { width: 100%; }
    .dir-btn-ghost, .dir-btn-primary { flex: 1; justify-content: center; }
}
@media (max-width: 560px) {
    /* Bumababa ang caption sa sariling linya kapag masikip na ang card. */
    .dir-stat { flex-wrap: wrap; gap: 14px; padding: 16px 18px; }
    .dir-stat-foot { margin-left: 0; width: 100%; }
    .dir-toolbar { flex-direction: column; align-items: stretch; }
    .dir-toolbar-right { flex-direction: column; align-items: stretch; }
    .dir-toolbar-right .emp-search-wrap,
    .dir-toolbar-right .emp-select-wrap { width: 100%; }
}

/* ── Page shell ──────────────────────────────────────────────────────────── */
.emp-page { max-width: none; width: 100%; margin: 0; }

/* ── Flash ───────────────────────────────────────────────────────────────── */
.emp-flash {
    display: flex; align-items: center; gap: 10px;
    background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--success);
    color: var(--text-primary); padding: 11px 16px; border-radius: 6px;
    font-size: 13.5px; font-weight: 500; margin-bottom: 16px;
}
.emp-flash i { color: var(--success); }

/* ── Page header ─────────────────────────────────────────────────────────── */
.emp-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 14px; margin-bottom: 22px;
}
.emp-header-left { display: flex; align-items: baseline; gap: 10px; }
.emp-title {
    font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0; letter-spacing: -0.01em;
}
.emp-count-chip {
    font-size: 12px; font-weight: 500; color: var(--text-secondary);
    background: transparent; border: none; padding: 0;
}
.emp-header-right {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}

/* ── Search ──────────────────────────────────────────────────────────────── */
.emp-search-wrap {
    position: relative; display: flex; align-items: center;
}
.emp-search-icon {
    position: absolute; left: 11px; color: var(--text-muted);
    font-size: 12px; pointer-events: none;
}
.emp-search {
    height: 38px; padding: 0 12px 0 32px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--surface); color: var(--text-primary); width: 210px;
    outline: none; transition: border-color .12s;
}
.emp-search:focus { border-color: var(--brand); }
.emp-search::placeholder { color: var(--text-muted); }

/* ── Select ──────────────────────────────────────────────────────────────── */
.emp-select-wrap { position: relative; }
.emp-select {
    height: 38px; padding: 0 30px 0 11px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--surface); color: var(--text-primary);
    appearance: none; -webkit-appearance: none;
    cursor: pointer; outline: none;
    transition: border-color .12s;
    min-width: 140px;
}
.emp-select:focus { border-color: var(--brand); }
.emp-select-icon {
    position: absolute; right: 11px; top: 50%;
    transform: translateY(-50%); color: var(--text-muted);
    font-size: 10px; pointer-events: none;
}

/* ── Delete All button — muted danger, secondary weight (not a big red block) ── */
.emp-del-all-btn {
    height: 38px; padding: 0 14px; font-size: 13px; font-weight: 500;
    background: var(--surface); color: var(--danger);
    border: 1px solid var(--border); border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
    transition: all .12s; white-space: nowrap;
}
.emp-del-all-btn:hover { background: rgba(179,64,58,0.08); border-color: var(--danger); }

/* ── Secondary button ────────────────────────────────────────────────────── */
.emp-btn-secondary {
    height: 38px; padding: 0 14px; font-size: 13px; font-weight: 500;
    background: var(--surface); color: var(--text-primary);
    border: 1px solid var(--border); border-radius: 6px;
    cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    transition: all .12s;
    white-space: nowrap;
}
.emp-btn-secondary:hover { background: var(--brand-subtle); border-color: var(--border-md); }

/* ── Table card ──────────────────────────────────────────────────────────── */
.emp-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 6px; overflow: hidden;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.emp-table { width: 100%; border-collapse: collapse; }
.emp-table thead th {
    background: var(--surface); padding: 10px 16px;
    font-size: 11px; font-weight: 600; letter-spacing: .04em;
    text-transform: uppercase; color: var(--text-secondary);
    border-bottom: 1px solid var(--border);
    white-space: nowrap; position: sticky; top: 0; z-index: 1;
}
.emp-table thead th:last-child { width: 48px; }
.emp-table tbody td {
    padding: 10px 16px; border-bottom: 1px solid var(--border);
    vertical-align: middle; font-size: 13.5px; color: var(--text-primary);
}
.emp-table tbody tr:last-child td { border-bottom: none; }
.emp-table tbody tr { transition: background .1s; }
.emp-table tbody tr:hover td { background: var(--brand-subtle); }

/* ── Employee cell (avatar + info) ───────────────────────────────────────── */
.emp-cell { display: flex; align-items: center; gap: 11px; }
.emp-avatar-img {
    width: 36px; height: 36px; border-radius: 50%;
    object-fit: cover; border: 1px solid var(--border);
    flex-shrink: 0;
}
.emp-avatar-initials {
    width: 36px; height: 36px; border-radius: 50%;
    font-size: 13px; font-weight: 600;
    background: var(--brand-subtle) !important; color: var(--brand);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; border: none;
}
/* Uniform avatar — one calm brand tint for everyone (no rainbow initials) */
[class*="emp-av-"] { background: var(--brand-subtle) !important; color: var(--brand) !important; }

.emp-info  { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.emp-name  { font-size: 13.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.emp-id-badge {
    font-size: 11px; font-weight: 500; font-family: ui-monospace,'SF Mono','Courier New',monospace;
    color: var(--text-secondary); background: transparent; border: none;
    padding: 0; width: fit-content;
}
.emp-meta { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
/* Tahimik na tatak — pantulong sa pagbasa, hindi babala, kaya walang matingkad
   na kulay. Ang punto lang ay makilala agad ang wala sa payroll. */
.emp-type-badge {
    font-size: 10px; font-weight: 600; letter-spacing: 0.3px;
    color: #a78bfa; background: rgba(139,92,246,0.14);
    border: 1px solid rgba(139,92,246,0.28);
    border-radius: 999px; padding: 1px 7px; white-space: nowrap;
}

/* ── Badges — one calm, neutral outline style (no colour fills) ──────────── */
.emp-badge-site, .emp-badge-labor, .emp-badge-fp {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500; white-space: nowrap;
    color: var(--text-secondary) !important; background: transparent !important;
    border: 1px solid var(--border) !important;
    padding: 3px 9px; border-radius: 6px;
}
.emp-badge-site i, .emp-badge-labor i, .emp-badge-fp i { font-size: 9px; color: var(--text-muted); }
.emp-badge-fp { font-family: ui-monospace,'SF Mono','Courier New',monospace; }

/* ── Shift ────────────────────────────────────────────────────────────────
   Hugis-badge tulad ng katabi nitong mga hanay, hindi mukhang form control —
   ang buong hilera ay babasahin bago may babaguhin. Ang kulay ay dumarating
   lamang kapag may dapat gawin: dilaw kung walang tatak, berdeng tsek kapag
   naitala na ang pagbabago. */
.emp-shift-wrap { position: relative; display: inline-flex; align-items: center; }
.emp-shift {
    appearance: none; -webkit-appearance: none;
    font-size: 12px; font-weight: 500; font-family: inherit;
    color: var(--text-secondary); background: transparent;
    border: 1px solid var(--border);
    padding: 3px 26px 3px 9px; border-radius: 6px;
    cursor: pointer; transition: border-color .15s, color .15s;
}
.emp-shift:hover:not(:disabled) { border-color: var(--accent, #2f7fd1); color: var(--text-primary); }
.emp-shift:focus-visible { outline: 2px solid var(--accent, #2f7fd1); outline-offset: 1px; }
.emp-shift:disabled { opacity: .55; cursor: progress; }
.emp-shift option { background: var(--surface, #131c2e); color: var(--text-primary); }

/* Ang caret. Nawala ito kasama ng appearance:none. */
.emp-shift-wrap::after {
    content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
    position: absolute; right: 9px; font-size: 10px;
    color: var(--text-muted); pointer-events: none;
}

/* Walang tatak: hindi ito error, kaya hindi pula — pero hindi rin dapat
   matabunan, dahil ang office default ang bumabagsak dito sa payroll. */
.emp-shift-wrap.is-unset .emp-shift {
    color: var(--warning, #d98324);
    border-color: color-mix(in srgb, var(--warning, #d98324) 45%, transparent);
}

.emp-shift-ok {
    position: absolute; right: -18px;
    font-size: 11px; color: var(--success, #16a34a);
    opacity: 0; transform: scale(.7); pointer-events: none;
    transition: opacity .18s, transform .18s;
}
.emp-shift-wrap.is-saved .emp-shift-ok { opacity: 1; transform: scale(1); }
.emp-shift-wrap.is-saved .emp-shift { border-color: color-mix(in srgb, var(--success, #16a34a) 50%, transparent); }

.emp-dash { color: var(--text-muted); font-size: 13px; }
.emp-rate { text-align: center; font-size: 13.5px; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }
/* Ang halaga ng kontrata ay kabuuan, hindi kada-oras — kaya may maliit na
   pananda sa ilalim para hindi ito mabasa bilang rate kada oras. */
.emp-rate-contract { display: block; }
.emp-rate-note {
    display: block; font-size: 10px; font-weight: 500; letter-spacing: 0.3px;
    color: var(--text-muted); margin-top: 1px;
}
.emp-vale { text-align: center; font-size: 13.5px; font-weight: 600; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.emp-vale.has-vale { color: var(--danger); }
.emp-actions-cell { text-align: right; white-space: nowrap; }

/* ── View switcher ───────────────────────────────────────────────────────── */
.dir-viewswitch {
    display: inline-flex; gap: 2px; padding: 2px;
    background: var(--bg-subtle);
    border: 1px solid var(--border, #2a3856);
    border-radius: 9px;
}
.dir-view-opt {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: none; background: none;
    border-radius: 7px; color: var(--text-muted, #8fa2bd);
    cursor: pointer; transition: background .15s, color .15s;
}
.dir-view-opt:hover { color: var(--text-primary, #e8eef7); }
.dir-view-opt.active { background: rgba(47,127,209,0.18); color: #6fa8dc; }
.dir-view-opt:focus-visible { outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px; }

/* ── Grid view ───────────────────────────────────────────────────────────────
   Parehong <table> ang pinagmumulan ng talahanayan at ng grid — CSS lang ang
   nagbabago. Walang pangalawang kopya ng bawat row, kaya imposibleng maglihis
   ang dalawa, at patuloy na gumagana ang search, salain at checkbox dahil
   pareho pa rin ang mga <tr> na tinatago o ipinapakita ng filter. */
.emp-table.view-grid { display: block; }
.emp-table.view-grid thead { display: none; }
.emp-table.view-grid tbody {
    display: grid; gap: 14px; padding: 16px;
    grid-template-columns: repeat(auto-fill, minmax(232px, 1fr));
}
.emp-table.view-grid tr {
    display: flex; flex-direction: column; gap: 2px; position: relative;
    background: var(--surface, #131c2e);
    border: 1px solid var(--border, #2a3856);
    border-radius: 12px; padding: 16px 14px 10px;
    transition: border-color .18s;
}
.emp-table.view-grid tr:hover { border-color: var(--accent, #2f7fd1); }
.emp-table.view-grid td { display: block; border: none; padding: 0; text-align: left; }

/* Pangalan at larawan sa itaas, nakasentro — ito ang mukha ng card. */
/* Ang unang cell ang Employee. Hindi nth-child(2) — nauna rito ang checkbox
   column noon, at nawala na iyon. */
.emp-table.view-grid td:first-child { text-align: center; margin-bottom: 10px; }
.emp-table.view-grid .emp-cell { flex-direction: column; align-items: center; gap: 8px; }
.emp-table.view-grid .emp-avatar-img,
.emp-table.view-grid .emp-avatar-initials { width: 56px; height: 56px; font-size: 20px; }
.emp-table.view-grid .emp-info { align-items: center; }
.emp-table.view-grid .emp-name { white-space: normal; text-align: center; }
.emp-table.view-grid .emp-meta { justify-content: center; flex-wrap: wrap; }

/* Bawat hanay ng talahanayan ay nagiging isang linya sa card: pangalan ng
   hanay sa kaliwa, halaga sa kanan. Ang ::before ang humahalili sa <thead>
   na nakatago sa anyong ito. */
.emp-table.view-grid td[data-label] {
    display: flex; align-items: baseline; justify-content: flex-end;
    gap: 6px; font-size: 12px; padding: 5px 0;
    border-top: 1px dashed var(--border, #2a3856);
}
.emp-table.view-grid td[data-label]::before {
    content: attr(data-label);
    margin-right: auto; flex-shrink: 0;
    font-size: 11px; font-weight: 600; letter-spacing: .3px;
    color: var(--text-muted, #8fa2bd);
}
/* Sa card, magkatabi ang halaga at ang "contract" na pananda. */
.emp-table.view-grid .emp-rate-contract,
.emp-table.view-grid .emp-rate-note { display: inline; }

.emp-table.view-grid td.emp-actions-cell {
    display: flex; justify-content: flex-end; align-items: center; gap: 6px;
    margin-top: 6px; padding-top: 8px;
    border-top: 1px solid var(--border, #2a3856);
}

/* Ang "walang laman" ay mensahe, hindi card — kaya buong lapad at hubad. */
.emp-table.view-grid tr.emp-empty-row {
    grid-column: 1 / -1; background: none; border: none; padding: 0;
}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.emp-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 48px 0; text-align: center;
}
.emp-empty-icon {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--bg-subtle); display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: var(--text-muted); margin-bottom: 14px;
}
.emp-empty-title { font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 5px; }
.emp-empty-sub   { font-size: 13px; color: var(--text-secondary); margin: 0; }
.emp-empty-row td { padding: 0; border-bottom: none; }

/* ── Three-dot menu ──────────────────────────────────────────────────────── */
.emp-more-wrap { position: relative; display: inline-block; }
.emp-more-btn {
    width: 32px; height: 32px; border-radius: 6px;
    border: 1px solid var(--border); background: var(--surface);
    color: var(--text-secondary); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .12s;
    font-size: 13px;
}
.emp-more-btn:hover        { background: var(--brand-subtle); border-color: var(--border-md); color: var(--text-primary); }
.emp-more-btn.active       { background: var(--brand-subtle); border-color: var(--brand); color: var(--brand); }
/* Fixed, not absolute. The card and the table's scroll wrapper both clip what
   escapes them, so an absolutely positioned menu opened inside the row and was
   never seen. Fixed leaves the clipping behind; the JS below puts it under the
   button it belongs to. */
.emp-more-menu {
    display: none; position: fixed;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 6px; box-shadow: var(--shadow-lg);
    z-index: 1080; min-width: 150px; overflow: hidden;
}
.emp-more-menu.open { display: block; }
.emp-more-item {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 14px; font-size: 13px; font-weight: 500;
    color: var(--text-primary); text-decoration: none;
    width: 100%; background: none; border: none; cursor: pointer;
    transition: background .1s;
}
.emp-more-item:hover        { background: var(--brand-subtle); }
.emp-more-item i            { width: 14px; text-align: center; font-size: 12px; color: var(--text-secondary); }
.emp-more-delete            { color: var(--danger); }
.emp-more-delete i          { color: var(--danger); }
.emp-more-delete:hover      { background: rgba(179,64,58,0.08); }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.emp-modal-content {
    border: 1px solid var(--border); border-radius: 6px;
    box-shadow: var(--shadow-xl); overflow: hidden; background: var(--surface);
}
.emp-modal-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 16px 20px;
    background: var(--surface); color: var(--text-primary); border-bottom: 1px solid var(--border);
}
.emp-modal-title { font-size: 15px; font-weight: 600; margin: 0 0 2px; }
.emp-modal-sub   { font-size: 12px; color: var(--text-secondary); margin: 0; }
.emp-site-add-panel {
    background: var(--bg-subtle); border: 1px solid var(--border);
    border-radius: 6px; padding: 14px 16px; margin-bottom: 16px;
}
.emp-site-add-label {
    font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
}
.emp-site-add-label i { color: var(--brand); }
.emp-modal-input {
    flex: 1; height: 38px; padding: 0 11px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--surface); color: var(--text-primary); outline: none;
    transition: border-color .12s;
}
.emp-modal-input:focus { border-color: var(--brand); }
.emp-site-add-btn {
    height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600;
    background: var(--brand); color: #fff; border: none;
    border-radius: 6px; cursor: pointer; white-space: nowrap;
    transition: background .12s;
}
.emp-site-add-btn:hover { background: var(--brand-strong); }
.emp-modal-err { font-size: 12px; color: var(--danger); margin-top: 6px; }
.emp-sites-loading { text-align: center; padding: 20px 0; color: var(--text-secondary); font-size: 13px; }

/* ── Site list rows ──────────────────────────────────────────────────────── */
.site-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 6px;
    border: 1px solid var(--border); margin-bottom: 8px;
    background: var(--surface); transition: border-color .12s;
}
.site-row:hover { border-color: var(--border-md); }
.site-row .site-name  { flex: 1; font-weight: 600; color: var(--text-primary); font-size: 13.5px; }
.site-row .site-count { font-size: 12px; color: var(--text-secondary); white-space: nowrap; }
.site-row input.site-edit-input {
    flex: 1; font-size: 13.5px; font-weight: 600;
    border: 1px solid var(--brand); border-radius: 6px; padding: 4px 8px;
    outline: none; background: var(--surface); color: var(--text-primary);
}
.site-action-btn {
    border: none; background: transparent;
    padding: 5px 7px; border-radius: 6px;
    cursor: pointer; font-size: 12px; line-height: 1;
    transition: background .12s; color: var(--text-secondary);
}
.site-action-btn:hover  { background: var(--brand-subtle); }
.site-action-btn.edit   { color: var(--text-secondary); }
.site-action-btn.save   { color: var(--success); }
.site-action-btn.cancel { color: var(--text-secondary); }
.site-action-btn.del    { color: var(--danger); }

/* Nananatili ang .emp-bulk-delete kahit wala nang bulk select: ito rin ang
   pindutang Remove sa modal ng bawat empleyado. */
.emp-bulk-delete {
    height: 34px; padding: 0 14px; font-size: 12px; font-weight: 600;
    background: var(--danger); color: #fff;
    border: none; border-radius: 6px; cursor: pointer;
    transition: opacity .12s;
    display: inline-flex; align-items: center; gap: 6px;
}
.emp-bulk-delete:hover    { opacity: .9; }
.emp-bulk-delete:disabled { opacity: .5; cursor: not-allowed; }

/* Dark mode is handled by the theme-aware design tokens used above. */

@keyframes empToastIn {
    from { opacity: 0; transform: translateX(14px); }
    to   { opacity: 1; transform: none; }
}
</style>

@endsection

{{-- ── Script ─────────────────────────────────────────────────────────────────
     Pushed rather than left in the content, because the content renders above
     Bootstrap's bundle: `new bootstrap.Modal(...)` at parse time threw here,
     which took the rest of the block with it and left the Delete item in the
     three-dot menu bound to nothing. --}}
@push('scripts')
<script>
(function () {
    const csrf = '{{ csrf_token() }}';

    // ── Three-dot menus ──────────────────────────────────────────────────────
    // The menu is position:fixed, so it has to be placed by hand — under the
    // button, right edges aligned, and flipped above it when there is no room
    // below. Fixed is what gets it out of the card and the scroll wrapper,
    // both of which clip anything that escapes them.
    function placeMenu(btn, menu) {
        const r = btn.getBoundingClientRect();
        const below = window.innerHeight - r.bottom;

        menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        menu.style.top  = (below < menu.offsetHeight + 12)
            ? (r.top - menu.offsetHeight - 6) + 'px'
            : (r.bottom + 6) + 'px';
    }

    function closeMenus() {
        document.querySelectorAll('.emp-more-menu.open').forEach(m => {
            m.classList.remove('open');
            m.previousElementSibling?.classList.remove('active');
        });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.emp-more-btn');

        document.querySelectorAll('.emp-more-menu.open').forEach(m => {
            if (!btn || m !== btn.nextElementSibling) {
                m.classList.remove('open');
                m.previousElementSibling?.classList.remove('active');
            }
        });

        if (btn) {
            e.stopPropagation();
            const menu    = btn.nextElementSibling;
            const opening = !menu.classList.contains('open');

            menu.classList.toggle('open', opening);
            btn.classList.toggle('active', opening);

            // Measured after it is shown, or the width and height are both zero.
            if (opening) placeMenu(btn, menu);
        }
    });

    // A fixed menu does not travel with the row it belongs to, so it closes
    // rather than drifting away from its button.
    window.addEventListener('scroll', closeMenus, true);
    window.addEventListener('resize', closeMenus);

    // ── Unified filter (site + search name) ──────────────────────────────────
    // Aling tab ang bukas. Pinagsasama ito sa search at sa site filter, kaya
    // ang tatlo ay nagpapaliit nang sabay imbes na maglaban-laban.
    let dirScope = 'all';

    function applyFilter() {
        const siteVal  = document.getElementById('siteFilter').value;
        const shiftVal = document.getElementById('shiftFilter').value;
        const query    = document.getElementById('empSearch').value.trim().toLowerCase();
        const rows     = document.querySelectorAll('#empTable tbody tr[data-site]');
        let visible    = 0;

        rows.forEach(r => {
            const siteOk  = !siteVal || r.dataset.site === siteVal;
            // 'none' ang hanapin kung sino ang wala pang tatak — iyon mismo ang
            // listahang kailangan mo habang hinahati mo ang dalawang crew.
            const shiftOk = !shiftVal
                          || (shiftVal === 'none' ? !r.dataset.shift : r.dataset.shift === shiftVal);
            const nameOk  = !query   || (r.dataset.name || '').includes(query)
                                     || (r.dataset.position || '').includes(query);
            const scopeOk = dirScope === 'all' || r.dataset.type === dirScope;
            const show    = siteOk && shiftOk && nameOk && scopeOk;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('noMatch').style.display = (rows.length > 0 && visible === 0) ? 'block' : 'none';

        // Ang bilang sa ibaba ay dapat sumasalamin sa NAKIKITA, hindi sa
        // kabuuan — kung hindi, nagsisinungaling ito habang naghahanap ka.
        const shown = document.getElementById('dirShown');
        if (shown) shown.textContent = visible;
    }

    // ── Tabs ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.dir-tab[data-scope]').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.dir-tab[data-scope]')
                    .forEach(t => t.classList.toggle('active', t === tab));
            dirScope = tab.dataset.scope;
            applyFilter();
        });
    });

    // ── View switch: talahanayan o grid ──────────────────────────────────────
    // Isang klase lang sa <table> ang ipinapalit. Hindi ginagalaw ang mga row,
    // kaya hindi kailangang muling patakbuhin ang filter pagkatapos lumipat —
    // ang itinago nito ay nananatiling nakatago sa dalawang anyo.
    (function () {
        const table = document.getElementById('empTable');
        const opts  = document.querySelectorAll('.dir-view-opt[data-view]');
        if (!table || !opts.length) return;

        const KEY = 'jeyanco.employees.view';

        function apply(view) {
            table.classList.toggle('view-grid', view === 'grid');
            opts.forEach(o => {
                const on = o.dataset.view === view;
                o.classList.toggle('active', on);
                o.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        // Napipili ito ng bawat tao para sa sarili niyang browser — hindi ito
        // setting ng buong opisina. Nakabalot sa try dahil sa mga browser na
        // nakasara ang site storage, kung saan nagtatapon ito ng error.
        let saved = 'table';
        try { saved = localStorage.getItem(KEY) || 'table'; } catch (e) {}
        apply(saved);

        opts.forEach(o => o.addEventListener('click', () => {
            apply(o.dataset.view);
            try { localStorage.setItem(KEY, o.dataset.view); } catch (e) {}
        }));
    })();

    // ── Delete: ask in a modal that names the person ─────────────────────────
    (function () {
        const modalEl = document.getElementById('empDeleteModal');
        const form    = document.getElementById('empDeleteForm');
        const nameEl  = document.getElementById('deleteModalName');
        if (!modalEl || !form) return;

        const base  = '{{ url('employees') }}';
        const modal = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.js-emp-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                form.action = base + '/' + btn.dataset.id;
                if (nameEl) nameEl.textContent = btn.dataset.name || '';
                closeMenus();
                modal.show();
            });
        });
    })();

    document.getElementById('siteFilter').addEventListener('change', applyFilter);
    document.getElementById('shiftFilter').addEventListener('change', applyFilter);
    document.getElementById('empSearch').addEventListener('input', applyFilter);

    // Walang maramihang pagbura sa pahinang ito. Inalis ang "Delete All" noon
    // dahil isang pagkakamali lang ang layo nito sa pagbura ng buong workforce,
    // at inalis ang bulk select dahil hindi naman ito ginagamit. Ang pagbura ay
    // isa-isa na lang, sa Remove ng bawat row, kung saan may modal na
    // nagsasabi kung sino ang tatanggalin. Nananatili ang bulk-delete route —
    // gamit pa rin ito ng Register & Manage para sa Removed nito.

    // ── Toast ────────────────────────────────────────────────────────────────
    function flashToast(msg, type) {
        let wrap = document.getElementById('emp-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'emp-toast-wrap';
            wrap.style.cssText = 'position:fixed;top:76px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:6px;min-width:240px;max-width:340px;';
            document.body.appendChild(wrap);
        }
        const pal = type === 'error'
            ? { bg:'#fee2e2', bd:'#fecaca', tx:'#991b1b', ic:'times-circle' }
            : { bg:'#dcfce7', bd:'#bbf7d0', tx:'#166534', ic:'check-circle' };
        const el = document.createElement('div');
        el.style.cssText = `background:${pal.bg};border:1px solid ${pal.bd};color:${pal.tx};padding:10px 14px;border-radius:9px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);animation:empToastIn .2s ease;`;
        el.innerHTML = `<i class="fas fa-${pal.ic}"></i> ${msg}`;
        wrap.appendChild(el);
        setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 320); }, 3000);
    }

    // ── Set Vale (manual per-employee balance) ───────────────────────────────
    const valeModalEl = document.getElementById('empValeModal');
    let   valeModal   = null;
    let   valeEmpId   = null;
    function getValeModal() {
        if (!valeModal && window.bootstrap) valeModal = new bootstrap.Modal(valeModalEl);
        return valeModal;
    }
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-set-vale');
        if (!btn) return;
        valeEmpId = btn.dataset.id;
        document.getElementById('valeModalName').textContent = btn.dataset.name;
        document.getElementById('valeInput').value = parseFloat(btn.dataset.vale || 0).toFixed(2);
        closeMenus();
        const m = getValeModal(); if (m) m.show();
        setTimeout(() => document.getElementById('valeInput').focus(), 250);
    });
    document.getElementById('valeSaveBtn').addEventListener('click', async function () {
        const amount = parseFloat(document.getElementById('valeInput').value);
        if (isNaN(amount) || amount < 0) { flashToast('Enter a valid amount.', 'error'); return; }
        this.disabled = true;
        try {
            const r = await fetch(`{{ url('employees') }}/${valeEmpId}/vale`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ vale: amount }),
            });
            const data = await r.json();
            if (data.success) {
                const cell = document.querySelector(`[data-vale-cell="${valeEmpId}"]`);
                if (cell) { cell.textContent = data.formatted; cell.classList.toggle('has-vale', data.vale > 0); }
                const m = getValeModal(); if (m) m.hide();
                flashToast('Vale balance updated.', 'success');
            } else { flashToast(data.message || 'Update failed.', 'error'); }
        } catch { flashToast('Network error — please try again.', 'error'); }
        finally { this.disabled = false; }
    });

    // ── Shift ────────────────────────────────────────────────────────────────
    // Kusang nagse-save ang dropdown. Ang mabilis na tumbok ang buong punto:
    // hahatiin mo ang crew sa dalawa, at ang pagbukas ng buong edit form kada
    // tao ay labing-isang pahina para sa labing-isang pindot.
    document.querySelectorAll('.emp-shift').forEach(sel => {
        // Ang huling nakaligtas na halaga, para may maibalik kapag pumalya ang
        // network — kung hindi, ipapakita ng dropdown ang isang tatak na hindi
        // naman naitala.
        sel.dataset.last = sel.value;

        sel.addEventListener('change', async function () {
            const row  = this.closest('tr');
            const wrap = this.closest('.emp-shift-wrap');
            const id   = this.dataset.id;
            const val  = this.value;

            this.disabled = true;
            try {
                const r = await fetch(`{{ url('employees') }}/${id}/shift`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ shift_id: val || null }),
                });
                const data = await r.json();

                if (data.success) {
                    this.dataset.last = val;
                    if (row) row.dataset.shift = val;
                    if (wrap) {
                        wrap.classList.toggle('is-unset', !val);
                        wrap.classList.add('is-saved');
                        setTimeout(() => wrap.classList.remove('is-saved'), 1400);
                    }
                    // Nakasalalay dito ang salain: kung naka-piling "Night" ka
                    // at inilipat mo ang isa sa Day, dapat lumabas siya agad sa
                    // listahang tinitingnan mo.
                    applyFilter();
                    flashToast(data.name
                        ? `{{ __('Moved to') }} ${escHtml(data.name)}.`
                        : `{{ __('Shift removed.') }}`, 'success');
                } else {
                    this.value = this.dataset.last;
                    flashToast(data.message || '{{ __('Update failed.') }}', 'error');
                }
            } catch {
                this.value = this.dataset.last;
                flashToast('{{ __('Network error — please try again.') }}', 'error');
            } finally {
                this.disabled = false;
            }
        });
    });

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
})();
</script>
@endpush
