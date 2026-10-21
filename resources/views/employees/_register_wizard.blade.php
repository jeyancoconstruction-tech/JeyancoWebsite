{{--
    Register employee, as a step-by-step pop-up on the Employees page
    (Michael's mockup, jeyanco-register-wizard.html, 2026-10-12).

    It was a page of its own, every section at once. The fields, their names
    and what saving does are the same as they were there: the form still posts
    to employees.store with profile_form, the labor type still fills the rate
    and the position, and province / city / barangay still come from the PSGC
    lists. What changed is the order they are asked in: Employment type and
    Employment & pay, the two that are required, then Personal, Address,
    Government IDs and Contact, each of which can be skipped, then a review.

    The first step — Regular or Contractual, the date hired, and for a
    contract its length in months — came the same day, from a second pair of
    screenshots. A contractual worker leaves the Active list the day after
    the contract ends; see Employee::endFinishedContracts().

    Edit Employee stays a page and carries the same fields
    (employees/_profile_fields.blade.php). RegisterWizardTest holds the two to
    the same list, so a field added to one is not missing from the other.

    Expects $laborTypes, $sites and $shifts, which the Employees page has.
--}}
@php
    $rwDayShift = $shifts->firstWhere('crosses_midnight', false)?->id ?? $shifts->first()?->id;
@endphp

@push('styles')
<style>
/* ── Register employee: the pop-up ────────────────────────────────────────
   The page behind is blurred rather than only dimmed. Scoped to the body
   class the script sets while this dialog is open, so no other dialog's
   backdrop is touched. */
body.rw-open .modal-backdrop { --bs-backdrop-bg: rgba(8, 14, 28, .42); --bs-backdrop-opacity: 1;
    -webkit-backdrop-filter: blur(7px); backdrop-filter: blur(7px); }
#regWizard .rw-dialog { max-width: min(900px, calc(100% - 32px)); margin-left: auto; margin-right: auto; }
/* Pressing outside asks before closing; it does not also shake the dialog. */
#regWizard.modal-static .modal-dialog { transform: none !important; }
html[data-bs-theme] #regWizard .modal-content.rw {
    display: flex; flex-direction: column; overflow: hidden;
    background: var(--bg-surface) !important; color: var(--text-primary);
    border: 1px solid var(--border) !important; border-radius: 16px !important;
    box-shadow: 0 30px 80px rgba(0, 0, 0, .35) !important;
}
.rw [hidden] { display: none !important; }

/* Head: what this is, and which step of how many. */
.rw-h { flex: none; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 18px 22px 0; }
.rw-h h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--text-primary); }
.rw-h p { margin: 3px 0 0; font-size: 13px; color: var(--text-muted); }
.rw-x { all: unset; box-sizing: border-box; cursor: pointer; width: 28px; height: 28px; border-radius: 7px; display: grid; place-items: center; color: var(--text-muted); }
.rw-x i { font-size: 16px; }
.rw-x:hover { background: var(--bg-subtle); color: var(--text-primary); }
.rw-x:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

/* The steps: a bar, a number and a name each. */
.rw-steps { flex: none; display: flex; gap: 6px; padding: 16px 22px; border-bottom: 1px solid var(--border); overflow-x: auto; scrollbar-width: none; }
.rw-steps::-webkit-scrollbar { display: none; }
.rw-st { all: unset; box-sizing: border-box; flex: 1 1 0; min-width: 96px; display: flex; flex-direction: column; gap: 6px; cursor: pointer;
    font-size: 12px; font-weight: 600; color: var(--text-muted); }
.rw-st:disabled { cursor: not-allowed; }
.rw-bar { height: 4px; border-radius: 4px; background: var(--border); }
.rw-lb { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.rw-n { flex: none; width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; font-size: 10.5px; border: 1.5px solid var(--border-md); }
.rw-n i { font-size: 11px; }
.rw-st.done { color: var(--text-primary); }
.rw-st.done .rw-bar { background: var(--success); }
.rw-st.done .rw-n { background: var(--success); border-color: var(--success); color: #fff; }
.rw-st.on { color: var(--brand); }
.rw-st.on .rw-bar { background: var(--brand); }
.rw-st.on .rw-n { border-color: var(--brand); color: var(--brand); }
.rw-st.err .rw-bar { background: var(--danger); }
.rw-st.err .rw-n { border-color: var(--danger); color: var(--danger); }
.rw-st:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; border-radius: 4px; }

/* The body: one step at a time. */
html[data-bs-theme] #regWizard .rw-b { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 20px 22px !important; background: var(--bg-surface); }
.rw-pane-h { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.rw-ic { flex: none; width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: var(--brand-subtle); color: var(--brand); }
.rw-ic i { font-size: 19px; }
.rw-pane-h b { display: block; font-size: 15.5px; color: var(--text-primary); }
.rw-pane-h small { font-size: 12.5px; color: var(--text-muted); }
.rw-tag { margin-left: auto; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; background: var(--bg-subtle); color: var(--text-muted); }
.rw-tag.req { background: var(--danger-soft); color: var(--danger); }

.rw-g { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.rw-g .full { grid-column: 1 / -1; }
.rw-g3 { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 14px; }
.rw-f { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.rw-f > label, .rw-f > .rw-label { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin: 0; }
.rw-f > label i, .rw-f > .rw-label i { font-style: normal; color: var(--danger); }
.rw .form-control, .rw .form-select { height: 42px; padding: 0 12px; font-size: 14px; min-width: 0; }
.rw .form-select { padding-right: 32px; cursor: pointer; }
.rw-g3 .form-control, .rw-g3 .form-select, .rw-g3 .rw-seg button { height: 40px; }
.rw-hp { font-size: 12px; color: var(--text-muted); }
.rw-em { display: none; font-size: 12px; color: var(--danger); }
.rw-g3 .rw-hp, .rw-g3 .rw-em { font-size: 11.5px; }
.rw-f.bad .form-control, .rw-f.bad .form-select:not(.rw-suffix) { border-color: var(--danger) !important; }
.rw-f.bad .rw-em { display: block; }
.rw-f.bad .rw-hp { display: none; }
.rw-inrow { display: flex; gap: 8px; }
.rw-inrow > :first-child { flex: 1; }
.rw-inrow > .form-select { flex: none; width: 88px; padding-right: 26px; background-position: right 8px center; }
.rw-peso { position: relative; }
.rw-peso > span { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted); pointer-events: none; }
.rw .rw-peso .form-control { padding-left: 28px !important; }
.rw-seg { display: flex; gap: 8px; }
.rw-seg button { all: unset; box-sizing: border-box; flex: 1; min-width: 0; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px;
    border: 1px solid var(--border); border-radius: 9px; font-size: 13.5px; font-weight: 600; cursor: pointer;
    background: var(--bg-subtle); color: var(--text-primary); white-space: nowrap; }
.rw-seg button i { font-size: 16px; }
.rw-seg button small { font-size: 11.5px; font-weight: 500; color: var(--text-muted); }
.rw-seg button.on { border-color: var(--brand); background: var(--brand-subtle); color: var(--brand); box-shadow: inset 0 0 0 1px var(--brand); }
.rw-seg button.on small { color: inherit; }
.rw-seg button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.rw-sub { grid-column: 1 / -1; margin: 6px 0 -4px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted); }
.rw-error { display: flex; gap: 8px; margin: 0 0 14px; padding: 10px 12px; border-radius: 9px; font-size: 12.5px; background: var(--danger-soft); color: var(--danger); }

/* Employment type: two cards to choose between, and the contract under them. */
.rw-types { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.rw-type { position: relative; display: flex; flex-direction: column; gap: 5px; min-height: 148px; margin: 0; padding: 18px; cursor: pointer;
    border: 1px solid var(--border); border-radius: 12px; background: var(--bg-subtle); transition: border-color .15s, background .15s; }
.rw-type input { position: absolute; opacity: 0; pointer-events: none; }
.rw-type-ic { width: 40px; height: 40px; margin-bottom: 9px; border-radius: 10px; display: grid; place-items: center;
    background: var(--bg-surface); border: 1px solid var(--border); color: var(--text-muted); }
.rw-type-ic i { font-size: 19px; }
.rw-type-dot { position: absolute; top: 16px; right: 16px; width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid var(--border-md); }
.rw-type b { font-size: 16px; color: var(--text-primary); }
.rw-type small { max-width: 46ch; font-size: 13px; line-height: 1.45; color: var(--text-muted); }
.rw-type:hover { border-color: var(--border-md); }
.rw-type:has(input:checked) { border-color: var(--brand); box-shadow: inset 0 0 0 1px var(--brand); background: var(--brand-subtle); }
.rw-type:has(input:checked) .rw-type-ic { background: var(--brand); border-color: var(--brand); color: #fff; }
.rw-type:has(input:checked) .rw-type-dot { border-color: var(--brand); background: radial-gradient(circle, var(--brand) 0 4px, transparent 4.5px); }
.rw-type:has(input:focus-visible) { outline: 2px solid var(--brand); outline-offset: 2px; }
.rw-unit { position: relative; }
.rw-unit > span { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 12.5px; color: var(--text-muted); pointer-events: none; }
.rw .rw-unit .form-control { padding-right: 68px !important; }

/* Review: every step as it was answered, each with a way back. */
.rw-rev { display: grid; gap: 12px; }
.rw-rv { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.rw-rv-h { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; font-size: 13.5px; font-weight: 700; background: var(--bg-subtle); color: var(--text-primary); }
.rw-rv-h button { all: unset; cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 12.5px; font-weight: 700; color: var(--brand); }
.rw-rv-h button:focus-visible { outline: 2px solid var(--brand); }
.rw-rv dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; margin: 0; padding: 12px 14px; font-size: 13px; }
.rw-rv dl div { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.rw-rv dt { font-size: 11.5px; font-weight: 500; color: var(--text-muted); }
.rw-rv dd { margin: 0; font-weight: 600; color: var(--text-primary); overflow-wrap: anywhere; }
.rw-rv dd.no { font-weight: 500; color: var(--text-muted); }

/* Done. */
.rw-ok { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 26px 10px; text-align: center; }
.rw-ok-big { width: 60px; height: 60px; border-radius: 50%; display: grid; place-items: center; background: var(--success-soft); color: var(--success); }
.rw-ok-big i { font-size: 30px; }
.rw-ok h4 { margin: 0; font-size: 18px; font-weight: 700; color: var(--text-primary); }
.rw-ok p { margin: 0; max-width: 46ch; font-size: 13.5px; color: var(--text-muted); }

/* Foot: where this step leaves you, and the way on. */
.rw-foot { flex: none; display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 14px 22px;
    border-top: 1px solid var(--border); background: var(--bg-subtle); }
.rw-meta { margin-right: auto; font-size: 12.5px; color: var(--text-muted); }
.rw-skip { all: unset; cursor: pointer; display: inline-flex; align-items: center; height: 40px; padding: 0 10px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--text-muted); }
.rw-skip:hover { color: var(--text-primary); }
.rw-skip:focus-visible { outline: 2px solid var(--brand); }
.rw-foot .emp-btn-save:disabled { opacity: .6; cursor: progress; }
.rw-dis { background: var(--danger-soft); }
.rw-dis .rw-meta { font-weight: 600; color: var(--text-primary); }
.rw-danger { height: 40px; padding: 0 18px; border-radius: var(--radius-sm, 8px); border: 1px solid var(--danger); background: var(--danger); color: #fff;
    font-size: 13.5px; font-weight: 700; cursor: pointer; }
.rw-danger:focus-visible { outline: 2px solid var(--danger); outline-offset: 2px; }

@media (max-width: 860px) { .rw-g3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) {
    .rw-g, .rw-g3, .rw-rv dl, .rw-types { grid-template-columns: minmax(0, 1fr); }
    .rw-type { min-height: 0; padding: 14px; }
    .rw-foot:not(.rw-dis) .rw-meta { display: none; }
    .rw-h, .rw-steps, .rw-foot { padding-left: 16px; padding-right: 16px; }
    html[data-bs-theme] #regWizard .rw-b { padding-left: 16px !important; padding-right: 16px !important; }
    .rw-st { min-width: 44px; }
    .rw-st .rw-t { display: none; }
    .rw-dis { flex-wrap: wrap; }
    .rw-dis .rw-meta { flex: 1 1 100%; }
}
</style>
@endpush

<div class="modal fade" id="regWizard" tabindex="-1" aria-hidden="true" aria-labelledby="rwTitle"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable rw-dialog">
        <div class="modal-content rw">
            <div class="rw-h">
                <div>
                    <h3 id="rwTitle">{{ __('Register employee') }}</h3>
                    <p id="rwSub">{{ __('Step 1 of 7') }} · {{ __('Employment type') }}</p>
                </div>
                <button type="button" class="rw-x" id="rwClose" aria-label="{{ __('Close') }}"><i class="ti ti-x" aria-hidden="true"></i></button>
            </div>

            <nav class="rw-steps" id="rwSteps" aria-label="{{ __('Steps') }}"></nav>

            {{-- novalidate: the steps check themselves, one at a time, and say
                 what is wrong beside the field. The server checks again. --}}
            <form class="modal-body rw-b" id="regWizardForm" action="{{ route('employees.store') }}" method="POST" novalidate autocomplete="off">
                @csrf
                {{-- A full profile form, as Register Employee always was: the
                     position and the site are required of it. --}}
                <input type="hidden" name="profile_form" value="1">

                <p class="rw-error" id="rwError" role="alert" hidden></p>

                {{-- ═══ 1 · Employment type ═══
                     How the worker is hired. A regular worker stays on the
                     Active list until somebody removes them; a contractual one
                     is hired for a number of months and leaves it the day
                     after the contract ends (Employee::endFinishedContracts()).
                     Pay is the same either way. The end date is worked out
                     from the date hired and the months — here for the office
                     to see, and again by the server, which is what is saved. --}}
                <section data-step="1">
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-clipboard-check" aria-hidden="true"></i></span>
                        <div><b>{{ __('Employment type') }}</b><small>{{ __('Choose how this worker is hired.') }}</small></div>
                        <span class="rw-tag req">{{ __('Required') }}</span>
                    </div>

                    <div class="rw-types" role="radiogroup" aria-label="{{ __('Employment type') }}">
                        <label class="rw-type">
                            <input type="radio" name="employment_type" value="{{ \App\Models\Employee::EMPLOYMENT_REGULAR }}" checked>
                            <span class="rw-type-ic"><i class="ti ti-user" aria-hidden="true"></i></span>
                            <span class="rw-type-dot" aria-hidden="true"></span>
                            <b>{{ __('Regular') }}</b>
                            <small>{{ __('Stays on the Active list until removed.') }}</small>
                        </label>
                        <label class="rw-type">
                            <input type="radio" name="employment_type" value="{{ \App\Models\Employee::EMPLOYMENT_CONTRACTUAL }}">
                            <span class="rw-type-ic"><i class="ti ti-calendar-event" aria-hidden="true"></i></span>
                            <span class="rw-type-dot" aria-hidden="true"></span>
                            <b>{{ __('Contractual') }}</b>
                            <small>{{ __('Hired for a set number of months. Leaves the Active list when the contract ends.') }}</small>
                        </label>
                    </div>

                    <div class="rw-g rw-g3 rw-contract">
                        {{-- A worker is nearly always registered on the day they
                             start, so today is the default. now() is Manila time. --}}
                        <div class="rw-f">
                            <label for="date_hired">{{ __('Date hired') }} <i>*</i></label>
                            <input type="date" class="form-control" id="date_hired" name="date_hired" value="{{ now()->toDateString() }}" required>
                            <span class="rw-em" data-say="{{ __('Enter the date hired.') }}">{{ __('Enter the date hired.') }}</span>
                        </div>
                        <div class="rw-f" id="rwContractLength" hidden>
                            <label for="contract_months">{{ __('Contract length') }} <i>*</i></label>
                            <div class="rw-unit"><input type="number" class="form-control" id="contract_months" name="contract_months" min="1" max="{{ \App\Models\Employee::CONTRACT_MONTHS_MAX }}" step="1" inputmode="numeric" placeholder="{{ __('e.g. 6') }}" disabled><span aria-hidden="true">{{ __('months') }}</span></div>
                            <span class="rw-hp">{{ __('1 to :max months.', ['max' => \App\Models\Employee::CONTRACT_MONTHS_MAX]) }}</span>
                            <span class="rw-em" data-say="{{ __('Enter 1 to :max months.', ['max' => \App\Models\Employee::CONTRACT_MONTHS_MAX]) }}">{{ __('Enter 1 to :max months.', ['max' => \App\Models\Employee::CONTRACT_MONTHS_MAX]) }}</span>
                        </div>
                        <div class="rw-f" id="rwContractEnds" hidden>
                            <label for="rwEnds">{{ __('Contract ends') }}</label>
                            <input type="text" class="form-control ep-locked" id="rwEnds" placeholder="{{ __('Set the contract length') }}" readonly tabindex="-1">
                            <span class="rw-hp">{{ __('Leaves the Active list after this date.') }}</span>
                        </div>
                    </div>
                </section>

                {{-- ═══ 2 · Employment & pay ═══ --}}
                <section data-step="2" hidden>
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-briefcase" aria-hidden="true"></i></span>
                        <div><b>{{ __('Employment & pay') }}</b><small>{{ __('Needed to put the worker on the payroll.') }}</small></div>
                        <span class="rw-tag req">{{ __('Required') }}</span>
                    </div>
                    <div class="rw-g rw-g3">
                        {{-- Name, in parts. `name` itself is composed from these
                             on save — it stays what the rest of the app reads. --}}
                        <div class="rw-f">
                            <label for="first_name">{{ __('First name') }} <i>*</i></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="{{ __('Juan') }}" required>
                            <span class="rw-em" data-say="{{ __('Enter the first name.') }}">{{ __('Enter the first name.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="middle_name">{{ __('Middle name') }}</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" placeholder="{{ __('Santos') }}">
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="last_name">{{ __('Last name') }} <i>*</i></label>
                            <div class="rw-inrow">
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="{{ __('Dela Cruz') }}" required>
                                {{-- Jr., Sr., II… kept apart from the surname and added to the end of the name. --}}
                                <select class="form-select rw-suffix" id="name_suffix" name="name_suffix" aria-label="{{ __('Suffix') }}" title="{{ __('Suffix: Jr., Sr., II…') }}">
                                    <option value="">{{ __('Suffix') }}</option>
                                    @foreach(\App\Models\Employee::SUFFIXES as $sx)<option value="{{ $sx }}">{{ $sx }}</option>@endforeach
                                </select>
                            </div>
                            <span class="rw-em" data-say="{{ __('Enter the last name.') }}">{{ __('Enter the last name.') }}</span>
                        </div>

                        {{-- Labor type comes before Position because it answers it:
                             the position IS the labor type, and `position` — the
                             column payroll reads — is derived from it on save. --}}
                        <div class="rw-f">
                            <label for="labor_type_selector">{{ __('Labor type') }} <i>*</i></label>
                            <select class="form-select" id="labor_type_selector" name="labor_type_id" required>
                                <option value="">{{ __('Select labor type') }}</option>
                                @foreach($laborTypes as $type)
                                    <option value="{{ $type->id }}" data-name="{{ $type->name }}" data-daily="{{ $type->daily_rate }}" data-ot="{{ $type->ot_rate }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <span class="rw-em" data-say="{{ __('Choose a labor type.') }}">{{ __('Choose a labor type.') }}</span>
                        </div>
                        {{-- Filled from the labor type and locked
                             (_position_from_labor.blade.php). --}}
                        <div class="rw-f">
                            <label for="job_title">{{ __('Position / job title') }} <i>*</i></label>
                            <input type="text" class="form-control" id="job_title" name="job_title" placeholder="{{ __('Follows the labor type') }}" title="{{ __('Follows the labor type') }}" required>
                            <span class="rw-em" data-say="{{ __('Choose a labor type to fill the position.') }}">{{ __('Choose a labor type to fill the position.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="site_select">{{ __('Site assignment') }} <i>*</i></label>
                            <select class="form-select" id="site_select" name="site_id" required>
                                <option value="">{{ __('Select a site') }}</option>
                                @foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach
                            </select>
                            <span class="rw-em" data-say="{{ __('Choose a site.') }}">{{ __('Choose a site.') }}</span>
                        </div>

                        {{-- Which crew they will be on. Every day they work is
                             stamped with the shift they were on that day. --}}
                        <div class="rw-f">
                            <span class="rw-label" id="rwShiftLabel">{{ __('Shift') }} <i>*</i></span>
                            <div class="rw-seg" id="rwShift" role="group" aria-labelledby="rwShiftLabel" data-default="{{ $rwDayShift }}">
                                @foreach($shifts as $sh)
                                    <button type="button" data-v="{{ $sh->id }}" data-name="{{ $sh->name }} · {{ \Carbon\Carbon::parse($sh->starts_at)->format('g:i A') }}"
                                            class="{{ $sh->id === $rwDayShift ? 'on' : '' }}" aria-pressed="{{ $sh->id === $rwDayShift ? 'true' : 'false' }}"><i class="ti ti-{{ $sh->crosses_midnight ? 'moon' : 'sun' }}" aria-hidden="true"></i>{{ $sh->name }} <small>{{ \Carbon\Carbon::parse($sh->starts_at)->format('g A') }}</small></button>
                                @endforeach
                            </div>
                            <input type="hidden" name="shift_id" id="shift_select" value="{{ $rwDayShift }}">
                        </div>
                        <div class="rw-f">
                            <label for="rate_per_hour">{{ __('Rate per hour') }} <i>*</i></label>
                            <div class="rw-peso"><span aria-hidden="true">₱</span><input type="number" class="form-control" id="rate_per_hour" name="rate_per_hour" min="0.01" step="0.01" inputmode="decimal" placeholder="0.00" required></div>
                            <span class="rw-hp" id="rwRateHint" data-say="{{ __('Fills in from the labor type.') }}">{{ __('Fills in from the labor type.') }}</span>
                            <span class="rw-em" data-say="{{ __('Enter a rate above ₱0.') }}">{{ __('Enter a rate above ₱0.') }}</span>
                        </div>
                        {{-- Other sites they also work at, besides the one above. --}}
                        <div class="rw-f">
                            <span class="rw-label" id="rwOtherSitesLabel">{{ __('Other sites') }}</span>
                            @include('employees._other_sites', ['sites' => $sites, 'chosen' => [], 'main' => 'site_select', 'labelId' => 'rwOtherSitesLabel'])
                            <span class="rw-hp">{{ __('Optional. They can scan at these sites too.') }}</span>
                        </div>
                        {{-- The date hired is on the first step, with the
                             contract it starts.
                             No photo field: Railway wipes uploads on every deploy.
                             No fingerprint field: the kiosk reads the finger, and
                             that enrolment is what makes the worker active. --}}
                    </div>
                </section>

                {{-- ═══ 3 · Personal information ═══ --}}
                <section data-step="3" hidden>
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-user" aria-hidden="true"></i></span>
                        <div><b>{{ __('Personal information') }}</b><small>{{ __('Can be filled in later.') }}</small></div>
                        <span class="rw-tag">{{ __('Optional') }}</span>
                    </div>
                    <div class="rw-g">
                        <div class="rw-f">
                            <label for="birth_date">{{ __('Date of birth') }}</label>
                            <input type="date" class="form-control" id="birth_date" name="birth_date">
                            <span class="rw-em" data-say="{{ __('Worker must be at least 18 years old.') }}">{{ __('Worker must be at least 18 years old.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="birth_place">{{ __('Place of birth') }}</label>
                            <input type="text" class="form-control" id="birth_place" name="birth_place" placeholder="{{ __('City / Municipality, Province') }}">
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="gender">{{ __('Gender') }}</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">{{ __('Select') }}</option>
                                @foreach(\App\Models\Employee::GENDERS as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                            </select>
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="civil_status">{{ __('Civil status') }}</label>
                            <select class="form-select" id="civil_status" name="civil_status">
                                <option value="">{{ __('Select') }}</option>
                                @foreach(\App\Models\Employee::CIVIL_STATUSES as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                            </select>
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="nationality">{{ __('Nationality') }}</label>
                            <input type="text" class="form-control" id="nationality" name="nationality" value="Filipino">
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="blood_type">{{ __('Blood type') }}</label>
                            <select class="form-select" id="blood_type" name="blood_type">
                                <option value="">{{ __('Select') }}</option>
                                @foreach(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
                            </select>
                            <span class="rw-em" data-say=""></span>
                        </div>
                    </div>
                </section>

                {{-- ═══ 4 · Address ═══
                     Province leads, because the two that follow are answers to
                     it: the city list is whatever is inside the chosen province,
                     and the barangay list whatever is inside the chosen city.
                     The lists come from PSGC via public/js/address-picker.js. --}}
                <section data-step="4" hidden>
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-home" aria-hidden="true"></i></span>
                        <div><b>{{ __('Address') }}</b><small>{{ __('Can be filled in later.') }}</small></div>
                        <span class="rw-tag">{{ __('Optional') }}</span>
                    </div>
                    <div class="rw-g">
                        <div class="rw-f">
                            <label for="address_province">{{ __('Province') }}</label>
                            <div class="ap-field"><input type="text" class="form-control" id="address_province" name="address_province" placeholder="{{ __('Type to search, e.g. Camarines') }}"></div>
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="address_city">{{ __('City / Municipality') }}</label>
                            <div class="ap-field"><input type="text" class="form-control" id="address_city" name="address_city" placeholder="{{ __('Pick a province first') }}"></div>
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="address_barangay">{{ __('Barangay') }}</label>
                            <div class="ap-field"><input type="text" class="form-control" id="address_barangay" name="address_barangay" placeholder="{{ __('Pick a city first') }}"></div>
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="address_postal">{{ __('ZIP code') }}</label>
                            <input type="text" class="form-control" id="address_postal" name="address_postal" inputmode="numeric" maxlength="4" placeholder="4400">
                            <span class="rw-em" data-say="{{ __('ZIP code has 4 digits.') }}">{{ __('ZIP code has 4 digits.') }}</span>
                        </div>
                        <div class="rw-f full">
                            <label for="address_street">{{ __('House no. / street') }}</label>
                            <input type="text" class="form-control" id="address_street" name="address_street" placeholder="{{ __('e.g. 123 Rizal St.') }}">
                            <span class="rw-em" data-say=""></span>
                        </div>
                    </div>
                </section>

                {{-- ═══ 5 · Government IDs ═══ --}}
                <section data-step="5" hidden>
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-id" aria-hidden="true"></i></span>
                        <div><b>{{ __('Government IDs') }}</b><small>{{ __('Needed for SSS, PhilHealth and Pag-IBIG deductions.') }}</small></div>
                        <span class="rw-tag">{{ __('Optional') }}</span>
                    </div>
                    <div class="rw-g">
                        <div class="rw-f">
                            <label for="sss_number">{{ __('SSS number') }}</label>
                            <input type="text" class="form-control ep-mono" id="sss_number" name="sss_number" data-mask="2-7-1" inputmode="numeric" placeholder="00-0000000-0">
                            <span class="rw-em" data-say="{{ __('SSS number has 10 digits.') }}">{{ __('SSS number has 10 digits.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="philhealth_number">{{ __('PhilHealth number') }}</label>
                            <input type="text" class="form-control ep-mono" id="philhealth_number" name="philhealth_number" data-mask="2-9-1" inputmode="numeric" placeholder="00-000000000-0">
                            <span class="rw-em" data-say="{{ __('PhilHealth number has 12 digits.') }}">{{ __('PhilHealth number has 12 digits.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="pagibig_number">{{ __('Pag-IBIG number') }}</label>
                            <input type="text" class="form-control ep-mono" id="pagibig_number" name="pagibig_number" data-mask="4-4-4" inputmode="numeric" placeholder="0000-0000-0000">
                            <span class="rw-em" data-say="{{ __('Pag-IBIG number has 12 digits.') }}">{{ __('Pag-IBIG number has 12 digits.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="tin_number">{{ __('TIN') }}</label>
                            <input type="text" class="form-control ep-mono" id="tin_number" name="tin_number" data-mask="3-3-3-3" inputmode="numeric" placeholder="000-000-000-000">
                            <span class="rw-em" data-say="{{ __('TIN has 9 to 12 digits.') }}">{{ __('TIN has 9 to 12 digits.') }}</span>
                        </div>
                    </div>
                </section>

                {{-- ═══ 6 · Contact information ═══ --}}
                <section data-step="6" hidden>
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-phone" aria-hidden="true"></i></span>
                        <div><b>{{ __('Contact information') }}</b><small>{{ __('Can be filled in later.') }}</small></div>
                        <span class="rw-tag">{{ __('Optional') }}</span>
                    </div>
                    <div class="rw-g">
                        <div class="rw-f">
                            <label for="phone">{{ __('Mobile number') }}</label>
                            <input type="text" class="form-control" id="phone" name="phone" data-mask="4-3-4" data-sep=" " inputmode="tel" placeholder="{{ __('09XX XXX XXXX') }}">
                            <span class="rw-em" data-say="{{ __('Use an 11-digit number starting with 09.') }}">{{ __('Use an 11-digit number starting with 09.') }}</span>
                        </div>
                        <div class="rw-f">
                            <label for="email">{{ __('Email address') }}</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com">
                            <span class="rw-em" data-say="{{ __('Enter a valid email address.') }}">{{ __('Enter a valid email address.') }}</span>
                        </div>
                        <p class="rw-sub">{{ __('In case of emergency') }}</p>
                        <div class="rw-f full">
                            <label for="emergency_contact_name">{{ __('Contact person') }}</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" placeholder="{{ __('Full name') }}">
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="emergency_contact_relation">{{ __('Relationship') }}</label>
                            <select class="form-select" id="emergency_contact_relation" name="emergency_contact_relation">
                                <option value="">{{ __('Select') }}</option>
                                @foreach(['Spouse', 'Parent', 'Sibling', 'Child', 'Relative', 'Friend'] as $r)<option value="{{ $r }}">{{ __($r) }}</option>@endforeach
                            </select>
                            <span class="rw-em" data-say=""></span>
                        </div>
                        <div class="rw-f">
                            <label for="emergency_contact_phone">{{ __('Contact number') }}</label>
                            <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" data-mask="4-3-4" data-sep=" " inputmode="tel" placeholder="{{ __('09XX XXX XXXX') }}">
                            <span class="rw-em" data-say="{{ __('Use an 11-digit number starting with 09.') }}">{{ __('Use an 11-digit number starting with 09.') }}</span>
                        </div>
                    </div>
                </section>

                {{-- ═══ 7 · Review ═══ --}}
                <section data-step="7" hidden>
                    <div class="rw-pane-h">
                        <span class="rw-ic"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                        <div><b>{{ __('Review') }}</b><small>{{ __('Check the details before registering.') }}</small></div>
                    </div>
                    <div class="rw-rev" id="rwReview"></div>
                </section>

                {{-- ═══ Done ═══ --}}
                <section data-step="8" hidden>
                    <div class="rw-ok">
                        <span class="rw-ok-big"><i class="ti ti-check" aria-hidden="true"></i></span>
                        <h4 id="rwOkName">{{ __('Employee registered') }}</h4>
                        <p id="rwOkMsg"></p>
                    </div>
                </section>
            </form>

            {{-- Closing with something typed asks first, here in the foot. --}}
            <div class="rw-foot rw-dis" id="rwDiscard" hidden>
                <span class="rw-meta">{{ __('Discard this registration? What you typed will be lost.') }}</span>
                <button type="button" class="emp-btn-cancel" id="rwKeep">{{ __('Keep editing') }}</button>
                <button type="button" class="rw-danger" id="rwDrop">{{ __('Discard') }}</button>
            </div>
            <div class="rw-foot" id="rwFoot">
                <span class="rw-meta" id="rwMeta">{{ __('Steps 1 and 2 are required.') }}</span>
                <button type="button" class="emp-btn-cancel" id="rwBack">{{ __('Cancel') }}</button>
                <button type="button" class="rw-skip" id="rwSkip" hidden>{{ __('Skip for now') }}</button>
                <button type="button" class="emp-btn-save" id="rwNext"><span>{{ __('Next') }}</span></button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/address-picker.js') }}"></script>

<script>
// ── Labor type → Rate Per Hour ──────────────────────────────────────────────
// Its own script, apart from the steps below, so that changing how the pop-up
// walks cannot take the rate with it. Position is filled separately, in
// _position_from_labor.blade.php, off the same change event.
(function () {
    const ltSelector = document.getElementById('labor_type_selector');
    const rateInput  = document.getElementById('rate_per_hour');
    if (!ltSelector || !rateInput) return;

    ltSelector.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        // data-daily is the labor type's daily rate; the hour is the standard
        // eight, not the span of the shift — see the note on paid hours.
        rateInput.value = opt && opt.value
            ? ((parseFloat(opt.dataset.daily) || 0) / 8).toFixed(2)
            : '';
    });

    // A labor type the browser put back in the select brings its rate with it.
    if (ltSelector.value) ltSelector.dispatchEvent(new Event('change'));
})();
</script>

@include('employees._position_from_labor')

<script>
// ── Register employee: the steps ────────────────────────────────────────────
// One step on screen at a time. A step is checked before the next opens; the
// optional ones can be skipped, and are checked again before anything is
// sent. Saving posts the same form Register Employee always posted, and the
// server's own answer about a field is shown beside that field.
(function () {
    const modalEl = document.getElementById('regWizard');
    const form    = document.getElementById('regWizardForm');
    if (!modalEl || !form) return;

    const $      = id => document.getElementById(id);
    const STEPS  = [@json(__('Type')), @json(__('Employment')), @json(__('Personal')), @json(__('Address')), @json(__('Gov’t IDs')), @json(__('Contact')), @json(__('Review'))];
    const NAMES  = [@json(__('Employment type')), @json(__('Employment & pay')), @json(__('Personal information')), @json(__('Address')), @json(__('Government IDs')), @json(__('Contact information')), @json(__('Review'))];
    // The optional steps as they read in the middle of a sentence.
    const LATER  = { 3: @json(__('personal information')), 4: @json(__('address')), 5: @json(__('government IDs')), 6: @json(__('contact information')) };
    // The first two steps must be filled in; the four after them can be skipped.
    const MUST   = 2, REVIEW = 7, DONE = 8;
    const CONTRACTUAL = @json(\App\Models\Employee::EMPLOYMENT_CONTRACTUAL);
    const OVER        = @json(__('That contract would already be over. Give it more months, or check the date hired.'));
    const MONTHS_MAX  = @json(\App\Models\Employee::CONTRACT_MONTHS_MAX);
    const csrf   = document.querySelector('meta[name="csrf-token"]').content;
    const digits = v => String(v).replace(/\D/g, '');
    const peso   = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const input  = name => form.elements[name];
    const val    = name => String((input(name) && input(name).value) || '').trim();
    const make   = (tag, text, cls) => { const x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; };
    const modal  = () => bootstrap.Modal.getOrCreateInstance(modalEl);
    const dated  = v => v ? new Date(v + 'T00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';

    let cur = 1, reached = 1, sending = false, registered = false;
    const wrong = new Set();   // steps holding something to fix

    // Province → City / Municipality → Barangay, from the PSGC tables in
    // public/psgc. If those files cannot be reached the three inputs stay the
    // plain text fields they are.
    const address = window.JeyancoAddress ? JeyancoAddress.init({
        base:     '{{ asset('psgc') }}',
        province: $('address_province'),
        city:     $('address_city'),
        barangay: $('address_barangay'),
    }) : null;

    // ── The row of steps ────────────────────────────────────────────────────
    function steps() {
        const nav = $('rwSteps');
        nav.textContent = '';
        STEPS.forEach(function (label, i) {
            const n   = i + 1;
            const cls = n === cur ? 'on' : wrong.has(n) ? 'err' : (n < reached || n < cur) ? 'done' : '';
            const b   = make('button', null, 'rw-st ' + cls);
            b.type = 'button';
            b.disabled = n > reached && n !== cur + 1;
            if (n === cur) b.setAttribute('aria-current', 'step');

            const num = make('span', cls === 'done' ? null : n, 'rw-n');
            if (cls === 'done') { const tick = make('i', null, 'ti ti-check'); tick.setAttribute('aria-hidden', 'true'); num.appendChild(tick); }
            const lb = make('span', null, 'rw-lb');
            lb.append(num, make('span', label, 'rw-t'));
            b.append(make('span', null, 'rw-bar'), lb);
            b.setAttribute('aria-label', label);
            b.addEventListener('click', () => go(n));
            nav.appendChild(b);
        });
    }

    function show(n) {
        cur     = n;
        reached = Math.max(reached, Math.min(n, REVIEW));
        form.querySelectorAll('[data-step]').forEach(s => { s.hidden = +s.dataset.step !== n; });

        const done = n === DONE;
        $('rwSteps').hidden = done;
        $('rwSub').textContent  = done ? @json(__('Done')) : 'Step ' + n + ' of 7 · ' + NAMES[n - 1];
        $('rwSkip').hidden      = n <= MUST || n >= REVIEW;
        $('rwNext').querySelector('span').textContent = n === REVIEW ? @json(__('Register employee')) : done ? @json(__('Done')) : @json(__('Next'));
        $('rwMeta').textContent = n <= MUST ? @json(__('Steps 1 and 2 are required.')) : n < REVIEW ? @json(__('You can skip this and fill it in later.')) : '';
        $('rwBack').textContent = done ? @json(__('Register another')) : n === 1 ? @json(__('Cancel')) : @json(__('Back'));
        $('rwError').hidden     = true;

        if (n === REVIEW) review();
        steps();
        form.scrollTop = 0;
        setTimeout(focusFirst, 40);
    }

    /** Into the first field of the step on screen. */
    function focusFirst() {
        const pane  = '[data-step="' + cur + '"] ';
        // The first step opens on two cards to choose between, so nothing
        // there is put in focus unless something on it is wrong.
        const first = cur < REVIEW && (form.querySelector(pane + '.rw-f.bad input, ' + pane + '.rw-f.bad select:not(.rw-suffix)')
                                    || (cur > 1 && form.querySelector(pane + 'input:not([type="hidden"]), ' + pane + 'select')));
        if (first && modalEl.classList.contains('show')) first.focus({ preventScroll: true });
    }

    // ── Checking a step ─────────────────────────────────────────────────────
    function mark(name, bad, message) {
        const el   = input(name);
        const wrap = el && el.closest('.rw-f');
        if (!wrap) return;
        wrap.classList.toggle('bad', bad);
        el.setAttribute('aria-invalid', bad ? 'true' : 'false');
        const em = wrap.querySelector('.rw-em');
        if (em) em.textContent = (bad && message) || em.dataset.say || '';
    }

    /** Each rule of a step as [field, is it wrong]. Only the first two steps must be filled in; the rest are held to their shape when something is typed. */
    function rules(n) {
        const out = [];
        const t   = (name, bad, why) => out.push([name, bad, why]);

        if (n === 1) {
            t('date_hired', !val('date_hired'));
            if (onContract()) {
                const months = Number(val('contract_months'));
                const whole  = /^\d+$/.test(val('contract_months')) && months >= 1 && months <= MONTHS_MAX;
                // In range, and not a contract that is over before it is saved.
                const over = whole && !!val('date_hired') && contractEnd() < today();
                t('contract_months', !whole || over, over ? OVER : null);
            }
        }
        if (n === 2) {
            ['first_name', 'last_name', 'labor_type_id', 'job_title', 'site_id'].forEach(k => t(k, !val(k)));
            t('rate_per_hour', !(parseFloat(val('rate_per_hour')) > 0));
        }
        if (n === 3) {
            const born = val('birth_date');
            let young  = false;
            if (born) { const d = new Date(born + 'T00:00'), line = new Date(); line.setFullYear(line.getFullYear() - 18); young = d > line; }
            t('birth_date', young);
        }
        if (n === 4) {
            const zip = val('address_postal');
            t('address_postal', !!zip && !/^\d{4}$/.test(zip));
        }
        if (n === 5) {
            const long = (name, ok) => { const d = digits(val(name)); t(name, !!d && !ok(d.length)); };
            long('sss_number', l => l === 10);
            long('philhealth_number', l => l === 12);
            long('pagibig_number', l => l === 12);
            long('tin_number', l => l >= 9 && l <= 12);
        }
        if (n === 6) {
            ['phone', 'emergency_contact_phone'].forEach(k => { const d = digits(val(k)); t(k, !!d && !/^09\d{9}$/.test(d)); });
            const mail = val('email');
            t('email', !!mail && !/^\S+@\S+\.\S+$/.test(mail));
        }
        return out;
    }

    /** @param say    mark the fields, or only take note of the step
     *  @param focus  go to the first one wrong (not while somebody is typing in another) */
    function check(n, say = true, focus = true) {
        const all = rules(n);
        const bad = all.filter(r => r[1]).map(r => r[0]);
        if (say) all.forEach(([name, isBad, why]) => mark(name, isBad, why));
        if (bad.length) wrong.add(n); else wrong.delete(n);
        if (bad.length && say && focus) input(bad[0]).focus();
        return !bad.length;
    }

    // ── Employment type ─────────────────────────────────────────────────────
    // Regular needs only the date hired. Contractual adds how many months,
    // and shows the day that makes the contract end: the same day of the
    // month that many months on, or the last day of a month too short for
    // it. The server works the date out again the same way and that is what
    // is saved; this is so the office sees it before they go on.
    const onContract = () => val('employment_type') === CONTRACTUAL;
    const iso        = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const today      = () => iso(new Date());

    /** The contract's last day as Y-m-d, or '' while it cannot be worked out. */
    function contractEnd() {
        const hired  = val('date_hired');
        const months = parseInt(val('contract_months'), 10);
        if (!hired || !(months >= 1)) return '';
        const [y, m, d] = hired.split('-').map(Number);
        const end  = new Date(y, m - 1 + months, 1);
        const last = new Date(end.getFullYear(), end.getMonth() + 1, 0).getDate();
        end.setDate(Math.min(d, last));
        return iso(end);
    }

    /** Show the contract fields for a contractual worker, and what they come to. */
    function contract() {
        const on = onContract();
        $('rwContractLength').hidden = !on;
        $('rwContractEnds').hidden   = !on;
        // Not sent, and not counted as typed, unless it applies.
        input('contract_months').disabled = !on;
        if (!on) { input('contract_months').value = ''; mark('contract_months', false); }

        const ends = on ? contractEnd() : '';
        $('rwEnds').value = ends ? dated(ends) : '';

        // An end already behind today is said where the months are typed,
        // as soon as it is; anything else waits for Next.
        if (on && ((ends && ends < today()) || form.querySelector('#rwContractLength.bad'))) check(1, true, false);
    }

    form.querySelectorAll('input[name="employment_type"]').forEach(r => r.addEventListener('change', function () {
        contract();
        if (onContract()) input('contract_months').focus();
        steps();
    }));
    ['contract_months', 'date_hired'].forEach(name => input(name).addEventListener('input', contract));

    function go(n) {
        if (n === cur) return;
        if (n > cur && !check(cur)) { steps(); return; }
        show(n);
    }

    // ── Review ──────────────────────────────────────────────────────────────
    const chosen = name => { const s = input(name), o = s && s.options[s.selectedIndex]; return o && o.value ? (o.dataset.name || o.textContent.trim()) : ''; };
    const fullName = () => [val('first_name'), val('middle_name'), val('last_name'), val('name_suffix')].filter(Boolean).join(' ');
    const otherSites = () => Array.from(form.querySelectorAll('.osx input[type="checkbox"]:checked:not(:disabled)'))
        .map(i => i.nextElementSibling.textContent.trim()).join(', ');

    function review() {
        const shift = $('rwShift').querySelector('button.on');
        const parts = [
            [1, NAMES[0], [['Type', onContract() ? @json(__('Contractual')) : @json(__('Regular'))], ['Date hired', dated(val('date_hired'))]]
                .concat(onContract() ? [['Contract length', val('contract_months') ? val('contract_months') + (val('contract_months') === '1' ? ' month' : ' months') : ''],
                                        ['Contract ends', $('rwEnds').value]] : [])],
            [2, NAMES[1], [['Name', fullName()], ['Labor type', chosen('labor_type_id')], ['Position', val('job_title')], ['Shift', shift ? shift.dataset.name : ''],
                           ['Rate per hour', val('rate_per_hour') ? peso(val('rate_per_hour')) : ''], ['Site', chosen('site_id')], ['Other sites', otherSites()]]],
            [3, NAMES[2], [['Date of birth', dated(val('birth_date'))], ['Place of birth', val('birth_place')], ['Gender', val('gender')], ['Civil status', val('civil_status')],
                           ['Nationality', val('nationality')], ['Blood type', val('blood_type')]]],
            [4, NAMES[3], [['Street', val('address_street')], ['Barangay', val('address_barangay')], ['City / Municipality', val('address_city')], ['Province', val('address_province')],
                           ['ZIP code', val('address_postal')]]],
            [5, NAMES[4], [['SSS', val('sss_number')], ['PhilHealth', val('philhealth_number')], ['Pag-IBIG', val('pagibig_number')], ['TIN', val('tin_number')]]],
            [6, NAMES[5], [['Mobile', val('phone')], ['Email', val('email')], ['Emergency contact', val('emergency_contact_name')], ['Relationship', val('emergency_contact_relation')],
                           ['Contact number', val('emergency_contact_phone')]]],
        ];

        const box = $('rwReview');
        box.textContent = '';
        parts.forEach(function ([n, title, rows]) {
            const card = make('div', null, 'rw-rv');
            const head = make('div', title, 'rw-rv-h');
            const edit = make('button', @json(__('Edit')));
            edit.type = 'button';
            edit.setAttribute('aria-label', @json(__('Edit')) + ' ' + title);
            edit.addEventListener('click', () => show(n));
            head.appendChild(edit);

            const list = document.createElement('dl');
            rows.forEach(function ([label, value]) {
                const row = document.createElement('div');
                row.append(make('dt', label), make('dd', value || @json(__('Not set')), value ? '' : 'no'));
                list.appendChild(row);
            });
            card.append(head, list);
            box.appendChild(card);
        });
    }

    // ── Saving ──────────────────────────────────────────────────────────────
    function busy(on) {
        sending = on;
        $('rwNext').disabled = on;
        $('rwNext').querySelector('span').textContent = on ? @json(__('Registering…')) : @json(__('Register employee'));
    }

    /** What the server refused, shown beside the field it refused, on the step that field is on. */
    function refused(errors) {
        let firstStep = null, loose = [];
        Object.keys(errors).forEach(function (key) {
            const el   = input(key.split('.')[0]);
            const pane = el && el.closest && el.closest('[data-step]');
            if (!pane || !el.closest('.rw-f')) { loose.push(errors[key][0]); return; }
            const n = +pane.dataset.step;
            mark(key.split('.')[0], true, errors[key][0]);
            wrong.add(n);
            if (firstStep === null || n < firstStep) firstStep = n;
        });
        show(firstStep || REVIEW);
        if (loose.length) { $('rwError').textContent = loose.join(' '); $('rwError').hidden = false; }
    }

    async function register() {
        if (sending) return;
        busy(true);
        try {
            const res  = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.success) { busy(false); finished(data); return; }
            busy(false);
            if (res.status === 422 && data.errors) { refused(data.errors); return; }
            $('rwError').textContent = data.message || @json(__('Could not register the employee. Please try again.'));
            $('rwError').hidden = false;
        } catch (err) {
            busy(false);
            $('rwError').textContent = @json(__('Network error. Please try again.'));
            $('rwError').hidden = false;
        }
    }

    function finished(data) {
        registered = true;
        // The list behind announces a new worker waiting in Pending. Whoever
        // just registered them here has been told already.
        window.rwJustRegistered = (window.rwJustRegistered || 0) + 1;

        // The optional steps left with nothing in them. Nationality comes
        // filled in, so it does not count as an answer.
        const empty = [3, 4, 5, 6].filter(n => Array.from(form.querySelectorAll('[data-step="' + n + '"] input, [data-step="' + n + '"] select'))
            .every(x => !x.value || x.name === 'nationality')).map(n => LATER[n]);

        $('rwOkName').textContent = data.name + ' ' + @json(__('is registered'));
        $('rwOkMsg').textContent  = 'Employee ' + data.number + ' · ' + [data.labor, data.site ? 'at ' + data.site : ''].filter(Boolean).join(' ') + '. '
            + (data.site
                ? (data.kiosk ? 'Next, enroll their fingerprint at the ' + data.site + ' kiosk.'
                              : 'Next, enroll their fingerprint at a kiosk. ' + data.site + ' has no kiosk set to it yet.')
                : 'Next, enroll their fingerprint at a kiosk.')
            + (data.contract_ends ? ' On contract until ' + data.contract_ends + ', and off the Active list after that.' : '')
            + (empty.length ? ' Still to fill in: ' + empty.join(', ') + '.' : '');

        show(DONE);
    }

    // ── Opening and closing ─────────────────────────────────────────────────
    function setShift(id) {
        input('shift_id').value = id || '';
        $('rwShift').querySelectorAll('button').forEach(function (b) {
            const on = b.dataset.v === String(id);
            b.classList.toggle('on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    /** Anything typed? The date hired and the nationality come filled in, and do not count. */
    function dirty() {
        return Array.from(form.querySelectorAll('input:not([type="hidden"]), select'))
            .some(x => x.type === 'radio' ? x.checked !== x.defaultChecked
                                          : x.value && !x.disabled && x.name !== 'nationality' && x.name !== 'date_hired');
    }

    function reset() {
        form.reset();
        contract();
        setShift($('rwShift').dataset.default);
        $('rwRateHint').textContent = $('rwRateHint').dataset.say;
        form.querySelectorAll('.rw-f.bad').forEach(function (f) {
            f.classList.remove('bad');
            const em = f.querySelector('.rw-em');
            if (em) em.textContent = em.dataset.say || '';
        });
        form.querySelectorAll('[aria-invalid]').forEach(x => x.removeAttribute('aria-invalid'));
        // The city and barangay lists belong to the province last chosen.
        if (address) { address.city.setItems([]); address.barangay.setItems([]); }
        wrong.clear();
        reached = 1;
    }

    function ask(on) {
        $('rwDiscard').hidden = !on;
        $('rwFoot').hidden    = on;
        if (on) $('rwKeep').focus();
    }

    function open() {
        reset();
        ask(false);
        show(1);
        modal().show();
    }

    function close(force) {
        if (!force && cur !== DONE && dirty()) { ask(true); return; }
        ask(false);
        modal().hide();
    }

    $('rwNext').addEventListener('click', function () {
        if (cur === DONE) { close(true); return; }
        if (cur < REVIEW) { if (!check(cur)) { steps(); return; } show(cur + 1); return; }
        // Everything once more, quietly, before it is sent: a skipped step
        // may be holding something to fix.
        for (let s = 1; s < REVIEW; s++) {
            if (!check(s, false)) { show(s); check(s, true, false); steps(); return; }
        }
        register();
    });
    $('rwBack').addEventListener('click', function () {
        if (cur === DONE) { reset(); show(1); }
        else if (cur === 1) close();
        else show(cur - 1);
    });
    $('rwSkip').addEventListener('click', function () { check(cur, false); show(cur + 1); });
    $('rwKeep').addEventListener('click', () => ask(false));
    $('rwDrop').addEventListener('click', () => close(true));
    $('rwClose').addEventListener('click', () => close());

    // A press outside, or Escape: asked the same way as the X — or, with the
    // question already up, taken as "keep editing".
    modalEl.addEventListener('hidePrevented.bs.modal', function (e) {
        e.preventDefault();   // no shake: the question in the foot is the answer
        if ($('rwDiscard').hidden) close(); else ask(false);
    });
    // Bootstrap takes the focus for the dialog itself once it has opened.
    modalEl.addEventListener('shown.bs.modal', focusFirst);
    // Escape with an address list open closes the list, not the dialog.
    let listOpen = false;
    form.addEventListener('keydown', function (e) { listOpen = e.key === 'Escape' && e.target.getAttribute('aria-expanded') === 'true'; }, true);
    form.addEventListener('keydown', function (e) {
        if (listOpen) { e.stopPropagation(); listOpen = false; return; }
        // Enter moves on, as Next does — unless it has just picked a suggestion.
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && !e.defaultPrevented) { e.preventDefault(); $('rwNext').click(); }
    });
    form.addEventListener('submit', e => e.preventDefault());

    modalEl.addEventListener('show.bs.modal', () => document.body.classList.add('rw-open'));
    modalEl.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('rw-open');
        // Somebody was registered: show the list they are waiting in, as
        // saving the page form used to land on it.
        if (registered) {
            registered = false;
            const pending = document.querySelector('.rmx-stat[data-tab="pending"]');
            if (pending) pending.click();
            // The lists are not patched under an open dialog; now that it
            // has closed they are read again at once, not a second later.
            if (window.Live && Live.refresh) Live.refresh();
        }
    });

    // ── Fields that help as they are typed ──────────────────────────────────
    $('rwShift').querySelectorAll('button').forEach(b => b.addEventListener('click', () => setShift(b.dataset.v)));

    $('labor_type_selector').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        $('rwRateHint').textContent = opt && opt.value
            ? peso(parseFloat(opt.dataset.daily) || 0) + ' a day ÷ 8 hours.'
            : $('rwRateHint').dataset.say;
        // The rate and the position were just filled in for it.
        if (wrong.has(2)) { check(2, true, false); steps(); }
    });

    // 00-0000000-0, 0000-0000-0000, 09XX XXX XXXX: the separators type themselves.
    form.querySelectorAll('[data-mask]').forEach(function (el) {
        el.addEventListener('input', function () {
            const parts = el.dataset.mask.split('-').map(Number), sep = el.dataset.sep || '-';
            const d = digits(el.value).slice(0, parts.reduce((a, b) => a + b, 0));
            const out = [];
            let at = 0;
            for (const p of parts) { if (at >= d.length) break; out.push(d.slice(at, at + p)); at += p; }
            el.value = out.join(sep);
        });
    });
    $('address_postal').addEventListener('input', e => { e.target.value = digits(e.target.value).slice(0, 4); });

    // A field marked wrong is looked at again as it is corrected.
    form.querySelectorAll('input, select').forEach(function (el) {
        el.addEventListener('input', function () {
            const wrap = el.closest('.rw-f');
            if (wrap && wrap.classList.contains('bad')) { check(cur, true, false); steps(); }
        });
    });

    // ── The Register employee button, and a link straight to this ───────────
    document.addEventListener('click', function (e) {
        const opener = e.target.closest('[data-register-open]');
        if (!opener) return;
        e.preventDefault();
        open();
    });

    // /employees/create and ?register=1 arrive with the pop-up open. The
    // mark is taken out of the address, so a reload does not reopen it.
    document.addEventListener('DOMContentLoaded', function () {
        try {
            const url = new URL(location.href);
            if (!url.searchParams.has('register')) return;
            url.searchParams.delete('register');
            history.replaceState(null, '', url);
            open();
        } catch (e) {}
    });
})();
</script>
