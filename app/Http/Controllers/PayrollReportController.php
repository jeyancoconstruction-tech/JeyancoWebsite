<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\PayrollRate;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\PayrollService;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Payroll Reports, under Payroll Records (laid out after Michael's
 * jeyanco-payroll-reports.html, 2026-09-26).
 *
 * Every figure comes from PayrollService::computeForRange — the same
 * computation Payroll Records, the payslip and the Remittance Tracker read —
 * over whole pay weeks, and nothing here prices anything of its own. The
 * reports used to read signed-off payroll runs, but runs have not been made
 * since Payroll Processing went, so they had nothing left to report.
 *
 * A pay week is a payroll period. Its week follows "Week starts" in Payroll
 * Settings, as the weekly periods payroll produces do.
 */
class PayrollReportController extends Controller
{
    public const REPORTS = [
        'summary'    => 'Payroll Summary',
        'employee'   => 'Payroll by Employee',
        'site'       => 'Labor Cost by Site',
        'overtime'   => 'Overtime',
        'deductions' => 'Deductions',
        'advances'   => 'Cash Advances',
    ];

    public const PRESETS = [
        'week'   => 'This week',
        'lweek'  => 'Last week',
        'month'  => 'This month',
        'lmonth' => 'Last month',
        'ytd'    => 'Year to date',
    ];

    /** The deduction columns, in the order the payslip lists them. */
    private const DEDUCTIONS = [
        'sss'     => 'SSS',
        'ph'      => 'PhilHealth',
        'pagibig' => 'Pag-IBIG',
        'tax'     => 'Tax',
        'advance' => 'Cash advance',
        'vale'    => 'Vale',
        'other'   => 'Other',
    ];

    public function index(Request $request, PayrollService $payroll)
    {
        return view('reports.index', $this->build($request, $payroll));
    }

    /**
     * The report on screen, for Excel. An HTML table under an .xls name, the
     * way Payroll Records exports: a real .xlsx needs ext-zip, which the
     * deployment image does not have.
     */
    public function export(Request $request, PayrollService $payroll)
    {
        $data = $this->build($request, $payroll);
        $html = view('reports.export', $data)->render();
        $file = 'payroll-report_' . $data['report'] . '_' . $data['from'] . '_to_' . $data['to'] . '.xls';

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
    }

    // ── The page ─────────────────────────────────────────────────────────────

    private function build(Request $request, PayrollService $payroll): array
    {
        $report = array_key_exists((string) $request->query('report'), self::REPORTS) ? $request->query('report') : 'summary';
        [$preset, $from, $to] = $this->period($request);
        $weeks  = max(1, (int) round((abs($from->diffInDays($to)) + 1) / 7));

        // The period before, as long as this one, for the "vs" figures.
        $prevTo   = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($weeks * 7 - 1);

        $rows = $payroll->remembered('reports', $from->toDateString(), $to->toDateString(), fn ($d) => $this->shape($d));
        $prev = $payroll->remembered('reports.prev', $prevFrom->toDateString(), $prevTo->toDateString(), fn ($d) => $this->shape($d));

        // Who each worker is, and where: read now rather than kept, so a
        // move to another site shows at once.
        $ids   = array_values(array_unique(array_merge(array_column($rows, 'id'), array_column($prev, 'id'))));
        $staff = Employee::withTrashed()->with(['site:id,name', 'laborType:id,name'])
            ->whereIn('id', $ids)->get(['id', 'name', 'position', 'site_id', 'labor_type_id'])->keyBy('id');

        $siteId = $request->filled('site') ? (int) $request->query('site') : null;
        $q      = trim((string) $request->query('q', ''));
        $keep   = function (array $r) use ($staff, $siteId, $q) {
            $e = $staff[$r['id']] ?? null;
            if ($siteId !== null && (int) ($e->site_id ?? 0) !== $siteId) {
                return false;
            }
            if ($q !== '') {
                $needle = mb_strtolower(ltrim($q, '#'));
                return str_contains(mb_strtolower($r['name']), $needle)
                    || ltrim((string) $r['id'], '0') === ltrim($needle, '0');
            }
            return true;
        };
        $rows = array_values(array_filter($rows, $keep));
        $prev = array_values(array_filter($prev, $keep));

        $who = fn (int $id, string $name, ?string $position = null) => $this->who($id, $name, $position, $staff[$id] ?? null);

        $rateSet = PayrollRate::effectiveOn($to->toDateString());
        $rates   = $rateSet ? $rateSet->toRates() : PayrollRate::fallbackRates();

        [$chart, $table] = match ($report) {
            'employee'   => $this->byEmployee($rows, $who),
            'site'       => $this->bySite($rows, $staff),
            'overtime'   => $this->overtime($rows, $who, $rates),
            'deductions' => $this->deductions($rows, $who),
            'advances'   => $this->advances($to, $siteId, $q, $staff),
            default      => $this->summary($rows, $from, $to),
        };

        return [
            'report'  => $report,
            'reports' => self::REPORTS,
            'presets' => self::PRESETS,
            'preset'  => $preset,
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'fromC'   => $from,
            'toC'     => $to,
            'weeks'   => $weeks,
            'kpis'    => $this->kpis($rows, $prev, $from, $to, $weeks),
            'chart'   => $chart,
            'table'   => $table,
            // Kept for the tests and anything else that reads the page's rows.
            'rows'    => $table['rows'],
            'sites'   => Site::orderBy('name')->get(['id', 'name']),
            'siteId'  => $siteId,
            'q'       => $q,
        ];
    }

    /**
     * The period asked for, as whole pay weeks, never past the week running
     * now. This month (the default) is the pay weeks that end in it, as the
     * Remittance Tracker counts them.
     *
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function period(Request $request): array
    {
        $today  = Carbon::now('Asia/Manila')->startOfDay();
        $starts = (int) (SystemSetting::current()->week_starts_on ?? Carbon::MONDAY);
        $weekOf = fn (Carbon $d) => $d->copy()->startOfDay()->subDays(($d->dayOfWeek - $starts + 7) % 7);
        $thisWeek = $weekOf($today);
        $lastDay  = $thisWeek->copy()->addDays(6);

        // Pay weeks that end in a month.
        $inMonth = function (Carbon $month) use ($weekOf) {
            $first = $month->copy()->startOfMonth();
            $end   = $weekOf($first)->addDays(6);
            if ($end->lt($first)) {
                $end->addWeek();
            }
            $last = $weekOf($month->copy()->endOfMonth()->startOfDay())->addDays(6);
            if ($last->gt($month->copy()->endOfMonth())) {
                $last->subWeek();
            }
            return [$end->copy()->subDays(6), $last];
        };

        $preset = (string) $request->query('preset', '');
        $fromIn = (string) $request->query('from', '');
        $toIn   = (string) $request->query('to', '');

        if (! array_key_exists($preset, self::PRESETS)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromIn) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toIn)) {
            $a = Carbon::parse($fromIn, 'Asia/Manila');
            $b = Carbon::parse($toIn, 'Asia/Manila');
            if ($b->lt($a)) {
                [$a, $b] = [$b, $a];
            }
            $from = $weekOf($a);
            $to   = $weekOf($b)->addDays(6);
            $preset = 'custom';
        } else {
            $preset = array_key_exists($preset, self::PRESETS) ? $preset : 'month';
            [$from, $to] = match ($preset) {
                'week'   => [$thisWeek->copy(), $lastDay->copy()],
                'lweek'  => [$thisWeek->copy()->subWeek(), $thisWeek->copy()->subDay()],
                'lmonth' => $inMonth($today->copy()->startOfMonth()->subMonthNoOverflow()),
                'ytd'    => [$inMonth($today->copy()->startOfYear())[0], $lastDay->copy()],
                default  => $inMonth($today->copy()),
            };
        }

        // Nothing past the week running now; never an empty range.
        if ($to->gt($lastDay)) {
            $to = $lastDay->copy();
        }
        if ($to->lt($from)) {
            $from = $weekOf($to);
        }

        return [$preset, $from, $to];
    }

    /**
     * One row per worker per pay week, off payroll's own weekly periods. The
     * overtime minutes come off the day rows, which are the only ones that
     * carry them. Regular pay is the gross less every premium and the paid
     * leave, as Payroll Records works it out.
     */
    private function shape(array $data): array
    {
        $ot = [];
        foreach ($data['days'] as $day) {
            foreach ($day['details'] as $d) {
                $ot[$d['employee_id']][$day['date']] = ($ot[$d['employee_id']][$day['date']] ?? 0) + (int) ($d['ot_minutes'] ?? 0);
            }
        }

        $out = [];
        foreach ($data['employees'] as $emp) {
            foreach ($emp['periods'] as $p) {
                $range = explode(' - ', (string) ($p['week_range'] ?? ''));
                if (count($range) !== 2) {
                    continue;
                }
                $start = Carbon::createFromFormat('m/d/Y', trim($range[0]), 'Asia/Manila')->startOfDay();
                $end   = Carbon::createFromFormat('m/d/Y', trim($range[1]), 'Asia/Manila')->startOfDay();

                $otMin = 0;
                foreach ($ot[$emp['employee_id']] ?? [] as $date => $m) {
                    if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                        $otMin += $m;
                    }
                }

                $gross   = (float) $p['gross'];
                $prem    = (float) $p['overtime'] + (float) $p['holidayPay'] + (float) ($p['restDayPay'] ?? 0) + (float) ($p['nightDiffPay'] ?? 0);
                $leave   = (float) ($p['leavePay'] ?? 0);

                $out[] = [
                    'id'       => (int) $emp['employee_id'],
                    'name'     => $emp['name'],
                    'position' => $emp['position'] ?? '',
                    'start'    => $start->toDateString(),
                    'end'      => $end->toDateString(),
                    'workdays' => (float) $p['workdays'],
                    'minutes'  => (int) $p['minutes'],
                    'otMin'    => $otMin,
                    'regular'  => round($gross - $prem - $leave, 2),
                    'leave'    => round($leave, 2),
                    'overtime' => round((float) $p['overtime'], 2),
                    'premiums' => round($prem, 2),
                    'bonus'    => round((float) $p['bonus'], 2),
                    'gross'    => round($gross, 2),
                    'sss'      => round((float) $p['sssDeduction'], 2),
                    'ph'       => round((float) $p['philhealthDeduction'], 2),
                    'pagibig'  => round((float) $p['pagibigDeduction'], 2),
                    'tax'      => round((float) $p['withholdingTax'], 2),
                    'advance'  => round((float) ($p['vale_advance'] ?? 0), 2),
                    'vale'     => round((float) $p['vale'], 2),
                    'other'    => round((float) $p['manualDeductions'], 2),
                    'ded'      => round((float) $p['totalDeductions'], 2),
                    'net'      => round((float) $p['net'], 2),
                ];
            }
        }

        return $out;
    }

    // ── The strip of totals ──────────────────────────────────────────────────

    private function kpis(array $rows, array $prev, Carbon $from, Carbon $to, int $weeks): array
    {
        $sum     = fn (array $rs, string $k) => round(array_sum(array_column($rs, $k)), 2);
        $workers = fn (array $rs) => count(array_unique(array_column(array_filter($rs, fn ($r) => $r['minutes'] > 0 || $r['gross'] > 0), 'id')));
        $vs      = function (float $now, float $before) {
            if ($before <= 0) {
                return null;
            }
            return round(($now - $before) / $before * 100, 1);
        };
        $firstWeek = $from->isoWeek;
        $lastWeek  = $to->copy()->subDays(6)->isoWeek;

        return [
            ['Payroll periods', (string) $weeks, $weeks === 1 ? 'Week ' . $firstWeek : 'Weeks ' . $firstWeek . '–' . $lastWeek, null, ''],
            ['Workers', (string) $workers($rows), null, $vs($workers($rows), $workers($prev)), ''],
            ['Hours', WorkSchedule::duration((int) array_sum(array_column($rows, 'minutes'))),
                WorkSchedule::duration((int) array_sum(array_column($rows, 'otMin'))) . ' overtime', null, ''],
            ['Gross pay', '₱' . number_format($sum($rows, 'gross'), 2), null, $vs($sum($rows, 'gross'), $sum($prev, 'gross')), ''],
            ['Deductions', '₱' . number_format($sum($rows, 'ded'), 2), 'SSS · PH · Pag-IBIG · tax · advances', null, ''],
            ['Net pay', '₱' . number_format($sum($rows, 'net'), 2), null, $vs($sum($rows, 'net'), $sum($prev, 'net')), 'net'],
        ];
    }

    // ── The reports ──────────────────────────────────────────────────────────

    /** One row per pay week: what each period came to. */
    private function summary(array $rows, Carbon $from, Carbon $to): array
    {
        $today = Carbon::now('Asia/Manila')->startOfDay();
        $weeks = [];
        foreach ($rows as $r) {
            $w = &$weeks[$r['start']];
            $w ??= ['start' => $r['start'], 'end' => $r['end'], 'ids' => [], 'minutes' => 0, 'gross' => 0, 'ded' => 0, 'net' => 0];
            if ($r['minutes'] > 0 || $r['gross'] > 0) {
                $w['ids'][$r['id']] = true;
            }
            $w['minutes'] += $r['minutes'];
            $w['gross']   += $r['gross'];
            $w['ded']     += $r['ded'];
            $w['net']     += $r['net'];
            unset($w);
        }
        ksort($weeks);
        if ($weeks) {
            $first = Carbon::parse(array_key_first($weeks), 'Asia/Manila');
            $last  = Carbon::parse(array_key_last($weeks), 'Asia/Manila');
            for ($d = $first->copy(); $d->lte($last); $d->addWeek()) {
                $weeks[$d->toDateString()] ??= ['start' => $d->toDateString(), 'end' => $d->copy()->addDays(6)->toDateString(),
                                               'ids' => [], 'minutes' => 0, 'gross' => 0, 'ded' => 0, 'net' => 0];
            }
            ksort($weeks);
        }

        $list = [];
        foreach ($weeks as $w) {
            $s = Carbon::parse($w['start'], 'Asia/Manila');
            $e = Carbon::parse($w['end'], 'Asia/Manila');
            $open = $e->gte($today);
            $list[] = [
                'week'    => ['v' => $w['start'], 't' => $s->isoWeekYear . '-W' . str_pad((string) $s->isoWeek, 2, '0', STR_PAD_LEFT)],
                'period'  => ['v' => $w['start'], 't' => $s->format('M j') . ' – ' . $e->format('M j')],
                'workers' => count($w['ids']),
                'minutes' => $w['minutes'],
                'gross'   => round($w['gross'], 2),
                'ded'     => round($w['ded'], 2),
                'net'     => round($w['net'], 2),
                'status'  => $open ? ['v' => 0, 't' => 'Open', 'c' => 'open'] : ['v' => 1, 't' => 'Closed', 'c' => 'paid'],
                '_week'   => 'W' . $s->isoWeek,
                '_open'   => $open,
            ];
        }

        $shown = array_slice($list, -12);
        $chart = [
            'kind'  => 'cols',
            'title' => 'Net pay per payroll period',
            'sub'   => count($shown) < count($list) ? 'Last 12 of ' . count($list) . ' weeks' : count($list) . ' ' . (count($list) === 1 ? 'week' : 'weeks'),
            'max'   => max(1, ...array_map(fn ($r) => $r['net'], $shown ?: [['net' => 0]])),
            'cols'  => array_map(fn ($r) => ['label' => $r['_week'], 'v' => $r['net'], 'cur' => $r['_open'], 'tip' => $r['week']['t'] . ' · net ₱' . number_format($r['net'], 2)], $shown),
        ];

        $ids = [];
        foreach ($rows as $r) {
            if ($r['minutes'] > 0 || $r['gross'] > 0) {
                $ids[$r['id']] = true;
            }
        }

        return [$chart, [
            'title' => 'Payroll periods',
            'cols'  => [
                ['week', 'Week', 'code', true], ['period', 'Period', 'text', true], ['workers', 'Workers', 'int'],
                ['minutes', 'Hours', 'dur'], ['gross', 'Gross', 'money'], ['ded', 'Deductions', 'minus'],
                ['net', 'Net', 'net'], ['status', 'Status', 'status', true],
            ],
            'rows'  => array_reverse($list),
            'foot'  => ['Total', '', count($ids), $this->sumCol($list, 'minutes'), $this->sumCol($list, 'gross'), $this->sumCol($list, 'ded'), $this->sumCol($list, 'net'), ''],
        ]];
    }

    /** One row per worker over the period. */
    private function byEmployee(array $rows, callable $who): array
    {
        $emps = $this->perEmployee($rows);
        usort($emps, fn ($a, $b) => $b['net'] <=> $a['net']);

        $chart = $this->hbars('Net pay by employee', count($emps) . ' ' . (count($emps) === 1 ? 'employee' : 'employees'), array_map(fn ($e) => [
            'label' => $this->short($e['name']), 'total' => $e['net'], 'fmt' => $this->pesoK($e['net']),
            'parts' => [['v' => $e['net'], 'c' => 'var(--rp-s1)', 'n' => 'Net pay', 'fmt' => '₱' . number_format($e['net'], 2)]],
        ], $emps));

        $list = array_map(fn ($e) => [
            'employee' => $who($e['id'], $e['name'], $e['position']),
            'workdays' => $e['workdays'],
            'minutes'  => $e['minutes'],
            'gross'    => $e['gross'],
            'ded'      => $e['ded'],
            'net'      => $e['net'],
        ], $emps);

        return [$chart, [
            'title' => 'Payroll by employee',
            'cols'  => [
                ['employee', 'Employee', 'emp', true], ['workdays', 'Days', 'num'], ['minutes', 'Hours', 'dur'],
                ['gross', 'Gross', 'money'], ['ded', 'Deductions', 'minus'], ['net', 'Net', 'net'],
            ],
            'rows'  => $list,
            'foot'  => ['Total · ' . count($list), $this->sumCol($list, 'workdays'), $this->sumCol($list, 'minutes'), $this->sumCol($list, 'gross'), $this->sumCol($list, 'ded'), $this->sumCol($list, 'net')],
        ]];
    }

    /** Labor cost by the site each worker is assigned to. */
    private function bySite(array $rows, $staff): array
    {
        $sites = [];
        foreach ($this->perEmployee($rows) as $e) {
            $name = $staff[$e['id']]->site->name ?? 'Unassigned';
            $s = &$sites[$name];
            $s ??= ['site' => $name, 'ids' => [], 'minutes' => 0, 'regular' => 0, 'premiums' => 0, 'cost' => 0];
            if ($e['minutes'] > 0 || $e['gross'] > 0) {
                $s['ids'][$e['id']] = true;
            }
            $s['minutes']  += $e['minutes'];
            $s['regular']  += $e['regular'] + $e['leave'];
            $s['premiums'] += $e['premiums'] + $e['bonus'];
            $s['cost']     += $e['gross'] + $e['bonus'];
            unset($s);
        }
        $sites = array_values(array_filter($sites, fn ($s) => $s['cost'] > 0 || $s['minutes'] > 0));
        usort($sites, fn ($a, $b) => $b['cost'] <=> $a['cost']);
        $total = array_sum(array_column($sites, 'cost')) ?: 1;

        $chart = $this->hbars('Labor cost by site', 'Gross pay and bonus', array_map(fn ($s) => [
            'label' => $s['site'], 'total' => $s['cost'], 'fmt' => $this->pesoK($s['cost']),
            'parts' => [
                ['v' => $s['regular'], 'c' => 'var(--rp-s1)', 'n' => 'Regular pay', 'fmt' => '₱' . number_format($s['regular'], 2)],
                ['v' => $s['premiums'], 'c' => 'var(--rp-s2)', 'n' => 'OT, premiums & bonus', 'fmt' => '₱' . number_format($s['premiums'], 2)],
            ],
        ], $sites), [['Regular pay', 'var(--rp-s1)'], ['OT, premiums & bonus', 'var(--rp-s2)']]);

        $list = array_map(fn ($s) => [
            'site'     => ['v' => $s['site'], 't' => $s['site']],
            'workers'  => count($s['ids']),
            'minutes'  => $s['minutes'],
            'regular'  => round($s['regular'], 2),
            'premiums' => round($s['premiums'], 2),
            'cost'     => round($s['cost'], 2),
            'share'    => round($s['cost'] / $total * 100, 1),
        ], $sites);

        return [$chart, [
            'title' => 'Labor cost by site',
            'cols'  => [
                ['site', 'Site', 'strong', true], ['workers', 'Workers', 'int'], ['minutes', 'Hours', 'dur'],
                ['regular', 'Regular pay', 'money'], ['premiums', 'OT, premiums & bonus', 'money'],
                ['cost', 'Labor cost', 'net'], ['share', 'Share', 'pct'],
            ],
            'rows'  => $list,
            'foot'  => ['Total', count(array_unique(array_merge(...array_map(fn ($s) => array_keys($s['ids']), $sites ?: [['ids' => []]])))),
                        $this->sumCol($list, 'minutes'), $this->sumCol($list, 'regular'), $this->sumCol($list, 'premiums'), $this->sumCol($list, 'cost'), $list ? 100.0 : 0.0],
        ]];
    }

    /** Overtime as payroll paid it: counted from attendance, priced by the rates. */
    private function overtime(array $rows, callable $who, array $rates): array
    {
        $emps = array_values(array_filter($this->perEmployee($rows), fn ($e) => $e['otMin'] > 0));
        usort($emps, fn ($a, $b) => $b['otMin'] <=> $a['otMin']);
        $pct = (int) round(((float) ($rates['ot_multiplier'] ?? 1.25)) * 100);

        $chart = $this->hbars('Overtime hours by employee', 'Paid at ' . $pct . '%', array_map(fn ($e) => [
            'label' => $this->short($e['name']), 'total' => $e['otMin'], 'fmt' => WorkSchedule::duration($e['otMin']),
            'parts' => [['v' => $e['otMin'], 'c' => 'var(--rp-s1)', 'n' => 'Overtime', 'fmt' => WorkSchedule::duration($e['otMin']) . ' · ₱' . number_format($e['overtime'], 2)]],
        ], $emps));

        $list = array_map(fn ($e) => [
            'employee' => $who($e['id'], $e['name'], $e['position']),
            'regMin'   => max(0, $e['minutes'] - $e['otMin']),
            'otMin'    => $e['otMin'],
            'share'    => $e['minutes'] > 0 ? round($e['otMin'] / $e['minutes'] * 100, 1) : 0.0,
            'overtime' => $e['overtime'],
        ], $emps);

        return [$chart, [
            'title' => 'Overtime',
            'cols'  => [
                ['employee', 'Employee', 'emp', true], ['regMin', 'Regular hrs', 'dur'], ['otMin', 'OT hrs', 'dur'],
                ['share', 'OT share', 'pct'], ['overtime', 'OT pay', 'net'],
            ],
            'rows'  => $list,
            'foot'  => ['Total · ' . count($list), $this->sumCol($list, 'regMin'), $this->sumCol($list, 'otMin'), '', $this->sumCol($list, 'overtime')],
        ]];
    }

    /** Every deduction, by kind, per worker. Kinds nobody had are left out. */
    private function deductions(array $rows, callable $who): array
    {
        $emps = array_values(array_filter($this->perEmployee($rows), fn ($e) => $e['ded'] > 0));
        usort($emps, fn ($a, $b) => $b['ded'] <=> $a['ded']);

        $colors = ['sss' => 'var(--rp-s1)', 'ph' => 'var(--rp-s2)', 'pagibig' => 'var(--rp-s3)', 'tax' => 'var(--rp-s5)',
                   'advance' => 'var(--rp-s4)', 'vale' => 'var(--rp-s6)', 'other' => 'var(--rp-s7)'];
        $kinds = array_filter(self::DEDUCTIONS, fn ($n, $k) => in_array($k, ['sss', 'ph', 'pagibig'], true)
            || array_sum(array_column($emps, $k)) > 0, ARRAY_FILTER_USE_BOTH);

        $chart = $this->hbars('Deductions by employee', 'By kind', array_map(fn ($e) => [
            'label' => $this->short($e['name']), 'total' => $e['ded'], 'fmt' => $this->pesoK($e['ded']),
            'parts' => array_values(array_map(fn ($k) => ['v' => $e[$k], 'c' => $colors[$k], 'n' => self::DEDUCTIONS[$k], 'fmt' => '₱' . number_format($e[$k], 2)],
                array_filter(array_keys($kinds), fn ($k) => $e[$k] > 0))),
        ], $emps), array_map(fn ($k) => [self::DEDUCTIONS[$k], $colors[$k]], array_keys($kinds)));

        $cols = [['employee', 'Employee', 'emp', true]];
        foreach ($kinds as $k => $n) {
            $cols[] = [$k, $n, 'money0', false, $colors[$k]];
        }
        $cols[] = ['ded', 'Total', 'net'];

        $list = array_map(function ($e) use ($who, $kinds) {
            $row = ['employee' => $who($e['id'], $e['name'], $e['position'])];
            foreach ($kinds as $k => $n) {
                $row[$k] = $e[$k];
            }
            $row['ded'] = $e['ded'];
            return $row;
        }, $emps);

        $foot = ['Total · ' . count($list)];
        foreach ($kinds as $k => $n) {
            $foot[] = $this->sumCol($list, $k);
        }
        $foot[] = $this->sumCol($list, 'ded');

        return [$chart, ['title' => 'Deductions', 'cols' => $cols, 'rows' => $list, 'foot' => $foot]];
    }

    /**
     * The cash advances on file, as Leave & Advances keeps them: issued by the
     * end of the period, with what has been collected and what is left. A
     * deleted advance is not listed, nor an old loan.
     */
    private function advances(Carbon $to, ?int $siteId, string $q, $staff): array
    {
        $loans = Loan::listed()->with(['employee' => fn ($e) => $e->withTrashed()->with('site:id,name', 'laborType:id,name')])
            ->where('issued_on', '<', $to->copy()->addDay()->toDateString())
            ->orderByDesc('issued_on')->get()
            ->filter(function (Loan $l) use ($siteId, $q) {
                $e = $l->employee;
                if (! $e) {
                    return false;
                }
                if ($siteId !== null && (int) $e->site_id !== $siteId) {
                    return false;
                }
                if ($q !== '') {
                    $needle = mb_strtolower(ltrim($q, '#'));
                    return str_contains(mb_strtolower($e->name), $needle) || ltrim((string) $e->id, '0') === ltrim($needle, '0');
                }
                return true;
            })->values();

        $owing = $loans->filter(fn (Loan $l) => (float) $l->balance > 0)->sortByDesc('balance')->values();
        $chart = $this->hbars('Outstanding balances', 'Of each advance', $owing->map(fn (Loan $l) => [
            'label' => $this->short($l->employee->name), 'total' => (float) $l->principal,
            'fmt'   => '₱' . number_format((float) $l->balance, 2) . ' left',
            'parts' => [
                ['v' => (float) $l->paid_amount, 'c' => 'var(--rp-s1)', 'n' => 'Collected', 'fmt' => '₱' . number_format((float) $l->paid_amount, 2)],
                ['v' => (float) $l->balance, 'c' => 'var(--rp-s2)', 'n' => 'Balance', 'fmt' => '₱' . number_format((float) $l->balance, 2)],
            ],
        ])->all(), [['Collected so far', 'var(--rp-s1)'], ['Balance', 'var(--rp-s2)']]);

        $list = $loans->map(fn (Loan $l) => [
            'employee'    => $this->who($l->employee->id, $l->employee->name, $l->employee->position, $l->employee),
            'issued'      => ['v' => $l->issued_on?->toDateString(), 't' => $l->issued_on?->format('M j, Y') ?? '—'],
            'principal'   => round((float) $l->principal, 2),
            'installment' => round((float) $l->installment, 2),
            'paid'        => round((float) $l->paid_amount, 2),
            'balance'     => round((float) $l->balance, 2),
            'status'      => (float) $l->balance > 0
                ? ['v' => 0, 't' => $l->status_label, 'c' => 'open']
                : ['v' => 1, 't' => 'Settled', 'c' => 'paid'],
        ])->all();

        return [$chart, [
            'title' => 'Cash advances',
            'cols'  => [
                ['employee', 'Employee', 'emp', true], ['issued', 'Issued', 'text', true], ['principal', 'Amount', 'money'],
                ['installment', 'Per payroll', 'money'], ['paid', 'Collected', 'money'], ['balance', 'Balance', 'net0'],
                ['status', 'Status', 'status', true],
            ],
            'rows'  => $list,
            'foot'  => ['Total · ' . count($list), '', $this->sumCol($list, 'principal'), '', $this->sumCol($list, 'paid'), $this->sumCol($list, 'balance'), ''],
        ]];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * A cell as text, by its column's kind — the page and the Excel export
     * both write their cells with this, so the two read the same.
     */
    public static function fmt(string $type, mixed $val): string
    {
        if (is_array($val)) {
            return (string) ($val['t'] ?? '');
        }
        if ($val === '' || $val === null) {
            return '';
        }
        if (is_string($val) && ! is_numeric($val)) {
            return $val;
        }
        $n = (float) $val;

        return match ($type) {
            'dur'            => WorkSchedule::duration((int) round($n)),
            'int'            => number_format($n),
            'num'            => rtrim(rtrim(number_format($n, 2), '0'), '.'),
            'pct'            => number_format($n, 1) . '%',
            'minus'          => $n > 0 ? '−₱' . number_format($n, 2) : '₱0.00',
            'money0', 'net0' => $n > 0 ? '₱' . number_format($n, 2) : '—',
            'money', 'net'   => '₱' . number_format($n, 2),
            default          => (string) $val,
        };
    }

    /** A worker's weeks added up. */
    private function perEmployee(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $e = &$out[$r['id']];
            if ($e === null) {
                $e = ['id' => $r['id'], 'name' => $r['name'], 'position' => $r['position']] + array_fill_keys(
                    ['workdays', 'minutes', 'otMin', 'regular', 'leave', 'overtime', 'premiums', 'bonus', 'gross',
                     'sss', 'ph', 'pagibig', 'tax', 'advance', 'vale', 'other', 'ded', 'net'], 0);
            }
            foreach ($e as $k => $v) {
                if (! in_array($k, ['id', 'name', 'position'], true)) {
                    $e[$k] += $r[$k];
                }
            }
            unset($e);
        }
        foreach ($out as &$e) {
            foreach (['regular', 'leave', 'overtime', 'premiums', 'bonus', 'gross', 'sss', 'ph', 'pagibig', 'tax', 'advance', 'vale', 'other', 'ded', 'net'] as $k) {
                $e[$k] = round($e[$k], 2);
            }
        }
        unset($e);

        return array_values(array_filter($out, fn ($e) => $e['minutes'] > 0 || $e['gross'] > 0 || $e['ded'] > 0 || $e['net'] > 0));
    }

    /** How a worker reads in a table: name, code, trade and site. */
    private function who(int $id, string $name, ?string $position, ?Employee $e): array
    {
        $palette = ['#2563eb', '#7c3aed', '#0d9488', '#be123c', '#4f46e5', '#0369a1', '#b45309', '#15803d'];
        $trade   = $e?->laborType?->name ?: ($position ?: ($e->position ?? ''));

        return [
            'v'    => $name,
            't'    => $name,
            'id'   => $id,
            'code' => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'sub'  => implode(' · ', array_filter(['#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $trade, $e?->site?->name])),
            'init' => mb_strtoupper(mb_substr($name, 0, 1)),
            'c'    => $palette[$id % count($palette)],
        ];
    }

    private function hbars(string $title, string $sub, array $items, array $legend = []): array
    {
        return ['kind' => 'bars', 'title' => $title, 'sub' => $sub, 'legend' => $legend,
                'max' => max(1, ...array_map(fn ($x) => $x['total'], $items ?: [['total' => 0]])), 'items' => $items];
    }

    private function sumCol(array $list, string $k): float
    {
        return round(array_sum(array_map(fn ($r) => is_array($r[$k] ?? null) ? 0 : (float) ($r[$k] ?? 0), $list)), 2);
    }

    /** The first two names: "Aldrin Santos Sapugay" is "Aldrin Santos". */
    private function short(string $name): string
    {
        return implode(' ', array_slice(preg_split('/\s+/', trim($name)), 0, 2));
    }

    private function pesoK(float $n): string
    {
        return $n >= 1e6 ? '₱' . number_format($n / 1e6, 2) . 'M'
            : ($n >= 1e4 ? '₱' . number_format($n / 1e3, 1) . 'k' : '₱' . number_format($n, 2));
    }
}
