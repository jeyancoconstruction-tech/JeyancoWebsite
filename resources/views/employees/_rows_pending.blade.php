@forelse($pending as $e)
    @php
        // Did the worker actually give a name at the kiosk? A bare fingerprint
        // scan creates the 'Unregistered Worker' placeholder instead.
        $named = $e->name && $e->name !== 'Unregistered Worker' && trim($e->name) !== '';

        // A kiosk registration already carries a name + labor type + rate, so the
        // admin only needs to CONFIRM (review + tweak) it. A bare fingerprint
        // detection has none of these and must be COMPLETED (details filled) first.
        //
        // A contractual worker is complete without either: they are paid against
        // a contract total, so they have no labor type and no hourly rate by
        // design, and must not be flagged as missing one.
        $hasDetails = $named && ($e->isContractual()
            || (! empty($e->labor_type_id) && (float) $e->rate_per_hour > 0));

        // Named but without a rate: the kiosk offered a position that is not one
        // of the web's labor types (e.g. "Foreman"), so no rate could be looked
        // up. Show the name they gave anyway — hiding it behind "New worker" made
        // a completed registration look like a bare scan.
        $missingRate = $named && ! $hasDetails;

        // Registered on the web: details are already filled in, but nobody has
        // read their finger yet. The opposite of a kiosk detection, and the
        // admin has nothing to complete — the kiosk does the next step.
        $awaitingFingerprint = empty($e->fingerprint_id);
    @endphp
    <tr>
        {{-- Also rendered by the 5-second live refresh, so a row that arrives
             from the kiosk is selectable the moment it appears. --}}
        <td class="rmx-check-col">
            <input type="checkbox" class="rmx-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}">
        </td>
        <td>
            @include('employees._person', ['e' => $e, 'displayName' => $named ? $e->name : 'New worker — needs details'])
            @if($awaitingFingerprint || $missingRate)
                <div class="rmx-tags">
                    @if($awaitingFingerprint)
                        <span class="rmx-pill rmx-pill-accent" title="{{ __('Registered on the web. Enrol their finger on the kiosk to activate them.') }}">
                            <i class="ti ti-fingerprint" aria-hidden="true"></i>{{ __('awaiting fingerprint') }}
                        </span>
                    @endif
                    @if($missingRate)
                        <span class="rmx-pill rmx-pill-warn" title="{{ __('The position \':position\' has no matching labor type, so there is no rate. Set one in Complete.', ['position' => $e->position]) }}">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>{{ __('no rate') }}
                        </span>
                    @endif
                </div>
            @endif
        </td>
        <td class="rmx-center">@include('employees._fp', ['e' => $e])</td>
        <td>
            {{-- The site the worker picked on the kiosk, which is what the admin
                 needs to see. The device name is secondary now that one kiosk is
                 carried between sites. --}}
            @if($e->site)
                <span class="rmx-pill"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ $e->site->name }}</span>
            @else
                <span class="rmx-pill"><i class="ti ti-device-tablet" aria-hidden="true"></i>{{ optional($e->kiosk)->name ?? 'Unknown site' }}</span>
            @endif
        </td>
        <td class="rmx-muted">{{ $e->created_at?->format('M d, Y g:i A') }}</td>
        <td class="rmx-center"><span class="rmx-logs">{{ $e->attendances_count }}</span></td>
        <td class="rmx-actions">
            <div class="rmx-actions-inner">
                @if($awaitingFingerprint)
                    {{-- Nothing for the admin to confirm — the kiosk holds the next
                         step. Edit stays available for fixing details meanwhile. --}}
                    <a href="{{ route('employees.edit', $e->id) }}" class="rmx-icon-btn rmx-edit"
                       title="{{ __('Edit') }}" aria-label="{{ __('Edit') }} {{ $e->name }}">
                        <i class="ti ti-pencil" aria-hidden="true"></i>
                    </a>
                @elseif($hasDetails)
                    {{-- The kiosk creates these with a bare name, so the parts are
                         split best-effort for the modal's three boxes. The admin
                         sees the result in editable fields — nothing is written to
                         the record on a guess. --}}
                    @php $np = $e->first_name
                            ? ['first_name' => $e->first_name, 'middle_name' => $e->middle_name, 'last_name' => $e->last_name]
                            : \App\Models\Employee::splitName($e->name); @endphp
                    <button type="button" class="rmx-text-btn rmx-restore js-emp-edit"
                            data-mode="confirm"
                            data-id="{{ $e->id }}"
                            data-first="{{ $np['first_name'] }}"
                            data-middle="{{ $np['middle_name'] }}"
                            data-last="{{ $np['last_name'] }}"
                            data-labor="{{ $e->labor_type_id }}"
                            data-rate="{{ $e->rate_per_hour }}"
                            data-site="{{ $e->site_id }}"
                            data-fp="{{ $e->fingerprint_id }}">
                        <i class="ti ti-check" aria-hidden="true"></i>{{ __('Confirm') }}
                    </button>
                @else
                    <button type="button" class="rmx-text-btn rmx-edit js-emp-edit"
                            data-mode="complete"
                            data-id="{{ $e->id }}"
                            data-first=""
                            data-middle=""
                            data-last=""
                            data-labor="{{ $e->labor_type_id }}"
                            data-rate="{{ $e->rate_per_hour }}"
                            data-site="{{ $e->site_id }}"
                            data-fp="{{ $e->fingerprint_id }}">
                        <i class="ti ti-user-edit" aria-hidden="true"></i>{{ __('Complete') }}
                    </button>
                @endif
                <form action="{{ route('employees.destroy', $e->id) }}" method="POST"
                      data-confirm="{{ __('This cancels the registration of :name. It can still be restored from the Removed tab.', ['name' => $e->name]) }}"
                      data-confirm-title="{{ __('Cancel this registration?') }}"
                      data-confirm-label="{{ __('Cancel registration') }}"
                      data-confirm-tone="warning">
                    @csrf @method('DELETE')
                    <button type="submit" class="rmx-icon-btn rmx-del"
                            title="{{ __('Cancel registration') }}" aria-label="{{ __('Cancel registration') }} {{ $e->name }}">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
@empty
    @include('employees._empty', ['icon' => 'fingerprint', 'title' => 'No workers waiting', 'sub' => 'Newly registered workers and unknown fingerprints scanned at the kiosk appear here.'])
@endforelse
