@php
    $initial = strtoupper(substr($e->name ?: 'U', 0, 1));

    // One colour per worker, kept by their id, so a face keeps its colour
    // from one visit to the next. The tint is the same colour at 13%.
    $palette = ['#3B82F6', '#8B5CF6', '#22C55E', '#F59E0B', '#06B6D4', '#EC4899'];
    $tint    = $palette[$e->id % count($palette)];
@endphp
<div class="rmx-person">
    <div class="rmx-avatar" style="background:{{ $tint }}22;color:{{ $tint }};">
        @if($e->photo)
            {{-- A row keeps its photo path after the file itself is gone —
                 Railway wipes storage on every deploy — and hiding the broken
                 image on its own left an empty circle. The letter is rendered
                 alongside, hidden, and takes over the moment the file fails. --}}
            <img src="{{ url('storage/' . $e->photo) }}" alt="{{ $e->name }}"
                 loading="lazy"
                 onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
            <span hidden>{{ $initial }}</span>
        @else
            {{ $initial }}
        @endif
    </div>
    <div class="rmx-who">
        <div class="rmx-name {{ $e->isPending() ? 'is-muted' : '' }}" title="{{ $displayName }}">{{ $displayName }}</div>
        <div class="rmx-id">#{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>
