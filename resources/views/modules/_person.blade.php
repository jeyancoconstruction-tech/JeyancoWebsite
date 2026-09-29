{{-- An employee cell: the worker icon, name, and one line of context. --}}
<div class="mod-person">
    <div class="mod-avatar">@include('partials.worker-icon')</div>
    <div style="min-width:0;">
        <div class="mod-person-name">{{ $name ?: '—' }}</div>
        @if(!empty($sub))<div class="mod-person-sub">{{ $sub }}</div>@endif
    </div>
</div>
