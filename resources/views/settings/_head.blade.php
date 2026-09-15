{{-- The header every System Settings tab opens with: where you are, what the
     tab is for, and when the settings were last saved and by whom (read from
     the Audit Log, which every save now writes to). --}}
@php
    $savedAt = $lastSaved?->created_at ?? ($system->exists ? $system->updated_at : null);
@endphp
<div class="sx-head">
    <div>
        <div class="sx-eyebrow">System · 04 / 04 · System Settings</div>
        <h1 class="sx-title">{{ $title }}</h1>
        <p class="sx-sub">{{ $sub }}</p>
    </div>
    <div class="sx-actions">
        <span class="st-last"><i data-lucide="history"></i>
            @if($savedAt)
                Last saved <b>{{ $savedAt->format('M j, Y · g:i A') }}</b>@if($lastSaved?->user_name) by {{ $lastSaved->user_name }}@endif
            @else
                Never changed — showing the built-in defaults
            @endif
        </span>
    </div>
</div>

@if($errors->any())
    <div class="sx-alert" role="alert"><i data-lucide="circle-alert"></i>
        <div><strong>Nothing was saved.</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    </div>
@endif
