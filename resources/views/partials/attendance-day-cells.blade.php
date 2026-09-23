{{-- The Session and Time In / Out cells of one workday.

     Both attendance tables put these two columns side by side, and both have
     to say the same thing about a day, so they are written once here.

     A day is one line. When it was worked in more than one stretch — a
     morning and an afternoon, or a stretch begun again after a mistaken
     time-out — the top line is the day itself, from the first clock in to the
     last clock out, and the stretches are spelled out under it. Neither half
     is thrown away: the AM and PM times are exactly what the office checks a
     timesheet against. --}}
@php
    $clock     = fn ($m) => $m?->format('h:i A');
    $stretches = $day->stretches();
    $several   = $day->hasSeveralStretches();
@endphp

<td class="p-2">
    <span class="att-sessions">
        @forelse($day->sessions() as $session)
            <span class="session-label {{ $session === 'AM' ? 'badge-am' : 'badge-pm' }}">{{ $session }}</span>
        @empty
            <span class="text-muted">&mdash;</span>
        @endforelse
    </span>
</td>

<td class="p-2">
    @if($stretches->isEmpty())
        <span class="text-muted fst-italic">{{ __('No time-in') }}</span>
    @else
        <span class="att-day-span">
            {{ $clock($day->firstIn()) }}
            &ndash;
            {{ $day->lastOut() ? $clock($day->lastOut()) : '--' }}
            @if($day->outDaysLater() > 0)
                <span class="att-nextday" title="{{ __('Timed out the next morning — the same workday') }}">+{{ $day->outDaysLater() }}</span>
            @endif
        </span>

        @if($several)
            {{-- What the day is made of. Written under the span rather than
                 on rows of its own: one worker, one day, one line. --}}
            <span class="att-stretches">
                @foreach($stretches as $part)
                    {{-- The day resolves its own times: a night crew files
                         its whole workday under the evening it opened, so
                         their 1:00 AM stretch is the next morning. --}}
                    @php [$in, $out] = $day->partsOf($part); @endphp
                    <span class="att-stretch">
                        <b>{{ $part->session ?: '—' }}</b>
                        {{ $clock($in) }} &ndash; {{ $out ? $clock($out) : '--' }}
                    </span>
                @endforeach
            </span>
        @endif
    @endif
</td>
