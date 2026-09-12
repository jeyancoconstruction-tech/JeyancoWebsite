<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanDeduction;
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
 * Payroll runs: create, calculate, review, recalculate, approve, finalise —
 * and, once final, where each deduction went and whether the worker was paid.
 *
 * The arithmetic belongs to PayrollService, which is called through
 * PayrollRunService and is not modified. Nothing here finalises on its own —
 * every state change is a deliberate POST from a button the user pressed.
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

    /**
     * One period, one worker at a time.
     *
     * A period with a run shows the run's frozen figures; one without shows
     * what processing would freeze, from the same computation. The bar at the
     * top is the run's own workflow, with the next step in it.
     */
    public function index(Request $request)
    {
        $periods = $this->periods();
        $period  = $periods[(string) $request->query('period')] ?? reset($periods);

        $run = PayrollRun::with(['creator', 'approver', 'finalizer'])
            ->whereDate('period_start', $period['from'])
            ->whereDate('period_end', $period['to'])
            ->orderBy('site_id')      // the all-sites run first, where there is one
            ->orderByDesc('id')
            ->first();

        $processed = $run && $run->status !== 'draft';
        $final     = $run?->isFinal() ?? false;
        $tracking  = PayrollRemittance::available();

        $items = $processed
            ? $run->items()->with($tracking ? ['remittances.submitter', 'remittances.completer'] : [])->get()
            : collect();

        $source = $processed
            ? $items->map(fn (PayrollRunItem $i) => [$i->toArray(), $i])
            : collect($this->runs->preview($period['from'], $period['to']))->map(fn (array $a) => [$a, null]);

        $people = Employee::with(['laborType', 'site'])
            ->whereIn('id', $source->map(fn ($s) => $s[0]['employee_id'])->all())
            ->get()
            ->keyBy('id');

        $rows = $source
            ->map(fn ($s) => $this->row($s[0], $s[1], $people->get($s[0]['employee_id'])))
            ->sortBy(fn ($r) => mb_strtolower($r['name']))
            ->values();

        $sel   = $rows->firstWhere('employee_id', (int) $request->query('employee')) ?? $rows->first();
        $view  = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'workflow';
        $track = $sel ? $this->trackRows($sel, $run, $tracking) : [];

        return view('payroll-processing.index', [
            'periods'   => $periods,
            'period'    => $period,
            'run'       => $run,
            'processed' => $processed,
            'final'     => $final,
            'rows'      => $rows,
            'sel'       => $sel,
            'view'      => $view,
            'totals'    => [
                'employees'  => $rows->count(),
                'gross'      => round($rows->sum('gross'), 2),
                'deductions' => round($rows->sum('deductions'), 2),
                'net'        => round($rows->sum('net'), 2),
            ],
            'stages'    => $this->stages($run, $processed, $items, $tracking),
            'lines'     => $sel ? $this->lines($sel) : ['earn' => [], 'ded' => []],
            'track'     => $track,
            'open'      => count(array_filter($track, fn ($t) => $t['open'])),
            'notice'    => $this->trackNotice($processed, $final, $tracking),
            'company'   => SystemSetting::current(),
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

        // Back to the period, where the next step is.
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
     * Move one payslip line along: a contribution submitted to its agency and
     * then confirmed remitted, or the net pay handed over. Only on a finalised
     * run — before that the figures can still change under the record.
     */
    public function track(Request $request, PayrollRunItem $item, string $kind)
    {
        abort_unless(isset(PayrollRemittance::KINDS[$kind]), 404);

        $action = $request->validate(['action' => 'required|in:submit,done,undo'])['action'];

        if (! PayrollRemittance::available()) {
            return back()->with('error', 'Remittance tracking needs a database update first (php artisan migrate).');
        }

        $run = $item->run;

        if (! $run?->isFinal()) {
            return back()->with('error', 'Remittances are tracked once the run is finalized.');
        }

        $label  = PayrollRemittance::KINDS[$kind];
        $amount = PayrollRemittance::amountFor($item, $kind);

        if ($amount <= 0) {
            return back()->with('error', "Nothing is due for {$label}.");
        }

        $word = fn (string $s) => $s === PayrollRemittance::DONE ? ($kind === 'net_pay' ? 'paid' : 'remitted') : $s;

        $line = PayrollRemittance::firstOrNew(['payroll_run_item_id' => $item->id, 'kind' => $kind]);
        $from = $line->status ?? PayrollRemittance::PENDING;
        $to   = PayrollRemittance::after($kind, $from, $action);

        if ($to === null) {
            return back()->with('error', "{$label} for {$item->employee_name} is {$word($from)}; that step does not follow.");
        }

        $line->fill(['status' => $to, 'amount' => $amount]);

        // Each step signs itself; undoing one takes its signature back off.
        match (true) {
            $action === 'submit'               => $line->fill(['submitted_by' => auth()->id(), 'submitted_at' => now()]),
            $action === 'done'                 => $line->fill(['completed_by' => auth()->id(), 'completed_at' => now()]),
            $from === PayrollRemittance::DONE  => $line->fill(['completed_by' => null, 'completed_at' => null]),
            default                            => $line->fill(['submitted_by' => null, 'submitted_at' => null]),
        };

        $line->save();

        AuditLog::record('Payroll', 'remittance',
            "{$label} for {$item->employee_name} ({$run->code}): {$word($from)} → {$word($to)}", $run);

        return back()->with('success', $action === 'undo'
            ? "Undone — {$label} for {$item->employee_name} is {$word($to)} again."
            : "{$label} for {$item->employee_name} marked {$word($to)}.");
    }

    // ── The page's pieces ───────────────────────────────────────────────────

    /**
     * The periods the picker offers: this week and the seven before it, this
     * month and the two before it, and any run on file for a range of its own.
     * Weeks start where payroll's weeks do.
     *
     * @return array<string, array{key: string, label: string, span: string, from: string, to: string, group: string}>
     */
    private function periods(): array
    {
        $out = [];
        $add = function (Carbon $from, Carbon $to, string $label, string $group) use (&$out) {
            $key = $from->toDateString() . '_' . $to->toDateString();

            $out[$key] ??= [
                'key'   => $key,
                'label' => $label,
                'span'  => self::span($from, $to),
                'from'  => $from->toDateString(),
                'to'    => $to->toDateString(),
                'group' => $group,
            ];
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

        foreach (PayrollRun::orderByDesc('period_start')->limit(40)->get() as $r) {
            $add($r->period_start, $r->period_end, $r->code . ' · ' . self::span($r->period_start, $r->period_end), 'Other runs');
        }

        return $out;
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
    private function row(array $a, ?PayrollRunItem $item, ?Employee $employee): array
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
            'item'             => $item,
            'name'             => $name,
            'initial'          => mb_strtoupper(mb_substr($name, 0, 1)),
            'color'            => self::COLOURS[$id % count(self::COLOURS)],
            'code'             => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
            'labor'            => $employee?->laborType?->name ?: ($a['position'] ?? null ?: 'No labor type'),
            'site'             => $employee?->site?->name,
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
     * Where the period stands, as the run's own workflow: each step done,
     * current, or still to come, and who took it.
     *
     * @return list<array{label: string, icon: string, note: string, state: string}>
     */
    private function stages(?PayrollRun $run, bool $processed, Collection $items, bool $tracking): array
    {
        $status    = $run?->status;
        $approved  = in_array($status, ['approved', 'finalized'], true);
        $finalized = $status === 'finalized';

        [$due, $done] = $finalized && $tracking ? $this->settlement($items) : [0, 0];

        $steps = [
            ['Computed', 'ti-calculator', true, 'From attendance'],
            ['Processed', 'ti-player-play', $processed,
                $processed ? self::signed($run->creator, $run->calculated_at) : 'Not yet'],
            ['Approved', 'ti-circle-check', $approved,
                $approved ? self::signed($run->approver, $run->approved_at) : 'Waiting'],
            ['Finalized', 'ti-lock', $finalized,
                $finalized ? self::signed($run->finalizer, $run->finalized_at) : 'Payslips issue here'],
            ['Remitted & paid', 'ti-transfer-out', $finalized && $tracking && $done >= $due, match (true) {
                ! $finalized => 'After finalizing',
                ! $tracking  => 'Awaiting setup',
                $due === 0   => 'Nothing to remit',
                default      => "{$done} of {$due} settled",
            }],
        ];

        $out     = [];
        $reached = false;

        foreach ($steps as [$label, $icon, $isDone, $note]) {
            $state = $isDone ? 'done' : ($reached ? 'todo' : 'now');
            $reached = $reached || ! $isDone;
            $out[] = compact('label', 'icon', 'note', 'state');
        }

        return $out;
    }

    /**
     * Across a finalised run: the lines with money to move, and how many
     * have moved.
     *
     * @return array{0: int, 1: int}
     */
    private function settlement(Collection $items): array
    {
        $due = $done = 0;

        foreach ($items as $item) {
            foreach (array_keys(PayrollRemittance::KINDS) as $kind) {
                if (PayrollRemittance::amountFor($item, $kind) <= 0) {
                    continue;
                }

                $due++;

                if ($item->remittances->firstWhere('kind', $kind)?->status === PayrollRemittance::DONE) {
                    $done++;
                }
            }
        }

        return [$due, $done];
    }

    /**
     * The tracker's rows for one worker: each contribution and the net pay,
     * with where it has got to, and the cash advance, which settles itself
     * when the run is finalised.
     */
    private function trackRows(array $s, ?PayrollRun $run, bool $tracking): array
    {
        $item  = $s['item'];
        $final = $run?->isFinal() ?? false;
        $live  = $final && $tracking && $item;   // lines can be moved

        $meta = [
            'sss'        => ['ti-building-bank', 'Employee share', $s['sss']],
            'philhealth' => ['ti-heart-plus', 'Employee share', $s['philhealth']],
            'pagibig'    => ['ti-home', 'Employee share', $s['pagibig']],
            'bir'        => ['ti-receipt-tax', 'Withholding on compensation · BIR 1601-C', $s['tax']],
            'net_pay'    => ['ti-wallet', 'Released to the worker', $s['net']],
        ];

        $undo = ['action' => 'undo', 'label' => 'Undo', 'icon' => 'ti-arrow-back-up', 'primary' => false];
        $rows = [];

        foreach (PayrollRemittance::KINDS as $kind => $label) {
            if ($kind === 'net_pay') {
                $rows[] = $this->advanceRow($s, $run, $final);
            }

            [$icon, $sub, $amount] = $meta[$kind];
            $pay = $kind === 'net_pay';
            $row = compact('kind', 'label', 'icon', 'sub', 'amount')
                 + ['by' => null, 'actions' => [], 'done_text' => null, 'open' => false];

            if ($amount <= 0) {
                $rows[] = ['state' => 'Nothing due', 'tone' => 'pp-b-muted', 'badge' => 'ti-minus'] + $row;
                continue;
            }

            if (! $live) {
                $rows[] = ['state' => 'Awaiting finalization', 'tone' => 'pp-b-muted', 'badge' => 'ti-hourglass'] + $row;
                continue;
            }

            $rec = $item->remittances->firstWhere('kind', $kind);

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

    /**
     * The cash advance instalment. Nobody marks it: finalising the run takes
     * it off the advance's balance, and the ledger says so.
     */
    private function advanceRow(array $s, ?PayrollRun $run, bool $final): array
    {
        $amount = $s['advance'] + $s['loan'];
        $row    = [
            'kind' => 'advance', 'label' => 'Cash advance', 'icon' => 'ti-cash', 'amount' => $amount,
            'sub'  => 'Taken off the balance when the run is finalized',
            'by'   => null, 'actions' => [], 'done_text' => null, 'open' => false,
        ];

        if ($amount <= 0) {
            return ['state' => 'Nothing due', 'tone' => 'pp-b-muted', 'badge' => 'ti-minus'] + $row;
        }

        if (! $final) {
            return ['state' => 'At finalization', 'tone' => 'pp-b-muted', 'badge' => 'ti-hourglass'] + $row;
        }

        $taken = (float) LoanDeduction::where('payroll_run_id', $run->id)
            ->whereIn('loan_id', Loan::where('employee_id', $s['employee_id'])->select('id'))
            ->sum('amount');

        return [
            'state'     => 'Collected', 'tone' => 'pp-b-green', 'badge' => 'ti-circle-check',
            'sub'       => '₱' . number_format($taken, 2) . ' taken off the balance',
            'by'        => self::signed($run->finalizer, $run->finalized_at),
            'done_text' => 'Collected',
        ] + $row;
    }

    private function trackNotice(bool $processed, bool $final, bool $tracking): ?string
    {
        return match (true) {
            ! $tracking  => 'Remittance tracking is switched on by a database update that has not been run on this server yet. The amounts below are right; they can be marked once it has.',
            ! $processed => 'This period has not been processed yet. Remittances are tracked on the final figures, once the run is processed, approved and finalized.',
            ! $final     => 'Remittances are tracked once this run is finalized. Until then its figures can still be recalculated.',
            default      => null,
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
