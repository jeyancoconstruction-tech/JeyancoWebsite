<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\AttendanceDay;
use App\Support\AttendanceDayView;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * The Daily Time Record: every date of a period for every worker, as the
 * Employees DTR tab of Payroll Reports lists it and prints it (Michael,
 * 2026-10-12).
 *
 * Nothing is worked out here that another page already works out:
 *
 *  - the scans and where each one belongs are AttendanceDayView's, the same
 *    four slots the Attendance page shows;
 *  - hours, overtime, lateness and the night differential's minutes are
 *    PayrollService's, per attendance row — so a day held for review has no
 *    hours here either, until the office settles it;
 *  - undertime is the Attendance page's: the last time out against the end
 *    of the regular hours;
 *  - an absence is AnalyticsService::due() with nothing scanned, and a day
 *    nobody is expected reads On leave, Holiday or Rest day in the order the
 *    Attendance page gives them.
 */
class DtrService
{
    public const COMPLETE = 'Complete';
    public const MISSING  = 'Missing scan';
    public const ABSENT   = 'Absent';
    public const LEAVE    = 'On leave';
    public const REST     = 'Rest day';
    public const HOLIDAY  = 'Holiday';
    public const RUNNING  = 'In progress';

    /** The view's slots, as the DTR's columns name them. */
    private const SLOTS = ['in' => 'in1', 'bo' => 'out1', 'bi' => 'in2', 'out' => 'out2'];

    private const TOTALS = [
        'days' => 0, 'worked' => 0, 'regular' => 0, 'ot' => 0, 'late' => 0, 'lates' => 0,
        'under' => 0, 'night' => 0, 'absent' => 0, 'missing' => 0,
    ];

    public function __construct(private readonly PayrollService $payroll)
    {
    }

    /**
     * Every worker's days from one date to another, by employee id:
     * ['days' => [...], 'totals' => [...]]. Kept by payroll's own memory, so
     * the list, a worker's pop-up and its downloads read one computation.
     *
     * @return array<int, array{days: list<array<string, mixed>>, totals: array<string, int>}>
     */
    public function range(string $from, string $to): array
    {
        return $this->payroll->remembered('reports.dtr', $from, $to,
            fn (array $paid) => $this->build($paid, $from, $to));
    }

    private function build(array $paid, string $from, string $to): array
    {
        $now   = Carbon::now('Asia/Manila');
        $today = $now->toDateString();
        $cfg   = $this->payroll->config();

        $rows = Attendance::with('shift')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();

        // What payroll made of each closed row. One still open has nothing to
        // price yet, as on the Attendance page.
        $closed = $rows->filter(fn (Attendance $r) => $r->time_in && $r->time_out)->pluck('id')->flip();
        $priced = [];

        foreach ($paid['days'] as $day) {
            foreach ($day['details'] as $d) {
                if (! empty($d['leave']) || ! is_int($d['id'] ?? null) || ! isset($closed[$d['id']])) {
                    continue;
                }

                $priced[$d['id']] = [
                    'minutes'       => (int) $d['minutes'],
                    'ot_minutes'    => (int) $d['ot_minutes'],
                    'late_minutes'  => (int) $d['late_minutes'],
                    'night_minutes' => (int) ($d['night_minutes'] ?? 0),
                ];
            }
        }

        $views = [];
        foreach (AttendanceDay::gather($rows) as $day) {
            $views[$day->employeeId()][$day->date()->toDateString()] = new AttendanceDayView($day, $now, true, $priced);
        }

        $leave    = AnalyticsService::leaveDays($from, $to);
        $holidays = $cfg['holidayTypeMap'] ?? [];
        $restDay  = ($cfg['restDayEnabled'] ?? true) ? (int) ($cfg['restDayOn'] ?? Carbon::SUNDAY) : null;
        $fallback = Shift::find(Shift::defaultForNewHire());

        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $dates[] = $d->toDateString();
        }

        $out = [];

        // Removed and deleted workers are among them when they scanned in
        // the period: the days they worked still happened. One who left
        // without a scan in it has no time record to show, and listing them
        // would fill a month with names no longer on the Employees page.
        // Pending ones are not workforce yet.
        foreach (Employee::withTrashed()->registered()->with('shift')->get() as $e) {
            $mine = $views[$e->id] ?? [];
            $left = $e->trashed() || in_array($e->status, [Employee::STATUS_ARCHIVED, Employee::STATUS_DELETED], true);

            if (! $mine && ($left || ! $this->onRoster($e, $from, min($to, $today)))) {
                continue;
            }

            $schedule = ($e->shift ?? $fallback)?->schedule();
            $days     = [];
            $totals   = self::TOTALS;

            foreach ($dates as $date) {
                $off = match (true) {
                    isset($leave[$e->id][$date])                                     => self::LEAVE,
                    isset($holidays[$date])                                          => self::HOLIDAY,
                    $restDay !== null && Carbon::parse($date)->dayOfWeek === $restDay => self::REST,
                    default                                                          => null,
                };

                $row = isset($mine[$date])
                    ? $this->worked($mine[$date], $priced, $off)
                    : $this->unworked($e, $date, $schedule, $now, $leave, $off);

                $days[] = $row;
                $this->count($totals, $row);
            }

            $out[$e->id] = ['days' => $days, 'totals' => $totals];
        }

        return $out;
    }

    /** A day with scans: where each one falls, and what payroll made of it. */
    private function worked(AttendanceDayView $view, array $priced, ?string $off): array
    {
        $day   = $view->day;
        $date  = $day->date()->toDateString();
        $key   = $view->key();
        $hours = $view->hours();
        $sched = $day->shift()?->schedule();

        $row = ['date' => $date, 'miss' => [], 'edited' => []] + array_fill_keys(self::SLOTS, null);

        foreach (self::SLOTS as $slot => $name) {
            $scan = $view->slot($slot);

            // A time out the system closed for the worker is not a scan, and
            // neither is the one a day in review is still waiting on.
            if (($view->tag($slot)['tone'] ?? null) === 'bad') {
                $row['miss'][] = $name;
                continue;
            }

            if ($scan) {
                $row[$name] = WorkSchedule::label($scan['at']);

                if ($scan['edited']) {
                    $row['edited'][] = $name;
                }
            }
        }

        // Late to the first session, and late back from the break.
        $late = 0;
        foreach (['in', 'bi'] as $slot) {
            $in = $view->slot($slot)['row'] ?? null;

            if ($in) {
                $late += isset($priced[$in->id])
                    ? $priced[$in->id]['late_minutes']
                    : (WorkSchedule::has($sched)
                        ? WorkSchedule::lateMinutes($sched, AttendanceDay::momentIn($in), $view->sessionOf($in), $date)
                        : 0);
            }
        }

        $night = 0;
        foreach ($day->stretches() as $r) {
            $night += $priced[$r->id]['night_minutes'] ?? 0;
        }

        $settled = $key === 'done';

        [$remark, $tone] = match ($key) {
            'done'            => [self::COMPLETE, 'good'],
            'review', 'norec' => [self::MISSING, 'bad'],
            default           => [self::RUNNING, 'live'],
        };

        return $row + [
            'kind'    => $key,
            'worked'  => $hours['worked'] ?? null,
            'regular' => $hours['regular'] ?? null,
            'ot'      => $hours['ot'] ?? null,
            'late'    => $key === 'norec' ? null : $late,
            'under'   => $settled ? $this->undertime($view) : null,
            'night'   => $hours ? $night : null,
            'remark'  => $remark,
            'tone'    => $tone,
            // Which scan is missing; or that the day worked was a day off.
            'note'    => match (true) {
                $settled               => $off,
                $remark === self::MISSING => $view->status()['label'],
                default                => null,
            },
        ];
    }

    /**
     * Minutes short at the end of the day: from the last time out to the end
     * of the regular hours — the Attendance page's "Undertime". A day that
     * stopped at the break is short the second session too, less the break
     * itself, which is nobody's working time.
     */
    private function undertime(AttendanceDayView $view): int
    {
        if (! $view->hasSchedule()) {
            return 0;
        }

        $last = ($view->slot('out') ?? $view->slot('bo'))['at'] ?? null;
        $end  = $view->expected('out');

        if (! $last || ! $last->lessThan($end)) {
            return 0;
        }

        $breakFrom = $view->expected('bo')->max($last);
        $breakTo   = $view->expected('bi')->min($end);
        $break     = $breakTo->greaterThan($breakFrom) ? (int) $breakFrom->diffInMinutes($breakTo) : 0;

        return max(0, (int) $last->diffInMinutes($end) - $break);
    }

    /** A day with no scans: nobody expected, absent, or nothing to say yet. */
    private function unworked(Employee $e, string $date, ?array $schedule, Carbon $now, array $leave, ?string $off): array
    {
        $row = ['date' => $date, 'miss' => [], 'edited' => []] + array_fill_keys(self::SLOTS, null) + [
            'kind' => 'none', 'worked' => null, 'regular' => null, 'ot' => null, 'late' => null,
            'under' => null, 'night' => null, 'remark' => '', 'tone' => '', 'note' => null,
        ];

        if (! $this->onRoster($e, $date, $date)) {
            return $row;
        }

        if ($off) {
            return ['kind' => 'off', 'remark' => $off, 'tone' => 'mute'] + $row;
        }

        if ($date <= $now->toDateString() && AnalyticsService::due($e, $date, $schedule, $now, $leave)) {
            return ['kind' => 'absent', 'remark' => self::ABSENT, 'tone' => 'bad'] + $row;
        }

        return $row;
    }

    /** On the Employees list at some point between two dates. */
    private function onRoster(Employee $e, string $from, string $to): bool
    {
        if ($e->created_at && $e->created_at->toDateString() > $to) {
            return false;
        }

        foreach ([$e->archived_at, $e->deleted_at] as $left) {
            if ($left && Carbon::parse($left)->toDateString() <= $from) {
                return false;
            }
        }

        // Off the list with no date to say since when: not counted as due.
        return $e->archived_at || $e->deleted_at
            || ! in_array($e->status, [Employee::STATUS_ARCHIVED, Employee::STATUS_DELETED], true);
    }

    private function count(array &$totals, array $row): void
    {
        // A day the office declined was not recorded, so it is not a day worked.
        if (in_array($row['kind'], ['done', 'review', 'work', 'break'], true)) {
            $totals['days']++;
        }
        if (in_array($row['kind'], ['review', 'norec'], true)) {
            $totals['missing']++;
        }
        if ($row['kind'] === 'absent') {
            $totals['absent']++;
        }
        if (($row['late'] ?? 0) > 0) {
            $totals['lates']++;
        }

        foreach (['worked', 'regular', 'ot', 'late', 'under', 'night'] as $k) {
            $totals[$k] += (int) ($row[$k] ?? 0);
        }
    }

    /**
     * "Oct 5 – 11, 2026", "Sep 28 – Oct 4, 2026", or the month by name when
     * the dates are a whole one.
     */
    public static function label(Carbon $from, Carbon $to): string
    {
        if ($from->day === 1 && $from->isSameMonth($to) && $to->day === $to->daysInMonth) {
            return $from->format('F Y');
        }

        return match (true) {
            $from->isSameDay($to)         => $from->format('M j, Y'),
            $from->isSameMonth($to)       => $from->format('M j') . ' – ' . $to->format('j, Y'),
            $from->year === $to->year     => $from->format('M j') . ' – ' . $to->format('M j, Y'),
            default                       => $from->format('M j, Y') . ' – ' . $to->format('M j, Y'),
        };
    }

    /** "8:00 AM – 5:00 PM · break 12:00 PM – 1:00 PM", or null with no hours on file. */
    public static function hoursOf(?Shift $shift, string $date): ?string
    {
        $schedule = $shift?->schedule();

        if (! WorkSchedule::has($schedule)) {
            return null;
        }

        $w = WorkSchedule::windows($schedule, $date);

        return WorkSchedule::label($w['AM'][0]) . ' – ' . WorkSchedule::label($w['PM'][1])
            . ' · ' . __('break') . ' ' . WorkSchedule::label($w['AM'][1]) . ' – ' . WorkSchedule::label($w['PM'][0]);
    }
}
