@if($e->site)
    <span class="rm-badge rm-badge-site"><i class="fas fa-map-marker-alt"></i> {{ $e->site->name }}</span>
@else
    <span class="rm-dash">—</span>
@endif
