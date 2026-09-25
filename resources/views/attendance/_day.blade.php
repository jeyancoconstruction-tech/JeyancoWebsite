{{-- One worker's workday on the Attendance page, and the scans under it.

     Both tables draw a day the same way, so it is written once here. The row
     is the day as a shift is laid out — first session in and out, second
     session in and out — with a timeline against the shift, the hours payroll
     makes of it, and where it stands. The row under it opens on a click: every
     scan behind the day, and either the hours in full or the time outs still
     waiting on the office.

     $d is an App\Support\AttendanceDayView. $tab is 'today' or 'history'. --}}
@use('App\Support\WorkSchedule')
@php
    $day     = $d->day;
    $emp     = $day->employee();
    $shift   = $day->shift();
    $status  = $d->status();
    $hours   = $d->hours();
    $tl      = $d->timeline();
    $key     = 'day-' . $day->first()->employee_id . '-' . $day->date()->toDateString();
    $ids     = implode(',', $day->ids());
    $name    = $emp->name ?? __('Unknown');
    $role    = $emp?->laborType?->name ?: $emp?->position;
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
    $isHoliday = in_array($day->date()->toDateString(), $holidayDates ?? []);
    $review  = $status['key'] === 'review';
    $cols    = $tab === 'history' ? 10 : 9;
@endphp
<tr class="atm-row" data-live-key="{{ $key }}" data-day="{{ $key }}" data-id="{{ $ids }}"
    tabindex="0" aria-expanded="false" aria-controls="{{ $key }}-detail">
    @if($tab === 'history')
        {{-- The checkbox carries every row behind the day, so deleting a
             Tuesday takes the whole Tuesday rather than its morning and
             leaving its afternoon. --}}
        <td class="att-check-col">
            <input type="checkbox" class="row-chk" value="{{ $ids }}" aria-label="{{ __('Select :name', ['name' => $name]) }}">
        </td>
    @endif

    <td>
        <div class="atm-emp">
            <span class="atm-ini" aria-hidden="true">{{ $initials }}</span>
            <div>
                <b>{{ $name }}</b>
                <small>{{ $role }}</small>
                {{-- Where the day was clocked: one kiosk is carried between
                     sites, so it is the attendance's site, not the worker's. --}}
                @if($day->site())
                    <span class="atm-site"><i class="fas fa-location-dot"></i>{{ $day->site()->name }}</span>
                @endif
            </div>
        </div>
    </td>

    <td>
        @if($shift)
            <span class="atm-shift {{ $shift->crosses_midnight ? 'night' : 'day' }}">
                <i class="fas {{ $shift->crosses_midnight ? 'fa-moon' : 'fa-sun' }}"></i>{{ $shift->name }}
            </span>
        @else
            <span class="atm-t is-mute">&mdash;</span>
        @endif
        @if($d->shiftHours())
            <span class="atm-shift-hrs">{{ $d->shiftHours() }}</span>
        @endif
        {{-- A row is filed under the workday it opened, and the night crew's
             opened last night — the one thing on the row that says why they
             are on today's list after midnight. --}}
        @if($tab === 'today' && $day->date()->lt(today()))
            <span class="atm-shift-hrs">
                {{ $day->date()->isSameDay(today()->subDay()) ? __('Started last night') : __('Started :date', ['date' => $day->date()->format('m/d/Y')]) }}
            </span>
        @endif
    </td>

    @foreach(App\Support\AttendanceDayView::SLOTS as $slot)
        @php $scan = $d->slot($slot); $tag = $d->tag($slot); @endphp
        <td @class(['atm-sep' => $slot === 'bi'])>
            <div class="atm-punch">
                @if($scan)
                    <span @class(['atm-t', 'is-mute' => $scan['guessed']])>
                        {{ WorkSchedule::label($scan['at']) }}
                        @if($scan['edited'])
                            <i class="atm-edited" title="{{ __('Set by the office') }}"></i>
                        @endif
                    </span>
                @else
                    <span class="atm-t is-mute">&mdash;</span>
                @endif
                @if($tag)
                    <span class="atm-tag {{ $tag['tone'] }}" @if(!empty($tag['title'])) title="{{ $tag['title'] }}" @endif>{{ $tag['text'] }}</span>
                @endif
            </div>
        </td>
    @endforeach

    <td class="atm-col-tl">
        @if($tl)
            <div class="atm-tl" title="{{ $tl['title'] }}">
                @foreach($tl['pieces'] as $p)
                    <span class="{{ $p['cls'] }}" style="left:{{ $p['left'] }}%;width:{{ $p['width'] }}%"></span>
                @endforeach
                @if($tl['now'] !== null)
                    <span class="now" style="left:{{ $tl['now'] }}%"></span>
                @endif
            </div>
            <div class="atm-tlax"><span>{{ $tl['axis'][0] }}</span><span>{{ $tl['axis'][1] }}</span><span>{{ $tl['axis'][2] }}</span></div>
        @else
            <span class="atm-t is-mute">&mdash;</span>
        @endif
    </td>

    <td class="atm-hrs">
        @if($hours)
            {{ WorkSchedule::duration($hours['regular']) }} <small class="d-inline">{{ __('reg') }}</small>
            @if($hours['ot'] > 0)
                <span class="atm-tag good">+{{ WorkSchedule::duration($hours['ot']) }} {{ __('OT') }}</span>
            @endif
            <small>{{ $review ? __('Pending review') : WorkSchedule::duration($hours['worked']) . ' ' . __('worked') }}</small>
        @else
            &mdash;
            <small>{{ $review ? __('Pending review') : __('In progress') }}</small>
        @endif
    </td>

    <td>
        <div class="atm-status">
            <div class="atm-status-tags">
                <span class="atm-pill {{ $status['tone'] }} {{ $status['live'] ? 'live' : '' }}"><span class="atm-dot"></span>{{ $status['label'] }}</span>
                @if($isHoliday)
                    <span class="atm-tag warn" title="{{ __('Holiday (Settings)') }}"><i class="fas fa-star me-1"></i>{{ __('Holiday') }}</span>
                @endif
            </div>
            <i class="fas fa-chevron-right atm-chev" aria-hidden="true"></i>
        </div>
    </td>
</tr>

<tr class="atm-detail" id="{{ $key }}-detail" data-live-key="{{ $key }}-detail" hidden>
    <td colspan="{{ $cols }}">
        <div class="atm-dgrid">
            <div class="atm-dbox">
                <h4>{{ __('Fingerprint scans') }}</h4>
                @php $scans = $d->scans(); @endphp
                @if(count($scans))
                    <ul class="atm-scans">
                        @foreach($scans as $s)
                            <li>
                                <span class="atm-t">{{ WorkSchedule::label($s['at']) }}</span>
                                <span>{{ $s['what'] }}<span class="k">{{ $s['where'] }}</span></span>
                                <span class="atm-tag {{ $s['tone'] }}">{{ $s['tag'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="atm-note">{{ __('No scans from any kiosk yet.') }}</p>
                @endif
            </div>

            @php $fixes = $d->fixes(); @endphp
            @if(count($fixes))
                <div class="atm-dbox">
                    <h4>{{ __('Fix missing scans') }}</h4>
                    @foreach($fixes as $f)
                        @php $suggest = $f['guess'] ?? $f['scheduled']; @endphp
                        <form class="atm-fix" data-fix="{{ route('attendance.time-out', $f['row']) }}">
                            <b>
                                {{ __(':slot is missing', ['slot' => $f['label']]) }}
                                @if($f['scheduled']) · {{ __('scheduled :time', ['time' => WorkSchedule::label($f['scheduled'])]) }} @endif
                            </b>
                            @if($f['guess'])
                                <small>{{ __('The system closed it at :time. Confirm it, or enter the time the worker actually left.', ['time' => WorkSchedule::label($f['guess'])]) }}</small>
                            @endif
                            @if($f['scheduled'])
                                <button type="submit" class="atm-btn pri" name="time" value="{{ $f['scheduled']->format('H:i') }}">
                                    {{ __('Use :time', ['time' => WorkSchedule::label($f['scheduled'])]) }}
                                </button>
                                <span class="atm-note">{{ __('or') }}</span>
                            @endif
                            <input type="time" value="{{ $suggest?->format('H:i') }}" aria-label="{{ __(':slot for :name', ['slot' => $f['label'], 'name' => $name]) }}">
                            <button type="submit" class="atm-btn">{{ __('Save time') }}</button>
                        </form>
                    @endforeach
                    <p class="atm-note">{{ __('A saved time is marked as edited and written to the audit log.') }}</p>
                </div>
            @elseif($hours)
                <div class="atm-dbox">
                    <h4>{{ __('Hours') }}</h4>
                    <div class="atm-sum">
                        @if($d->workedThrough())
                            <div><span>{{ __('Straight through') }}</span><b>{{ WorkSchedule::duration($hours['worked']) }}</b></div>
                            <div><span>{{ __('Break') }}</span><b>{{ __('No scan') }}</b></div>
                            <div><span>{{ __('Shift') }}</span><b>{{ $d->shiftHours() ?? '—' }}</b></div>
                        @else
                            <div><span>{{ __('1st session') }} · {{ $d->clockOf('AM') }}</span><b>{{ $hours['first'] > 0 ? WorkSchedule::duration($hours['first']) : '—' }}</b></div>
                            <div><span>{{ __('Break') }}</span><b>{{ $hours['break'] !== null ? WorkSchedule::duration($hours['break']) . ($hours['allowed'] !== null ? ' / ' . WorkSchedule::duration($hours['allowed']) : '') : '—' }}</b></div>
                            <div><span>{{ __('2nd session') }} · {{ $d->clockOf('PM') }}</span><b>{{ $hours['second'] > 0 ? WorkSchedule::duration($hours['second']) : '—' }}</b></div>
                        @endif
                        <div><span>{{ __('Regular') }}</span><b>{{ WorkSchedule::duration($hours['regular']) }}</b></div>
                        <div><span>{{ __('Overtime') }}</span><b @class(['is-good' => $hours['ot'] > 0])>{{ WorkSchedule::duration($hours['ot']) }}</b></div>
                        <div><span>{{ __('Total worked') }}</span><b>{{ WorkSchedule::duration($hours['worked']) }}</b></div>
                    </div>
                    @if($rule = $d->hoursRule())
                        <p class="atm-note">{{ $rule }}</p>
                    @endif
                </div>
            @else
                <div class="atm-dbox">
                    <h4>{{ __('Hours') }}</h4>
                    <p class="atm-note">{{ __('The hours are worked out once the day\'s last time out is scanned.') }}</p>
                    @if($rule = $d->hoursRule())
                        <p class="atm-note">{{ $rule }}</p>
                    @endif
                </div>
            @endif
        </div>
    </td>
</tr>
