@if($e->site)
    <span class="rmx-pill"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ $e->site->name }}</span>
@else
    <span class="rmx-dash">—</span>
@endif
