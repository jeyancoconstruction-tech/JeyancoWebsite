@if($e->fingerprint_id)
    <span class="rm-badge rm-badge-fp"><i class="fas fa-fingerprint"></i> {{ $e->fingerprint_id }}</span>
@else
    <span class="rm-dash">Not set</span>
@endif
