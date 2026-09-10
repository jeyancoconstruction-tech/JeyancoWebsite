@extends('layouts')
@section('page_title', 'Register & Manage Employees')

@section('content')
@php
    use App\Models\Employee;
    $renderName = fn ($e) => ($e->isPending() && $e->name === 'Unregistered Worker') ? 'Unregistered Worker' : $e->name;
@endphp
<div class="rm-page">

    {{-- ── Flash / errors ──────────────────────────────────────────────────── --}}
    {{-- session('success') is a toast now. --}}
    @if($errors->any())
    <div class="rm-alert rm-alert-err">
        <i class="fas fa-exclamation-circle"></i>
        <div><strong>{{ __('Please fix the following:') }}</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
    @endif

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="rm-header">
        <div>
            <h1 class="rm-title">{{ __('Register & Manage Employees') }}</h1>
            {{-- One line. The only thing here that cannot be guessed from the
                 page is why a worker sits in Pending; where they show up once
                 they are active is evident from the app itself. --}}
            <p class="rm-sub">{{ __('New workers and kiosk detections stay in Pending until a fingerprint is enrolled.') }}</p>
        </div>
        <div class="rm-header-actions">
            {{-- "Clear All Fingerprints" used to sit here. It wiped every enrolled
                 finger in one click — the whole workforce had to re-enrol, and
                 there was no way to act on just a few. The per-tab select-all
                 below covers the real need without that blast radius. --}}
            {{-- Goes to the full registration form rather than the compact
                 modal: a complete worker profile (personal, contact, address,
                 education, work history, skills) does not fit in a dialog.
                 The modal stays for completing kiosk detections and quick
                 edits, where only pay details change. --}}
            <a href="{{ route('employees.create') }}" class="rm-btn-primary" id="rmAddBtn">
                <i class="fas fa-user-plus"></i> {{ __('Register Employee') }}
            </a>
        </div>
    </div>

    {{-- Which tab opens, decided here rather than after paint. ?tab=pending is
         where saving a worker lands; with nothing asked for, Active leads,
         unless there is nobody active and somebody pending — a fresh system
         should not open on an empty table. --}}
    @php
        $openTab = in_array(request('tab'), ['active', 'pending', 'removed'], true)
            ? request('tab')
            : (($active->count() === 0 && $pending->count() > 0) ? 'pending' : 'active');
    @endphp

    {{-- ── Stat chips (also switch tabs) ───────────────────────────────────── --}}
    <div class="rm-stats">
        <button class="rm-stat rm-stat-active {{ $openTab === 'active' ? 'active' : '' }}" data-tab="active">
            <span class="rm-stat-num">{{ $active->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-user-check"></i> {{ __('Active') }}</span>
        </button>
        <button class="rm-stat rm-stat-pending {{ $openTab === 'pending' ? 'active' : '' }}" data-tab="pending">
            <span class="rm-stat-num">{{ $pending->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-fingerprint"></i> {{ __('Pending from kiosk') }}</span>
        </button>
        <button class="rm-stat rm-stat-removed {{ $openTab === 'removed' ? 'active' : '' }}" data-tab="removed">
            <span class="rm-stat-num">{{ $removed->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-trash-can-arrow-up"></i> {{ __('Removed') }}</span>
        </button>
    </div>

    {{-- ── Tabs ────────────────────────────────────────────────────────────── --}}
    <div class="rm-tabs">
        <button class="rm-tab {{ $openTab === 'active' ? 'active' : '' }}" data-tab="active">{{ __('Active') }} <span class="rm-tab-count">{{ $active->count() }}</span></button>
        <button class="rm-tab {{ $openTab === 'pending' ? 'active' : '' }}" data-tab="pending">{{ __('Pending') }} <span class="rm-tab-count">{{ $pending->count() }}</span></button>
        <button class="rm-tab {{ $openTab === 'removed' ? 'active' : '' }}" data-tab="removed">{{ __('Removed') }} <span class="rm-tab-count">{{ $removed->count() }}</span></button>
    </div>

    {{-- ═══ ACTIVE ═════════════════════════════════════════════════════════ --}}
    {{-- Active leads the page: the day-to-day job here is looking up a worker
         who is already on the payroll. Pending is the exception queue, and the
         stat chip plus the sidebar badge already announce it when it is not
         empty — so it sits second rather than in front of the common case. --}}
    <div class="rm-pane {{ $openTab === 'active' ? 'active' : '' }}" data-pane="active">
        <div class="rm-card">
            {{-- Bulk removal is destructive, so it is something you opt into.
                 Until "Select" is pressed the checkbox column stays hidden —
                 a page used mostly for looking a worker up should not open
                 with an empty tickbox sitting in front of every row. --}}
            <div class="rm-tools">
                <span class="rm-tools-spacer"></span>
                <button type="button" class="rm-btn-ghost js-select-toggle">
                    <i class="fas fa-list-check"></i> <span class="js-select-label">{{ __('Select') }}</span>
                </button>
            </div>
            <div class="rm-bulk" data-bulk="active" hidden>
                <span class="rm-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rm-bulk-plain js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rm-bulk-spacer"></span>
                <button type="button" class="rm-bulk-danger js-bulk-remove"><i class="fas fa-trash"></i> {{ __('Remove selected') }}</button>
            </div>
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr>
                            <th class="rm-check-col"><input type="checkbox" class="rm-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Employee') }}</th><th>{{ __('Site') }}</th><th>{{ __('Labor Type') }}</th>
                            <th class="text-center">{{ __('Rate / hr') }}</th><th>{{ __('Fingerprint') }}</th>
                            <th class="text-center">{{ __('Logs') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($active as $e)
                        <tr>
                            <td class="rm-check-col"><input type="checkbox" class="rm-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}"></td>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rm-rate">₱{{ number_format($e->rate_per_hour, 2) }}</td>
                            <td>@include('employees._fp', ['e' => $e])</td>
                            <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
                            <td class="rm-actions">
                                {{-- Edit opens the full Register Employee form,
                                     not the quick modal. Two screens for the same
                                     job meant a worker could be corrected in a
                                     five-field dialog that never showed the
                                     twenty other fields on their record. The
                                     pending rows above already link here. --}}
                                <a href="{{ route('employees.edit', $e->id) }}" class="rm-btn-ghost">
                                    <i class="fas fa-pen"></i> {{ __('Edit') }}
                                </a>
                                @include('employees._menu', ['e' => $e, 'context' => 'active'])
                            </td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'users', 'title' => 'No active employees', 'sub' => 'Complete a pending detection, or use Register Employee to get started.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ PENDING ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'pending' ? 'active' : '' }}" data-pane="pending">
        <div class="rm-card">
            {{-- The text lives in one <span>: .rm-card-note is a flex row, so
                 loose text nodes each become their own flex item and the
                 sentence breaks into columns. --}}
            <div class="rm-card-note">
                <i class="fas fa-circle-info"></i>
                <span>{{ __('Please scan your fingerprint on the kiosk.') }}</span>
            </div>
            <div class="rm-tools">
                <span class="rm-tools-spacer"></span>
                <button type="button" class="rm-btn-ghost js-select-toggle">
                    <i class="fas fa-list-check"></i> <span class="js-select-label">{{ __('Select') }}</span>
                </button>
            </div>
            <div class="rm-bulk" data-bulk="pending" hidden>
                <span class="rm-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rm-bulk-plain js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rm-bulk-spacer"></span>
                <button type="button" class="rm-bulk-danger js-bulk-remove"><i class="fas fa-xmark"></i> {{ __('Cancel selected') }}</button>
            </div>
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr>
                            <th class="rm-check-col"><input type="checkbox" class="rm-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Worker') }}</th><th>{{ __('Fingerprint') }}</th><th>{{ __('Site') }}</th>
                            <th>{{ __('First seen') }}</th><th class="text-center">{{ __('Logs') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="rmPendingBody">
                        @include('employees._rows_pending', ['pending' => $pending])
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ REMOVED ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'removed' ? 'active' : '' }}" data-pane="removed">
        <div class="rm-card">
            <div class="rm-card-note">
                <i class="fas fa-circle-info"></i>
                <span>{{ __('Removed records are hidden everywhere but never lost. Restore them, or permanently delete as a last resort.') }}</span>
            </div>
            <div class="rm-tools">
                <span class="rm-tools-spacer"></span>
                <button type="button" class="rm-btn-ghost js-select-toggle">
                    <i class="fas fa-list-check"></i> <span class="js-select-label">{{ __('Select') }}</span>
                </button>
            </div>
            <div class="rm-bulk" data-bulk="removed" hidden>
                <span class="rm-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rm-bulk-plain js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rm-bulk-spacer"></span>
                <button type="button" class="rm-bulk-plain js-bulk-restore"><i class="fas fa-rotate-left"></i> {{ __('Restore selected') }}</button>
                <button type="button" class="rm-bulk-danger js-bulk-purge"><i class="fas fa-trash"></i> {{ __('Delete permanently') }}</button>
            </div>
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr><th class="rm-check-col"><input type="checkbox" class="rm-check-all" aria-label="{{ __('Select all') }}"></th><th>{{ __('Employee') }}</th><th>{{ __('Site') }}</th><th>{{ __('Labor Type') }}</th><th>{{ __('Removed') }}</th><th class="text-center">{{ __('Logs') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($removed as $e)
                        <tr>
                            <td class="rm-check-col"><input type="checkbox" class="rm-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}"></td>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rm-muted">{{ $e->deleted_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
                            <td class="rm-actions">@include('employees._menu', ['e' => $e, 'context' => 'removed'])</td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'trash-can-arrow-up', 'title' => 'Nothing removed', 'sub' => 'Removed records can be restored from here.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════ EMPLOYEE FORM MODAL ═══════════════════════
     One modal serves four jobs — Add, Confirm, Complete and Edit — with the
     title, the sub-line and the submit label swapped by openModal(). It now
     speaks the same language as the Register Employee page: the .ep-* chrome
     from employees/_profile_styles.blade.php, Bootstrap form controls the
     design tokens already theme for both modes, and the app's own brand rather
     than the electric blue this modal alone still used.

     Every id, name and value is unchanged — the JS below and the controllers
     behind it read exactly what they read before. --}}
<div class="modal fade" id="empFormModal" tabindex="-1" aria-hidden="true" aria-labelledby="empFormTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form id="empForm" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_method" id="empFormMethod" value="POST">
        <input type="hidden" name="_form_mode" id="empFormModeField" value="">
        <input type="hidden" name="_form_id" id="empFormIdField" value="">

        <div class="emp-head">
            <span class="emp-head-icon" aria-hidden="true"><i class="fas fa-helmet-safety"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="empFormTitle">{{ __('Complete Registration') }}</h6>
                <p class="emp-head-sub" id="empFormSub">{{ __('Set this worker\'s details to activate them.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="modal-body emp-body">

            {{-- The page-level alert sits behind the modal, so when validation
                 sent the admin back here the form reopened saying nothing at
                 all about what was wrong. Same errors, shown where they can be
                 read and beside the field that raised them. --}}
            @if($errors->any())
                <div class="emp-alert" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>{{ __('Please fix the following:') }}</strong>
                        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Worker') }}</h3>
                        <p class="ep-section-sub">{{ __('Who this record is for.') }}</p>
                    </div>
                </header>
                {{-- Three fields, the same three Register Employee asks for.
                     The single Full Name box that used to be here wrote the
                     `name` column straight, leaving first/middle/last as
                     whatever they were — so a correction made here and the
                     same correction made on the full form disagreed. The
                     controller composes `name` from these, so both routes now
                     end at the same value.

                     Middle Name carries no asterisk: the full form requires it
                     because it posts profile_form, and this modal does not. --}}
                <div class="emp-grid emp-grid-3">
                    <div class="emp-field">
                        <label class="ep-label" for="empFirst">{{ __('First Name') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <input type="text" name="first_name" id="empFirst"
                               class="form-control @error('first_name') is-invalid @enderror"
                               placeholder="{{ __('Juan') }}"
                               autocomplete="off" required aria-required="true">
                        @error('first_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="empMiddle">{{ __('Middle Name') }}</label>
                        <input type="text" name="middle_name" id="empMiddle"
                               class="form-control @error('middle_name') is-invalid @enderror"
                               placeholder="{{ __('Santos') }}" autocomplete="off">
                        @error('middle_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="empLast">{{ __('Last Name') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <input type="text" name="last_name" id="empLast"
                               class="form-control @error('last_name') is-invalid @enderror"
                               placeholder="{{ __('Dela Cruz') }}"
                               autocomplete="off" required aria-required="true">
                        @error('last_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                @error('name')
                    <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                @else
                    <span class="ep-hint" id="empNameHint">{{ __('As it should read on the payslip.') }}</span>
                @enderror
            </section>

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-helmet-safety"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                        <p class="ep-section-sub">{{ __('What the worker is paid and where they are assigned.') }}</p>
                    </div>
                </header>
                <div class="emp-grid">
                    <div class="emp-field">
                        <label class="ep-label" for="empLabor">{{ __('Labor Type') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <select name="labor_type_id" id="empLabor"
                                class="form-select @error('labor_type_id') is-invalid @enderror"
                                required aria-required="true">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($laborTypes as $lt)
                                <option value="{{ $lt->id }}" data-daily="{{ $lt->daily_rate }}">{{ $lt->name }}</option>
                            @endforeach
                        </select>
                        @error('labor_type_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empRateView">{{ __('Rate / hour') }}</label>
                        {{-- Filled from the labor type, never typed. It reads as a
                             locked field, the same way Register Employee shows a
                             value the form works out for you. --}}
                        <div class="emp-rate" id="empRateBox" aria-live="polite">
                            <span class="emp-rate-cur" aria-hidden="true">₱</span>
                            <span id="empRateView" class="emp-rate-val" tabindex="-1">—</span>
                            <i class="fas fa-lock emp-rate-lock" aria-hidden="true" title="{{ __('Set by the labor type') }}"></i>
                            <input type="hidden" name="rate_per_hour" id="empRate">
                        </div>
                        <span class="ep-hint" id="empRateHint">{{ __('Auto from labor type') }}</span>
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empSite">{{ __('Site') }}</label>
                        <select name="site_id" id="empSite" class="form-select @error('site_id') is-invalid @enderror">
                            <option value="">{{ __('— Unassigned —') }}</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('site_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empShift">{{ __('Shift') }}</label>
                        <select name="shift_id" id="empShift" class="form-select @error('shift_id') is-invalid @enderror"
                                aria-describedby="empShiftHint">
                            @foreach($shifts as $sh)
                                <option value="{{ $sh->id }}" @selected(! $sh->crosses_midnight)>
                                    {{ $sh->name }} — {{ \Carbon\Carbon::parse($sh->starts_at)->format('g:i A') }}
                                </option>
                            @endforeach
                        </select>
                        @error('shift_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @else
                            <span class="ep-hint" id="empShiftHint">{{ __('Movable later from the employee list.') }}</span>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Kiosk') }}</h3>
                        <p class="ep-section-sub">{{ __('The slot their finger is stored in on the kiosk.') }}</p>
                    </div>
                </header>

                <div class="emp-field">
                    <label class="ep-label" for="empFp">
                        {{ __('Fingerprint ID') }}
                        <span class="ep-optional">{{ __('(from the kiosk)') }}</span>
                    </label>
                    <div class="emp-fp">
                        <span class="emp-fp-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                        <input type="text" name="fingerprint_id" id="empFp"
                               class="form-control emp-fp-input ep-mono @error('fingerprint_id') is-invalid @enderror"
                               placeholder="{{ __('Not enrolled yet') }}" autocomplete="off"
                               inputmode="numeric" aria-describedby="empFpHint">
                    </div>
                    @error('fingerprint_id')
                        <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                    @else
                        <span class="ep-hint" id="empFpHint">{{ __('The slot this worker\'s finger is stored in on the kiosk. Filled in by the scan — change it only if the kiosk was re-enrolled.') }}</span>
                    @enderror
                </div>

                {{-- No photo field — see the note in employees/create.blade.php.
                     The section keeps its name because the fingerprint slot
                     above is still the other half of it. --}}
            </section>
        </div>

        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req" aria-hidden="true">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save" id="empFormSubmit">
                <i class="fas fa-check" aria-hidden="true"></i> <span>{{ __('Save & Activate') }}</span>
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- The Register Employee page's own chrome — .ep-label, .ep-hint, .ep-req,
     .ep-optional, .ep-mono. Included rather than copied, so the modal and the
{{-- In the head, not the body. A stylesheet the parser only reaches near
     the end of the page paints everything above it unstyled first. --}}
@push('styles')
     full form cannot drift apart. --}}
@include('employees._profile_styles')
@include('employees._modal_styles')

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
.rm-page { max-width: none; width: 100%; margin: 0; }

.rm-alert { display:flex; gap:10px; align-items:flex-start; padding:12px 16px; border-radius:10px; font-size:13.5px; margin-bottom:18px; border-left:4px solid transparent; }
.rm-alert-err { background:#fef2f2; color:#991b1b; border-left-color:#dc2626; }

.rm-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.rm-title { font-size:1.5rem; font-weight:700; color:#0f172a; margin:0 0 5px; }
.rm-sub { font-size:.875rem; color:#64748b; margin:0; max-width:640px; }

.rm-btn-primary { height:42px; padding:0 20px; font-size:14px; font-weight:700; color:#fff; border:none; border-radius:9px; cursor:pointer;
    background:var(--brand,#1769e0); display:inline-flex; align-items:center; gap:8px; box-shadow:none; transition:transform .1s, opacity .15s; white-space:nowrap; }
.rm-btn-primary:hover { opacity:.93; transform:translateY(-1px); }
/* Register Employee is an <a>, so keep it looking like the button it replaced. */
a.rm-btn-primary, a.rm-btn-primary:hover, a.rm-btn-primary:focus { text-decoration:none; color:#fff; }
/* Edit is a link on both the active and the pending rows, and a link that
   looks like a button should not carry an underline. */
a.rm-btn-ghost, a.rm-btn-ghost:hover, a.rm-btn-ghost:focus { text-decoration:none; }

.rm-header-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.rm-btn-danger { height:42px; padding:0 18px; font-size:14px; font-weight:700; color:#b91c1c; border:1px solid #fecaca; border-radius:9px;
    cursor:pointer; background:#fef2f2; display:inline-flex; align-items:center; gap:8px; transition:background .15s, transform .1s; white-space:nowrap; }
.rm-btn-danger:hover { background:#fee2e2; transform:translateY(-1px); }
.rm-btn-danger:disabled { opacity:.6; cursor:not-allowed; transform:none; }

/* Named at the kiosk but no rate could be resolved — the position they picked
   is not one of the web's labor types. */
.rm-needs-rate { display:inline-flex; align-items:center; gap:5px; margin-top:3px; margin-left:46px;
    font-size:11px; font-weight:600; color:#b45309; background:#fffbeb; border:1px solid #fde68a;
    border-radius:7px; padding:1px 7px; white-space:nowrap; }
.rm-needs-rate i { font-size:9.5px; }

/* Registered on the web, waiting for the kiosk to read their finger. */
.rm-awaiting-fp { display:inline-flex; align-items:center; gap:5px; margin-top:3px; margin-left:46px;
    font-size:11px; font-weight:600; white-space:nowrap; border-radius:7px; padding:1px 7px;
    color:var(--brand,#1e5c9b); background:var(--brand-subtle,#eff6ff); border:1px solid var(--border,#bfdbfe); }
.rm-awaiting-fp i { font-size:9.5px; }

/* Stat chips */
.rm-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
@media(max-width:720px){ .rm-stats{ grid-template-columns:repeat(2,1fr); } }
.rm-stat { text-align:left; background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:16px 18px; cursor:pointer;
    display:flex; flex-direction:column; gap:6px; transition:border-color .15s, box-shadow .15s, transform .1s; }
.rm-stat:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(15,23,42,.07); }
.rm-stat.active { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.08); }
.rm-stat-num { font-size:1.7rem; font-weight:800; color:#0f172a; line-height:1; }
.rm-stat-lbl { font-size:12.5px; font-weight:600; color:#64748b; display:flex; align-items:center; gap:6px; }
.rm-stat-pending  .rm-stat-num { color:#b45309; }
.rm-stat-active   .rm-stat-num { color:#15803d; }
.rm-stat-archived .rm-stat-num { color:#7c3aed; }
.rm-stat-removed  .rm-stat-num { color:#dc2626; }

/* Tabs */
.rm-tabs { display:flex; gap:6px; margin-bottom:18px; overflow-x:auto; padding:2px 2px 4px; }
.rm-tab { background:none; padding:10px 14px; font-size:14px; font-weight:600; color:#64748b; cursor:pointer;
    white-space:nowrap; transition:color .15s, background .15s, border-color .15s; }
.rm-tab:hover { color:#1e293b; }
.rm-tab-count { font-size:11px; font-weight:700; background:#f1f5f9; color:#475569; border-radius:10px; padding:1px 7px; margin-left:3px; }
.rm-tab.active .rm-tab-count { background:#eff6ff; color:#2563eb; }

/* ── Bulk selection ─────────────────────────────────────────────────────── */
/* The checkbox column only exists while the pane is in selection mode. It is
   hidden on both the <th> and the <td>, so the column collapses entirely
   rather than leaving an empty gutter. */
.rm-check-col { display:none; width:38px; text-align:center; padding-left:14px !important; padding-right:0 !important; }
.rm-pane.selecting .rm-check-col { display:table-cell; }
.rm-check, .rm-check-all {
    width:16px; height:16px; cursor:pointer; accent-color:var(--brand,#1e5c9b); vertical-align:middle;
}
.rm-check-all:disabled { cursor:not-allowed; opacity:.4; }

/* Toolbar strip that carries the Select toggle. */
.rm-tools { display:flex; align-items:center; gap:10px; padding:11px 16px; border-bottom:1px solid #eef2f7; }
.rm-tools-spacer { flex:1 1 auto; }
/* Pressed state: the button stays visible as "Done" while selecting, so the
   way out of selection mode is the same control that got you in. */
.js-select-toggle.is-on { background:#1e5c9b; border-color:#1e5c9b; color:#fff; }
.js-select-toggle.is-on:hover { background:#17497c; color:#fff; }
.js-select-toggle:disabled { opacity:.5; cursor:not-allowed; }

.rm-bulk {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    margin:12px 16px; padding:10px 14px; border-radius:10px;
    background:rgba(30,92,155,0.08); border:1px solid rgba(30,92,155,0.28);
}
.rm-bulk[hidden] { display:none; }
.rm-bulk-count { font-size:13px; font-weight:600; }
.rm-bulk-count strong { font-size:15px; }
.rm-bulk-spacer { flex:1 1 auto; }
.rm-bulk-plain, .rm-bulk-danger {
    border-radius:8px; padding:7px 13px; font-size:12.5px; font-weight:600;
    cursor:pointer; display:inline-flex; align-items:center; gap:6px;
    transition:filter .15s, opacity .15s;
}
.rm-bulk-plain  { background:transparent; border:1px solid rgba(148,163,184,.5); color:inherit; }
.rm-bulk-danger { background:#dc2626; border:1px solid #dc2626; color:#fff; }
.rm-bulk-plain:hover, .rm-bulk-danger:hover { filter:brightness(1.08); }
.rm-bulk-plain:disabled, .rm-bulk-danger:disabled { opacity:.55; cursor:not-allowed; }

/* Contractual tag. Recorded only — payroll still computes the same way. */
.rm-badge-contract {
    background: rgba(232,163,61,0.14); color: #b26f00;
    border: 1px solid rgba(232,163,61,0.55); margin-left: 5px;
}

.rm-pane { display:none; }
.rm-pane.active { display:block; }

.rm-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; }
.rm-card-note { display:flex; gap:9px; align-items:flex-start; padding:13px 18px; background:#f8fafc; border-bottom:1px solid #eef2f7; font-size:12.5px; color:#475569; }
/* Keep the copy as ONE flex item — otherwise each text node and <strong>
   becomes its own item and the sentence lays out as columns. */
.rm-card-note > span { flex:1; min-width:0; line-height:1.65; }
.rm-card-note i { color:#3b82f6; margin-top:3px; flex:none; }
.rm-card-note strong { color:#334155; font-weight:700; }

.rm-table { width:100%; border-collapse:collapse; }
.rm-table thead th { background:#f8fafc; padding:11px 16px; font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.rm-table tbody td { padding:13px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle; font-size:14px; }
.rm-table tbody tr:last-child td { border-bottom:none; }
.rm-table tbody tr:hover td { background:#f8fafc; }

.rm-person { display:flex; align-items:center; gap:12px; }
.rm-avatar { width:42px; height:42px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
    font-size:15px; font-weight:700; color:#fff; background:linear-gradient(135deg,#3b82f6,#3b82f6); overflow:hidden; border:2px solid #e0e7ef; }
.rm-avatar img { width:100%; height:100%; object-fit:cover; }
.rm-person-info { display:flex; flex-direction:column; gap:3px; min-width:0; }
.rm-person-name { font-size:14px; font-weight:600; color:#0f172a; }
.rm-person-name.muted { color:#b45309; font-style:italic; }
.rm-id { font-size:11px; font-weight:600; font-family:monospace; color:#64748b; background:#f1f5f9; border:1px solid #e2e8f0; padding:1px 6px; border-radius:4px; width:fit-content; }

.rm-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:4px 9px; border-radius:20px; white-space:nowrap; }
.rm-badge-site  { color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; }
.rm-badge-labor { color:#fff; background:linear-gradient(135deg,#3b82f6,#3b82f6); }
.rm-badge-fp    { color:#fff; background:#059669; font-family:monospace; }
.rm-badge i { font-size:10px; }
.rm-dash { color:#94a3b8; font-size:13px; }
.rm-muted { color:#64748b; font-size:13px; white-space:nowrap; }
.rm-rate { text-align:center; font-weight:700; color:#374151; }
.rm-pill { display:inline-block; min-width:26px; font-size:12px; font-weight:700; color:#475569; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:20px; padding:2px 8px; }

.rm-actions { text-align:right; white-space:nowrap; }
.rm-actions > * { vertical-align:middle; }
.rm-actions > * + * { margin-left:6px; }
.rm-btn-complete { height:34px; padding:0 14px; font-size:13px; font-weight:700; color:#fff; border:none; border-radius:8px; cursor:pointer;
    background:#3b82f6; display:inline-flex; align-items:center; gap:6px; transition:opacity .15s; }
.rm-btn-complete:hover { opacity:.9; }
.rm-btn-ghost { height:34px; padding:0 13px; font-size:13px; font-weight:600; color:#475569; background:#f1f5f9; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s; }
.rm-btn-ghost:hover { background:#e2e8f0; color:#1e293b; }
.rm-btn-danger-ghost { color:#dc2626; }
.rm-btn-accept { height:34px; padding:0 14px; font-size:13px; font-weight:700; color:#fff; border:none; border-radius:8px; cursor:pointer;
    background:linear-gradient(135deg,#15803d,#22c55e); display:inline-flex; align-items:center; gap:6px; transition:opacity .15s; }
.rm-btn-accept:hover { opacity:.9; }
.rm-btn-reject { height:34px; padding:0 13px; font-size:13px; font-weight:700; color:#dc2626; background:#fef2f2; border:1.5px solid #fecaca; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s; }
.rm-btn-reject:hover { background:#fee2e2; }

/* kebab menu */
.rm-menu-wrap { position:relative; display:inline-block; }
.rm-menu-btn { width:34px; height:34px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .12s; }
.rm-menu-btn:hover, .rm-menu-btn.active { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
/* Fixed, not absolute: .rm-card and the table's scroll wrapper both clip what
   escapes them, so the menu opened inside the row and was never seen. Placed
   by the script below. */
.rm-menu { display:none; position:fixed; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 28px rgba(0,0,0,.1); z-index:1080; min-width:172px; overflow:hidden; }
.rm-menu.open { display:block; }
.rm-menu-item { display:flex; align-items:center; gap:9px; padding:10px 14px; font-size:13px; font-weight:500; color:#374151; background:none; border:none; width:100%; text-align:left; cursor:pointer; text-decoration:none; transition:background .1s; }
.rm-menu-item:hover { background:#f8fafc; }
.rm-menu-item i { width:14px; text-align:center; font-size:12px; }
.rm-menu-item.danger { color:#dc2626; }
.rm-menu-item.danger:hover { background:#fef2f2; }
.rm-menu-item.ok { color:#15803d; }
.rm-menu-item.ok:hover { background:#f0fdf4; }

/* empty */
.rm-empty td { padding:0 !important; }
.rm-empty-inner { display:flex; flex-direction:column; align-items:center; padding:52px 0; text-align:center; }
.rm-empty-icon { width:60px; height:60px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:22px; color:#94a3b8; margin-bottom:14px; }
.rm-empty-title { font-size:15px; font-weight:600; color:#374151; margin:0 0 5px; }
.rm-empty-sub { font-size:13px; color:#94a3b8; margin:0; max-width:380px; }

/* modal */

/* ── Dark mode ───────────────────────────────────────────────────────────── */
[data-bs-theme="dark"] .rm-title { color:#e8edf5; }
[data-bs-theme="dark"] .rm-sub { color:#94a3b8; }
[data-bs-theme="dark"] .rm-stat { background:#151d2e; border-color:#283449; }
[data-bs-theme="dark"] .rm-stat-num { color:#e8edf5; }
[data-bs-theme="dark"] .rm-stat.active { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .rm-tab { color:#94a3b8; }
[data-bs-theme="dark"] .rm-tab-count { background:#1c2740; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-btn-reject { background:#2a1416; border-color:#5b2426; color:#f87171; }
[data-bs-theme="dark"] .rm-btn-reject:hover { background:#3a1a1d; }
[data-bs-theme="dark"] .rm-card { background:#151d2e; border-color:#283449; }
[data-bs-theme="dark"] .rm-card-note { background:#0f1a2e; border-bottom-color:#1c2740; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-card-note strong { color:#dbe4f0; }
[data-bs-theme="dark"] .rm-table thead th { background:#1c2740; color:#6b7d96; border-bottom-color:#283449; }
[data-bs-theme="dark"] .rm-table tbody td { border-bottom-color:#1a2336; }
[data-bs-theme="dark"] .rm-table tbody tr:hover td { background:#1a2336; }
[data-bs-theme="dark"] .rm-person-name { color:#e8edf5; }
[data-bs-theme="dark"] .rm-id { background:#1c2740; border-color:#283449; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-muted { color:#9fb0c7; }
[data-bs-theme="dark"] .rm-rate { color:#cdd7e5; }
[data-bs-theme="dark"] .rm-pill { background:#1c2740; border-color:#283449; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-badge-site { color:#86efac; background:#052e16; border-color:#166534; }
[data-bs-theme="dark"] .rm-btn-ghost { background:#1c2740; border-color:#283449; color:#94a3b8; }
[data-bs-theme="dark"] .rm-btn-ghost:hover { background:#283449; color:#e2e8f0; }
/* Must come after the .rm-btn-ghost dark rule above — same specificity, so
   whichever is written last wins, and the pressed state has to. */
[data-bs-theme="dark"] .rm-tools { border-bottom-color:#1c2740; }
[data-bs-theme="dark"] .js-select-toggle.is-on { background:#2563eb; border-color:#2563eb; color:#fff; }
[data-bs-theme="dark"] .js-select-toggle.is-on:hover { background:#1d4ed8; color:#fff; }
[data-bs-theme="dark"] .rm-menu-btn { background:#1c2740; border-color:#283449; color:#94a3b8; }
[data-bs-theme="dark"] .rm-menu-btn:hover, [data-bs-theme="dark"] .rm-menu-btn.active { background:#172554; border-color:#1d4ed8; color:#93c5fd; }
[data-bs-theme="dark"] .rm-menu { background:#1c2740; border-color:#283449; box-shadow:0 8px 24px rgba(0,0,0,.4); }
[data-bs-theme="dark"] .rm-menu-item { color:#cdd7e5; }
[data-bs-theme="dark"] .rm-menu-item:hover { background:#283449; }
[data-bs-theme="dark"] .rm-empty-icon { background:#1c2740; color:#475569; }
[data-bs-theme="dark"] .rm-empty-title { color:#9fb0c7; }

</style>
@endpush

{{-- ── Script ──────────────────────────────────────────────────────────────── --}}
<script>
(function () {
    // ── Tabs + stat chips ────────────────────────────────────────────────────
    function switchTab(name) {
        document.querySelectorAll('.rm-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
        document.querySelectorAll('.rm-stat').forEach(s => s.classList.toggle('active', s.dataset.tab === name));
        document.querySelectorAll('.rm-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === name));
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
    }
    document.querySelectorAll('.rm-tab, .rm-stat').forEach(el => el.addEventListener('click', () => switchTab(el.dataset.tab)));
    // The open tab is already decided in the markup — see $openTab above — so
    // there is nothing to correct here on load. The hash is still honoured for
    // links saved before ?tab= existed; without JavaScript those now open on
    // Active instead of moving, which is the same page either way.
    const hash = (location.hash || '').replace('#', '');
    if (['pending','active','removed'].includes(hash)) switchTab(hash);

    // ── Kebab menus ──────────────────────────────────────────────────────────
    // Fixed, so placed by hand: under the button, right edges aligned, flipped
    // above when there is no room below.
    function placeRmMenu(btn, menu) {
        const r = btn.getBoundingClientRect();
        const below = window.innerHeight - r.bottom;

        menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        menu.style.top  = (below < menu.offsetHeight + 12)
            ? (r.top - menu.offsetHeight - 6) + 'px'
            : (r.bottom + 6) + 'px';
    }

    function closeRmMenus() {
        document.querySelectorAll('.rm-menu.open').forEach(m => {
            m.classList.remove('open');
            m.previousElementSibling?.classList.remove('active');
        });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.rm-menu-btn');
        document.querySelectorAll('.rm-menu.open').forEach(m => {
            if (!btn || m !== btn.nextElementSibling) {
                m.classList.remove('open');
                m.previousElementSibling?.classList.remove('active');
            }
        });
        if (btn) {
            e.stopPropagation();
            const menu = btn.nextElementSibling, opening = !menu.classList.contains('open');
            menu.classList.toggle('open', opening);
            btn.classList.toggle('active', opening);

            // Measured after it is shown, or width and height are both zero.
            if (opening) placeRmMenu(btn, menu);
        }
    });

    // A fixed menu does not travel with its row, so it closes rather than
    // drifting away from the button it belongs to.
    window.addEventListener('scroll', closeRmMenus, true);
    window.addEventListener('resize', closeRmMenus);

    // ── Employee form modal (add / complete / edit) ──────────────────────────
    const storeUrl = "{{ route('employees.store') }}";
    const baseUrl  = "{{ url('employees') }}";
    const nextFp   = "{{ $nextFingerprintId }}";

    const modalEl   = document.getElementById('empFormModal');
    const form      = document.getElementById('empForm');
    const methodEl  = document.getElementById('empFormMethod');
    const titleEl   = document.getElementById('empFormTitle');
    const subEl     = document.getElementById('empFormSub');
    const submitLbl = document.querySelector('#empFormSubmit span');
    const firstEl   = document.getElementById('empFirst');
    const middleEl  = document.getElementById('empMiddle');
    const lastEl    = document.getElementById('empLast');
    const laborEl   = document.getElementById('empLabor');
    const rateEl    = document.getElementById('empRate');
    const rateView  = document.getElementById('empRateView');
    const siteEl    = document.getElementById('empSite');
    const fpEl      = document.getElementById('empFp');

    let bsModal = null;
    function getModal() {
        if (!bsModal && window.bootstrap) bsModal = new bootstrap.Modal(modalEl);
        return bsModal;
    }

    function updateRate() {
        const opt = laborEl.options[laborEl.selectedIndex];
        if (opt && opt.value) {
            const hourly = (parseFloat(opt.dataset.daily || 0) / 8);
            rateView.textContent = hourly.toFixed(2);
            rateEl.value = hourly.toFixed(2);
        } else {
            rateView.textContent = '—'; rateEl.value = '';
        }
    }
    laborEl.addEventListener('change', updateRate);

    // The photo picker was here. It is gone with the field itself — see the
    // note in employees/create.blade.php. Leaving the handlers behind would
    // have been worse than useless: binding a listener to an element that no
    // longer exists throws, and the throw would take the whole modal script
    // down with it, exactly as a missing Bootstrap once did to the other
    // picker.

    const modeField = document.getElementById('empFormModeField');
    const idField   = document.getElementById('empFormIdField');

    function openModal(mode, d) {
        modeField.value = mode;
        idField.value   = d.id || '';
        // The row carries the parts already split by Employee::splitName(), so
        // a worker created by the kiosk with only a bare name still opens with
        // the three boxes filled in rather than one of them holding all of it.
        firstEl.value  = d.first  || '';
        middleEl.value = d.middle || '';
        lastEl.value   = d.last   || '';
        siteEl.value = d.site || '';
        fpEl.value   = d.fp || (mode === 'add' ? nextFp : '');
        laborEl.value = d.labor || '';
        updateRate();

        if (mode === 'add') {
            form.action = storeUrl; methodEl.value = 'POST';
            titleEl.textContent = 'Add Employee Manually';
            subEl.textContent = 'Register a worker without a kiosk scan.';
            submitLbl.textContent = 'Register';
        } else if (mode === 'confirm') {
            form.action = `${baseUrl}/${d.id}/complete`; methodEl.value = 'POST';
            titleEl.textContent = 'Confirm the information';
            subEl.textContent = 'Review the details submitted from the kiosk — fix any typo and add a photo if needed, then activate.';
            submitLbl.textContent = 'Confirm & Activate';
        } else if (mode === 'complete') {
            form.action = `${baseUrl}/${d.id}/complete`; methodEl.value = 'POST';
            titleEl.textContent = 'Complete Registration';
            subEl.textContent = 'Set this kiosk-detected worker’s details to activate them.';
            submitLbl.textContent = 'Save & Activate';
        } else { // edit
            form.action = `${baseUrl}/${d.id}`; methodEl.value = 'PUT';
            titleEl.textContent = 'Edit Employee';
            subEl.textContent = 'Update this employee’s details.';
            submitLbl.textContent = 'Save Changes';
        }
        const m = getModal(); if (m) m.show();
        setTimeout(() => firstEl.focus(), 250);
    }

    // Delegated so pending rows swapped in by live polling stay clickable.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-emp-edit');
        if (!btn) return;
        openModal(btn.dataset.mode, {
            id: btn.dataset.id, labor: btn.dataset.labor,
            first: btn.dataset.first, middle: btn.dataset.middle, last: btn.dataset.last,
            rate: btn.dataset.rate, site: btn.dataset.site, fp: btn.dataset.fp,
        });
    });
    // "Register Employee" is a link to the full form now — no modal to open.

    form.addEventListener('submit', function () {
        const b = document.getElementById('empFormSubmit');
        b.disabled = true; b.querySelector('i').className = 'fas fa-spinner fa-spin';
    });

    // If validation failed server-side, reopen the form so errors aren't lost.
    @if($errors->any() && (old('first_name') || old('name')))
        openModal('{{ old('_form_mode', 'complete') }}', {
            id: '{{ old('_form_id') }}',
            first: @json(old('first_name')), middle: @json(old('middle_name')), last: @json(old('last_name')),
            labor: '{{ old('labor_type_id') }}', site: '{{ old('site_id') }}', fp: '{{ old('fingerprint_id') }}'
        });
    @endif

    // ── Realtime: auto-refresh kiosk-detected (pending) workers ──────────────
    // Polls a lightweight feed every few seconds so new fingerprint scans on
    // the Pi kiosk appear here without the admin having to refresh the page.
    (function () {
        const liveUrl = "{{ route('employees.register.live') }}";
        const body    = document.getElementById('rmPendingBody');
        let lastSig     = @json($liveSignature ?? null);
        let prevPending = {{ $pending->count() }};

        function setCount(sel, val) { const el = document.querySelector(sel); if (el) el.textContent = val; }
        function updateCounts(c) {
            ['pending', 'active', 'removed'].forEach(k => {
                setCount('.rm-stat-' + k + ' .rm-stat-num', c[k]);
                setCount('.rm-tab[data-tab="' + k + '"] .rm-tab-count', c[k]);
            });
            const badge = document.querySelector('.nav-pending-badge');
            if (badge) {
                badge.textContent = c.pending;
                badge.style.display = c.pending > 0 ? '' : 'none';
            }
        }
        // Was its own bottom-centre bar in a hardcoded blue, built with
        // innerHTML. Same name and same call signature; the shared notifier
        // does the drawing, and escapes the message.
        function toast(msg) { Notify.info(msg); }

        async function poll() {
            try {
                const res = await fetch(liveUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();
                if (!d || d.signature === lastSig) return;   // nothing changed
                lastSig = d.signature;
                if (body) body.innerHTML = d.pending_html;
                updateCounts(d.counts);
                if (d.counts.pending > prevPending) {
                    const n = d.counts.pending - prevPending;
                    toast(n + ' new worker' + (n > 1 ? 's' : '') + ' detected from the kiosk');
                }
                prevPending = d.counts.pending;
            } catch (e) { /* offline / transient — try again next tick */ }
        }
        setInterval(poll, 5000);
    })();
})();


// ── Bulk selection ───────────────────────────────────────────────────────────
// Replaces the old "Clear All Fingerprints" sledgehammer: pick exactly the rows
// you mean — or tick the header box for the whole tab — and act on them once.
// Each tab keeps its own selection, and the buttons match what that tab can do.
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const ENDPOINTS = {
        remove:  { url: '{{ route('employees.bulk-delete') }}',       method: 'DELETE' },
        restore: { url: '{{ route('employees.bulk-restore') }}',      method: 'PATCH'  },
        purge:   { url: '{{ route('employees.bulk-force-delete') }}', method: 'DELETE' },
    };

    const paneOf   = el   => el.closest('.rm-pane');
    const boxesIn  = pane => Array.from(pane.querySelectorAll('tbody .rm-check'));
    const pickedIn = pane => boxesIn(pane).filter(b => b.checked);

    function sync(pane) {
        if (!pane) return;
        const boxes     = boxesIn(pane);
        const picked    = pickedIn(pane);
        const all       = pane.querySelector('.rm-check-all');
        const bar       = pane.querySelector('.rm-bulk');
        const toggle    = pane.querySelector('.js-select-toggle');
        const selecting = pane.classList.contains('selecting');

        if (all) {
            all.disabled      = boxes.length === 0;
            all.checked       = boxes.length > 0 && picked.length === boxes.length;
            all.indeterminate = picked.length > 0 && picked.length < boxes.length;
        }
        // Nothing to select on an empty tab, so the toggle has no job there.
        if (toggle) toggle.disabled = boxes.length === 0 && !selecting;
        if (bar) {
            // The bar rides along with selection mode rather than appearing on
            // the first tick: entering the mode should show what can be done
            // with a selection, not hide it until something is already chosen.
            bar.hidden = !selecting;
            const n = bar.querySelector('.rm-bulk-count strong');
            if (n) n.textContent = picked.length;
            bar.querySelectorAll('button').forEach(b => { b.disabled = picked.length === 0; });
        }
    }

    // Entering the mode reveals the checkbox column; leaving it drops whatever
    // was ticked, so a stale selection can never survive out of sight.
    function setSelecting(pane, on) {
        if (!pane) return;
        pane.classList.toggle('selecting', on);
        const toggle = pane.querySelector('.js-select-toggle');
        if (toggle) {
            toggle.classList.toggle('is-on', on);
            const label = toggle.querySelector('.js-select-label');
            if (label) label.textContent = on ? 'Done' : 'Select';
        }
        if (!on) {
            boxesIn(pane).forEach(b => { b.checked = false; });
            const all = pane.querySelector('.rm-check-all');
            if (all) { all.checked = false; all.indeterminate = false; }
        }
        sync(pane);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-select-toggle');
        if (!btn) return;
        const pane = paneOf(btn);
        setSelecting(pane, !pane.classList.contains('selecting'));
    });

    function syncAll() { document.querySelectorAll('.rm-pane').forEach(sync); }

    document.addEventListener('change', function (e) {
        const t = e.target;
        if (t.classList && t.classList.contains('rm-check-all')) {
            const pane = paneOf(t);
            boxesIn(pane).forEach(b => { b.checked = t.checked; });
            sync(pane);
        } else if (t.classList && t.classList.contains('rm-check')) {
            sync(paneOf(t));
        }
    });

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-bulk-clear, .js-bulk-remove, .js-bulk-restore, .js-bulk-purge');
        if (!btn) return;

        const pane = paneOf(btn);

        if (btn.classList.contains('js-bulk-clear')) {
            boxesIn(pane).forEach(b => { b.checked = false; });
            sync(pane);
            return;
        }

        const ids = pickedIn(pane).map(b => b.value);
        if (!ids.length) return;

        const kind = btn.classList.contains('js-bulk-restore') ? 'restore'
                   : btn.classList.contains('js-bulk-purge')   ? 'purge'
                   : 'remove';
        const one  = ids.length === 1;
        const many = ids.length + (one ? ' record' : ' records');

        const ASK = {
            purge:   { title: 'Permanently delete ' + many + '?',
                       message: 'This cannot be undone. Their attendance history and photos go too.',
                       confirmLabel: 'Delete permanently', tone: 'danger' },
            restore: { title: 'Restore ' + many + '?',
                       message: 'They move back to the list they came from.',
                       confirmLabel: 'Restore', tone: 'brand' },
        };
        const ask = ASK[kind] || {
            title: 'Remove ' + many + '?',
            message: 'They move to the Removed tab and can be restored from there.',
            confirmLabel: 'Remove', tone: 'warning',
        };

        if (!await Notify.confirm(ask)) return;

        const { url, method } = ENDPOINTS[kind];
        const label = btn.innerHTML;
        pane.querySelectorAll('.rm-bulk button').forEach(b => { b.disabled = true; });
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working…';

        try {
            const res = await fetch(url, {
                method:  method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ ids: ids }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                const done = data.deleted ?? data.restored ?? ids.length;
                Notify.success(done + (done === 1 ? ' record' : ' records') + ' updated');
                setTimeout(() => location.reload(), 700);
                return;
            }
            Notify.error(data.message || 'Could not complete that action.');
        } catch (err) {
            Notify.error('Network error — please try again.');
        }

        // Hand the buttons back to sync() rather than blanket-enabling them —
        // it is the one place that knows whether anything is still selected.
        btn.innerHTML = label;
        sync(pane);
    });

    // The pending rows are swapped out wholesale by the 5-second live refresh,
    // which fires no change event — without this the count would keep showing a
    // selection whose rows are already gone.
    const pendingBody = document.getElementById('rmPendingBody');
    if (pendingBody) {
        new MutationObserver(() => sync(paneOf(pendingBody))).observe(pendingBody, { childList: true });
    }

    syncAll();
})();
</script>
@endsection
