{{-- One worker's workday on the Attendance page, and the detail behind it.

     Both tables draw a day the same way, so it is written once here. The row
     answers one question — who came in, when, and is anything wrong — with
     the day's time in, its time out, the hours and where it stands. Everything
     else (both sessions, the timeline, every scan, the hours in full and the
     fixes) is in the row under it, which the page shows in a side panel when
     the row is clicked.

     $d is an App\Support\AttendanceDayView. $tab is 'today' or 'history'. --}}
@use('App\Support\WorkSchedule')
@php
    $day     = $d->day;
    $emp     = $day->employee();
    $shift   = $day->shift();
    $status  = $d->status();
    $hours   = $d->hours();
    $tl      = $d->timeline();
    $key     = 'day-' . $day->employeeId() . '-' . $day->date()->toDateString();
    $name    = $emp->name ?? __('Unknown');
    $role    = $emp?->laborType?->name ?: $emp?->position;
    $isHoliday = in_array($day->date()->toDateString(), $holidayDates ?? []);
    $review  = $status['key'] === 'review';
    $decided = $d->decision();
    $flag    = $status['tone'] === 'bad';

    // The one line under the name: what they do, where the day was clocked
    // (one kiosk is carried between sites, so it is the attendance's site,
    // not the worker's) and which shift.
    $sub = collect([$role, $day->site()?->name, $shift?->name])->filter()->implode(' · ');

    // A row is filed under the workday it opened, and the night crew's opened
    // last night — the one thing that says why they are on today's list after
    // midnight.
    $started = $tab === 'today' && $day->date()->lt(today())
        ? ($day->date()->isSameDay(today()->subDay()) ? __('Started last night') : __('Started :date', ['date' => $day->date()->format('m/d/Y')]))
        : null;

    // Time out on the list is the day's last one: the 2nd session's when it
    // has one (or is missing one), otherwise the 1st session's.
    $outSlot = ($d->slot('out') || ($d->tag('out')['tone'] ?? null) === 'bad') ? 'out' : 'bo';

    // A time on the list: the scan, or what stands in for it. A time the
    // system guessed is not shown as a time — it was never scanned.
    $cell = function (string $slot) use ($d) {
        $scan = $d->slot($slot);
        $tag  = $d->tag($slot);

        return [
            'time'   => $scan && ! $scan['guessed'] ? WorkSchedule::label($scan['at']) : null,
            'edited' => $scan['edited'] ?? false,
            'tag'    => $tag,
        ];
    };
    $in  = $cell('in');
    $out = $cell($outSlot);
@endphp
<tr @class(['atm-row', 'is-flag' => $flag]) data-live-key="{{ $key }}" data-day="{{ $key }}"
    tabindex="0" aria-expanded="false" aria-controls="{{ $key }}-detail">
    <td>
        <div class="atm-emp">
            <b>{{ $name }}</b>
            <small>{{ $sub }}@if($started) · {{ $started }}@endif</small>
        </div>
    </td>

    @foreach([$in, $out] as $c)
        <td>
            <div class="atm-punch">
                @if($c['time'])
                    <span class="atm-t">{{ $c['time'] }}@if($c['edited'])<i class="atm-edited" title="{{ __('Set by the office') }}"></i>@endif</span>
                @elseif(! $c['tag'])
                    <span class="atm-t is-mute">&mdash;</span>
                @endif
                @if($c['tag'])
                    <span class="atm-note-t {{ $c['tag']['tone'] === 'bad' ? 'bad' : '' }}" @if(!empty($c['tag']['title'])) title="{{ $c['tag']['title'] }}" @endif>{{ $c['tag']['text'] }}</span>
                @endif
            </div>
        </td>
    @endforeach

    <td class="atm-hrs">
        @if($hours)
            {{ WorkSchedule::duration($hours['regular']) }}
            <small>
                {{ $review ? __('Pending review') : WorkSchedule::duration($hours['worked']) . ' ' . __('worked') }}@if($hours['ot'] > 0) · +{{ WorkSchedule::duration($hours['ot']) }} {{ __('OT') }}@endif
            </small>
        @elseif($day->isUnscanned())
            <span class="atm-t is-mute">&mdash;</span>
        @elseif($status['key'] === 'norec')
            &mdash;
            <small>{{ __('Not paid') }}</small>
        @else
            &mdash;
            <small>{{ $review ? __('Pending review') : __('In progress') }}</small>
        @endif
    </td>

    <td>
        <div class="atm-status">
            <span class="atm-st {{ $status['tone'] }} {{ $status['live'] ? 'live' : '' }}"><span class="atm-dot"></span>{{ $status['label'] }}</span>
            @if($isHoliday)
                <span class="atm-note-t" title="{{ __('Holiday (Settings)') }}"><i class="fas fa-star me-1"></i>{{ __('Holiday') }}</span>
            @endif
        </div>
    </td>
    <td class="atm-col-chev"><i class="fas fa-chevron-right atm-chev" aria-hidden="true"></i></td>
</tr>

<tr class="atm-detail" id="{{ $key }}-detail" data-live-key="{{ $key }}-detail" hidden>
    <td colspan="6">
        <div class="atm-dhead">
            <div>
                <h3>{{ $name }}</h3>
                <p>{{ collect([$role, $day->site()?->name, $shift ? $shift->name . ($d->shiftHours() ? ' ' . $d->shiftHours() : '') : null, $day->date()->format('D, m/d/Y')])->filter()->implode(' · ') }}</p>
            </div>
            <button type="button" class="atm-close" data-atm-close aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="atm-dstate {{ $status['tone'] }}">
            <span class="atm-st {{ $status['tone'] }} {{ $status['live'] ? 'live' : '' }}"><span class="atm-dot"></span>{{ $status['label'] }}</span>
            @if($isHoliday)<span class="atm-note-t"><i class="fas fa-star me-1"></i>{{ __('Holiday') }}</span>@endif
            @if($started)<span class="atm-note-t">{{ $started }}</span>@endif
        </div>

        {{-- Both sessions, every slot, with what the list leaves out: late,
             overbreak, undertime, due and expected times. --}}
        <div class="atm-ses">
            @foreach([['in', 'bo', __('1st session')], ['bi', 'out', __('2nd session')]] as [$a, $b, $label])
                <div>
                    <h5>{{ $label }}@if($d->shiftHours()) <span>· {{ $d->clockOf($a === 'in' ? 'AM' : 'PM') }}</span>@endif</h5>
                    @foreach([$a => __('Time in'), $b => __('Time out')] as $slot => $what)
                        @php $scan = $d->slot($slot); $tag = $d->tag($slot); @endphp
                        <div class="atm-ses-r">
                            <span>{{ $what }}</span>
                            <span class="atm-ses-v">
                                @if($scan)
                                    <b @class(['is-mute' => $scan['guessed']])>{{ WorkSchedule::label($scan['at']) }}@if($scan['edited'])<i class="atm-edited" title="{{ __('Set by the office') }}"></i>@endif</b>
                                @elseif(! $tag)
                                    <b class="is-mute">&mdash;</b>
                                @endif
                                @if($tag)
                                    <small class="{{ $tag['tone'] === 'bad' ? 'bad' : '' }}" @if(!empty($tag['title'])) title="{{ $tag['title'] }}" @endif>{{ $tag['text'] }}</small>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        @if($tl)
            <div class="atm-dsec">
                <h4>{{ __('Timeline') }}</h4>
                <div class="atm-tl" title="{{ $tl['title'] }}">
                    @foreach($tl['pieces'] as $p)
                        <span class="{{ $p['cls'] }}" style="left:{{ $p['left'] }}%;width:{{ $p['width'] }}%"></span>
                    @endforeach
                    @if($tl['now'] !== null)
                        <span class="now" style="left:{{ $tl['now'] }}%"></span>
                    @endif
                </div>
                <div class="atm-tlax">
                    @foreach($tl['ticks'] as $t)
                        <span class="{{ $t['align'] }}" style="left:{{ $t['left'] }}%">{{ $t['label'] }}</span>
                    @endforeach
                </div>
                <div class="atm-key">
                    <span><i class="k-w"></i>{{ __('Worked') }}</span>
                    <span><i class="k-b"></i>{{ __('Break') }}</span>
                    <span><i class="k-m"></i>{{ __('Missing or late') }}</span>
                    @if($tl['now'] !== null)<span><i class="k-n"></i>{{ __('Now') }}</span>@endif
                </div>
            </div>
        @endif

        <div class="atm-dgrid">
            @php $fixes = $d->fixes(); @endphp
            @if(count($fixes))
                <div class="atm-dbox">
                    <h4>{{ __('Fix missing scans') }}</h4>
                    @foreach($fixes as $f)
                        @if($f['kind'] === 'break')
                            {{-- In and out scanned, nothing at the break: accept or decline (2026-09-27). --}}
                            <form class="atm-fix" data-fix-break="{{ route('attendance.break', $f['row']) }}"
                                  data-who="{{ $name }}" data-day="{{ $day->date()->format('m/d/Y') }}"
                                  data-times="{{ WorkSchedule::label($f['in']) }} – {{ WorkSchedule::label($f['out']) }}">
                                <b>{{ __('1st session time out and 2nd session time in are missing') }}</b>
                                <small>{{ __('Scanned in at :in and out at :out, with nothing at the break. Accept it as worked straight through the break, or decline it: the day is marked Not recorded and not paid. Either can be undone.', ['in' => WorkSchedule::label($f['in']), 'out' => WorkSchedule::label($f['out'])]) }}</small>
                                <button type="submit" class="atm-btn pri" name="decision" value="accept">{{ __('Accept · straight through the break') }}</button>
                                <button type="submit" class="atm-btn danger" name="decision" value="decline">{{ __('Decline · not recorded') }}</button>
                            </form>
                            @continue
                        @endif
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
                    <p class="atm-note">{{ collect($fixes)->every(fn ($f) => $f['kind'] === 'break')
                        ? __('Your choice is written to the audit log.')
                        : __('A saved time is marked as edited and written to the audit log.') }}</p>
                </div>
            @elseif($decided && $decided['kind'] === 'declined')
                <div class="atm-dbox">
                    <h4>{{ __('Not recorded') }}</h4>
                    <form class="atm-decided" data-fix-break="{{ route('attendance.break', $decided['row']) }}">
                        <span>{{ __('Declined — nothing was scanned at the break, so this day is not recorded and not paid.') }}
                            @if($decided['by'])<small>{{ __('By :name', ['name' => $decided['by']]) }}@if($decided['at']) · {{ $decided['at']->format('m/d/Y g:i A') }}@endif</small>@endif</span>
                        <button type="submit" class="atm-btn" name="decision" value="undo"><i class="fas fa-rotate-left me-1"></i>{{ __('Undo') }}</button>
                    </form>
                </div>
            @elseif($hours)
                <div class="atm-dbox">
                    <h4>{{ __('Hours') }}</h4>
                    @if($decided)
                        <form class="atm-decided" data-fix-break="{{ route('attendance.break', $decided['row']) }}">
                            <span>{{ __('Accepted as worked straight through the break.') }}
                                @if($decided['by'])<small>{{ __('By :name', ['name' => $decided['by']]) }}@if($decided['at']) · {{ $decided['at']->format('m/d/Y g:i A') }}@endif</small>@endif</span>
                            <button type="submit" class="atm-btn" name="decision" value="undo"><i class="fas fa-rotate-left me-1"></i>{{ __('Undo') }}</button>
                        </form>
                    @endif
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
                        <div><span>{{ __('Overtime') }}</span><b>{{ WorkSchedule::duration($hours['ot']) }}</b></div>
                        <div><span>{{ __('Total worked') }}</span><b>{{ WorkSchedule::duration($hours['worked']) }}</b></div>
                    </div>
                    @if($rule = $d->hoursRule())
                        <p class="atm-note">{{ $rule }}</p>
                    @endif
                </div>
            @else
                <div class="atm-dbox">
                    <h4>{{ __('Hours') }}</h4>
                    <p class="atm-note">{{ $day->isUnscanned()
                        ? __('Nothing scanned for this workday yet.')
                        : __('The hours are worked out once the day\'s last time out is scanned.') }}</p>
                    @if($rule = $d->hoursRule())
                        <p class="atm-note">{{ $rule }}</p>
                    @endif
                </div>
            @endif

            <div class="atm-dbox">
                <h4>{{ __('Fingerprint scans') }}</h4>
                @php $scans = $d->scans(); @endphp
                @if(count($scans))
                    <ul class="atm-scans">
                        @foreach($scans as $s)
                            <li>
                                <span class="atm-t">{{ WorkSchedule::label($s['at']) }}</span>
                                <span>{{ $s['what'] }}<span class="k">{{ $s['where'] }}</span></span>
                                <span class="atm-note-t {{ $s['tone'] === 'bad' ? 'bad' : '' }}">{{ $s['tag'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="atm-note">{{ __('No scans from any kiosk yet.') }}</p>
                @endif
            </div>
        </div>
    </td>
</tr>
