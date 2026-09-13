<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\PayrollRemittance;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SystemSetting;
use App\Services\PayrollRunService;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Payroll Processing: a period and a worker at a time — the salary
 * computation in stages, where each contribution and the net pay have got
 * to, and the payslip.
 *
 * The figures are PayrollService's, through PayrollRunService and unchanged:
 * a finalised run's frozen ones where there is one for the period, and
 * otherwise what the attendance comes to now. The run routes below stay for
 * the run pages that still open; nothing here finalises on its own.
 */
class PayrollProcessingController extends Controller
{
    /** The three ways a worker's period can be looked at. */
    private const VIEWS = ['workflow', 'tracker', 'payslip'];

    /** Avatar colours, picked by employee id so a worker keeps theirs. */
    private const COLOURS = ['#3B82F6', '#8B5CF6', '#22C55E', '#F59E0B', '#EF4444', '#06B6D4', '#EC4899'];

    public function __construct(private PayrollRunService $runs)
    {
    }

    public function index(Request $request)
    {
        $periods = $this->periods();
        $period  = $this->pick($request->query('period'), $periods) ?? reset($periods);
        $periods[$period['key']] ??= $period;

        [$run, $rows] = $this->figures($period);

        $tracking = PayrollRemittance::available();
        $sel      = $rows->firstWhere('employee_id', (int) $request->query('employee')) ?? $rows->first();
        $view     = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'workflow';

        $marks = $tracking && $sel
            ? PayrollRemittance::with(['submitter', 'completer'])
                ->where('employee_id', $sel['employee_id'])
                ->where('period_start', $period['from'])
                ->where('period_end', $period['to'])
                ->get()
                ->keyBy('kind')
            : collect();

        $track = $sel ? $this->trackRows($sel, $marks, $tracking) : [];

        // The numbers the period was priced at, for the payslip to show its
        // workings — resolved at the period's end, as Payroll Records does.
        $rateSet = PayrollRate::effectiveOn($period['to']);
        $rates   = $rateSet ? $rateSet->toRates() : PayrollRate::fallbackRates();

        return view('payroll-processing.index', [
            'periods'  => $periods,
            'period'   => $period,
            'run'      => $run,
            'rows'     => $rows,
            'sel'      => $sel,
            'view'     => $view,
            'lines'    => $sel ? $this->lines($sel) : ['earn' => [], 'ded' => []],
            'track'    => $track,
            'open'     => count(array_filter($track, fn ($t) => $t['open'])),
            'notice'   => $sel ? $this->trackNotice($sel, $tracking) : null,
            'slip'     => $sel ? $this->slip($sel, $rates) : null,
            'company'  => SystemSetting::current(),
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
     * Move one line of a worker's pay for a period along: a contribution
     * submitted to its agency and then confirmed remitted, or the net pay
     * handed over. The amount is worked out here, never taken from the form.
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
        $row    = $this->figures($period)[1]->firstWhere('employee_id', $employee->id);
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
     * The period's figures: a finalised run's, frozen, where one was cut for
     * it; otherwise what the attendance comes to now, from the computation a
     * run would freeze. A run short of final is not shown — nothing on this
     * page moves it on any more, so its figures could only go stale.
     *
     * @return array{0: ?PayrollRun, 1: Collection<int, array>}
     */
    private function figures(array $period): array
    {
        $run = PayrollRun::with('finalizer')
            ->whereDate('period_start', $period['from'])
            ->whereDate('period_end', $period['to'])
            ->where('status', 'finalized')
            ->orderBy('site_id')      // the all-sites run first, where there is one
            ->orderByDesc('id')
            ->first();

        $source = $run
            ? $run->items()->get()->map(fn (PayrollRunItem $i) => $i->toArray())
            : collect($this->runs->preview($period['from'], $period['to']));

        $people = Employee::with(['laborType', 'site'])
            ->whereIn('id', $source->pluck('employee_id')->all())
            ->get()
            ->keyBy('id');

        $rows = $source
            ->map(fn (array $a) => $this->row($a, $people->get($a['employee_id'])))
            ->sortBy(fn ($r) => mb_strtolower($r['name']))
            ->values();

        return [$run, $rows];
    }

    /**
     * The periods the picker offers: this week and the seven before it, this
     * month and the two before it, and any finalised run with a range of its
     * own. Weeks start where payroll's weeks do.
     *
     * @return array<string, array{key: string, label: string, span: string, from: string, to: string, group: string}>
     */
    private function periods(): array
    {
        $out = [];
        $add = function (Carbon $from, Carbon $to, string $label, string $group) use (&$out) {
            $key = $from->toDateString() . '_' . $to->toDateString();

            $out[$key] ??= self::entry($from, $to, $label, $group);
        };

        $starts = (int) (SystemSetting::current()->week_starts_on ?? Carbon::MONDAY);
        $week   = now()->startOfWeek($starts)->startOfDay();

        for ($i = 0; $i < 8; $i++) {
            $from = $week->copy()->subWeeks($i);
            $to   = $from->copy()->addDays(6);

            // Numbered by the middle of the week, which is the ISO week however
            // the office starts its own.
            $add($from, $to, 'Week ' . $from->copy()->addDays(3)->isoWeek() . ' · ' . self::span($from, $to), 'Weekly');
        }

        $month = now()->startOfMonth();

        for ($i = 0; $i < 3; $i++) {
            $from = $month->copy()->subMonthsNoOverflow($i);
            $add($from, $from->copy()->endOfMonth(), $from->format('F Y') . ' (monthly)', 'Monthly');
        }

        foreach (PayrollRun::where('status', 'finalized')->orderByDesc('period_start')->limit(40)->get() as $r) {
            $add($r->period_start, $r->period_end, $r->code . ' · ' . self::span($r->period_start, $r->period_end), 'Finalized runs');
        }

        return $out;
    }

    /**
     * The period a "2026-09-07_2026-09-13" key names: one the picker offers,
     * or any other real range up to two months long — a remittance can fall
     * due after its week has dropped off the list.
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
            $from = Carbon::parse($m[1]);
            $to   = Carbon::parse($m[2]);
        } catch (\Throwable) {
            return null;
        }

        $real = $from->toDateString() === $m[1] && $to->toDateString() === $m[2];

        return $real && $from->lte($to) && $from->diffInDays($to) <= 62
            ? self::entry($from, $to, self::span($from, $to), 'Other')
            : null;
    }

    private static function entry(Carbon $from, Carbon $to, string $label, string $group): array
    {
        return [
            'key'   => $from->toDateString() . '_' . $to->toDateString(),
            'label' => $label,
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

    /** One worker's period, in the same shape whether it is frozen or not. */
    private function row(array $a, ?Employee $employee): array
    {
        $id   = (int) $a['employee_id'];
        $name = (string) ($a['employee_name'] ?? $employee?->name ?? 'Employee #' . $id);

        // regular_hours is every hour worked, overtime included: it is the
        // engine's "hours" (PayrollRunService::buildItem). Whole minutes are
        // what payroll counts, so that is what is shown.
        $minutes = (int) round((float) $a['regular_hours'] * 60);
        $otMins  = (int) round((float) $a['ot_hours'] * 60);
        $otPay   = (float) $a['overtime_pay'];

        return [
            'employee_id'      => $id,
            'name'             => $name,
            'initial'          => mb_strtoupper(mb_substr($name, 0, 1)),
            'color'            => self::COLOURS[$id % count(self::COLOURS)],
            'code'             => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'labor'            => $employee?->laborType?->name ?: ($a['position'] ?? null ?: 'No labor type'),
            'site'             => $employee?->site?->name,
            // The worker's own number with each agency, off the employee form,
            // for whoever files the remittance.
            'ids'              => [
                'sss'        => $employee?->sss_number,
                'philhealth' => $employee?->philhealth_number,
                'pagibig'    => $employee?->pagibig_number,
                'bir'        => $employee?->tin_number,
            ],
            'daily_rate'       => (float) $a['daily_rate'],
            'hourly_rate'      => (float) $a['hourly_rate'],
            'days'             => (float) $a['days_worked'],
            'minutes'          => $minutes,
            'ot_minutes'       => $otMins,
            'regular_minutes'  => max(0, $minutes - $otMins),
            'late_minutes'     => (int) $a['late_minutes'],
            'basic'            => (float) $a['basic_pay'],
            'overtime'         => $otPay,
            // What an hour of overtime actually paid, so the line can say so
            // without re-deriving a multiplier that may have changed mid-period.
            'ot_rate'          => $otMins > 0 ? round($otPay / ($otMins / 60), 2) : 0.0,
            'night'            => (float) $a['night_diff_pay'],
            'holiday'          => (float) $a['holiday_pay'],
            'rest'             => (float) $a['rest_day_pay'],
            'leave'            => (float) $a['leave_pay'],
            'leave_days'       => (float) $a['paid_leave_days'],
            'bonus'            => (float) $a['bonus'],
            'other_earnings'   => (float) $a['other_earnings'],
            'sss'              => (float) $a['sss'],
            'philhealth'       => (float) $a['philhealth'],
            'pagibig'          => (float) $a['pagibig'],
            'tax'              => (float) $a['tax'],
            'vale'             => (float) $a['vale'],
            'advance'          => (float) $a['advance_deduction'],
            'loan'             => (float) $a['loan_deduction'],
            'other_deductions' => (float) $a['other_deductions'],
            'gross'            => (float) $a['gross_pay'],
            'deductions'       => (float) $a['total_deductions'],
            'net'              => (float) $a['net_pay'],
        ];
    }

    /**
     * The payslip's lines, each saying what produced it — a column of totals
     * cannot answer "why is this 746", and that is the question that gets asked.
     *
     * @return array{earn: list<array>, ded: list<array>}
     */
    private function lines(array $s): array
    {
        $line = fn (string $label, string $icon, ?string $note, float $amount) => compact('label', 'icon', 'note', 'amount');
        $peso = fn (float $n) => '₱' . number_format($n, 2);

        $earn = [
            $line('Basic pay', 'ti-clock',
                WorkSchedule::duration($s['regular_minutes']) . ' × ' . $peso($s['hourly_rate']) . '/hr', $s['basic']),
            $line('Overtime', 'ti-clock-plus', $s['ot_minutes'] > 0
                ? WorkSchedule::duration($s['ot_minutes']) . ' at ' . $peso($s['ot_rate']) . '/hr' : null, $s['overtime']),
            $line('Night differential', 'ti-moon', '10 PM – 6 AM premium', $s['night']),
            $line('Holiday pay', 'ti-calendar-star', 'Holiday premium', $s['holiday']),
            $line('Rest day pay', 'ti-armchair', 'Rest day premium', $s['rest']),
        ];

        if ($s['leave'] > 0) {
            $days   = rtrim(rtrim(number_format($s['leave_days'], 2), '0'), '.');
            $earn[] = $line('Paid leave', 'ti-beach', $days . ' day' . ($days === '1' ? '' : 's') . ' approved', $s['leave']);
        }

        $earn[] = $line('Bonus', 'ti-gift', $s['bonus'] > 0 ? 'Not taxed' : null, $s['bonus']);

        if ($s['other_earnings'] > 0) {
            $earn[] = $line('Other earnings', 'ti-plus', null, $s['other_earnings']);
        }

        $ded = [
            $line('SSS', 'ti-building-bank', 'Employee share', $s['sss']),
            $line('PhilHealth', 'ti-heart-plus', 'Employee share', $s['philhealth']),
            $line('Pag-IBIG', 'ti-home', 'Employee share', $s['pagibig']),
            $line('Withholding tax', 'ti-receipt-tax', 'Per BIR table', $s['tax']),
        ];

        if ($s['vale'] > 0) {
            $ded[] = $line('Vale', 'ti-wallet', 'Settled this period', $s['vale']);
        }

        $ded[] = $line('Cash advance', 'ti-cash', $s['advance'] > 0 ? 'Instalment' : null, $s['advance']);

        // Loans are no longer issued; a run from before still shows its own.
        if ($s['loan'] > 0) {
            $ded[] = $line('Loan', 'ti-cash', 'Instalment', $s['loan']);
        }

        $ded[] = $line('Other deductions', 'ti-minus', $s['other_deductions'] > 0 ? 'Adjustments' : null, $s['other_deductions']);

        return ['earn' => $earn, 'ded' => $ded];
    }

    /**
     * The payslip, laid out as the Payroll Records receipt lays its own: the
     * rate the days were priced at, each earning and deduction named with
     * what it was worked out at, then gross − deductions + bonus. The bonus
     * sits below the line there because it is not wages, and so it does here.
     *
     * @return array{meta: string, basis: string, earn: list<array{0: string, 1: float}>, ded: list<array{0: string, 1: float}>, gross: float, deductions: float, bonus: float, net: float}
     */
    private function slip(array $s, array $rates): array
    {
        $x    = fn ($m) => '×' . number_format((float) $m, 2);
        $pct  = fn ($r) => number_format((float) $r, 2) . '%';
        $peso = fn (float $n) => '₱' . number_format($n, 2);
        $days = rtrim(rtrim(number_format($s['days'], 2), '0'), '.');

        $earn = [
            ['Regular pay (' . $days . 'd)', $s['basic']],
            ['Overtime (' . $x($rates['ot_multiplier'] ?? 0) . ')', $s['overtime']],
            ['Night differential (' . $x($rates['night_diff_multiplier'] ?? 0) . ')', $s['night']],
            ['Holiday pay (' . $x($rates['regular_holiday_multiplier'] ?? 0) . ')', $s['holiday']],
            ['Rest day pay (' . $x($rates['rest_day_multiplier'] ?? 0) . ')', $s['rest']],
        ];

        // Payroll Records never sees these two; a period that has them still
        // has to add up.
        if ($s['leave'] > 0) {
            $earn[] = ['Paid leave', $s['leave']];
        }
        if ($s['other_earnings'] > 0) {
            $earn[] = ['Other earnings', $s['other_earnings']];
        }

        $ded = [
            ['SSS (' . $pct($rates['sss_rate'] ?? 0) . ')', $s['sss']],
            ['PhilHealth (' . $pct($rates['philhealth_rate'] ?? 0) . ')', $s['philhealth']],
            ['Pag-IBIG (' . $pct($rates['pagibig_rate'] ?? 0) . ')', $s['pagibig']],
            [($rates['withholding_tax'] ?? true) ? 'Withholding tax (BIR)' : 'Withholding tax (off)', $s['tax']],
            [$s['advance'] > 0 ? 'Vale / cash advance (' . $peso($s['advance']) . ' instalment)' : 'Vale / cash advance',
                $s['vale'] + $s['advance']],
            ['Other adjustments', $s['other_deductions'] + $s['loan']],
        ];

        return [
            'meta'       => $s['code'] . ' · ' . $s['labor'] . ' · ' . $days . 'd / ' . WorkSchedule::duration($s['minutes']),
            'basis'      => $peso($s['daily_rate']) . '/day · ' . $peso($s['hourly_rate']) . '/hr · '
                          . $days . ' day' . ($days === '1' ? '' : 's') . ' worked'
                          . ($s['late_minutes'] > 0 ? ' · ' . $s['late_minutes'] . 'm late' : ''),
            'earn'       => $earn,
            'ded'        => $ded,
            'gross'      => round($s['gross'] - $s['bonus'], 2),
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

    /** The cash advance instalment: taken off the pay, with nothing to remit. */
    private function advanceRow(array $s): array
    {
        $amount = $s['advance'] + $s['loan'];
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
