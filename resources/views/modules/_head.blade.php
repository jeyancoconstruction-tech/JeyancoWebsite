{{-- Page title, sub-line and actions. One shape for every new module.
     $title, $sub, and an optional `actions` slot passed as $actions. --}}
<div class="mod-head">
    <div>
        <h1 class="mod-title">{{ $title }}</h1>
        @if(!empty($sub))<p class="mod-sub">{{ $sub }}</p>@endif
    </div>
    @if(!empty($actions))
        <div class="mod-head-actions">{!! $actions !!}</div>
    @endif
</div>

