{{-- The header every signed-in page opens with: its name on the left, its
     actions on the right, and nothing underneath. One component so the title
     is the same size, weight and colour, in the same place, on every page.
     Styled in public/page-header.css.

     <x-page-header :title="__('Employees')">
         <x-slot:badge id="smCount">7 sites</x-slot:badge>   beside the title (optional)
         <x-slot:actions> …buttons… </x-slot:actions>        on the right (optional)
     </x-page-header>

     Extra attributes land on the <header>, e.g. class="no-print". --}}
@props(['title'])
<header {{ $attributes->class('page-head') }}>
    <div class="page-head-main">
        <h1 class="page-head-title">{{ $title }}</h1>
        @isset($badge)
            <span {{ $badge->attributes->class('page-head-badge') }}>{{ $badge }}</span>
        @endisset
    </div>
    @isset($actions)
        <div {{ $actions->attributes->class('page-head-actions') }}>{{ $actions }}</div>
    @endisset
</header>
