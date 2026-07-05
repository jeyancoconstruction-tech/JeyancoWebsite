@extends('layouts')

@section('page_title', 'Settings')

@section('content')
<div class="settings-wrapper">
    
    <div class="settings-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>System Settings</h1>
                <p>Manage payroll configurations and labor type definitions</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-exclamation-circle me-2"></i>Error!</strong> Please fix the errors below.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-check-circle me-2"></i>Success!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <ul class="nav nav-tabs settings-tabs mb-0" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab" aria-selected="true">
                <i data-lucide="wallet" class="me-2"></i>Payroll Settings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="labor-tab" data-bs-toggle="tab" data-bs-target="#labor" type="button" role="tab" aria-selected="false">
                <i data-lucide="briefcase" class="me-2"></i>Labor Types
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="holiday-tab" data-bs-toggle="tab" data-bs-target="#holiday" type="button" role="tab" aria-selected="false">
                <i data-lucide="calendar" class="me-2"></i>Holidays
            </button>
        </li>
    </ul>

    <div class="tab-content settings-content">
        <!-- PAYROLL SETTINGS TAB -->
        <div class="tab-pane fade show active" id="payroll" role="tabpanel">
            <form method="POST" action="{{ route('settings.update') }}" class="settings-form">
                @csrf
                @method('PUT')

                {{-- Pay Config + Government Contributions --}}
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="ps-card h-100" id="ot_rate_section">
                            <div class="ps-card-header">
                                <i class="fas fa-sliders-h"></i>
                                <div>
                                    <h6>Pay Rate Configuration</h6>
                                    <p>Applied across all payroll calculations.</p>
                                </div>
                            </div>
                            <div class="ps-card-body">
                                <div class="mb-4">
                                    <label class="ps-label" for="ot_multiplier">Overtime Multiplier</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="10"
                                               class="form-control ps-input @error('ot_multiplier') is-invalid @enderror"
                                               id="ot_multiplier" name="ot_multiplier"
                                               value="{{ $settings->ot_multiplier ?? 1.25 }}">
                                        <span class="input-group-text ps-ig-text">×</span>
                                    </div>
                                    @error('ot_multiplier')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <small class="text-muted d-block mt-1">Multiplied against hourly rate for overtime hours.</small>
                                </div>
                                <div class="mb-4">
                                    <label class="ps-label" for="bonus">Bonus (per period)</label>
                                    <div class="input-group">
                                        <span class="input-group-text ps-ig-text">₱</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control ps-input @error('bonus') is-invalid @enderror"
                                               id="bonus" name="bonus"
                                               value="{{ $settings->bonus ?? 0 }}">
                                    </div>
                                    @error('bonus')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <small class="text-muted d-block mt-1">Flat amount added per employee each pay period.</small>
                                </div>
                                <div class="mb-0">
                                    <label class="ps-label">Sunday Rest Day Pay</label>
                                    <div class="ps-toggle-row">
                                        <label class="ps-toggle-switch">
                                            <input type="checkbox" name="sunday_rest_day_enabled" value="1"
                                                   id="sunday_rest_day_enabled"
                                                   {{ ($settings->sunday_rest_day_enabled ?? true) ? 'checked' : '' }}>
                                            <span class="ps-toggle-slider"></span>
                                        </label>
                                        <div class="ps-toggle-label">
                                            <span class="ps-toggle-status" id="restDayStatus">
                                                {{ ($settings->sunday_rest_day_enabled ?? true) ? 'Enabled' : 'Disabled' }}
                                            </span>
                                            — Apply <strong>130% rest day rate</strong> to all Sundays
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Sunday is the designated rest day. When enabled, employees earn an extra 30% on top of their regular day rate for any Sunday work.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="ps-card h-100" id="sss_section">
                            <div class="ps-card-header">
                                <i class="fas fa-percent"></i>
                                <div>
                                    <h6>Government Contributions</h6>
                                    <p>Deducted from gross pay per employee. Enter 0 to skip.</p>
                                </div>
                            </div>
                            <div class="ps-card-body">
                                <div class="mb-3">
                                    <label class="ps-label" for="sss">SSS</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01"
                                               class="form-control ps-input @error('sss') is-invalid @enderror"
                                               id="sss" name="sss" value="{{ $settings->sss ?? 0 }}">
                                        <span class="input-group-text ps-ig-text">%</span>
                                    </div>
                                    @error('sss')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3" id="philhealth_section">
                                    <label class="ps-label" for="philhealth">PhilHealth</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01"
                                               class="form-control ps-input @error('philhealth') is-invalid @enderror"
                                               id="philhealth" name="philhealth" value="{{ $settings->philhealth ?? 0 }}">
                                        <span class="input-group-text ps-ig-text">%</span>
                                    </div>
                                    @error('philhealth')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-0" id="pagibig_section">
                                    <label class="ps-label" for="pagibig">PAG-IBIG</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01"
                                               class="form-control ps-input @error('pagibig') is-invalid @enderror"
                                               id="pagibig" name="pagibig" value="{{ $settings->pagibig ?? 0 }}">
                                        <span class="input-group-text ps-ig-text">%</span>
                                    </div>
                                    @error('pagibig')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn ps-save-btn">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
            </form>
        </div>

        <!-- LABOR TYPES TAB -->
        <div class="tab-pane fade" id="labor" role="tabpanel">
            <div class="row g-4">

                {{-- Add New Labor Type --}}
                <div class="col-lg-4">
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <i class="fas fa-plus-circle"></i>
                            <div>
                                <h6>Add Labor Type</h6>
                                <p>Hourly and OT rates are derived from the daily rate automatically.</p>
                            </div>
                        </div>
                        <div class="ps-card-body">
                            <form method="POST" action="{{ route('labor-types.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="ps-label">Name</label>
                                    <input type="text" class="form-control ps-input @error('name') is-invalid @enderror"
                                           name="name" placeholder="e.g., Engineer, Technician"
                                           value="{{ old('name') }}" required>
                                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-4">
                                    <label class="ps-label">Daily Rate (₱)</label>
                                    <div class="input-group">
                                        <span class="input-group-text ps-ig-text">₱</span>
                                        <input type="number" step="0.01"
                                               class="form-control ps-input"
                                               name="daily_rate" placeholder="1000.00" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn ps-add-btn w-100">
                                    <i class="fas fa-plus me-2"></i>Add Labor Type
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Existing Labor Types --}}
                <div class="col-lg-8">
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <i class="fas fa-list-ul"></i>
                            <div>
                                <h6>Labor Types</h6>
                                <p>Hourly = Daily ÷ 8 &nbsp;·&nbsp; OT uses the multiplier from Payroll Settings.</p>
                            </div>
                        </div>
                        <div class="ps-card-body p-0" id="lt-list-container">
                            @forelse($laborTypes as $type)
                            <div class="lt-row">
                                <div class="lt-info">
                                    <span class="lt-name">{{ $type->name }}</span>
                                    <div class="lt-rates">
                                        <span class="lt-rate-pill">Daily&nbsp;<strong>{{ $type->getFormattedDailyRate() }}</strong></span>
                                        <span class="lt-rate-pill">Hourly&nbsp;<strong>{{ $type->getFormattedHourlyRate() }}</strong></span>
                                        <span class="lt-rate-pill">OT&nbsp;<strong>{{ $type->getFormattedOTRate() }}</strong></span>
                                    </div>
                                </div>
                                <div class="lt-actions">
                                    <div class="dropdown">
                                        <button class="lt-menu-btn" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false">⋮</button>
                                        <ul class="dropdown-menu dropdown-menu-end lt-dropdown">
                                            <li>
                                                <button class="dropdown-item" type="button"
                                                        data-bs-toggle="modal" data-bs-target="#editModal{{ $type->id }}">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('labor-types.delete', $type->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"
                                                            onclick="return confirm('Delete this labor type? Employees using it will be affected.')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            {{-- Edit Modal --}}
                            <div class="modal fade" id="editModal{{ $type->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0" style="border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,.1);">
                                        <div class="modal-header" style="background:linear-gradient(135deg,#1e3a8a,#1e40af); color:#fff; border:none; border-radius:12px 12px 0 0;">
                                            <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Labor Type</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('labor-types.update', $type->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="ps-label">Name</label>
                                                    <input type="text" class="form-control ps-input" name="name"
                                                           value="{{ $type->name }}" required>
                                                </div>
                                                <div class="mb-1">
                                                    <label class="ps-label">Daily Rate (₱)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text ps-ig-text">₱</span>
                                                        <input type="number" step="0.01" class="form-control ps-input"
                                                               name="daily_rate" value="{{ $type->daily_rate }}" required>
                                                    </div>
                                                    <small class="text-muted d-block mt-1">Hourly and OT rates update automatically.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top p-3" style="background:#f8fafc; border-radius:0 0 12px 12px;">
                                                <button type="button" class="btn btn-light fw-600" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn ps-save-btn" style="padding:8px 20px;">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            @empty
                            <div class="lt-empty">
                                <i class="fas fa-inbox"></i>
                                <p>No labor types yet. Add your first one using the form on the left.</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- HOLIDAYS TAB -->
        <div class="tab-pane fade" id="holiday" role="tabpanel">

            {{-- Info banner --}}
            <div class="hc-info-banner mb-4">
                <i class="fas fa-magic" style="margin-top:1px;flex-shrink:0;"></i>
                <span>Official Philippine holidays are loaded automatically. Click a holiday to toggle it on/off; click any blank date to add a custom holiday. Use <strong>Enable All / Disable All</strong> for bulk year-wide changes.</span>
            </div>

            {{-- Calendar card --}}
            <div class="hc-card mb-3">

                {{-- Nav bar: arrows + month heading + quick jump --}}
                <div class="hc-nav-bar">
                    <button id="hcal-prev" class="hc-arrow-btn" aria-label="Previous month">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <div class="hc-center-block">
                        <h2 class="hc-month-heading" id="hc-month-heading">Loading…</h2>
                        <div class="hc-quick-nav">
                            <select id="hc-month-sel" class="hc-sel" aria-label="Jump to month">
                                <option value="0">January</option><option value="1">February</option>
                                <option value="2">March</option><option value="3">April</option>
                                <option value="4">May</option><option value="5">June</option>
                                <option value="6">July</option><option value="7">August</option>
                                <option value="8">September</option><option value="9">October</option>
                                <option value="10">November</option><option value="11">December</option>
                            </select>
                            <div class="hc-yr-ctrl">
                                <button id="hc-yr-dec" class="hc-yr-btn" aria-label="Previous year">−</button>
                                <span id="hc-yr-val">{{ $holidayYear }}</span>
                                <button id="hc-yr-inc" class="hc-yr-btn" aria-label="Next year">+</button>
                            </div>
                        </div>
                    </div>

                    <button id="hcal-next" class="hc-arrow-btn" aria-label="Next month">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                {{-- Action bar: stats + bulk buttons --}}
                <div class="hc-action-bar">
                    <div class="hc-stat-group">
                        <span class="hc-stat hc-stat-on">
                            <i class="fas fa-check-circle" style="font-size:10px;"></i>
                            Active: <strong id="hstat-active">–</strong>
                        </span>
                        <span class="hc-stat hc-stat-off">
                            <i class="fas fa-ban" style="font-size:10px;"></i>
                            Disabled: <strong id="hstat-disabled">–</strong>
                        </span>
                    </div>
                    <div class="hc-btn-group">
                        <button id="hcal-enable-all" class="hc-pill hc-pill-green">
                            <i class="fas fa-check-double"></i> Enable All
                        </button>
                        <button id="hcal-disable-all" class="hc-pill hc-pill-red">
                            <i class="fas fa-ban"></i> Disable All
                        </button>
                        <button class="hc-pill hc-pill-indigo" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                            <i class="fas fa-plus"></i> Add Custom
                        </button>
                    </div>
                </div>

                {{-- Day-of-week header --}}
                <div class="hc-dow">
                    <span>Sun</span><span>Mon</span><span>Tue</span>
                    <span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                </div>

                {{-- Sliding calendar viewport --}}
                <div class="hc-viewport" id="hc-viewport">
                    <div id="hcal-view" class="hc-view"></div>
                </div>

                {{-- Holiday list panel (shown when Active / Disabled badge is clicked) --}}
                <div id="hc-list-panel" class="hc-list-panel" style="display:none;">
                    <div class="hc-list-header">
                        <span id="hc-list-title" class="hc-list-title"></span>
                        <button id="hc-list-close" class="hc-list-close" aria-label="Close">✕</button>
                    </div>
                    <div id="hc-list-body"></div>
                </div>
            </div>

            {{-- Legend + pay rates --}}
            <div class="hc-footer-row mb-4">
                <div class="hc-legend">
                    <span class="hc-leg-item hc-leg-regular"><span class="hc-leg-dot"></span>Regular Holiday</span>
                    <span class="hc-leg-item hc-leg-special"><span class="hc-leg-dot"></span>Special (Non-Working)</span>
                    <span class="hc-leg-item hc-leg-custom"><span class="hc-leg-dot"></span>Custom Holiday</span>
                    <span class="hc-leg-item hc-leg-off"><i class="fas fa-ban" style="font-size:9px;margin-right:4px;"></i>Disabled</span>
                </div>
                <div class="hc-rates">
                    <span class="hc-rate hc-rate-regular">Regular — <strong>200%</strong></span>
                    <span class="hc-rate hc-rate-special">Special — <strong>130%</strong></span>
                    <span class="hc-rate hc-rate-custom">Custom — <strong>200%</strong></span>
                </div>
            </div>

            {{-- Add Custom Holiday Modal --}}
            <div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0" style="border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,0.14);">
                        <div class="modal-header border-0" style="background:linear-gradient(135deg,#1e3a8a,#1e40af);color:white;border-radius:14px 14px 0 0;padding:20px 24px;">
                            <h5 class="modal-title fw-bold mb-0" id="addHolidayModalLabel">
                                <i class="fas fa-plus-circle me-2"></i>Add Custom Holiday
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="{{ route('holidays.store') }}">
                            @csrf
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-600">Date <span style="color:#dc2626;">*</span></label>
                                    <input type="date" id="hmod-date" name="date"
                                           class="form-control @error('date') is-invalid @enderror"
                                           value="{{ old('date') }}" required>
                                    @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-600">Label <span class="text-muted fw-400">(optional)</span></label>
                                    <input type="text" id="hmod-title" name="title" class="form-control"
                                           placeholder="e.g., Company Foundation Day"
                                           value="{{ old('title') }}">
                                </div>
                                <div id="hmod-recognized" class="mt-2" style="display:none;">
                                    <span class="d-inline-flex align-items-center gap-2 px-3 py-2" style="background:#dbeafe;border-radius:8px;color:#1e40af;font-size:13px;font-weight:600;">
                                        <i class="fas fa-check-circle"></i>
                                        Official PH holiday: <span id="hmod-recognized-text"></span>
                                    </span>
                                </div>
                                <div class="mt-3 p-3" style="background:#dbeafe;border-radius:8px;font-size:13px;color:#1e40af;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Applies the <strong>Holiday Pay Multiplier</strong> to all employees for that day. Attendance logs are not modified.
                                </div>
                            </div>
                            <div class="modal-footer border-0 p-3" style="background:#f8fafc;border-radius:0 0 14px 14px;">
                                <button type="button" class="btn" style="background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;border-radius:8px;" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn fw-600" style="background:#16a34a;color:white;border:none;padding:8px 22px;border-radius:8px;">
                                    <i class="fas fa-plus me-1"></i>Add Holiday
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Floating tooltip (follows cursor over holiday cells) --}}
            <div id="hcal-tip" style="position:fixed;display:none;z-index:9999;background:#1e293b;color:#e8edf5;padding:9px 13px;border-radius:9px;font-size:12px;pointer-events:none;max-width:230px;box-shadow:0 6px 20px rgba(0,0,0,0.28);line-height:1.5;">
                <div id="hcal-tip-name" style="font-weight:700;font-size:13px;"></div>
                <div id="hcal-tip-type" style="opacity:0.65;font-size:11px;"></div>
                <div id="hcal-tip-action" style="margin-top:3px;font-size:11px;color:#93c5fd;"></div>
            </div>

            {{-- Context panel (custom holidays) --}}
            <div id="hcal-ctx" class="hc-ctx" style="display:none;">
                <div id="hcal-ctx-title" class="hc-ctx-title"></div>
                <div id="hcal-ctx-meta"  class="hc-ctx-meta"></div>
                <div style="display:flex;flex-direction:column;gap:7px;">
                    <button id="hcal-ctx-toggle" class="hc-ctx-btn"></button>
                    <button id="hcal-ctx-edit"   class="hc-ctx-btn" style="background:#f59e0b;">
                        <i class="fas fa-edit me-1"></i>Edit Label
                    </button>
                    <button id="hcal-ctx-del"    class="hc-ctx-btn" style="background:#dc2626;">
                        <i class="fas fa-trash me-1"></i>Remove Holiday
                    </button>
                </div>
            </div>

            {{-- Edit custom holiday modal --}}
            <div class="modal fade" id="editHolidayModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
                    <div class="modal-content border-0" style="border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,0.14);">
                        <div class="modal-header border-0" style="background:linear-gradient(135deg,#1e3a8a,#1e40af);color:white;border-radius:14px 14px 0 0;padding:18px 22px;">
                            <h5 class="modal-title fw-bold mb-0"><i class="fas fa-edit me-2"></i>Edit Custom Holiday</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <input type="hidden" id="hmod-eid">
                            <div class="mb-3">
                                <label class="form-label fw-600 small text-muted text-uppercase" style="letter-spacing:.5px;">Date</label>
                                <div id="hmod-edate" class="fw-700" style="color:#1e3a8a;font-size:1rem;"></div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label fw-600">Holiday Label <span style="color:#dc2626;">*</span></label>
                                <input type="text" id="hmod-etitle" class="form-control" placeholder="e.g., Company Anniversary" maxlength="100">
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 pt-0">
                            <button type="button" class="btn" style="background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;border-radius:8px;" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="hmod-esave" class="btn fw-600" style="background:#1e3a8a;color:#fff;border:none;padding:8px 22px;border-radius:8px;">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Calendar CSS --}}
            <style>
            /* ── Info banner ────────────────────────────────────────────── */
            .hc-info-banner {
                display: flex; align-items: flex-start; gap: 10px;
                background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
                padding: 12px 16px; font-size: 13px; color: #1e40af;
            }
            [data-bs-theme="dark"] .hc-info-banner { background: #172554; border-color: #1e3a8a; color: #93c5fd; }

            /* ── Calendar card ───────────────────────────────────────────── */
            .hc-card {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 16px;
                overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            }
            [data-bs-theme="dark"] .hc-card { background: #1c2740; border-color: #283449; box-shadow: none; }

            /* ── Navigation bar ──────────────────────────────────────────── */
            .hc-nav-bar {
                display: flex; align-items: center; gap: 0;
                padding: 18px 20px 14px; border-bottom: 1px solid #e2e8f0;
            }
            [data-bs-theme="dark"] .hc-nav-bar { border-color: #283449; }

            .hc-arrow-btn {
                flex-shrink: 0; width: 40px; height: 40px; border-radius: 10px;
                border: 1px solid #e2e8f0; background: #f8fafc; color: #475569;
                cursor: pointer; display: flex; align-items: center; justify-content: center;
                font-size: 13px; transition: all .15s;
            }
            .hc-arrow-btn:hover { background: #e0e7ff; border-color: #6366f1; color: #4f46e5; }
            [data-bs-theme="dark"] .hc-arrow-btn { background: #283449; border-color: #334155; color: #93c5fd; }
            [data-bs-theme="dark"] .hc-arrow-btn:hover { background: #1e3a8a; border-color: #1e40af; }

            .hc-center-block { flex: 1; text-align: center; padding: 0 16px; }
            .hc-month-heading {
                font-size: 1.4rem; font-weight: 800; color: #1e3a8a;
                margin: 0 0 8px; letter-spacing: -.3px;
            }
            [data-bs-theme="dark"] .hc-month-heading { color: #93c5fd; }

            .hc-quick-nav {
                display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;
            }
            .hc-sel {
                font-size: 12px; font-weight: 600; color: #475569;
                border: 1px solid #e2e8f0; border-radius: 7px; padding: 4px 8px;
                background: #f8fafc; cursor: pointer; outline: none; transition: border-color .15s;
            }
            .hc-sel:focus { border-color: #6366f1; }
            [data-bs-theme="dark"] .hc-sel { background: #283449; border-color: #334155; color: #93c5fd; }

            .hc-yr-ctrl { display: flex; align-items: center; gap: 4px; }
            .hc-yr-btn {
                width: 26px; height: 26px; border-radius: 6px; flex-shrink: 0;
                border: 1px solid #e2e8f0; background: #f8fafc; color: #475569;
                cursor: pointer; font-size: 15px; font-weight: 700; line-height: 1;
                display: flex; align-items: center; justify-content: center; transition: all .15s;
            }
            .hc-yr-btn:hover { background: #e0e7ff; border-color: #6366f1; color: #4f46e5; }
            [data-bs-theme="dark"] .hc-yr-btn { background: #283449; border-color: #334155; color: #93c5fd; }
            #hc-yr-val {
                font-size: 13px; font-weight: 700; color: #1e3a8a; min-width: 40px; text-align: center;
            }
            [data-bs-theme="dark"] #hc-yr-val { color: #93c5fd; }

            /* ── Action bar ──────────────────────────────────────────────── */
            .hc-action-bar {
                display: flex; align-items: center; justify-content: space-between;
                padding: 10px 20px; gap: 12px; flex-wrap: wrap;
                background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            }
            [data-bs-theme="dark"] .hc-action-bar { background: #151d2e; border-color: #283449; }

            .hc-stat-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .hc-stat {
                display: inline-flex; align-items: center; gap: 5px;
                padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;
            }
            .hc-stat-on  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; cursor:pointer; user-select:none; transition:filter .15s; }
            .hc-stat-off { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; cursor:pointer; user-select:none; transition:filter .15s; }
            .hc-stat-on:hover, .hc-stat-off:hover { filter:brightness(.93); }
            [data-bs-theme="dark"] .hc-stat-on  { background: #052e16; border-color: #14532d; color: #86efac; }
            [data-bs-theme="dark"] .hc-stat-off { background: #450a0a; border-color: #7f1d1d; color: #fca5a5; }

            /* ── Holiday list panel ──────────────────────────────────────────── */
            .hc-list-panel { border-top:1px solid #e2e8f0; padding:14px 20px 18px; max-height:300px; overflow-y:auto; }
            [data-bs-theme="dark"] .hc-list-panel { border-top-color:#283449; }
            .hc-list-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
            .hc-list-title  { font-size:13px; font-weight:700; color:#374151; }
            [data-bs-theme="dark"] .hc-list-title { color:#e8edf5; }
            .hc-list-close  { background:none; border:none; font-size:17px; line-height:1; cursor:pointer; color:#94a3b8; padding:0; }
            .hc-list-close:hover { color:#475569; }
            .hc-list-row    { display:flex; align-items:center; gap:10px; padding:7px 10px; border-radius:8px; border-bottom:1px solid #f1f5f9; font-size:13px; }
            .hc-list-row:last-child { border-bottom:none; }
            [data-bs-theme="dark"] .hc-list-row { border-bottom-color:#1c2740; }
            .hc-list-date   { font-size:11.5px; font-weight:600; color:#64748b; min-width:75px; }
            [data-bs-theme="dark"] .hc-list-date { color:#9fb0c7; }
            .hc-list-name   { flex:1; font-weight:600; color:#1e293b; }
            [data-bs-theme="dark"] .hc-list-name { color:#e8edf5; }
            .hc-list-typetag { font-size:10px; padding:2px 8px; border-radius:99px; font-weight:700; white-space:nowrap; }
            .hlt-regular { background:#dbeafe; color:#1e40af; }
            .hlt-special { background:#ffedd5; color:#c2410c; }
            .hlt-custom  { background:#ede9fe; color:#6d28d9; }
            [data-bs-theme="dark"] .hlt-regular { background:#172554; color:#93c5fd; }
            [data-bs-theme="dark"] .hlt-special { background:#431407; color:#fdba74; }
            [data-bs-theme="dark"] .hlt-custom  { background:#2e1065; color:#c4b5fd; }
            .hc-list-tgl { height:26px; padding:0 11px; font-size:11px; font-weight:700; border:none; border-radius:6px; cursor:pointer; white-space:nowrap; transition:background .15s; }
            .hlt-btn-enable  { background:#dcfce7; color:#15803d; }
            .hlt-btn-enable:hover { background:#bbf7d0; }
            .hlt-btn-disable { background:#fee2e2; color:#b91c1c; }
            .hlt-btn-disable:hover { background:#fecaca; }
            [data-bs-theme="dark"] .hlt-btn-enable  { background:#052e16; color:#86efac; }
            [data-bs-theme="dark"] .hlt-btn-disable { background:#450a0a; color:#fca5a5; }

            .hc-btn-group { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
            .hc-pill {
                display: inline-flex; align-items: center; gap: 5px;
                border: none; color: #fff; font-size: 12px; font-weight: 600;
                padding: 6px 14px; border-radius: 8px; cursor: pointer;
                transition: opacity .15s; line-height: 1.4; white-space: nowrap;
            }
            .hc-pill:hover { opacity: .85; }
            .hc-pill:disabled { opacity: .5; cursor: not-allowed; }
            .hc-pill-green  { background: #16a34a; }
            .hc-pill-red    { background: #dc2626; }
            .hc-pill-indigo { background: #6366f1; }

            /* ── Day-of-week header ──────────────────────────────────────── */
            .hc-dow {
                display: grid; grid-template-columns: repeat(7, 1fr);
                padding: 10px 16px 2px; gap: 3px;
            }
            .hc-dow span {
                text-align: center; font-size: 0.68rem; font-weight: 700;
                color: #94a3b8; padding: 4px 0; text-transform: uppercase; letter-spacing: .5px;
            }
            [data-bs-theme="dark"] .hc-dow span { color: #475569; }

            /* ── Viewport + slide animation ──────────────────────────────── */
            .hc-viewport { padding: 4px 16px 18px; overflow: hidden; }
            .hc-view { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
            @keyframes hcSlideRight { from { transform:translateX(52px); opacity:0; } to { transform:none; opacity:1; } }
            @keyframes hcSlideLeft  { from { transform:translateX(-52px); opacity:0; } to { transform:none; opacity:1; } }
            .hc-anim-right { animation: hcSlideRight .26s cubic-bezier(.25,.1,.25,1) both; }
            .hc-anim-left  { animation: hcSlideLeft  .26s cubic-bezier(.25,.1,.25,1) both; }

            /* ── Day cells ───────────────────────────────────────────────── */
            .hc-cell {
                display: flex; flex-direction: column; align-items: center;
                justify-content: flex-start; padding: 7px 2px 5px; border-radius: 9px;
                min-height: 54px; cursor: default; position: relative;
                transition: transform .12s, box-shadow .12s;
            }
            .hc-cell-num { font-size: 0.9rem; font-weight: 600; line-height: 1; color: #374151; }
            [data-bs-theme="dark"] .hc-cell-num { color: #cbd5e1; }
            .hc-cell.hc-muted .hc-cell-num { color: #d1d5db; }
            [data-bs-theme="dark"] .hc-cell.hc-muted .hc-cell-num { color: #334155; }

            .hc-cell-name {
                font-size: 0.5rem; line-height: 1.2; text-align: center;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                width: 100%; padding: 0 2px; margin-top: 3px; opacity: 0.85;
            }
            @media (max-width: 500px) { .hc-cell-name { display: none; } }

            .hc-dot { position: absolute; bottom: 5px; width: 5px; height: 5px; border-radius: 50%; }

            .hc-cell.hday { cursor: pointer; }
            .hc-cell.hday:hover { transform: scale(1.13); box-shadow: 0 4px 14px rgba(0,0,0,0.16); z-index: 3; }
            .hc-cell.hday:active { transform: scale(1.05); }

            .hc-cell.ht-regular { background: #dbeafe; }
            .hc-cell.ht-regular .hc-cell-num  { color: #1e40af; }
            .hc-cell.ht-regular .hc-cell-name { color: #1e40af; }
            .hc-cell.ht-regular .hc-dot       { background: #1e40af; }

            .hc-cell.ht-special { background: #fef3c7; }
            .hc-cell.ht-special .hc-cell-num  { color: #92400e; }
            .hc-cell.ht-special .hc-cell-name { color: #92400e; }
            .hc-cell.ht-special .hc-dot       { background: #d97706; }

            .hc-cell.ht-custom { background: #ede9fe; }
            .hc-cell.ht-custom .hc-cell-num  { color: #5b21b6; }
            .hc-cell.ht-custom .hc-cell-name { color: #5b21b6; }
            .hc-cell.ht-custom .hc-dot       { background: #7c3aed; }

            .hc-cell.ht-disabled { background: #f1f5f9; }
            .hc-cell.ht-disabled .hc-cell-num  { color: #94a3b8; text-decoration: line-through; }
            .hc-cell.ht-disabled .hc-cell-name { color: #94a3b8; text-decoration: line-through; }
            .hc-cell.ht-disabled .hc-dot       { background: #cbd5e1; }

            .hc-cell.hc-today { outline: 2px solid #6366f1; outline-offset: -2px; }
            .hc-cell.hc-today:not(.hday) .hc-cell-num { color: #4f46e5; font-weight: 800; }
            .hc-cell.hc-blank:hover { background: #f0f9ff; cursor: pointer; }
            [data-bs-theme="dark"] .hc-cell.hc-blank:hover { background: #172554; }

            [data-bs-theme="dark"] .hc-cell.ht-regular { background: rgba(30,58,138,.28); }
            [data-bs-theme="dark"] .hc-cell.ht-regular .hc-cell-num  { color: #93c5fd; }
            [data-bs-theme="dark"] .hc-cell.ht-regular .hc-cell-name { color: #93c5fd; }
            [data-bs-theme="dark"] .hc-cell.ht-special { background: rgba(120,53,15,.32); }
            [data-bs-theme="dark"] .hc-cell.ht-special .hc-cell-num  { color: #fbbf24; }
            [data-bs-theme="dark"] .hc-cell.ht-special .hc-cell-name { color: #fbbf24; }
            [data-bs-theme="dark"] .hc-cell.ht-custom  { background: rgba(76,29,149,.32); }
            [data-bs-theme="dark"] .hc-cell.ht-custom  .hc-cell-num  { color: #c4b5fd; }
            [data-bs-theme="dark"] .hc-cell.ht-custom  .hc-cell-name { color: #c4b5fd; }
            [data-bs-theme="dark"] .hc-cell.ht-disabled { background: #283449; }
            [data-bs-theme="dark"] .hc-cell.ht-disabled .hc-cell-num { color: #475569; }
            [data-bs-theme="dark"] .hc-cell.hc-today { outline-color: #818cf8; }

            /* ── Footer row ──────────────────────────────────────────────── */
            .hc-footer-row {
                display: flex; flex-wrap: wrap; align-items: center;
                justify-content: space-between; gap: 12px;
            }
            .hc-legend { display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
            .hc-leg-item {
                display: inline-flex; align-items: center; gap: 6px;
                padding: 4px 10px; border-radius: 7px; font-size: 11.5px; font-weight: 600;
                cursor: pointer; user-select: none;
                transition: filter .12s, transform .08s;
            }
            .hc-leg-item:hover  { filter: brightness(0.95); }
            .hc-leg-item:active { transform: translateY(1px); }
            .hc-leg-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
            .hc-leg-regular { background:#dbeafe; color:#1e40af; border-left:3px solid #1e40af; }
            .hc-leg-regular .hc-leg-dot { background:#1e40af; }
            .hc-leg-special { background:#fef3c7; color:#92400e; border-left:3px solid #d97706; }
            .hc-leg-special .hc-leg-dot { background:#d97706; }
            .hc-leg-custom  { background:#ede9fe; color:#5b21b6; border-left:3px solid #7c3aed; }
            .hc-leg-custom  .hc-leg-dot { background:#7c3aed; }
            .hc-leg-off     { background:#f1f5f9; color:#64748b; border-left:3px solid #94a3b8; }
            [data-bs-theme="dark"] .hc-leg-regular { background:rgba(30,58,138,.2); color:#93c5fd; }
            [data-bs-theme="dark"] .hc-leg-special { background:rgba(120,53,15,.2); color:#fbbf24; }
            [data-bs-theme="dark"] .hc-leg-custom  { background:rgba(76,29,149,.2); color:#c4b5fd; }
            [data-bs-theme="dark"] .hc-leg-off     { background:#283449; color:#64748b; }

            .hc-rates { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
            .hc-rate { font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
            .hc-rate::before { content:''; display:inline-block; width:7px; height:7px; border-radius:50%; flex-shrink:0; }
            .hc-rate-regular { color:#1e40af; } .hc-rate-regular::before { background:#1e40af; }
            .hc-rate-special { color:#92400e; } .hc-rate-special::before { background:#d97706; }
            .hc-rate-custom  { color:#5b21b6; } .hc-rate-custom::before  { background:#7c3aed; }
            [data-bs-theme="dark"] .hc-rate-regular { color:#93c5fd; }
            [data-bs-theme="dark"] .hc-rate-special { color:#fbbf24; }
            [data-bs-theme="dark"] .hc-rate-custom  { color:#c4b5fd; }

            /* ── Context panel ───────────────────────────────────────────── */
            .hc-ctx {
                position: fixed; z-index: 9998; background: #fff;
                border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;
                min-width: 220px; box-shadow: 0 10px 32px rgba(0,0,0,0.14);
            }
            .hc-ctx-title { font-weight: 700; font-size: 13px; color: #1e3a8a; margin-bottom: 3px; }
            .hc-ctx-meta  { font-size: 11px; color: #64748b; margin-bottom: 14px; }
            .hc-ctx-btn {
                width: 100%; padding: 7px 12px; font-weight: 600; font-size: 13px;
                cursor: pointer; border: none; border-radius: 7px; color: #fff;
                display: flex; align-items: center; justify-content: center;
            }
            [data-bs-theme="dark"] .hc-ctx { background: #1c2740; border-color: #283449; box-shadow: 0 10px 32px rgba(0,0,0,0.4); }
            [data-bs-theme="dark"] .hc-ctx-title { color: #93c5fd; }
            [data-bs-theme="dark"] .hc-ctx-meta  { color: #64748b; }

            @keyframes hcalFlash { from { opacity:0; transform:translateX(16px); } to { opacity:1; transform:none; } }
            </style>

            {{-- Flatpickr (date picker for Add Custom Holiday modal) --}}
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

            {{-- Calendar JavaScript --}}
            <script>
            (function () {
                // ── Server-side data ──────────────────────────────────────────────
                const officialMap = @json($officialMap);
                let   calData     = @json($holidayCalendar);
                let   holidayMap  = toMap(calData);
                let   calYear     = {{ $holidayYear }};
                let   calMonth    = new Date().getMonth();
                const csrfToken   = '{{ csrf_token() }}';
                const toggleUrl   = '{{ route("holidays.toggle") }}';
                const bulkUrl     = '{{ route("holidays.bulk-toggle") }}';
                const calApiUrl   = '{{ route("holidays.calendar") }}';

                const MONTHS = ['January','February','March','April','May','June',
                                'July','August','September','October','November','December'];
                const TODAY  = new Date().toISOString().slice(0, 10);

                function toMap(arr) {
                    const m = {};
                    for (const h of arr) m[h.date] = h;
                    return m;
                }

                // ── Render single month ───────────────────────────────────────────
                function renderCalendar(animDir) {
                    const view = document.getElementById('hcal-view');
                    view.innerHTML = buildMonthHTML(calYear, calMonth);
                    view.className = 'hc-view';
                    if (animDir) {
                        void view.offsetWidth;
                        view.classList.add(animDir === 'next' ? 'hc-anim-right' : 'hc-anim-left');
                    }
                    updateHeader();
                    updateStats();
                    attachCellListeners(view);
                }

                function buildMonthHTML(year, month) {
                    const offset = new Date(year, month, 1).getDay();
                    const days   = new Date(year, month + 1, 0).getDate();
                    let html = '';
                    for (let i = 0; i < offset; i++) html += '<div class="hc-cell hc-empty"></div>';
                    for (let d = 1; d <= days; d++) {
                        const mm   = String(month + 1).padStart(2, '0');
                        const dd   = String(d).padStart(2, '0');
                        const date = `${year}-${mm}-${dd}`;
                        const h    = holidayMap[date];
                        const tc   = date === TODAY ? ' hc-today' : '';
                        if (h) {
                            const cls  = 'ht-' + (h.is_active ? (h.type || 'custom') : 'disabled');
                            const name = h.title.length > 13 ? h.title.slice(0, 12) + '…' : h.title;
                            html += `<div class="hc-cell hday ${cls}${tc}" data-date="${date}">` +
                                    `<span class="hc-cell-num">${d}</span>` +
                                    `<span class="hc-cell-name">${name}</span>` +
                                    `<span class="hc-dot"></span></div>`;
                        } else {
                            html += `<div class="hc-cell hc-blank${tc}" data-date="${date}">` +
                                    `<span class="hc-cell-num">${d}</span></div>`;
                        }
                    }
                    return html;
                }

                function attachCellListeners(view) {
                    view.querySelectorAll('.hday').forEach(cell => {
                        const date    = cell.dataset.date;
                        const holiday = holidayMap[date];
                        cell.addEventListener('mouseenter', e => showTip(e, holiday));
                        cell.addEventListener('mousemove',  e => moveTip(e));
                        cell.addEventListener('mouseleave', hideTip);
                        cell.addEventListener('click',      () => doClick(date, cell, holiday));
                    });
                    view.querySelectorAll('.hc-blank').forEach(cell => {
                        cell.addEventListener('click', () => openAddModal(cell.dataset.date));
                    });
                }

                let hmodFp = null;

                function openAddModal(date) {
                    if (hmodFp) { hmodFp.setDate(date, true); }
                    else {
                        const inp = document.getElementById('hmod-date');
                        if (inp) { inp.value = date; }
                    }
                    new bootstrap.Modal(document.getElementById('addHolidayModal')).show();
                }

                // ── Header + quick-jump controls ──────────────────────────────────
                function updateHeader() {
                    document.getElementById('hc-month-heading').textContent = MONTHS[calMonth] + ' ' + calYear;
                    document.getElementById('hc-yr-val').textContent = calYear;
                    const sel = document.getElementById('hc-month-sel');
                    if (sel) sel.value = calMonth;
                }

                document.getElementById('hcal-prev').addEventListener('click', () => navMonth(-1));
                document.getElementById('hcal-next').addEventListener('click', () => navMonth(1));

                document.getElementById('hc-month-sel').addEventListener('change', function () {
                    const dir = parseInt(this.value) > calMonth ? 'next' : 'prev';
                    calMonth  = parseInt(this.value);
                    renderCalendar(dir);
                });
                document.getElementById('hc-yr-dec').addEventListener('click', () => changeYear(-1));
                document.getElementById('hc-yr-inc').addEventListener('click', () => changeYear(1));

                async function navMonth(delta) {
                    let m = calMonth + delta, y = calYear;
                    if (m < 0)  { m = 11; y--; }
                    if (m > 11) { m = 0;  y++; }
                    if (y !== calYear) await loadYear(y);
                    calMonth = m;
                    renderCalendar(delta > 0 ? 'next' : 'prev');
                }

                async function changeYear(delta) {
                    await loadYear(calYear + delta);
                    renderCalendar('next');
                }

                async function loadYear(year) {
                    const vp = document.getElementById('hc-viewport');
                    vp.style.opacity = '0.4';
                    try {
                        const r    = await fetch(`${calApiUrl}?year=${year}`, { headers: { 'Accept':'application/json', 'X-CSRF-TOKEN':csrfToken } });
                        const data = await r.json();
                        calYear = data.year; calData = data.calendar; holidayMap = toMap(calData);
                    } catch { flash('Could not load year — please try again.', 'error'); }
                    finally  { vp.style.opacity = ''; }
                }

                // ── Stats ─────────────────────────────────────────────────────────
                function updateStats() {
                    const all = Object.values(holidayMap);
                    const act = all.filter(h => h.is_active).length;
                    document.getElementById('hstat-active').textContent   = act;
                    document.getElementById('hstat-disabled').textContent = all.length - act;
                }

                // ── Holiday list panel ────────────────────────────────────────────
                const listPanel = document.getElementById('hc-list-panel');
                const listTitle = document.getElementById('hc-list-title');
                const listBody  = document.getElementById('hc-list-body');
                let   listFilter = null; // 'active'|'disabled'|'regular'|'special'|'custom'|null

                document.getElementById('hc-list-close').addEventListener('click', closeListPanel);
                document.querySelector('.hc-stat-on') .addEventListener('click', () => toggleListPanel('active'));
                document.querySelector('.hc-stat-off').addEventListener('click', () => toggleListPanel('disabled'));

                // Legend items act as type filters for the list panel.
                document.querySelector('.hc-leg-regular').addEventListener('click', () => toggleListPanel('regular'));
                document.querySelector('.hc-leg-special').addEventListener('click', () => toggleListPanel('special'));
                document.querySelector('.hc-leg-custom') .addEventListener('click', () => toggleListPanel('custom'));
                document.querySelector('.hc-leg-off')    .addEventListener('click', () => toggleListPanel('disabled'));

                function toggleListPanel(filter) {
                    if (listFilter === filter) { closeListPanel(); return; }
                    listFilter = filter;
                    renderListPanel();
                    listPanel.style.display = 'block';
                    listPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }

                function closeListPanel() {
                    listFilter = null;
                    listPanel.style.display = 'none';
                }

                function renderListPanel() {
                    const TYPE_LABEL = { regular:'Regular', special:'Special', custom:'Custom' };

                    // Predicate per filter. Type filters show ACTIVE holidays of that
                    // type (disabled ones have their own "Disabled" category/colour).
                    const matches = {
                        active:   h => h.is_active,
                        disabled: h => !h.is_active,
                        regular:  h => h.is_active && (h.type || 'custom') === 'regular',
                        special:  h => h.is_active && (h.type || 'custom') === 'special',
                        custom:   h => h.is_active && (h.type || 'custom') === 'custom',
                    };
                    const TITLE = {
                        active:   'Active Holidays',
                        disabled: 'Disabled Holidays',
                        regular:  'Regular Holidays',
                        special:  'Special (Non-Working) Holidays',
                        custom:   'Custom Holidays',
                    };

                    const holidays = Object.values(holidayMap)
                        .filter(matches[listFilter] || (() => false))
                        .sort((a, b) => a.date.localeCompare(b.date));

                    listTitle.textContent = `${TITLE[listFilter] || 'Holidays'} (${holidays.length})`;

                    if (!holidays.length) {
                        listBody.innerHTML = '<div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px;">No holidays in this category.</div>';
                        return;
                    }

                    listBody.innerHTML = holidays.map(h => {
                        const [y, m, d] = h.date.split('-');
                        const label = TYPE_LABEL[h.type] || 'Custom';
                        const btnCls  = h.is_active ? 'hlt-btn-disable' : 'hlt-btn-enable';
                        const btnText = h.is_active ? '⊗ Disable' : '✓ Enable';
                        return `<div class="hc-list-row">
                            <span class="hc-list-date">${m}/${d}/${y}</span>
                            <span class="hc-list-name">${h.title}</span>
                            <span class="hc-list-typetag hlt-${h.type || 'custom'}">${label}</span>
                            <button class="hc-list-tgl ${btnCls}" data-date="${h.date}">${btnText}</button>
                        </div>`;
                    }).join('');

                    listBody.querySelectorAll('.hc-list-tgl').forEach(btn => {
                        btn.addEventListener('click', () => doToggleFromList(btn.dataset.date));
                    });
                }

                async function doToggleFromList(date) {
                    const btn = listBody.querySelector(`.hc-list-tgl[data-date="${date}"]`);
                    if (btn) { btn.disabled = true; btn.textContent = '…'; }
                    try {
                        const r = await post(toggleUrl, { date });
                        if (r.success) {
                            if (holidayMap[date]) holidayMap[date].is_active = r.is_active;
                            updateStats();
                            renderCalendar();
                            renderListPanel();
                            flash(r.is_active ? 'Holiday enabled.' : 'Holiday disabled.', r.is_active ? 'success' : 'warn');
                        } else {
                            flash('Update failed.', 'error');
                            if (btn) btn.disabled = false;
                        }
                    } catch {
                        flash('Network error.', 'error');
                        if (btn) btn.disabled = false;
                    }
                }

                // ── Tooltip ───────────────────────────────────────────────────────
                const tip     = document.getElementById('hcal-tip');
                const tipName = document.getElementById('hcal-tip-name');
                const tipType = document.getElementById('hcal-tip-type');
                const tipAct  = document.getElementById('hcal-tip-action');
                const TL = { regular:'Regular Holiday', special:'Special (Non-Working)', custom:'Custom Holiday' };

                function showTip(e, h) {
                    tipName.textContent = h.title;
                    tipType.textContent = TL[h.type] || 'Holiday';
                    tipAct.textContent  = h.is_official ? (h.is_active ? 'Click to disable' : 'Click to enable') : 'Click for options';
                    tip.style.display = 'block'; moveTip(e);
                }
                function moveTip(e) {
                    tip.style.left = Math.min(e.clientX + 14, window.innerWidth - 260) + 'px';
                    tip.style.top  = (e.clientY - 10) + 'px';
                }
                function hideTip() { tip.style.display = 'none'; }

                // ── Click dispatcher ──────────────────────────────────────────────
                function doClick(date, cell, holiday) {
                    hideTip();
                    if (holiday.is_official) doToggle(date, cell, holiday);
                    else showCtx(date, cell, holiday);
                }

                // ── Context panel ─────────────────────────────────────────────────
                const ctx      = document.getElementById('hcal-ctx');
                const ctxTitle = document.getElementById('hcal-ctx-title');
                const ctxMeta  = document.getElementById('hcal-ctx-meta');
                const ctxTgl   = document.getElementById('hcal-ctx-toggle');
                const ctxEdit  = document.getElementById('hcal-ctx-edit');
                const ctxDel   = document.getElementById('hcal-ctx-del');
                let   ctxState = null;

                function showCtx(date, cell, holiday) {
                    ctxState = { date, cell, holiday };
                    ctxTitle.textContent = holiday.title;
                    ctxMeta.textContent  = 'Custom Holiday · ' + (holiday.is_active ? 'Active' : 'Disabled');
                    ctxTgl.style.background = holiday.is_active ? '#f59e0b' : '#16a34a';
                    ctxTgl.innerHTML = holiday.is_active
                        ? '<i class="fas fa-ban" style="margin-right:5px;"></i>Disable'
                        : '<i class="fas fa-check" style="margin-right:5px;"></i>Enable';
                    const rect = cell.getBoundingClientRect();
                    let x = rect.right + 10, y = rect.top - 4;
                    if (x + 250 > window.innerWidth)  x = rect.left - 260;
                    if (y + 195 > window.innerHeight) y = window.innerHeight - 205;
                    if (x < 6) x = 6;
                    ctx.style.left = x + 'px'; ctx.style.top = y + 'px'; ctx.style.display = 'block';
                }

                function hideCtx() { ctx.style.display = 'none'; ctxState = null; }

                document.addEventListener('click', e => {
                    if (ctx.style.display !== 'none' && !ctx.contains(e.target)) hideCtx();
                }, { capture: true });

                ctxTgl.addEventListener('click', () => { const s = ctxState; hideCtx(); if (s) doToggle(s.date, s.cell, s.holiday); });
                ctxEdit.addEventListener('click', () => { const s = ctxState; hideCtx(); if (s) openEdit(s.holiday); });
                ctxDel.addEventListener('click', async () => {
                    const s = ctxState; hideCtx();
                    if (!s || !confirm(`Remove "${s.holiday.title}"? The holiday premium will no longer apply.`)) return;
                    await doDelete(s.date, s.cell, s.holiday);
                });

                // ── AJAX delete ───────────────────────────────────────────────────
                async function doDelete(date, cell, holiday) {
                    cell.style.opacity = '0.35'; cell.style.pointerEvents = 'none';
                    try {
                        const r = await httpReq(`/holidays/${holiday.id}`, {}, 'DELETE');
                        if (r.success) {
                            delete holidayMap[date]; renderCalendar();
                            flash(`"${holiday.title}" removed.`, 'success');
                        } else { cell.style.opacity = ''; cell.style.pointerEvents = ''; flash('Delete failed.', 'error'); }
                    } catch { cell.style.opacity = ''; cell.style.pointerEvents = ''; flash('Network error.', 'error'); }
                }

                // ── Edit custom holiday ────────────────────────────────────────────
                const editModalEl = document.getElementById('editHolidayModal');
                const hmodEid     = document.getElementById('hmod-eid');
                const hmodEdate   = document.getElementById('hmod-edate');
                const hmodEtitle  = document.getElementById('hmod-etitle');
                const hmodEsave   = document.getElementById('hmod-esave');

                function fmtDate(d) { const [y,m,dd]=d.split('-'); return `${m}/${dd}/${y}`; }

                function openEdit(holiday) {
                    hmodEid.value = holiday.id;
                    hmodEdate.textContent = fmtDate(holiday.date);
                    hmodEtitle.value = holiday.title;
                    hmodEtitle.classList.remove('is-invalid');
                    new bootstrap.Modal(editModalEl).show();
                }

                if (hmodEsave) {
                    hmodEsave.addEventListener('click', async () => {
                        const id = hmodEid.value, title = hmodEtitle.value.trim();
                        if (!title) { hmodEtitle.classList.add('is-invalid'); hmodEtitle.focus(); return; }
                        hmodEtitle.classList.remove('is-invalid');
                        hmodEsave.disabled = true;
                        try {
                            const data = await httpReq(`/holidays/${id}`, { title }, 'PUT');
                            if (data.success) {
                                const h = data.holiday;
                                if (holidayMap[h.date]) holidayMap[h.date].title = h.title;
                                renderCalendar();
                                bootstrap.Modal.getInstance(editModalEl)?.hide();
                                flash(`Label updated to "${h.title}".`, 'success');
                            } else { flash(data.message || 'Update failed.', 'error'); }
                        } catch { flash('Network error.', 'error'); }
                        finally  { hmodEsave.disabled = false; }
                    });
                }

                // ── Single toggle ──────────────────────────────────────────────────
                async function doToggle(date, cell, holiday) {
                    cell.style.opacity = '0.45'; cell.style.pointerEvents = 'none'; hideTip();
                    try {
                        const r = await post(toggleUrl, { date });
                        if (r.success) {
                            holiday.is_active = r.is_active;
                            if (holidayMap[date]) holidayMap[date].is_active = r.is_active;
                            cell.className = `hc-cell hday ht-${r.is_active ? (holiday.type||'custom') : 'disabled'}${date===TODAY?' hc-today':''}`;
                            updateStats();
                            flash(r.is_active ? 'Holiday enabled.' : 'Holiday disabled.', r.is_active ? 'success' : 'warn');
                        } else { flash('Update failed.', 'error'); }
                    } catch { flash('Network error.', 'error'); }
                    finally  { cell.style.opacity = ''; cell.style.pointerEvents = ''; }
                }

                // ── Bulk toggle ────────────────────────────────────────────────────
                document.getElementById('hcal-enable-all').addEventListener('click', () => doBulk('enable'));
                document.getElementById('hcal-disable-all').addEventListener('click', () => {
                    if (confirm(`Disable all ${calYear} holidays? You can re-enable them anytime.`)) doBulk('disable');
                });

                async function doBulk(action) {
                    const btns = [document.getElementById('hcal-enable-all'), document.getElementById('hcal-disable-all')];
                    btns.forEach(b => b.disabled = true);
                    const vp = document.getElementById('hc-viewport');
                    vp.style.opacity = '0.4';
                    try {
                        const r = await post(bulkUrl, { action, year: calYear });
                        if (r.success) {
                            calData = r.calendar; holidayMap = toMap(calData); renderCalendar();
                            flash(action === 'enable' ? 'All holidays enabled.' : 'All holidays disabled.', action === 'enable' ? 'success' : 'warn');
                        } else { flash('Bulk update failed.', 'error'); }
                    } catch { flash('Network error.', 'error'); }
                    finally  { vp.style.opacity = ''; btns.forEach(b => b.disabled = false); }
                }

                // ── Fetch helpers ──────────────────────────────────────────────────
                async function post(url, body) { return httpReq(url, body, 'POST'); }
                async function httpReq(url, body, method) {
                    const opts = { method, headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrfToken, 'Accept':'application/json' } };
                    if (method !== 'GET' && method !== 'HEAD') opts.body = JSON.stringify(body);
                    return (await fetch(url, opts)).json();
                }

                // ── Flash toast ────────────────────────────────────────────────────
                function flash(msg, type) {
                    let c = document.getElementById('hcal-flash-wrap');
                    if (!c) {
                        c = document.createElement('div');
                        c.id = 'hcal-flash-wrap';
                        c.style.cssText = 'position:fixed;top:76px;right:20px;z-index:9997;display:flex;flex-direction:column;gap:6px;min-width:260px;max-width:360px;';
                        document.body.appendChild(c);
                    }
                    const pal = {
                        success:{ bg:'#dcfce7',bd:'#bbf7d0',tx:'#166534',ic:'check-circle' },
                        warn:   { bg:'#fef3c7',bd:'#fde68a',tx:'#92400e',ic:'exclamation-triangle' },
                        error:  { bg:'#fee2e2',bd:'#fecaca',tx:'#991b1b',ic:'times-circle' },
                    }[type] || { bg:'#f0fdf4',bd:'#bbf7d0',tx:'#166534',ic:'check-circle' };
                    const el = document.createElement('div');
                    el.style.cssText = `background:${pal.bg};border:1px solid ${pal.bd};color:${pal.tx};padding:10px 14px;border-radius:9px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);animation:hcalFlash .22s ease;`;
                    el.innerHTML = `<i class="fas fa-${pal.ic}"></i> ${msg}`;
                    c.appendChild(el);
                    setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 320); }, 2800);
                }

                // ── Add modal: Flatpickr (MM-DD-YYYY display) + PH holiday auto-fill ──
                const hmodDateEl = document.getElementById('hmod-date');
                const hmodTitle  = document.getElementById('hmod-title');
                const hmodRec    = document.getElementById('hmod-recognized');
                const hmodRecTx  = document.getElementById('hmod-recognized-text');
                if (hmodDateEl) {
                    hmodFp = flatpickr(hmodDateEl, {
                        dateFormat: 'Y-m-d',
                        altInput:   true,
                        altFormat:  'm-d-Y',
                        allowInput: true,
                        onChange: function (selectedDates, dateStr) {
                            const info = officialMap[dateStr];
                            if (info) {
                                hmodRec.style.display = 'block';
                                hmodRecTx.textContent  = info.title + ' — ' + info.type;
                                if (!hmodTitle.value.trim()) hmodTitle.value = info.title;
                            } else { hmodRec.style.display = 'none'; }
                        },
                    });
                }

                // ── Touch swipe (mobile) ───────────────────────────────────────────
                const vpEl = document.getElementById('hc-viewport');
                let touchX = 0;
                vpEl.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive:true });
                vpEl.addEventListener('touchend',   e => {
                    const dx = e.changedTouches[0].clientX - touchX;
                    if (Math.abs(dx) > 40) navMonth(dx < 0 ? 1 : -1);
                }, { passive:true });

                // ── Keyboard navigation ────────────────────────────────────────────
                document.addEventListener('keydown', e => {
                    if (!document.querySelector('#holiday.show.active')) return;
                    if (e.key === 'ArrowLeft')  navMonth(-1);
                    if (e.key === 'ArrowRight') navMonth(1);
                });

                // ── Auto-open Add modal on validation error ────────────────────────
                @if($errors->has('date'))
                window.addEventListener('load', () => {
                    const el = document.getElementById('addHolidayModal');
                    if (el) new bootstrap.Modal(el).show();
                });
                @endif

                // ── Init ───────────────────────────────────────────────────────────
                renderCalendar();
            })();
            </script>
        </div>
    </div>
</div>

<style>
.settings-wrapper {
    background: white;
    border-radius: 0.5rem;
    padding: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.settings-header h1 {
    font-size: 1.875rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.settings-header p {
    color: #6b7280;
    font-size: 0.95rem;
}

.settings-tabs {
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 2rem;
}

.settings-tabs .nav-link {
    color: #6b7280;
    border: none;
    padding: 1rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.settings-tabs .nav-link:hover {
    color: #6366f1;
}

.settings-tabs .nav-link.active {
    color: #6366f1;
    border-bottom: 3px solid #6366f1;
    background: none;
}

.settings-content {
    padding: 2rem 0;
}

.form-group label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-control {
    border: 1px solid #d1d5db;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
}

.form-control:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn-primary, .btn-success {
    background-color: #6366f1;
    border: none;
}

.btn-primary:hover, .btn-success:hover {
    background-color: #4f46e5;
}

.labor-types-form {
    background: #f9fafb;
    padding: 1.5rem;
    border-radius: 0.5rem;
}

.table {
    margin-bottom: 0;
}

.table th {
    background-color: #f3f4f6;
    font-weight: 600;
    color: #374151;
}

.table-hover tbody tr:hover {
    background-color: #f9fafb;
}

/* Dynamic payroll rate configuration */
.settings-section-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e3a8a;
}

.payroll-config-section {
    padding: 1.5rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.payroll-formula {
    font-size: 0.85rem;
    color: #475569;
    background: #eef2ff;
    border: 1px solid #e0e7ff;
    border-radius: 8px;
    padding: 12px 16px;
}

/* ── ps-card system (Payroll / Labor Settings) ──────────────────────────── */
.ps-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.ps-card-header { display:flex; align-items:flex-start; gap:10px; padding:14px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
.ps-card-header > i { color:#1e3a8a; margin-top:3px; flex-shrink:0; }
.ps-card-header h6 { font-weight:700; color:#0f172a; margin:0 0 2px; font-size:.95rem; }
.ps-card-header p { color:#64748b; font-size:.8rem; margin:0; line-height:1.4; }
.ps-card-body { padding:20px; }
.ps-card-body.p-0 { padding:0; }
.ps-label { font-weight:600; font-size:.875rem; color:#374151; margin-bottom:6px; display:block; }
.ps-input { border-color:#e2e8f0 !important; }
.ps-input:focus { border-color:#6366f1 !important; box-shadow:0 0 0 3px rgba(99,102,241,.1) !important; }
.ps-ig-text { background:#f8fafc !important; border-color:#e2e8f0 !important; font-weight:600; color:#374151; }

/* Toggle switch (Sunday rest day) */
.ps-toggle-row { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; }
.ps-toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
.ps-toggle-switch input { opacity:0; width:0; height:0; }
.ps-toggle-slider { position:absolute; inset:0; background:#cbd5e1; border-radius:99px; cursor:pointer; transition:background .2s; }
.ps-toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.ps-toggle-switch input:checked + .ps-toggle-slider { background:#16a34a; }
.ps-toggle-switch input:checked + .ps-toggle-slider::before { transform:translateX(20px); }
.ps-toggle-label { font-size:.875rem; color:#374151; line-height:1.4; }
.ps-toggle-status { font-weight:700; color:#16a34a; }
.ps-toggle-switch input:not(:checked) ~ .ps-toggle-label .ps-toggle-status,
.ps-toggle-status.off { color:#94a3b8; }
[data-bs-theme="dark"] .ps-toggle-row { background:#1c2740; border-color:#283449; }
[data-bs-theme="dark"] .ps-toggle-label { color:#cbd5e1; }
[data-bs-theme="dark"] .ps-toggle-slider { background:#374357; }

/* Derived rate chips (single display — no duplicate) */
.ps-rates-row { display:flex; gap:10px; flex-wrap:wrap; }
.ps-rate-chip { flex:1; min-width:130px; padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; }
.ps-rate-label { display:block; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:3px; }
.ps-rate-value { color:#1e40af; font-size:1rem; font-weight:700; }

/* Deduction chips */
.ps-deduct-chip { padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; }
.ps-deduct-label { display:block; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin-bottom:3px; }
.ps-deduct-val { display:block; color:#dc2626; font-size:1.15rem; margin:2px 0; }
.ps-deduct-info { color:#94a3b8; font-size:.78rem; }
.ps-deduct-total { padding:12px 16px; background:#fef3c7; border:1px solid #fde68a; border-radius:8px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
.ps-deduct-total-val { color:#92400e; font-size:1.2rem; font-weight:700; flex-shrink:0; }

/* Preview badge */
.ps-badge { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6366f1; background:#eef2ff; border:1px solid #e0e7ff; border-radius:99px; padding:2px 8px; vertical-align:middle; margin-left:4px; }

/* Action buttons */
.ps-save-btn { background:linear-gradient(135deg,#1e3a8a,#1e40af); color:#fff !important; border:none; padding:11px 32px; border-radius:8px; font-weight:600; box-shadow:0 4px 12px rgba(30,58,138,.2); }
.ps-save-btn:hover { background:linear-gradient(135deg,#1e40af,#2563eb); }
.ps-add-btn { background:#16a34a; color:#fff !important; border:none; font-weight:600; border-radius:8px; }
.ps-add-btn:hover { background:#15803d; }
/* ── Labor Type rows (lt-*) ─────────────────────────────────────────────── */
.lt-row { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-bottom:1px solid #e2e8f0; gap:12px; }
.lt-row:last-child { border-bottom:none; }
.lt-info { flex:1; min-width:0; }
.lt-name { display:block; font-weight:700; color:#1e3a8a; font-size:.95rem; margin-bottom:5px; }
.lt-rates { display:flex; gap:5px; flex-wrap:wrap; }
.lt-rate-pill { font-size:.74rem; color:#475569; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:99px; padding:2px 10px; }
.lt-rate-pill strong { color:#0f172a; font-weight:700; }
.lt-actions { flex-shrink:0; }
.lt-menu-btn { background:none; border:1px solid transparent; color:#94a3b8; font-size:20px; line-height:1; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; cursor:pointer; padding:0; transition:background .15s,color .15s,border-color .15s; }
.lt-menu-btn:hover { background:#f1f5f9; color:#1e3a8a; border-color:#e2e8f0; }
.lt-dropdown { min-width:140px; }
.lt-dropdown .dropdown-item { font-size:.875rem; padding:7px 14px; }
.lt-dropdown .dropdown-item i { width:14px; }
.lt-empty { padding:48px 24px; text-align:center; color:#94a3b8; }
.lt-empty i { font-size:2rem; opacity:.4; display:block; margin-bottom:8px; }
.lt-empty p { margin:0; font-size:.9rem; }

#lt-list-container { max-height:480px; overflow-y:auto; scroll-behavior:smooth; }

/* Dark mode — global settings page */
[data-bs-theme="dark"] .settings-wrapper { background: #1c2740; box-shadow: none; }
[data-bs-theme="dark"] .settings-header h1 { color: #e2e8f0; }
[data-bs-theme="dark"] .settings-header p { color: #94a3b8; }
[data-bs-theme="dark"] .settings-tabs { border-color: #283449; }
[data-bs-theme="dark"] .settings-tabs .nav-link { color: #94a3b8; }
[data-bs-theme="dark"] .settings-tabs .nav-link.active { color: #818cf8; border-color: #818cf8; }
[data-bs-theme="dark"] .settings-tabs .nav-link:hover { color: #818cf8; }
[data-bs-theme="dark"] .payroll-formula { background: #1e3a8a20; border-color: #1e3a8a; color: #93c5fd; }
[data-bs-theme="dark"] .payroll-config-section { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .labor-types-form { background: #151d2e; }
[data-bs-theme="dark"] .table th { background-color: #151d2e; color: #cbd5e1; }
[data-bs-theme="dark"] .table-hover tbody tr:hover { background-color: #283449; }
[data-bs-theme="dark"] .form-group label { color: #cbd5e1; }
[data-bs-theme="dark"] .ps-card { background:#1c2740; border-color:#283449; }
[data-bs-theme="dark"] .ps-card-header { background:#151d2e; border-color:#283449; }
[data-bs-theme="dark"] .ps-card-header h6 { color:#e2e8f0; }
[data-bs-theme="dark"] .ps-label { color:#cbd5e1; }
[data-bs-theme="dark"] .ps-input { background:#1c2740 !important; color:#e2e8f0; border-color:#283449 !important; }
[data-bs-theme="dark"] .ps-ig-text { background:#151d2e !important; border-color:#283449 !important; color:#94a3b8; }
[data-bs-theme="dark"] .ps-rate-chip { background:rgba(30,58,138,.2); border-color:#1e3a8a; }
[data-bs-theme="dark"] .ps-rate-value { color:#93c5fd; }
[data-bs-theme="dark"] .ps-deduct-chip { background:#151d2e; border-color:#283449; }
[data-bs-theme="dark"] .ps-deduct-label { color:#94a3b8; }
[data-bs-theme="dark"] .ps-deduct-val { color:#f87171; }
[data-bs-theme="dark"] .ps-deduct-total { background:rgba(251,191,36,.08); border-color:rgba(251,191,36,.2); }
[data-bs-theme="dark"] .ps-deduct-total-val { color:#fbbf24; }
[data-bs-theme="dark"] .ps-badge { background:rgba(99,102,241,.15); border-color:rgba(99,102,241,.3); }
[data-bs-theme="dark"] .lt-row { border-color:#283449; }
[data-bs-theme="dark"] .lt-name { color:#93c5fd; }
[data-bs-theme="dark"] .lt-rate-pill { background:#151d2e; border-color:#283449; color:#94a3b8; }
[data-bs-theme="dark"] .lt-rate-pill strong { color:#e2e8f0; }
[data-bs-theme="dark"] .lt-menu-btn:hover { background:#1c2740; color:#93c5fd; border-color:#283449; }
[data-bs-theme="dark"] .lt-dropdown { background:#1c2740; border-color:#283449; }
[data-bs-theme="dark"] .lt-dropdown .dropdown-item { color:#e8edf5; }
[data-bs-theme="dark"] .lt-dropdown .dropdown-item:hover { background:#283449; color:#e8edf5; }
[data-bs-theme="dark"] .lt-dropdown .dropdown-item.text-danger { color:#f87171 !important; }
[data-bs-theme="dark"] .lt-dropdown .dropdown-divider { border-color:#283449; }
</style>

<script>
// Initialize tabs based on URL parameters and hash
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const hash = window.location.hash.substring(1);
    const tab = urlParams.get('tab');
    
    // Map of hashes to their configuration
    const hashMap = {
        'ot_rate': { tabElement: 'payroll-tab', target: 'ot_rate_section' },
        'sss': { tabElement: 'payroll-tab', target: 'sss_section' },
        'philhealth': { tabElement: 'payroll-tab', target: 'philhealth_section' },
        'pagibig': { tabElement: 'payroll-tab', target: 'pagibig_section' },
        'labor': { tabElement: 'labor-tab', target: null }
    };
    
    let targetTabElement = null;
    let scrollTarget = null;
    
    // Priority 1: Hash navigation (from search results)
    if (hash && hashMap[hash]) {
            targetTabElement = hashMap[hash].tabElement;
        scrollTarget = hashMap[hash].target;
    }
    // Priority 2: Query parameter (from form submissions)
    else if (tab) {
        if (tab === 'labor') {
            targetTabElement = 'labor-tab';
        } else if (tab === 'payroll') {
            targetTabElement = 'payroll-tab';
        } else if (tab === 'holiday') {
            targetTabElement = 'holiday-tab';
        }
    }
    
    // Switch to the appropriate tab
    if (targetTabElement) {
        setTimeout(function() {
            const tabElement = document.getElementById(targetTabElement);
            if (tabElement) {
                try { new bootstrap.Tab(tabElement).show(); } catch (e) {}
            }
        }, 100);
    }
    
    // Scroll to target if needed
    if (scrollTarget) {
        setTimeout(function() {
            const targetElement = document.getElementById(scrollTarget);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                targetElement.style.animation = 'highlightPulse 1.5s ease-in-out';
            }
        }, 300);
    }
});

// Add highlight animation keyframe
const style = document.createElement('style');
style.textContent = `
    @keyframes highlightPulse {
        0% {
            background-color: transparent;
        }
        50% {
            background-color: rgba(99, 102, 241, 0.15);
        }
        100% {
            background-color: transparent;
        }
    }
`;
document.head.appendChild(style);

// ── Three-dot dropdown init (fixed strategy avoids clipping inside overflow container) ──
function initLtDropdowns(container) {
    container.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(btn => {
        if (!bootstrap.Dropdown.getInstance(btn)) {
            new bootstrap.Dropdown(btn, { popperConfig: { strategy: 'fixed' } });
        }
    });
}
document.addEventListener('DOMContentLoaded', () => {
    const ltList = document.getElementById('lt-list-container');
    if (ltList) initLtDropdowns(ltList);
});

// ── Add Labor Type — AJAX (no page scroll) ──────────────────────────────────
(function () {
    const form      = document.querySelector('form[action="{{ route('labor-types.store') }}"]');
    const listEl    = document.getElementById('lt-list-container');
    const nameInput = form && form.querySelector('[name="name"]');
    const rateInput = form && form.querySelector('[name="daily_rate"]');
    const submitBtn = form && form.querySelector('[type="submit"]');
    if (!form || !listEl) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        // Clear previous inline errors
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        const origHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding…';

        try {
            const r = await fetch(form.action, {
                method:  'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body:    new FormData(form),
            });
            const data = await r.json();

            if (r.ok && data.success) {
                // Remove empty-state placeholder if present
                const emptyEl = listEl.querySelector('.lt-empty');
                if (emptyEl) emptyEl.remove();

                // Prepend new row + modal HTML
                listEl.insertAdjacentHTML('afterbegin', data.html);

                // Init the new row's dropdown with fixed strategy
                initLtDropdowns(listEl);

                // Scroll only the list container to the top
                listEl.scrollTop = 0;

                // Reset form
                nameInput.value = '';
                rateInput.value = '';
                nameInput.focus();
            } else if (r.status === 422 && data.errors) {
                if (data.errors.name) {
                    nameInput.classList.add('is-invalid');
                    const msg = document.createElement('div');
                    msg.className = 'invalid-feedback d-block';
                    msg.textContent = data.errors.name[0];
                    nameInput.closest('.mb-3').appendChild(msg);
                }
            } else {
                alert(data.message || 'Could not add labor type. Please try again.');
            }
        } catch {
            alert('Network error — please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origHtml;
        }
    });
})();
</script>

<script>
(function () {
    const chk    = document.getElementById('sunday_rest_day_enabled');
    const status = document.getElementById('restDayStatus');
    if (!chk || !status) return;
    chk.addEventListener('change', function () {
        status.textContent = this.checked ? 'Enabled' : 'Disabled';
        status.style.color = this.checked ? '#16a34a' : '#94a3b8';
    });
})();
</script>
@endsection