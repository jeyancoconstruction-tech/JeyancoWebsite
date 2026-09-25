<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\SystemSetting;
use App\Models\Shift;
use App\Models\Site;
use App\Notifications\AttendanceAlert;
use App\Services\PayrollService;
use App\Support\AttendanceDay;
use App\Support\AttendanceDayView;
use App\Support\WorkSchedule;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * The status control's values, and the day statuses each one keeps.
     *
     * All is everybody on the roster, scanned or not; Present is everybody
     * who scanned — the Present today card, which the control has no button
     * for.
     */
    private const VIEWS = [
        'all'        => null,
        'present'    => ['work', 'break', 'review', 'done'],
        'clocked-in' => ['work'],
        'break'      => ['break'],
        'missed'     => ['review'],
        'done'       => ['done'],
    ];

    /** What each tab opens on when nobody has chosen: everybody who scanned today, and every past day. */
    private const TODAY_DEFAULT   = 'present';
    private const HISTORY_DEFAULT = 'all';

    /** What the lists load with every row: who, where, under which shift, and from which kiosk. */
    private const WITH = ['employee.laborType', 'site', 'shift', 'kiosk', 'reviewer'];

    /**
     * Web: Display attendance page (admin panel)
     */
    public function index(Request $request, PayrollService $payroll)
    {
        $today = Carbon::today();
        $now   = Carbon::now();

        // A day nobody closed is closed at the end of its session and flagged
        // for review. There is no scheduler on this deployment, so this runs
        // where attendance is read — as it already does for payroll and the
        // kiosk's board. Without it a forgotten time-out sat open for good,
        // reading "Invalid" on a row the office could not resolve from here.
        Attendance::closeStale(null, $now);

        // ── Global filters ──────────────────────────────────────────────────
        // Site and Shift are read once and applied to everything the page
        // shows: both tables and all four cards. Anything less and the cards
        // would be counting a different set of records from the one under
        // them, which is worse than having no filter at all.
        $sites  = Site::orderBy('name')->get();
        $shifts = Shift::orderBy('id')->get();

        // Checked against the lists rather than trusted. A stale bookmark
        // pointing at a deleted site should show the unfiltered page, not an
        // empty one with no way to tell why.
        $siteId  = $request->query('site');
        $shiftId = $request->query('shift');
        $siteId  = $sites->contains('id', (int) $siteId)   ? (int) $siteId  : null;
        $shiftId = $shifts->contains('id', (int) $shiftId) ? (int) $shiftId : null;

        // How far back the History table reaches. Unlike Site and Shift this
        // one narrows History alone: Today's Attendance is a single workday,
        // and the cards count today and this week, so every range would be
        // either a no-op or a lie up there. The control comes and goes with
        // the History tab for the same reason.
        //
        // Day counts include today, the way the audit log's periods do — "last
        // 7 days" is today and the six before it. Anything rejected falls back
        // to the whole history rather than to an empty table.
        $range = $request->query('range');
        $range = is_string($range) && in_array($range, ['7', '30', '3m', '6m', 'year', 'all'], true)
            ? $range
            : 'all';

        // A plain date comparison rather than whereDate(): the column is a
        // date, so this compares like for like on both engines and can still
        // use the index. See the MySQL-only SQL already in this app.
        // NoOverflow on the month steps: plain subMonths() on the 31st
        // walks into a month that has no 31st and lands three days late, so
        // 'last 3 months' would quietly mean something else five days a year.
        $rangeStart = match ($range) {
            '7'    => Carbon::today()->subDays(6),
            '30'   => Carbon::today()->subDays(29),
            '3m'   => Carbon::today()->subMonthsNoOverflow(3),
            '6m'   => Carbon::today()->subMonthsNoOverflow(6),
            'year' => Carbon::today()->startOfYear(),
            default => null,
        };

        $filtered = function ($query) use ($siteId, $shiftId) {
            // The attendance carries its own site and shift: one kiosk is
            // moved between sites, and a worker's shift can change, so
            // reading either off the employee would answer for today rather
            // than for the day the record is about.
            if ($siteId)  { $query->where('site_id', $siteId); }
            if ($shiftId) { $query->where('shift_id', $shiftId); }
            return $query;
        };

        // Which status the lists are narrowed to: the segmented control over
        // the tables, and the cards, which are links to the same thing. It
        // narrows the lists and deliberately NOT the cards: clicking Working
        // now must not go on to rewrite Present today as the same number.
        //
        // Nobody choosing is not the same as choosing All. Today opens on
        // Present — everybody who scanned today, working, on break, finished
        // or waiting on a review — the Present today card (Michael's call,
        // 2026-09-25; it opened on Working before). History opens on every
        // day, since "working" in the past is a question with almost no
        // answers.
        $view = array_key_exists((string) $request->query('view'), self::VIEWS)
            ? (string) $request->query('view')
            : null;
        $todayView   = $view ?? self::TODAY_DEFAULT;
        $historyView = $view ?? self::HISTORY_DEFAULT;

        // Working and On break are questions about now. A finished day is
        // neither, and History has no buttons for them, so there they read
        // as everybody rather than as an empty list.
        if (in_array($historyView, ['clocked-in', 'break'], true)) {
            $historyView = 'all';
        }

        // A name typed into the search box. Like the status, it narrows the
        // lists only — it asks "where is this person", not "how many".
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 60);

        // ── Today ───────────────────────────────────────────────────────────
        // The day each crew is working, which is not the same date for both
        // of them. A night shift that timed in at 8pm is still on its own
        // workday at 2am, on a row dated the evening before; under a plain
        // calendar filter this table went empty at midnight on exactly the
        // crew still standing on site, and their rows appeared in the history
        // as missed sign-outs while they were working.
        //
        // The site each clock was taken at comes with the row: one kiosk is
        // carried between sites, so "which site" is a property of the
        // attendance, not of the worker. The shift comes too — each day is
        // read against the shift it was worked under.
        $todayAll = $filtered(
                Attendance::with(self::WITH)
                    ->ofRegistered()
                    ->fromWorkday($now)
                    ->whereNotNull('time_in')
                    ->orderByDesc('time_in')
            )->get();

        // One line per worker per day. A morning and an afternoon are one
        // day's attendance, not two — see App\Support\AttendanceDay.
        $todayAllDays = AttendanceDay::gather($todayAll);

        // Every day read once for where it stands, unpriced: the cards count
        // from these and a status does not depend on the hours.
        $todayRead = AttendanceDayView::all($todayAllDays, $now, true, []);

        // Narrowed in memory rather than by a second query: the day view is
        // already loaded whole, because the cards above are counted from it.
        $wanted = self::VIEWS[$todayView];
        $named  = fn (?string $name) => $search === '' || str_contains(mb_strtolower((string) $name), mb_strtolower($search));
        $keep   = $todayRead->filter(fn (AttendanceDayView $d) =>
            ($wanted === null || in_array($d->key(), $wanted, true)) && $named($d->day->employee()?->name)
        );

        $scanned    = $keep->map(fn (AttendanceDayView $d) => $d->day)->values();
        $todayBoard = AttendanceDayView::all($scanned, $now, true, $this->priced($payroll, $scanned));

        // All is the whole roster: everybody who has not scanned is listed
        // too, with where they stand — expected later, not in yet, absent, or
        // not expected today at all.
        if ($wanted === null) {
            $todayBoard = $todayBoard->concat(
                $this->unscanned($todayAll->pluck('employee_id')->unique()->all(), $now, $siteId, $shiftId, $search)
            )->values();
        }

        $todayAttendances = $todayBoard->map(fn (AttendanceDayView $d) => $d->day)->values();

        // ── History ─────────────────────────────────────────────────────────
        // Workdays that have finished, which for the night crew is the
        // following morning rather than midnight. History is paginated, so it
        // narrows in SQL — filtering the fifteen rows on screen would quietly
        // ignore the rest of the result.
        //
        // Paginated by DAY, not by row. A worker's Tuesday is several rows,
        // and fifteen rows to a page would cut a day in half at the page
        // break. So the days are paged first and their rows fetched after.
        $historyFilters = fn ($query) => $filtered($query)
            ->ofRegistered()
            ->beforeWorkday($now)
            ->when($search !== '', fn ($q) => $q->whereHas('employee',
                fn ($e) => $e->withTrashed()->where('name', 'like', '%' . $search . '%')))
            ->when($historyView === 'missed', fn ($q) => $q->missedSignOut($now))
            ->when($rangeStart, fn ($q) => $q->where('date', '>=', $rangeStart->toDateString()));

        $daysQuery = $historyFilters(Attendance::query())
            ->select('employee_id', 'date')
            ->groupBy('employee_id', 'date');

        // Completed is a question about the whole day: nothing of it left
        // open and nothing the system had to close.
        if ($historyView === 'done') {
            $daysQuery->havingRaw(
                'SUM(CASE WHEN needs_review = 1 OR (time_in IS NOT NULL AND time_out IS NULL) THEN 1 ELSE 0 END) = 0'
            );
        }

        $days = $daysQuery
            ->orderBy('date', 'desc')
            ->orderBy('employee_id')
            ->paginate(15)
            // Without this, page 2 drops the filters and quietly shows
            // everything again.
            ->withQueryString();

        // Every row behind the fifteen days on this page. One OR per day
        // rather than a composite IN, which SQLite does not take and the
        // suite runs on SQLite.
        //
        // Deliberately NOT re-filtered by the status: a day matched on one of
        // its stretches is shown whole, or the reader would see a missed
        // sign-out with the rest of its own day missing.
        $historyDays = collect($days->items())->isEmpty()
            ? collect()
            : AttendanceDay::gather(
                $filtered(Attendance::with(self::WITH))
                    ->where(function ($q) use ($days) {
                        foreach ($days->items() as $day) {
                            $q->orWhere(fn ($w) => $w
                                ->where('employee_id', $day->employee_id)
                                ->where('date', $day->date));
                        }
                    })
                    ->orderBy('date', 'desc')
                    ->get()
            )->sortByDesc(fn ($d) => $d->date()->toDateString() . '|' . str_pad((string) $d->first()->employee_id, 8, '0', STR_PAD_LEFT))
             ->values();

        $historyBoard = AttendanceDayView::all($historyDays, $now, false, $this->priced($payroll, $historyDays));

        // The pager and the empty state read this; the rows come from
        // $historyDays above.
        $historyAttendances = $days;

        // ── Cards ───────────────────────────────────────────────────────────
        // Counted by worker, not by row: a day is several rows — a morning,
        // an afternoon after lunch, a stretch begun again after a mistaken
        // time-out — and counting those made one man on site read as "3
        // present" beside a workforce of one. Counted from the whole day
        // view, not from whatever the status or the search has narrowed it to.
        $presentToday = $todayAll->unique('employee_id')->count();
        $nightCrew    = $todayRead->filter(fn (AttendanceDayView $d) => (bool) $d->day->shift()?->crosses_midnight)->count();

        $working   = $todayRead->filter(fn (AttendanceDayView $d) => $d->key() === 'work');
        $clockedIn = $working->count();
        $inSecond  = $working->filter(fn (AttendanceDayView $d) => $d->status()['half'] === 'PM')->count();

        $breaks   = $todayRead->filter(fn (AttendanceDayView $d) => $d->key() === 'break');
        $onBreak  = $breaks->count();
        $overBreak = $breaks->filter(fn (AttendanceDayView $d) => $d->status()['over'])->count();

        $weekStart = Carbon::today()->startOfWeek(); // Monday — resets each week

        // Days waiting on the office this week, by the same rule the rows
        // use: the shift is over, plus an hour, and nobody clocked out — or
        // the system had to close it. Counting by date instead made a night
        // crew invalid every night; counting only still-open rows made the
        // ones the system had closed disappear from the card while the row
        // still flagged them. One per day, however many stretches it has.
        $invalidCount = $filtered(
                Attendance::whereBetween('date', [$weekStart, $today])
                    ->missedSignOut($now)
            )->get(['employee_id', 'date'])
             ->unique(fn ($r) => $r->employee_id . '|' . Carbon::parse($r->date)->toDateString())
             ->count();

        $reviewToday   = $todayRead->filter(fn (AttendanceDayView $d) => $d->key() === 'review')->count();
        $reviewEarlier = max(0, $invalidCount - $reviewToday);

        // Global holiday dates (overlay) — shown as a secondary tag.
        $holidayDates = Holiday::dateList();

        // ── Notifications ──────────────────────────────────────────────────
        $user = auth()->user();

        // Deliberately unfiltered: an alert is about the whole workforce, and
        // firing it off a filtered count would mean "no invalid attendance"
        // simply because Site B is selected.
        $invalidAll = Attendance::whereBetween('date', [$weekStart, $today])
            ->missedSignOut($now)
            ->count();

        if ($invalidAll > 0) {
            AttendanceAlert::fireOnce($user, 'invalid_clock_in',
                'Invalid Attendance Detected',
                "{$invalidAll} employee" . ($invalidAll > 1 ? 's' : '') . " clocked in but never clocked out."
            );
        }

        $totalEmployees = Employee::active()->count();
        if ($totalEmployees > 0 && $presentToday < ($totalEmployees / 2)) {
            AttendanceAlert::fireOnce($user, 'low_attendance',
                'Low Attendance Today',
                "Only {$presentToday} of {$totalEmployees} employees are present today."
            );
        }

        // Which tab to open on. A chosen status knows what it was counting but
        // not where those rows live: a missed sign-out is past its shift's
        // end, so it is usually in the history, while somebody still on site
        // is always on the day view. Landing on an empty table and leaving
        // the reader to find the other one is not an answer to "show me who".
        $openTab = $request->query('tab') === 'history' ? 'history' : 'today';
        if (in_array($view, ['missed', 'done'], true) && $todayAttendances->isEmpty() && $historyAttendances->total() > 0) {
            $openTab = 'history';
        }

        return view('attendance', compact(
            'todayAttendances', 'historyAttendances', 'historyDays', 'todayBoard', 'historyBoard',
            'presentToday', 'nightCrew', 'clockedIn', 'inSecond', 'onBreak', 'overBreak',
            'invalidCount', 'reviewToday', 'reviewEarlier', 'holidayDates',
            'sites', 'shifts', 'siteId', 'shiftId', 'range', 'view', 'todayView', 'historyView',
            'search', 'openTab', 'now'
        ));
    }

    /**
     * Everybody on the roster with nothing scanned for their shift's workday,
     * as days with no rows: the rest of the answer to "all of them".
     *
     * Read off the worker, since there is no attendance to read: their home
     * site and their current shift. Leave, the rest day and a holiday are
     * named, so somebody nobody expected is not reported absent.
     *
     * @param  array<int, int>  $present  employees already on the list
     * @return Collection<int, AttendanceDayView>
     */
    private function unscanned(array $present, Carbon $now, ?int $siteId, ?int $shiftId, string $search): Collection
    {
        $roster = Employee::active()
            ->with(['laborType', 'shift', 'site'])
            ->whereNotIn('id', $present)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($shiftId, fn ($q) => $q->where('shift_id', $shiftId))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->get();

        if ($roster->isEmpty()) {
            return collect();
        }

        $leave = LeaveRequest::approved()
            ->whereIn('employee_id', $roster->pluck('id'))
            ->overlapping($now->copy()->subDay()->toDateString(), $now->copy()->addDay()->toDateString())
            ->get(['employee_id', 'starts_on', 'ends_on'])
            ->groupBy('employee_id');

        $holidays = array_flip(Holiday::dateList());
        $restDay  = (Setting::first()?->sunday_rest_day_enabled ?? true)
            ? SystemSetting::current()->restDayOn()
            : null;

        // Not in yet and absent first — the ones the office may have to chase
        // — then those expected later, then those not expected at all.
        $rank = ['notin' => 0, 'absent' => 1, 'sched' => 2, 'off' => 3];

        return $roster->map(function (Employee $e) use ($leave, $holidays, $restDay, $now) {
            $shift = Shift::forEmployee($e);
            $date  = Attendance::workdayOf($shift?->schedule(), $now);

            $onLeave = ($leave[$e->id] ?? collect())->contains(fn ($l) =>
                Carbon::parse($l->starts_on)->toDateString() <= $date
                && Carbon::parse($l->ends_on)->toDateString() >= $date);

            $off = match (true) {
                $onLeave                                     => __('On leave'),
                isset($holidays[$date])                      => __('Holiday'),
                Carbon::parse($date)->dayOfWeek === $restDay => __('Rest day'),
                default                                      => null,
            };

            return AttendanceDayView::unscanned($e, $shift, $date, $now, $off);
        })->sortBy(fn (AttendanceDayView $d) => ($rank[$d->key()] ?? 9) . '|' . mb_strtolower((string) $d->day->employee()?->name))
          ->values();
    }

    /**
     * What payroll makes of these days' rows, by attendance id: the minutes
     * worked, the overtime among them, and how late the session started.
     *
     * Read from PayrollService rather than measured here, so the page and the
     * payslip say the same thing. Only closed stretches are priced; one still
     * running has nothing to price yet.
     *
     * @param  Collection<int, AttendanceDay>  $days
     * @return array<int, array{minutes: int, ot_minutes: int, late_minutes: int}>
     */
    private function priced(PayrollService $payroll, Collection $days): array
    {
        $rows = $days->flatMap(fn (AttendanceDay $d) => $d->rows)
            ->filter(fn (Attendance $r) => $r->time_in && $r->time_out);

        if ($rows->isEmpty()) {
            return [];
        }

        $dates  = $rows->map(fn (Attendance $r) => Carbon::parse($r->date)->toDateString());
        $result = $payroll->computeForRange($dates->min(), $dates->max(), [
            'employees'    => $rows->pluck('employee_id')->unique()->values()->all(),
            'cashAdvances' => false,
        ]);

        // Only the stretches asked about. The range brings the worker's other
        // rows along, including one still open — which payroll has nothing to
        // price yet, and measures lateness for by the old whole-shift rule.
        $closed = $rows->pluck('id')->flip();
        $priced = [];

        foreach ($result['days'] as $day) {
            foreach ($day['details'] as $d) {
                // Leave days ride in the same list, under ids of their own.
                if (! empty($d['leave']) || ! is_int($d['id'] ?? null) || ! isset($closed[$d['id']])) {
                    continue;
                }

                $priced[$d['id']] = [
                    'minutes'      => (int) $d['minutes'],
                    'ot_minutes'   => (int) $d['ot_minutes'],
                    'late_minutes' => (int) $d['late_minutes'],
                ];
            }
        }

        return $priced;
    }

    /**
     * Settle a time out nobody scanned: the office confirms the time the
     * shift says, or types the one it knows, from under the row.
     *
     * Only a stretch still waiting on one can be settled here — open, or
     * closed by the system at a guess. A time out a worker scanned is theirs,
     * and is not rewritten from this page.
     */
    public function setTimeOut(Request $request, Attendance $attendance)
    {
        $data = $request->validate(['time' => ['required', 'date_format:H:i']]);

        if (empty($attendance->time_in) || (! empty($attendance->time_out) && ! $attendance->needs_review)) {
            return response()->json(['success' => false, 'message' => __('This record already has a time out.')], 422);
        }

        if ($attendance->employee?->isPending()) {
            return response()->json(['success' => false, 'message' => __('This worker has not finished registering.')], 422);
        }

        // The time is read against the time in, rolling into the next morning
        // when it reads earlier — a night crew's 5:00 AM follows their 8:00 PM.
        $in  = AttendanceDay::momentIn($attendance);
        $out = $in->copy()->setTimeFromTimeString($data['time'])->startOfMinute();

        if ($out->lessThanOrEqualTo($in)) {
            $out->addDay();
        }

        $now = Carbon::now();

        if ($out->greaterThan($now)) {
            return response()->json(['success' => false, 'message' => __('That time has not come yet.')], 422);
        }

        if ($in->diffInMinutes($out) > 18 * 60) {
            return response()->json(['success' => false, 'message' => __('A time out has to fall within 18 hours of the time in (:in).',
                ['in' => WorkSchedule::label($in)])], 422);
        }

        // Nor may it run into the worker's next stretch of the same day.
        $next = Attendance::where('employee_id', $attendance->employee_id)
            ->where('date', $attendance->date)
            ->whereKeyNot($attendance->id)
            ->whereNotNull('time_in')
            ->with('shift')
            ->get()
            ->map(fn (Attendance $r) => AttendanceDay::momentIn($r))
            ->filter(fn (Carbon $t) => $t->greaterThan($in))
            ->sort()
            ->first();

        if ($next && $out->greaterThan($next)) {
            return response()->json(['success' => false, 'message' => __('The time out has to come before the next time in (:next).',
                ['next' => WorkSchedule::label($next)])], 422);
        }

        $attendance->forceFill([
            'time_out'     => $out->format('Y-m-d H:i:s'),
            'close_type'   => 'admin',
            'needs_review' => false,
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => $now,
        ])->save();

        $name = $attendance->employee?->name ?? __('Unknown');

        AuditLog::record('Attendance', 'updated',
            "Set the time out for {$name} on " . Carbon::parse($attendance->date)->format('m/d/Y') . ' to ' . WorkSchedule::label($out),
            $attendance
        );

        return response()->json([
            'success' => true,
            'message' => __('Time out saved for :name: :time.', ['name' => $name, 'time' => WorkSchedule::label($out)]),
        ]);
    }

    /** Delete selected history records (past days only). */
    public function bulkDeleteHistory(Request $request)
    {
        $ids     = array_filter((array) $request->input('ids', []), 'is_numeric');
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected.']);
        }
        // "Past days" as the shift that worked them reckons days. On the
        // calendar a night crew's running day is already yesterday, so
        // clearing history at two in the morning deleted the shift in
        // progress out from under the people working it.
        $deleted = Attendance::whereIn('id', $ids)
            ->beforeWorkday()
            ->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }
}
