@forelse($pending as $e)
    <tr>
        <td>@include('employees._person', ['e' => $e, 'displayName' => 'New worker — needs details'])</td>
        <td>@include('employees._fp', ['e' => $e])</td>
        <td>
            <span class="rm-badge rm-badge-site"><i class="fas fa-tablet-screen-button"></i>
                {{ optional($e->kiosk)->name ?? 'Site A Kiosk' }}</span>
        </td>
        <td class="rm-muted">{{ $e->created_at?->format('M d, Y g:i A') }}</td>
        <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
        <td class="rm-actions">
            <button class="rm-btn-complete js-emp-edit"
                    data-mode="complete"
                    data-id="{{ $e->id }}"
                    data-name=""
                    data-labor="{{ $e->labor_type_id }}"
                    data-rate="{{ $e->rate_per_hour }}"
                    data-site="{{ $e->site_id }}"
                    data-fp="{{ $e->fingerprint_id }}">
                <i class="fas fa-user-pen"></i> Complete
            </button>
            @include('employees._menu', ['e' => $e, 'context' => 'pending'])
        </td>
    </tr>
@empty
    @include('employees._empty', ['icon' => 'fingerprint', 'title' => 'No pending detections', 'sub' => 'When a new fingerprint clocks in on the Site A kiosk, it will show up here.'])
@endforelse
