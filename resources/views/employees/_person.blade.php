<div class="rm-person">
    <div class="rm-avatar">
        @if($e->photo)
            {{-- If the file is missing the row still has its path, so the
                 image fails and used to leave a broken icon and the alt text.
                 Hide it and let the initial underneath show through. --}}
            <img src="{{ url('storage/' . $e->photo) }}" alt="{{ $e->name }}"
                 loading="lazy" onerror="this.style.display='none'">
        @else
            {{ strtoupper(substr($e->name ?: 'U', 0, 1)) }}
        @endif
    </div>
    <div class="rm-person-info">
        <span class="rm-person-name {{ $e->isPending() ? 'muted' : '' }}">{{ $displayName }}</span>
        <span class="rm-id">#{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</span>
    </div>
</div>
