<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Site;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * The figures behind Analytics & Insights, for one set of filters.
 *
 * Nothing is measured afresh. Hours, overtime, lateness and pay are read off
 * the rows PayrollService already computed — the ones payslips are built
 * from — so a chart cannot disagree with what somebody was paid. What this
 * adds is the slicing: by the site each clock was taken at, by the shift each
 * day was worked under, by the worker's status, and by day and pay week.
 *
 * Pending registrations are left out throughout. They are not workforce until
 * the kiosk has their finger.
 */
class AnalyticsService
{
    /** The Date range options, in the order the page lists them. */
    public const RANGES = [
        '30'    => 'Last 30 days',
        '14'    => 'Last 14 days',
        '7'     => 'Last 7 days',
        'month' => 'This month',
    ];

    public const STATUSES = [
        'all'      => 'All status',
        'active'   => 'Active',
        'inactive' => 'Inactive',
    ];

    public function __construct(private PayrollService $payroll)
    {
    }

    /**
     * The filters as asked for, checked rather than trusted. A stale bookmark
     * naming a deleted site falls back to every site instead of an empty page
     * nobody could explain.
     *
     * @return array{range: string, site: int|string, shift: int|string, status: string}
     */
    public function filters(array $input): array
    {
        $pick = fn (string $key, string $default): string =>
            is_scalar($input[$key] ?? null) ? (string) $input[$key] : $default;

        $range  = $pick('range', '30');
        $site   = $pick('site', 'all');
        $shift  = $pick('shift', 'all');
        $status = $pick('status', 'all');

        return [
            'range'  => array_key_exists($range, self::RANGES) ? $range : '30',
            'site'   => ctype_digit($site) && Site::whereKey((int) $site)->exists() ? (int) $site : 'all',
            'shift'  => ctype_digit($shift) && Shift::whereKey((int) $shift)->exists() ? (int) $shift : 'all',
            'status' => array_key_exists($status, self::STATUSES) ? $status : 'all',
        ];
    }

    /** Every figure the page shows, for one set of filters. */
    public function build(array $f): array
    {
        $now     = Carbon::now();
        $to      = $now->copy()->startOfDay();
        $from    = $f['range'] === 'month'
            ? $to->copy()->startOfMonth()
            : $to->copy()->subDays((int) $f['range'] - 1);
        $fromStr = $from->toDateString();
        $toStr   = $to->toDateString();

        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $dates[] = $d->toDateString();
        }

        $cfg        = $this->payroll->config();
        $computed   = $this->payroll->computeForRange($fromStr, $toStr);
        $weeks      = $this->payWeeks($computed['weeks']);
        $siteNames  = Site::orderBy('name')->pluck('name', 'id');
        $shiftNames = Shift::orderBy('id')->pluck('name', 'id');

        // Everybody the status filter admits. Site and shift then narrow the
        // roster by where each worker is assigned; attendance is narrowed on
        // its own, by where each clock was actually taken.
        $people = Employee::registered()
            ->when($f['status'] === 'active', fn ($q) => $q->where('status', Employee::STATUS_ACTIVE))
            ->when($f['status'] === 'inactive', fn ($q) => $q->where('status', Employee::STATUS_ARCHIVED))
            ->get(['id', 'status', 'site_id', 'shift_id', 'created_at', 'archived_at'])
            ->keyBy('id');

        $defaultShift = Shift::defaultForNewHire();
        $shiftOf      = fn (Employee $e): int => (int) ($e->shift_id ?? $defaultShift);

        $roster = $people->filter(fn (Employee $e) =>
            ($f['site'] === 'all' || (int) $e->site_id === $f['site'])
            && ($f['shift'] === 'all' || $shiftOf($e) === $f['shift']));

        // ── Attendance, as payroll read it ─────────────────────────────────
        // Where each record was clocked and under which shift. The payroll
        // rows name the shift but carry no site, and one kiosk is carried
        // between sites, so the attendance is asked rather than the worker.
        $where = Attendance::whereBetween('date', [$fromStr, $toStr])
            ->get(['id', 'site_id', 'shift_id'])
            ->keyBy('id');

        $zero       = array_fill_keys($dates, 0);
        $late       = $zero;   // late starts in scope, per day
        $came       = [];      // date => [employee => true], wherever they clocked
        $inScope    = [];      // date => [employee => true], at the site and shift asked for
        $shiftHours = [];      // shift id => [date => hours]
        $share      = [];      // pay week => employee => gross in scope, and in all
        $totals     = ['late' => 0, 'ot' => 0.0, 'hours' => 0.0, 'gross' => 0.0];

        foreach ($computed['days'] as $day) {
            $date = Carbon::parse($day['date'])->toDateString();
            if (! array_key_exists($date, $zero)) {
                continue;
            }

            $week = $this->weekOf($weeks, $date);

            foreach ($day['details'] as $r) {
                $emp = (int) $r['employee_id'];
                if (! $people->has($emp)) {
                    continue;
                }

                $row   = $where->get($r['id']);
                $match = ($f['site'] === 'all' || (int) ($row->site_id ?? 0) === $f['site'])
                      && ($f['shift'] === 'all' || (int) ($row->shift_id ?? 0) === $f['shift']);

                $came[$date][$emp] = true;

                if ($week !== null) {
                    $s = $share[$week][$emp] ?? ['in' => 0.0, 'all' => 0.0, 'in_n' => 0, 'all_n' => 0];
                    $s['all'] += (float) $r['gross'];
                    $s['all_n']++;
                    if ($match) {
                        $s['in'] += (float) $r['gross'];
                        $s['in_n']++;
                    }
                    $share[$week][$emp] = $s;
                }

                if (! $match) {
                    continue;
                }

                $inScope[$date][$emp] = true;

                if ((int) $r['late_minutes'] > 0) {
                    $late[$date]++;
                    $totals['late']++;
                }

                $key = (int) ($row->shift_id ?? 0);
                $shiftHours[$key][$date] = ($shiftHours[$key][$date] ?? 0) + (float) $r['hours'];

                $totals['ot']    += (float) $r['ot_hours'];
                $totals['hours'] += (float) $r['hours'];
                $totals['gross'] += (float) $r['gross'];
            }
        }

        // A day is one worker on one workday, however many rows it took.
        $present = $zero;
        foreach ($dates as $date) {
            $present[$date] = count($inScope[$date] ?? []);
        }

        // ── Who was due and did not come ───────────────────────────────────
        // Counted against the roster by where each worker is assigned: a
        // crew member who clocked in at another site still came to work.
        $leave    = $this->leaveDays($fromStr, $toStr);
        $absent   = $zero;
        $expected = $zero;
        $bySite   = [];   // home site id (0 for none) => ['due' => n, 'came' => n]

        foreach ($dates as $date) {
            if ($this->dayOff($date, $cfg)) {
                continue;
            }

            foreach ($roster as $e) {
                if (! $this->due($e, $date, $cfg['shifts'][$shiftOf($e)] ?? null, $now, $leave)) {
                    continue;
                }

                $showed = isset($came[$date][$e->id]);
                $home   = $siteNames->has($e->site_id) ? (int) $e->site_id : 0;

                $expected[$date]++;
                $bySite[$home]['due']  = ($bySite[$home]['due'] ?? 0) + 1;
                $bySite[$home]['came'] = ($bySite[$home]['came'] ?? 0) + ($showed ? 1 : 0);

                if (! $showed) {
                    $absent[$date]++;
                }
            }
        }

        // ── Attendance rate by home site ───────────────────────────────────
        $sites = [];
        foreach ($siteNames->all() + [0 => 'Unassigned'] as $id => $name) {
            if (($bySite[$id]['due'] ?? 0) > 0) {
                $sites[] = ['name' => $name, 'rate' => (int) round($bySite[$id]['came'] / $bySite[$id]['due'] * 100)];
            }
        }

        // ── Hours by shift ─────────────────────────────────────────────────
        $datasets = [];
        foreach ($shiftNames as $id => $name) {
            if ($f['shift'] === 'all' || $f['shift'] === (int) $id) {
                $datasets[] = ['label' => $name, 'data' => $this->series($shiftHours[$id] ?? [], $dates)];
            }
        }

        // Hours clocked under no shift, or one since deleted.
        $orphaned = [];
        foreach ($shiftHours as $id => $byDate) {
            if (! $shiftNames->has($id)) {
                foreach ($byDate as $date => $hours) {
                    $orphaned[$date] = ($orphaned[$date] ?? 0) + $hours;
                }
            }
        }
        if ($orphaned) {
            $datasets[] = ['label' => 'No shift', 'data' => $this->series($orphaned, $dates)];
        }

        // ── Payroll by pay week ────────────────────────────────────────────
        // Gross is summed from the records in scope. Net is a figure for a
        // worker's whole week — deductions, advances and the bonus are not
        // per day — so a filter takes the share of it that its records earned.
        $payroll = ['labels' => [], 'gross' => [], 'net' => []];
        foreach ($weeks as $w) {
            $gross = $net = 0.0;

            foreach ($w['details'] as $d) {
                $s = $share[$w['key']][(int) $d['employee_id']] ?? null;
                if ($s === null) {
                    continue;
                }

                $part = $s['all'] > 0
                    ? $s['in'] / $s['all']
                    : ($s['all_n'] > 0 ? $s['in_n'] / $s['all_n'] : 0.0);

                $gross += $s['in'];
                $net   += (float) $d['net'] * $part;
            }

            $payroll['labels'][] = $this->span(max($w['from'], $fromStr), min($w['to'], $toStr));
            $payroll['gross'][]  = round($gross, 2);
            $payroll['net'][]    = round($net, 2);
        }

        // ── The cards ──────────────────────────────────────────────────────
        $workingDays = count(array_filter($dates, fn ($d) => $expected[$d] > 0 || $present[$d] > 0));
        $activeSites = $roster->where('status', Employee::STATUS_ACTIVE)->pluck('site_id')->filter()->unique()->count();

        $shiftName = $f['shift'] !== 'all' ? (string) ($shiftNames[$f['shift']] ?? '') : '';
        $scope     = implode(' · ', array_filter([
            $f['site'] !== 'all' ? $siteNames[$f['site']] ?? null : null,
            $shiftName !== '' ? (stripos($shiftName, 'shift') === false ? $shiftName . ' shift' : $shiftName) : null,
            $f['status'] !== 'all' ? self::STATUSES[$f['status']] . ' employees' : null,
        ]));

        return [
            'filters' => $f,
            'period'  => [
                'from'  => $fromStr,
                'to'    => $toStr,
                'label' => $f['range'] === 'month'
                    ? $to->format('F Y')
                    : $this->span($fromStr, $toStr) . ', ' . $to->format('Y'),
                'scope' => $scope,
                'tag'   => $f['range'] === 'month' ? 'This month' : $f['range'] . '-day view',
            ],
            'cards' => [
                'totalEmp' => $roster->count(),
                'present'  => round(array_sum($present) / max(1, $workingDays), 1),
                'absent'   => round(array_sum($absent) / max(1, $workingDays), 1),
                'late'     => $totals['late'],
                'ot'       => round($totals['ot'], 1),
                'hours'    => (int) round($totals['hours']),
                'payroll'  => round($totals['gross'], 2),
            ],
            'meta' => [
                'totalEmp' => $activeSites . ' active site' . ($activeSites === 1 ? '' : 's'),
                'present'  => 'Avg / working day',
                'absent'   => 'Avg / working day',
                'late'     => 'Instances',
                'ot'       => 'Total OT hours',
                'hours'    => $scope === '' ? 'All employees' : 'Employees in scope',
                'payroll'  => 'Gross for period',
            ],
            'trend' => [
                'labels'  => array_map(fn ($d) => Carbon::parse($d)->format('M d'), $dates),
                'present' => array_values($present),
                'late'    => array_values($late),
                'absent'  => array_values($absent),
            ],
            'shiftHours' => [
                'labels'   => array_map(fn ($d) => Carbon::parse($d)->format('M d'), $dates),
                'datasets' => $datasets,
            ],
            'sites'      => $sites,
            'payroll'    => $payroll,
            'updated_at' => $now->format('g:i A'),
        ];
    }

    /** A rest day or a holiday: nobody is due, so nobody is absent. */
    private function dayOff(string $date, array $cfg): bool
    {
        if (isset($cfg['holidayTypeMap'][$date])) {
            return true;
        }

        return ($cfg['restDayEnabled'] ?? true)
            && Carbon::parse($date)->dayOfWeek === (int) ($cfg['restDayOn'] ?? Carbon::SUNDAY);
    }

    /**
     * Was this worker due on site that day? Not before they were taken on,
     * not after they left, not on approved leave — and not on a shift that has
     * not started yet, or the night crew would read as absent every morning.
     */
    private function due(Employee $e, string $date, ?array $schedule, Carbon $now, array $leave): bool
    {
        if ($e->created_at && $e->created_at->toDateString() > $date) {
            return false;
        }
        if ($e->archived_at && $e->archived_at->toDateString() <= $date) {
            return false;
        }
        if (isset($leave[$e->id][$date])) {
            return false;
        }

        if (WorkSchedule::has($schedule)) {
            $starts = WorkSchedule::windows($schedule, $date)['AM'][0];

            return $now->greaterThanOrEqualTo($starts->copy()->addMinutes((int) ($schedule['grace'] ?? 0)));
        }

        // No hours on file to say when the day begins: only a day already
        // over can have been missed.
        return $date < $now->toDateString();
    }

    /** Approved leave, as employee => [date => true], clipped to the range. */
    private function leaveDays(string $from, string $to): array
    {
        $days = [];

        foreach (LeaveRequest::approved()->overlapping($from, $to)->get(['employee_id', 'starts_on', 'ends_on']) as $l) {
            $start = max($from, $l->starts_on->toDateString());
            $end   = min($to, $l->ends_on->toDateString());

            foreach (CarbonPeriod::create($start, $end) as $d) {
                $days[$l->employee_id][$d->toDateString()] = true;
            }
        }

        return $days;
    }

    /**
     * Payroll's own weeks, with their dates read back out of the label it
     * keys them by, in order. The week opens on the day System Settings names,
     * so the boundaries are taken from payroll rather than worked out again.
     */
    private function payWeeks(array $weeks): array
    {
        return collect($weeks)->map(function (array $w) {
            [$a, $b] = array_map('trim', explode(' - ', $w['week_range']));

            return [
                'key'     => $w['week_range'],
                'from'    => Carbon::createFromFormat('!m/d/Y', $a)->toDateString(),
                'to'      => Carbon::createFromFormat('!m/d/Y', $b)->toDateString(),
                'details' => $w['details'],
            ];
        })->sortBy('from')->values()->all();
    }

    private function weekOf(array $weeks, string $date): ?string
    {
        foreach ($weeks as $w) {
            if ($w['from'] <= $date && $date <= $w['to']) {
                return $w['key'];
            }
        }

        return null;
    }

    /** One value per date, rounded for display. */
    private function series(array $byDate, array $dates): array
    {
        return array_map(fn ($d) => round((float) ($byDate[$d] ?? 0), 1), $dates);
    }

    /** "Sep 07 – 13", or "Aug 31 – Sep 06" across a month. */
    private function span(string $from, string $to): string
    {
        $a = Carbon::parse($from);
        $b = Carbon::parse($to);

        if ($from === $to) {
            return $a->format('M d');
        }

        return $a->format('M d') . ' – ' . ($a->isSameMonth($b) ? $b->format('d') : $b->format('M d'));
    }
}
