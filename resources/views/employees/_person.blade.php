{{-- A worker: the hard-hat icon (or their photo), name and number. One colour
     for every worker, on every page, so a face never reads as a status. --}}
<div class="rmx-person">
    <div class="rmx-avatar">
        @if($e->photo)
            {{-- A row keeps its photo path after the file itself is gone —
                 Railway wipes storage on every deploy — and hiding the broken
                 image on its own left an empty circle. The worker icon is
                 rendered alongside, hidden, and takes over the moment the file fails. --}}
            <img src="{{ url('storage/' . $e->photo) }}" alt="{{ $e->name }}"
                 loading="lazy"
                 onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
            <span class="rmx-avatar-ico" hidden>@include('partials.worker-icon', ['size' => '100%'])</span>
        @else
            <span class="rmx-avatar-ico">@include('partials.worker-icon', ['size' => '100%'])</span>
        @endif
    </div>
    <div class="rmx-who">
        <div class="rmx-name {{ $e->isPending() ? 'is-muted' : '' }}" title="{{ $displayName }}">{{ $displayName }}</div>
        <div class="rmx-id">#{{ str_pad($e->id, 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>
