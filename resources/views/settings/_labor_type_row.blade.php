{{-- One labor type. Rendered both by the Labor Types list and, on its own, by
     the AJAX add — which is why the list @includes this file rather than
     repeating the markup and letting the two drift apart.

     A row of the rate ladder: the daily rate (the one that is set), the
     hourly and OT rates worked out from it, and a bar against the top rate.
     The bar, the order and the "Top rate / Lowest rate" note are drawn by the
     page's script from data-rate, so a row added without a reload fits in. --}}
@php
    $words    = preg_split('/\s+/', trim(preg_replace('/[^\pL\s]/u', ' ', $type->name)));
    $initials = mb_strtoupper(count($words) > 1 ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1) : mb_substr($words[0] ?? '', 0, 2));
@endphp
<div class="lt-row" data-id="{{ $type->id }}" data-name="{{ mb_strtolower($type->name) }}" data-rate="{{ (float) $type->daily_rate }}">
    <div class="lt-who">
        <span class="lt-mark" aria-hidden="true">{{ $initials }}</span>
        <div class="lt-info">
            <span class="lt-name">{{ $type->name }}</span>
            <small class="lt-note" data-lt-note></small>
        </div>
    </div>
    <span class="lt-num lt-daily">{{ $type->getFormattedDailyRate() }}</span>
    <span class="lt-num">{{ $type->getFormattedHourlyRate() }}</span>
    <span class="lt-num">{{ $type->getFormattedOTRate() }}</span>
    <span class="lt-scale" aria-hidden="true"><i data-lt-bar></i></span>
    <div class="lt-actions">
        <button class="lt-icon-btn" type="button" data-bs-toggle="modal" data-bs-target="#editModal{{ $type->id }}"
                aria-label="{{ __('Edit :name', ['name' => $type->name]) }}" title="{{ __('Edit') }}">
            <i class="fas fa-pen"></i>
        </button>
        <div class="dropdown">
            <button class="lt-menu-btn" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false" aria-label="{{ __('Options for :name', ['name' => $type->name]) }}">
                <i class="fas fa-ellipsis-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end lt-dropdown">
                <li>
                    <button class="dropdown-item" type="button"
                            data-bs-toggle="modal" data-bs-target="#editModal{{ $type->id }}">
                        <i class="fas fa-edit me-2"></i>{{ __('Edit') }}
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('labor-types.delete', $type->id) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger"
                                data-confirm="{{ __('Employees on this labor type will be affected.') }}"
                                data-confirm-title="{{ __('Delete this labor type?') }}"
                                data-confirm-label="{{ __('Delete') }}"
                                data-confirm-tone="danger">
                            <i class="fas fa-trash me-2"></i>{{ __('Delete') }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal{{ $type->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 lt-modal">
            <div class="modal-header lt-modal-head">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>{{ __('Edit Labor Type') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('labor-types.update', $type->id) }}">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="ps-label">{{ __('Name') }}</label>
                        <input type="text" class="form-control ps-input" name="name"
                               value="{{ $type->name }}" required>
                    </div>
                    <div class="mb-1">
                        <label class="ps-label">{{ __('Daily Rate (₱)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text ps-ig-text">₱</span>
                            <input type="number" step="0.01" class="form-control ps-input"
                                   name="daily_rate" value="{{ $type->daily_rate }}" required>
                        </div>
                        <small class="lt-modal-hint">{{ __('Hourly and OT rates update automatically.') }}</small>
                    </div>
                </div>
                <div class="modal-footer lt-modal-foot">
                    <button type="button" class="btn lt-modal-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn ps-save-btn" style="padding:8px 20px;">{{ __('Update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
