{{-- One empty state, so eleven tables say "nothing here" the same way. --}}
<tr>
    <td colspan="{{ $cols ?? 6 }}">
        <div class="mod-empty">
            <i class="fas {{ $icon ?? 'fa-inbox' }}"></i>
            <p class="mod-empty-title">{{ $title ?? 'Nothing to show' }}</p>
            <p class="mod-empty-sub">{{ $sub ?? '' }}</p>
        </div>
    </td>
</tr>

