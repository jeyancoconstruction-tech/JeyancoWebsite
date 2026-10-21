@php $others = $e->otherSiteList(); @endphp
@if($e->site)
    <span class="rmx-pill"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ $e->site->name }}</span>
@else
    <span class="rmx-dash">—</span>
@endif
{{-- The other sites they also work at, counted beside the main one. --}}
@if($others->isNotEmpty())
    <span class="rmx-pill rmx-more-sites" title="{{ __('Also at') }} {{ $others->pluck('name')->join(', ', ' and ') }}">+{{ $others->count() }}</span>
@endif
