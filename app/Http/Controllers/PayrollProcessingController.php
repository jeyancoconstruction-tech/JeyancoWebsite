<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\PayrollRemittance;
use App\Models\PayrollRun;
use App\Models\SystemSetting;
use App\Services\PayrollRunService;
use App\Services\PayrollService;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Payroll Processing: a week and a worker at a time — the salary computation
 * in stages, where each contribution and the net pay have got to, and the
 * payslip.
 *
 * It is Payroll Records looked at one worker at a time, not a payroll of its
 * own. The figures are PayrollService::computeForRange() for the same
 * Monday-to-Sunday week Payroll Records shows, read the way its receipt reads
 * them. Everything Payroll Settings says — rates, multipliers, contributions,
 * withholding, shifts and their grace, holidays, the rest day, the bonus, the
 * vale ceiling — reaches both pages from that one place, so the two cannot
 * disagree.
 *
 * The run routes below stay for the run pages that still open; nothing on
 * this page reads a run.
 */
class PayrollProcessingController extends Controller
{
    /** The three ways a worker's week can be looked at. */
    private const VIEWS = ['workflow', 'tracker', 'payslip'];

    /** Avatar colours, picked by employee id so a worker keeps theirs. */
    private const COLOURS = ['#3B82F6', '#8B5CF6', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#EC4899'];

    /** How many weeks back the picker reaches. */
    private const WEEKS = 12;

    public function __construct(private PayrollRunService $runs, private PayrollService $payroll)
    {
    }

    public function index(Request $request)
    {
        $periods = $this->periods();
        $period  = $this->pick($request->query('period'), $periods) ?? reset($periods);
        $periods[$period['key']] ??= $period;

        $rows     = $this->figures($period);
        $tracking = PayrollRemittance::available();

        // Open on the worker asked for, or else on somebody with pay to look at.
        $sel  = $rows->firstWhere('employee_id', (int) $request->query('employee'))
             ?? $rows->firstWhere('worked', true)
             ?? $rows->first();
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'workflow';

        $marks = $tracking && $sel
            ? PayrollRemittance::with(['submitter', 'completer'])
                ->where('employee_id', $sel['employee_id'])
                ->where('period_start', $period['from'])
                ->where('period_end', $period['to'])
                ->get()
                ->keyBy('kind')
            : collect();

        $track = $sel ? $this->trackRows($sel, $marks, $tracking) : [];

        // The numbers the week was priced at, for the payslip to show its
        // workings — resolved at the week's end, as Payroll Records does.
        $rateSet = PayrollRate::effectiveOn($period['to']);
        $rates   = $rateSet ? $rateSet->toRates() : PayrollRate::fallbackRates();

        return view('payroll-processing.index', [
            'periods' => $periods,
            'period'  => $period,
            'rows'    => $rows,
            'sel'     => $sel,
            'view'    => $view,
            'lines'   => $sel ? $this->lines($sel) : ['earn' => [], 'ded' => []],
            'track'   => $track,
            'open'    => count(array_filter($track, fn ($t) => $t['open'])),
            'notice'  => $sel ? $this->trackNotice($sel, $tracking) : null,
            'slip'    => $sel ? $this->slip($sel, $rates) : null,
            'company' => SystemSetting::current(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'site_id'      => 'nullable|exists:sites,id',
            'title'        => 'nullable|string|max:120',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $run = PayrollRun::create($data + [
            'code'       => PayrollRun::nextCode(),
            'status'     => 'draft',
            'created_by' => auth()->id(),
        ]);

        AuditLog::record('Payroll', 'created',
            'Created payroll run ' . $run->code . ' for ' . $run->period_label, $run);

        // Straight into the figures: a draft with nothing in it has nothing
        // to review, and the user's next click would be Calculate anyway.
        $this->runs->calculate($run);

        // Back to the period.
        return redirect()->route('payroll-processing.index', [
            'period' => $run->period_start->toDateString() . '_' . $run->period_end->toDateString(),
        ])->with('success', 'Payroll run ' . $run->code . ' created and calculated.');
    }

    public function show(PayrollRun $run)
    {
        $run->load(['items.employee', 'items.site', 'site', 'creator', 'approver', 'finalizer']);

        return view('payroll-processing.show', ['run' => $run]);
    }

    public function calculate(PayrollRun $run)
    {
        if (! $run->isEditable()) {
            return back()->with('error',
                'Run ' . $run->code . ' is ' . strtolower($run->status_label)
                . '. Reopen it before recalculating.');
        }

        $this->runs->calculate($run);

        AuditLog::record('Payroll', 'recalculated', 'Recalculated ' . $run->code, $run);

        return back()->with('success', 'Figures recalculated from attendance.');
    }

    public function approve(PayrollRun $run)
    {
        if ($run->status !== 'calculated') {
            return back()->with('error', 'Only a calculated run can be approved.');
        }

        $run->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLog::record('Payroll', 'approved', 'Approved payroll run ' . $run->code, $run);

        return back()->with('success', 'Run ' . $run->code . ' approved. Finalise it to issue payslips.');
    }

    /**
     * The irreversible step. Cash advance instalments are collected HERE and
     * only here, so a run recalculated five times never collects five times.
     */
    public function finalize(Request $request, PayrollRun $run)
    {
        $request->validate(['confirm' => 'required|accepted']);

        if ($run->status !== 'approved') {
            return back()->with('error', 'Only an approved run can be finalised.');
        }

        $collected = $this->runs->collectLoans($run);

        $run->update([
            'status'       => 'finalized',
            'finalized_by' => auth()->id(),
            'finalized_at' => now(),
        ]);

        AuditLog::record('Payroll', 'finalized',
            'Finalised ' . $run->code . ' — ₱' . number_format($run->total_net, 2)
            . ' net across ' . $run->employee_count . ' worker(s), '
            . $collected . ' instalment collection(s) posted', $run);

        return back()->with('success', 'Run finalised. Payslips are now available.');
    }

    /** Approved but not yet finalised, and something was wrong. */
    public function reopen(PayrollRun $run)
    {
        if ($run->isFinal()) {
            return back()->with('error', 'A finalised run cannot be reopened.');
        }

        $run->update(['status' => 'calculated', 'approved_by' => null, 'approved_at' => null]);

        AuditLog::record('Payroll', 'reopened', 'Reopened ' . $run->code, $run);

        return back()->with('success', 'Run reopened for editing.');
    }

    public function destroy(PayrollRun $run)
    {
        if ($run->isFinal()) {
            return back()->with('error', 'A finalised run cannot be deleted.');
        }

        $code = $run->code;
        $run->delete();

        AuditLog::record('Payroll', 'deleted', 'Deleted payroll run ' . $code);

        return redirect()->route('payroll-processing.index')->with('success', 'Run ' . $code . ' deleted.');
    }

    /**
     * Move one line of a worker's pay for a week along: a contribution
     * submitted to its agency and then confirmed remitted, or the net pay
     * handed over. The amount is Payroll Records' figure, worked out here,
     * never taken from the form.
     */
    public function track(Request $request, Employee $employee, string $kind)
    {
        abort_unless(isset(PayrollRemittance::KINDS[$kind]), 404);

        $data   = $request->validate(['period' => 'required|string', 'action' => 'required|in:submit,done,undo']);
        $period = $this->pick($data['period'], $this->periods());

        abort_unless($period !== null, 404);

        if (! PayrollRemittance::available()) {
            return back()->with('error', 'Remittance tracking needs a database update first (php artisan migrate).');
        }

        $label  = PayrollRemittance::KINDS[$kind];
        $row    = $this->figures($period)->firstWhere('employee_id', $employee->id);
        $amount = $row ? PayrollRemittance::amountOf($row, $kind) : 0.0;

        if ($amount <= 0) {
            return back()->with('error', "Nothing is due for {$label} in {$period['span']}.");
        }

        $word = fn (string $s) => $s === PayrollRemittance::DONE ? ($kind === 'net_pay' ? 'paid' : 'remitted') : $s;

        $line = PayrollRemittance::firstOrNew([
            'employee_id'  => $employee->id,
            'period_start' => $period['from'],
            'period_end'   => $period['to'],
            'kind'         => $kind,
        ]);

        $from = $line->status ?? PayrollRemittance::PENDING;
        $to   = PayrollRemittance::after($kind, $from, $data['action']);

        if ($to === null) {
            return back()->with('error', "{$label} for {$employee->name} is {$word($from)}; that step does not follow.");
        }

        $line->fill(['status' => $to, 'amount' => $amount]);

        // Each step signs itself; undoing one takes its signature back off.
        match (true) {
            $data['action'] === 'submit'      => $line->fill(['submitted_by' => auth()->id(), 'submitted_at' => now()]),
            $data['action'] === 'done'        => $line->fill(['completed_by' => auth()->id(), 'completed_at' => now()]),
            $from === PayrollRemittance::DONE => $line->fill(['completed_by' => null, 'completed_at' => null]),
            default                           => $line->fill(['submitted_by' => null, 'submitted_at' => null]),
        };

        $line->save();

        AuditLog::record('Payroll', 'remittance',
            "{$label} for {$employee->name} ({$period['span']}): {$word($from)} → {$word($to)}");

        return back()->with('success', $data['action'] === 'undo'
            ? "Undone — {$label} for {$employee->name} is {$word($to)} again."
            : "{$label} for {$employee->name} marked {$word($to)}.");
    }

    // ── The page's pieces ───────────────────────────────────────────────────

    /**
     * The week's figures, one row per worker: Payroll Records' own, from the
     * same computation for the same range — plus everyone else on the active
     * roster at zero.
     *
     * @return Collection<int, array>
     */
    private function figures(array $period): Collection
    {
        $data = $this->payroll->computeForRange($period['from'], $period['to']);

        // Off the days, per worker: the rate they were priced at — read off
        // the first of them, as the Payroll Records receipt reads it — and the
        // overtime in whole minutes, which the totals do not carry.
        $priced = [];
        $otMins = [];

        foreach ($data['days'] as $day) {
            foreach ($day['details'] as $d) {
                $id = (int) $d['employee_id'];

                $priced[$id] ??= ['daily' => (float) $d['dailyRate'], 'hourly' => (float) $d['rate']];
                $otMins[$id]  = ($otMins[$id] ?? 0) + (int) ($d['ot_minutes'] ?? round((float) $d['ot_hours'] * 60));
            }
        }

        $paid = collect($data['employees']);
        $ids  = $paid->pluck('employee_id')->all();

        $people = Employee::with(['laborType', 'site', 'shift'])->whereIn('id', $ids)->get()->keyBy('id');

        // Everyone on the active roster is listed, paid or not. A worker just
        // registered, or off all week, is somebody the office comes here to
        // look for, and "nothing this week" is an answer. Pending
        // registrations stay out, as everywhere else: they are not on the
        // payroll until the finger is enrolled.
        $idle = Employee::with(['laborType', 'site', 'shift'])->active()->whereNotIn('id', $ids)->get();

        return $paid
            ->map(fn (array $e) => $this->row(
                $e,
                $priced[$e['employee_id']] ?? null,
                $otMins[$e['employee_id']] ?? 0,
                $people->get($e['employee_id'])
            ))
            ->merge($idle->map(fn (Employee $e) => $this->idle($e)))
            ->sortBy(fn ($r) => mb_strtolower($r['name']))
            ->values();
    }

    /**
     * One worker's week, read the way the Payroll Records receipt reads it:
     * the period's totals, with the deductions itemised off its weeks.
     */
    private function row(array $e, ?array $priced, int $otMins, ?Employee $employee): array
    {
        $t     = $e['totals'];
        $weeks = collect($e['periods'] ?? []);
        $sum   = fn (string $k) => round((float) $weeks->sum($k), 2);

        $minutes = (int) ($t['minutes'] ?? round((float) $t['hours'] * 60));
        $otMins  = min($otMins, $minutes);
        $gross   = (float) $t['gross'];
        $otPay   = (float) $t['overtime'];
        $night   = (float) ($t['nightDiffPay'] ?? 0);
        $holiday = (float) ($t['holidayPay'] ?? 0);
        $rest    = (float) ($t['restDayPay'] ?? 0);
        $rate    = $priced ?? $this->rateOf($employee);

        return array_merge($this->blank((int) $e['employee_id'], (string) $e['name'], $employee), [
            'worked'           => $minutes > 0 || $gross > 0 || (int) $t['workdays'] > 0,
            'daily_rate'       => $rate['daily'],
            'hourly_rate'      => $rate['hourly'],
            'days'             => (float) $t['workdays'],
            'minutes'          => $minutes,
            'ot_minutes'       => $otMins,
            'regular_minutes'  => $minutes - $otMins,
            'late_minutes'     => (int) $weeks->sum('late_minutes'),

            // Regular pay is the gross less every premium in it, as the
            // receipt works it out.
            'basic'            => round($gross - $otPay - $holiday - $rest - $night, 2),
            'overtime'         => $otPay,
            // What an hour of overtime actually paid, so the line can say so
            // without re-deriving a multiplier that may have changed mid-week.
            'ot_rate'          => $otMins > 0 ? round($otPay / ($otMins / 60), 2) : 0.0,
            'night'            => $night,
            'holiday'          => $holiday,
            'rest'             => $rest,

            // Added to net, not to gross: a bonus is not wages.
            'bonus'            => (float) $t['bonus'],

            'sss'              => $sum('sssDeduction'),
            'philhealth'       => $sum('philhealthDeduction'),
            'pagibig'          => $sum('pagibigDeduction'),
            'tax'              => $sum('withholdingTax'),
            // The vale includes any cash advance instalment; the instalment is
            // kept as well, so the line can say how much of it was one.
            'vale'             => $sum('vale'),
            'advance'          => $sum('vale_advance'),
            'other_deductions' => $sum('manualDeductions'),

            'gross'            => $gross,
            'deductions'       => (float) $t['totalDeductions'],
            'net'              => (float) $t['net'],
        ]);
    }

    /** A worker on the roster with nothing this week: every figure zero but the rate. */
    private function idle(Employee $e): array
    {
        $rate = $this->rateOf($e);

        return array_merge($this->blank($e->id, $e->name, $e), [
            'daily_rate'  => $rate['daily'],
            'hourly_rate' => $rate['hourly'],
        ]);
    }

    /**
     * A worker's rate with no priced day to read it off: the labour type's
     * daily rate over the paid hours of their shift, as payroll divides it.
     *
     * @return array{daily: float, hourly: float}
     */
    private function rateOf(?Employee $e): array
    {
        $s     = $e?->shift?->schedule();
        $daily = (float) ($e?->laborType?->daily_rate ?? ((float) ($e?->rate_per_hour ?? 0) * 8));
        $hours = $s && WorkSchedule::has($s) ? max(1.0, WorkSchedule::paidHours($s)) : 8.0;

        return ['daily' => round($daily, 2), 'hourly' => round($daily / $hours, 2)];
    }

    /** The parts of a row that are about the worker rather than the pay — with the pay at zero. */
    private function blank(int $id, string $name, ?Employee $employee): array
    {
        return [
            'employee_id' => $id,
            'worked'      => false,
            'name'        => $name,
            'initial'     => mb_strtoupper(mb_substr($name, 0, 1)),
            'color'       => self::COLOURS[$id % count(self::COLOURS)],
            'code'        => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'labor'       => $employee?->laborType?->name ?: ($employee?->position ?: 'No labor type'),
            'site'        => $employee?->site?->name,
            // The worker's own number with each agency, off the employee form,
            // for whoever files the remittance.
            'ids'         => [
                'sss'        => $employee?->sss_number,
                'philhealth' => $employee?->philhealth_number,
                'pagibig'    => $employee?->pagibig_number,
                'bir'        => $employee?->tin_number,
            ],
        ]
        + array_fill_keys(['minutes', 'ot_minutes', 'regular_minutes', 'late_minutes'], 0)
        + array_fill_keys([
            'daily_rate', 'hourly_rate', 'days', 'basic', 'overtime', 'ot_rate', 'night', 'holiday', 'rest',
            'bonus', 'sss', 'philhealth', 'pagibig', 'tax', 'vale', 'advance', 'other_deductions',
            'gross', 'deductions', 'net',
        ], 0.0);
    }

    /**
     * The weeks the picker offers: this one and the ones before it, Monday to
     * Sunday — the weeks Payroll Records offers, so the same week reads the
     * same on both pages.
     *
     * @return array<string, array{key: string, label: string, span: string, from: string, to: string, group: string}>
     */
    private function periods(): array
    {
        $out    = [];
        $monday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        for ($i = 0; $i < self::WEEKS; $i++) {
            $from = $monday->copy()->subWeeks($i);
            $row  = self::week($from, 'Weekly');

            $out[$row['key']] = $row;
        }

        return $out;
    }

    /**
     * The week a "2026-09-07_2026-09-13" key names: one the picker offers, or
     * an earlier Monday-to-Sunday week — a remittance can fall due after its
     * week has dropped off the list. Any other range is not a pay week.
     */
    private function pick(?string $key, array $periods): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (isset($periods[$key])) {
            return $periods[$key];
        }

        if (! preg_match('/^(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/', $key, $m)) {
            return null;
        }

        try {
            $from = Carbon::parse($m[1])->startOfDay();
            $to   = Carbon::parse($m[2])->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $real = $from->toDateString() === $m[1] && $to->toDateString() === $m[2];

        return $real && $from->isMonday() && $to->toDateString() === $from->copy()->addDays(6)->toDateString()
            ? self::week($from, 'Earlier')
            : null;
    }

    /** A Monday-to-Sunday week, starting on $monday. */
    private static function week(Carbon $monday, string $group): array
    {
        $from = $monday->copy()->startOfDay();
        $to   = $from->copy()->addDays(6);

        return [
            'key'   => $from->toDateString() . '_' . $to->toDateString(),
            'label' => 'Week ' . $from->isoWeek() . ' · ' . self::span($from, $to),
            'span'  => self::span($from, $to),
            'from'  => $from->toDateString(),
            'to'    => $to->toDateString(),
            'group' => $group,
        ];
    }

    /** "Sep 07–13, 2026", "Aug 31–Sep 06, 2026", "Dec 28, 2025–Jan 03, 2026". */
    private static function span(Carbon $a, Carbon $b): string
    {
        return match (true) {
            $a->year !== $b->year   => $a->format('M d, Y') . '–' . $b->format('M d, Y'),
            $a->month !== $b->month => $a->format('M d') . '–' . $b->format('M d, Y'),
            default                 => $a->format('M d') . '–' . $b->format('d, Y'),
        };
    }

    /**
     * The salary computation's lines, each saying what produced it — a column
     * of totals cannot answer "why is this 746", and that is what gets asked.
     *
     * @return array{earn: list<array>, ded: list<array>}
     */
    private function lines(array $s): array
    {
        $line = fn (string $label, string $icon, ?string $note, float $amount) => compact('label', 'icon', 'note', 'amount');
        $peso = fn (float $n) => '₱' . number_format($n, 2);

        return [
            'earn' => [
                $line('Basic pay', 'ti-clock',
                    WorkSchedule::duration($s['regular_minutes']) . ' × ' . $peso($s['hourly_rate']) . '/hr', $s['basic']),
                $line('Overtime', 'ti-clock-plus', $s['ot_minutes'] > 0
                    ? WorkSchedule::duration($s['ot_minutes']) . ' at ' . $peso($s['ot_rate']) . '/hr' : null, $s['overtime']),
                $line('Night differential', 'ti-moon', '10 PM – 6 AM premium', $s['night']),
                $line('Holiday pay', 'ti-calendar-star', 'Holiday premium', $s['holiday']),
                $line('Rest day pay', 'ti-armchair', 'Rest day premium', $s['rest']),
            ],
            'ded' => [
                $line('SSS', 'ti-building-bank', 'Employee share', $s['sss']),
                $line('PhilHealth', 'ti-heart-plus', 'Employee share', $s['philhealth']),
                $line('Pag-IBIG', 'ti-home', 'Employee share', $s['pagibig']),
                $line('Withholding tax', 'ti-receipt-tax', 'Per BIR table', $s['tax']),
                $line('Vale / cash advance', 'ti-cash',
                    $s['advance'] > 0 ? $peso($s['advance']) . ' instalment' : null, $s['vale']),
                $line('Other deductions', 'ti-minus', $s['other_deductions'] > 0 ? 'Adjustments' : null, $s['other_deductions']),
            ],
        ];
    }

    /**
     * The payslip, laid out as the Payroll Records receipt lays its own: the
     * rate the days were priced at, each earning and deduction named with
     * what it was worked out at, then gross − deductions + bonus.
     *
     * @return array{meta: string, basis: string, earn: list<array{0: string, 1: float}>, ded: list<array{0: string, 1: float}>, gross: float, deductions: float, bonus: float, net: float}
     */
    private function slip(array $s, array $rates): array
    {
        $x    = fn ($m) => '×' . number_format((float) $m, 2);
        $pct  = fn ($r) => number_format((float) $r, 2) . '%';
        $peso = fn (float $n) => '₱' . number_format($n, 2);
        $days = rtrim(rtrim(number_format($s['days'], 2), '0'), '.');

        return [
            'meta'       => $s['code'] . ' · ' . $s['labor'] . ' · ' . $days . 'd / ' . WorkSchedule::duration($s['minutes']),
            'basis'      => $peso($s['daily_rate']) . '/day · ' . $peso($s['hourly_rate']) . '/hr · '
                          . $days . ' day' . ($days === '1' ? '' : 's') . ' worked'
                          . ($s['late_minutes'] > 0 ? ' · ' . $s['late_minutes'] . 'm late' : ''),
            'earn'       => [
                ['Regular pay (' . $days . 'd)', $s['basic']],
                ['Overtime (' . $x($rates['ot_multiplier'] ?? 0) . ')', $s['overtime']],
                ['Night differential (' . $x($rates['night_diff_multiplier'] ?? 0) . ')', $s['night']],
                ['Holiday pay (' . $x($rates['regular_holiday_multiplier'] ?? 0) . ')', $s['holiday']],
                ['Rest day pay (' . $x($rates['rest_day_multiplier'] ?? 0) . ')', $s['rest']],
            ],
            'ded'        => [
                ['SSS (' . $pct($rates['sss_rate'] ?? 0) . ')', $s['sss']],
                ['PhilHealth (' . $pct($rates['philhealth_rate'] ?? 0) . ')', $s['philhealth']],
                ['Pag-IBIG (' . $pct($rates['pagibig_rate'] ?? 0) . ')', $s['pagibig']],
                [($rates['withholding_tax'] ?? true) ? 'Withholding tax (BIR)' : 'Withholding tax (off)', $s['tax']],
                [$s['advance'] > 0 ? 'Vale / cash advance (' . $peso($s['advance']) . ' instalment)' : 'Vale / cash advance', $s['vale']],
                ['Other adjustments', $s['other_deductions']],
            ],
            'gross'      => $s['gross'],
            'deductions' => $s['deductions'],
            'bonus'      => $s['bonus'],
            'net'        => $s['net'],
        ];
    }

    /**
     * The tracker's rows for one worker: each contribution and the net pay,
     * with where it has got to, and the cash advance instalment, which comes
     * off the pay and has nowhere further to go.
     */
    private function trackRows(array $s, Collection $marks, bool $tracking): array
    {
        $meta = [
            'sss'        => ['ti-building-bank', 'Employee share'],
            'philhealth' => ['ti-heart-plus', 'Employee share'],
            'pagibig'    => ['ti-home', 'Employee share'],
            'bir'        => ['ti-receipt-tax', 'Withholding on compensation · BIR 1601-C'],
            'net_pay'    => ['ti-wallet', 'Released to the worker'],
        ];

        // What each agency calls the worker's number with it, and what to say
        // when the employee form has none — a remittance cannot be filed
        // without it, so it is better noticed here than at the counter.
        $ids = [
            'sss'        => ['SSS No.', 'No SSS number on file'],
            'philhealth' => ['PhilHealth No.', 'No PhilHealth number on file'],
            'pagibig'    => ['Pag-IBIG MID No.', 'No Pag-IBIG MID number on file'],
            'bir'        => ['TIN', 'No TIN on file'],
        ];

        $undo = ['action' => 'undo', 'label' => 'Undo', 'icon' => 'ti-arrow-back-up', 'primary' => false];
        $rows = [];

        foreach (PayrollRemittance::KINDS as $kind => $label) {
            if ($kind === 'net_pay') {
                $rows[] = $this->advanceRow($s);
            }

            [$icon, $sub] = $meta[$kind];
            $amount = PayrollRemittance::amountOf($s, $kind);
            $pay    = $kind === 'net_pay';
            $rec    = $marks->get($kind);
            $number = isset($ids[$kind]) ? (trim((string) ($s['ids'][$kind] ?? '')) ?: null) : null;

            $row = compact('kind', 'label', 'icon', 'sub', 'amount') + [
                'id_label'   => $ids[$kind][0] ?? null,
                'id'         => $number,
                'id_missing' => isset($ids[$kind]) && $number === null ? $ids[$kind][1] : null,
                'by' => null, 'actions' => [], 'done_text' => null, 'open' => false,
                // What the line came to when it was last moved, where the
                // attendance has moved it since.
                'was' => $rec && abs($rec->amount - $amount) >= 0.01 ? $rec->amount : null,
            ];

            if ($amount <= 0 && ! $rec) {
                $rows[] = ['state' => 'Nothing due', 'tone' => 'pp-b-muted', 'badge' => 'ti-minus'] + $row;
                continue;
            }

            if (! $tracking) {
                $rows[] = ['state' => 'Pending', 'tone' => 'pp-b-muted', 'badge' => 'ti-clock'] + $row;
                continue;
            }

            $rows[] = match ($rec?->status ?? PayrollRemittance::PENDING) {
                PayrollRemittance::SUBMITTED => [
                    'state'   => 'Submitted', 'tone' => 'pp-b-blue', 'badge' => 'ti-send', 'open' => true,
                    'by'      => self::signed($rec->submitter, $rec->submitted_at),
                    'actions' => [['action' => 'done', 'label' => 'Mark done', 'icon' => null, 'primary' => true], $undo],
                ] + $row,
                PayrollRemittance::DONE => [
                    'state'     => $pay ? 'Paid' : 'Remitted', 'tone' => 'pp-b-green', 'badge' => 'ti-circle-check',
                    'by'        => self::signed($rec->completer, $rec->completed_at),
                    'done_text' => $pay ? 'Paid' : 'Remitted',
                    'actions'   => [$undo],
                ] + $row,
                default => [
                    'state'   => 'Pending', 'tone' => 'pp-b-amber', 'badge' => 'ti-clock', 'open' => true,
                    'actions' => [$pay
                        ? ['action' => 'done', 'label' => 'Mark paid', 'icon' => null, 'primary' => true]
                        : ['action' => 'submit', 'label' => 'Submit', 'icon' => null, 'primary' => true]],
                ] + $row,
            };
        }

        return $rows;
    }

    /** The cash advance instalment: taken off the pay inside the vale, with nothing to remit. */
    private function advanceRow(array $s): array
    {
        $amount = $s['advance'];
        $row    = [
            'kind' => 'advance', 'label' => 'Cash advance', 'icon' => 'ti-cash', 'amount' => $amount,
            'sub'  => 'Instalment taken from this pay',
            'id_label' => null, 'id' => null, 'id_missing' => null,
            'by'   => null, 'actions' => [], 'done_text' => null, 'open' => false, 'was' => null,
        ];

        return $amount <= 0
            ? ['state' => 'Nothing due', 'tone' => 'pp-b-muted', 'badge' => 'ti-minus'] + $row
            : ['state' => 'Deducted', 'tone' => 'pp-b-blue', 'badge' => 'ti-receipt'] + $row;
    }

    /** Why the tracker has nothing to act on, when that is not obvious. */
    private function trackNotice(array $s, bool $tracking): ?string
    {
        return match (true) {
            ! $tracking => 'Remittance tracking is switched on by a database update that has not been run on this server yet. The amounts below are right; they can be marked once it has.',
            ! $s['worked'] => 'No attendance for ' . $s['name'] . ' in this period, so there is nothing to remit or pay yet.',
            $s['sss'] + $s['philhealth'] + $s['pagibig'] + $s['tax'] <= 0
                => 'No contributions or tax were taken off this pay, so there is nothing to remit for this period — only the net pay to release. If there should have been, check the contribution rates in Payroll Settings.',
            default => null,
        };
    }

    /** "by Admin · Sep 12, 2:14 PM", with whichever half is known. */
    private static function signed($user, $at): string
    {
        $who  = $user?->name ? 'by ' . $user->name : '';
        $when = $at ? $at->format('M j, g:i A') : '';

        return trim($who . ($who && $when ? ' · ' : '') . $when) ?: 'Done';
    }
}
