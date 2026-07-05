<div class="rm-person">
    <div class="rm-avatar">
        @if($e->photo)
            <img src="{{ url('storage/' . $e->photo) }}" alt="{{ $e->name }}">
        @else
            {{ strtoupper(substr($e->name ?: 'U', 0, 1)) }}
        @endif
    </div>
    <div class="rm-person-info">
        <span class="rm-person-name {{ $e->isPending() ? 'muted' : '' }}">{{ $displayName }}</span>
        <span class="rm-id">#{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</span>
    </div>
</div>
