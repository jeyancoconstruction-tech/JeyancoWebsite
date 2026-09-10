<div class="rm-person">
    <div class="rm-avatar">
        @php $initial = strtoupper(substr($e->name ?: 'U', 0, 1)); @endphp
        @if($e->photo)
            {{-- A row keeps its photo path after the file itself is gone —
                 Railway wipes storage on every deploy — and hiding the broken
                 image on its own left an empty circle with no initial in it.
                 The letter is rendered alongside, hidden, and takes over the
                 moment the file fails. Same shape the Employee Directory uses. --}}
            <img src="{{ url('storage/' . $e->photo) }}" alt="{{ $e->name }}"
                 loading="lazy"
                 onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
            <span hidden>{{ $initial }}</span>
        @else
            {{ $initial }}
        @endif
    </div>
    <div class="rm-person-info">
        <span class="rm-person-name {{ $e->isPending() ? 'muted' : '' }}">{{ $displayName }}</span>
        <span class="rm-id">#{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</span>
    </div>
</div>
