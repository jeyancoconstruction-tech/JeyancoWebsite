@if($e->fingerprint_id)
    <span class="rmx-pill rmx-pill-ok" title="{{ __('Fingerprint slot on the kiosk') }}"><i class="ti ti-fingerprint" aria-hidden="true"></i>{{ $e->fingerprint_id }}</span>
@else
    <span class="rmx-pill" title="{{ __('No fingerprint enrolled yet') }}"><i class="ti ti-fingerprint" aria-hidden="true"></i>{{ __('Not set') }}</span>
@endif
