{{-- An employee cell: initial, name, and one line of context. --}}
<div class="mod-person">
    <div class="mod-avatar">{{ strtoupper(substr($name ?: '?', 0, 1)) }}</div>
    <div style="min-width:0;">
        <div class="mod-person-name">{{ $name ?: '—' }}</div>
        @if(!empty($sub))<div class="mod-person-sub">{{ $sub }}</div>@endif
    </div>
</div>
