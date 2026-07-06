@forelse($pending as $e)
    @php
        // A kiosk registration already carries a name + labor type + rate, so the
        // admin only needs to CONFIRM (review + tweak) it. A bare fingerprint
        // detection has none of these and must be COMPLETED (details filled) first.
        $hasDetails = $e->name !== 'Unregistered Worker'
            && ! empty($e->labor_type_id)
            && (float) $e->rate_per_hour > 0;
        $photoUrl = $e->photo ? asset('storage/' . $e->photo) : '';
    @endphp
    <tr>
        <td>@include('employees._person', ['e' => $e, 'displayName' => $hasDetails ? $e->name : 'New worker — needs details'])</td>
        <td>@include('employees._fp', ['e' => $e])</td>
        <td>
            <span class="rm-badge rm-badge-site"><i class="fas fa-tablet-screen-button"></i>
                {{ optional($e->kiosk)->name ?? 'Site A Kiosk' }}</span>
        </td>
        <td class="rm-muted">{{ $e->created_at?->format('M d, Y g:i A') }}</td>
        <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
        <td class="rm-actions">
            @if($hasDetails)
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
