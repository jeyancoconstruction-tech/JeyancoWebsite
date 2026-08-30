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

        $photoUrl = $e->photo ? asset('storage/' . $e->photo) : '';
    @endphp
    <tr>
        {{-- Also rendered by the 5-second live refresh, so a row that arrives
             from the kiosk is selectable the moment it appears. --}}
        <td class="rm-check-col">
            <input type="checkbox" class="rm-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}">
        </td>
        <td>
            @include('employees._person', ['e' => $e, 'displayName' => $named ? $e->name : 'New worker — needs details'])
            @if($awaitingFingerprint)
                <span class="rm-awaiting-fp" title="Registered on the web. Enrol their finger on the kiosk to activate them.">
                    <i class="fas fa-fingerprint"></i> awaiting fingerprint
                </span>
            @endif
            @if($missingRate)
                <span class="rm-needs-rate" title="Ang position na '{{ $e->position }}' ay walang katugmang labor type, kaya walang rate. Itakda ito sa Complete.">
                    <i class="fas fa-triangle-exclamation"></i> walang rate
                </span>
            @endif
        </td>
        <td>@include('employees._fp', ['e' => $e])</td>
        <td>
            {{-- The site the worker picked on the kiosk, which is what the admin
                 needs to see. The device name is secondary now that one kiosk is
                 carried between sites. --}}
            @if($e->site)
                <span class="rm-badge rm-badge-site"><i class="fas fa-map-marker-alt"></i> {{ $e->site->name }}</span>
            @else
                <span class="rm-badge rm-badge-site"><i class="fas fa-tablet-screen-button"></i>
                    {{ optional($e->kiosk)->name ?? 'Unknown site' }}</span>
            @endif
        </td>
        <td class="rm-muted">{{ $e->created_at?->format('M d, Y g:i A') }}</td>
        <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
        <td class="rm-actions">
            @if($awaitingFingerprint)
                {{-- Nothing for the admin to confirm — the kiosk holds the next
                     step. Edit stays available for fixing details meanwhile. --}}
                <a href="{{ route('employees.edit', $e->id) }}" class="rm-btn-ghost">
                    <i class="fas fa-pen"></i> Edit
                </a>
            @elseif($hasDetails)
                <button class="rm-btn-accept js-emp-edit"
                        data-mode="confirm"
                        data-id="{{ $e->id }}"
                        data-name="{{ $e->name }}"
                        data-labor="{{ $e->labor_type_id }}"
                        data-rate="{{ $e->rate_per_hour }}"
                        data-site="{{ $e->site_id }}"
                        data-fp="{{ $e->fingerprint_id }}"
                        data-photo="{{ $photoUrl }}">
                    <i class="fas fa-check"></i> Confirm
                </button>
            @else
                <button class="rm-btn-complete js-emp-edit"
                        data-mode="complete"
                        data-id="{{ $e->id }}"
                        data-name=""
                        data-labor="{{ $e->labor_type_id }}"
                        data-rate="{{ $e->rate_per_hour }}"
                        data-site="{{ $e->site_id }}"
                        data-fp="{{ $e->fingerprint_id }}"
                        data-photo="{{ $photoUrl }}">
                    <i class="fas fa-user-pen"></i> Complete
                </button>
            @endif
            <form action="{{ route('employees.destroy', $e->id) }}" method="POST" style="display:inline;"
                  onsubmit="return confirm('Reject and remove {{ addslashes($e->name) }}? It can still be restored from the Removed tab.')">
                @csrf @method('DELETE')
                <button type="submit" class="rm-btn-reject"><i class="fas fa-xmark"></i> Reject</button>
            </form>
        </td>
    </tr>
@empty
    @include('employees._empty', ['icon' => 'fingerprint', 'title' => 'No workers awaiting approval', 'sub' => 'When a worker registers or scans a new fingerprint on the kiosk, they appear here for you to Confirm or Reject.'])
@endforelse
