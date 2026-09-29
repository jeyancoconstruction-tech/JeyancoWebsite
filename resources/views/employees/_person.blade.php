@php
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
                 image on its own left an empty circle. The worker icon is
                 rendered alongside, hidden, and takes over the moment the file fails. --}}
            <img src="{{ url('storage/' . $e->photo) }}" alt="{{ $e->name }}"
                 loading="lazy"
                 onerror="this.hidden = true; this.nextElementSibling.removeAttribute('hidden');">
            <svg class="rmx-worker" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 10a5.5 5.5 0 0 1 11 0"/><path d="M4.5 10.2h15"/><path d="M12 4.5v2.8"/><path d="M8.2 11a3.8 3.8 0 0 0 7.6 0"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
        @else
            {{-- A worker, not a letter: the letter only repeated the name beside it. --}}
            <svg class="rmx-worker" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 10a5.5 5.5 0 0 1 11 0"/><path d="M4.5 10.2h15"/><path d="M12 4.5v2.8"/><path d="M8.2 11a3.8 3.8 0 0 0 7.6 0"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
        @endif
    </div>
    <div class="rmx-who">
        <div class="rmx-name {{ $e->isPending() ? 'is-muted' : '' }}" title="{{ $displayName }}">{{ $displayName }}</div>
        <div class="rmx-id">#{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>
