@php
    // An icon for the trade, read off the labor type's own name so a type the
    // office adds later still gets one — the plain tool when nothing matches.
    $laborName = $e->laborType?->name;
    $trade     = strtolower((string) $laborName);
    $laborIcon = match (true) {
        str_contains($trade, 'mason')                                  => 'bucket',
        str_contains($trade, 'electric')                               => 'bolt',
        str_contains($trade, 'carpent')                                => 'hammer',
        str_contains($trade, 'plumb')                                  => 'droplet',
        str_contains($trade, 'paint')                                  => 'paint',
        str_contains($trade, 'weld')                                   => 'flame',
        str_contains($trade, 'foreman') || str_contains($trade, 'supervis') => 'helmet',
        str_contains($trade, 'driver')  || str_contains($trade, 'operator') => 'truck',
        str_contains($trade, 'helper')  || str_contains($trade, 'labor')    => 'shovel',
        default                                                        => 'tool',
    };
@endphp
@if($laborName)
    <span class="rmx-labor"><i class="ti ti-{{ $laborIcon }}" aria-hidden="true"></i>{{ $laborName }}</span>
@else
    <span class="rmx-dash">—</span>
@endif
