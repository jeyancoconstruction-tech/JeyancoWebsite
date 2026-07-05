<div class="lt-row" data-id="{{ $type->id }}">
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

<div class="modal fade" id="editModal{{ $type->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0" style="border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,.1);">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e3a8a,#1e40af);color:#fff;border:none;border-radius:12px 12px 0 0;">
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
                <div class="modal-footer border-top p-3" style="background:#f8fafc;border-radius:0 0 12px 12px;">
                    <button type="button" class="btn btn-light fw-600" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn ps-save-btn" style="padding:8px 20px;">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
