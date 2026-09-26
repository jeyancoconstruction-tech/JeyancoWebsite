{{-- The header every System Settings tab opens with: the shared page header,
     with when the settings were last saved and by whom on its right (read
     from the Audit Log, which every save now writes to). --}}
@php
    $savedAt = $lastSaved?->created_at ?? ($system->exists ? $system->updated_at : null);
@endphp
<x-page-header :title="$title">
    <x-slot:actions>
        <span class="st-last"><i data-lucide="history"></i>
            @if($savedAt)
                Last saved <b>{{ $savedAt->format('M j, Y · g:i A') }}</b>@if($lastSaved?->user_name) by {{ $lastSaved->user_name }}@endif
            @else
                Never changed — showing the built-in defaults
            @endif
        </span>
    </x-slot:actions>
</x-page-header>

@if($errors->any())
    <div class="sx-alert" role="alert"><i data-lucide="circle-alert"></i>
        <div><strong>Nothing was saved.</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    </div>
@endif
