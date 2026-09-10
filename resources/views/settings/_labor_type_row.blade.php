{{-- One labor type. Rendered both by the Labor Types list and, on its own, by
     the AJAX add — which is why the list @includes this file rather than
     repeating the markup and letting the two drift apart. --}}
<div class="lt-row" data-id="{{ $type->id }}">
    <div class="lt-info">
        <span class="lt-name">{{ $type->name }}</span>
        <div class="lt-rates">
            {{-- The daily rate is the one that is set; the other two are it,
                 divided and multiplied. The fill says which is which. --}}
            <span class="lt-rate lt-rate-primary">{{ __('Daily') }} <b>{{ $type->getFormattedDailyRate() }}</b></span>
            <span class="lt-rate">{{ __('Hourly') }} <b>{{ $type->getFormattedHourlyRate() }}</b></span>
            <span class="lt-rate">{{ __('OT') }} <b>{{ $type->getFormattedOTRate() }}</b></span>
        </div>
    </div>
    <div class="lt-actions">
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
