@if($e->laborType)
    <span class="rm-badge rm-badge-labor"><i class="fas fa-briefcase"></i> {{ $e->laborType->name }}</span>
@else
    <span class="rm-dash">—</span>
@endif
