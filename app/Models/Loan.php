<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cash advance issued to a worker and collected back over several payrolls.
 *
 * Distinct from `vale`, which payroll already handles and which is settled
 * inside a single period.
 *
 * The table is still `loans`, and its type column still tells an advance from
 * a loan. Loans are no longer issued, but the ones that were keep their
 * history, and a run calculated while they were still charged settles them at
 * finalisation (PayrollRunService::collectLoans).
 */
class Loan extends Model
{
    /** The only kind issued now. */
    public const ADVANCE = 'advance';

    /**
     * The most the company advances one worker: ₱30,000, by company policy.
     *
     * A limit on what the worker owes, not on one application — someone
     * still paying back ₱20,000 can be advanced ₱10,000 more, and a balance
     * paid down makes room again. Change the figure here; the form, its hint
     * and the check on saving all read it.
     */
    public const LIMIT_PER_EMPLOYEE = 30000.00;

    /** Labels for every kind on file, the retired one included. */
    public const TYPES = [
        'loan'    => 'Loan',
        'advance' => 'Cash Advance',
    ];

    public const STATUSES = [
        'active'    => 'Active',
        'paid'      => 'Fully Paid',
        'on_hold'   => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'employee_id', 'type', 'reference', 'principal', 'balance', 'installment',
        'schedule', 'issued_on', 'starts_on', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'principal'   => 'float',
        'balance'     => 'float',
        'installment' => 'float',
        'issued_on'   => 'date',
        'starts_on'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(LoanDeduction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Cash advances — everything this table still issues. */
    public function scopeAdvances(Builder $q): Builder
    {
        return $q->where('type', self::ADVANCE);
    }

    /** Rows a payroll run should look at: still owed, and not paused. */
    public function scopeCollectible(Builder $q): Builder
    {
        return $q->where('status', 'active')->where('balance', '>', 0);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->settled ? 'paid' : $this->status] ?? ucfirst((string) $this->status);
    }

    /** The day a pay week opens, as payroll reads it. */
    public static function payWeekStartsOn(): int
    {
        return (int) (SystemSetting::current()->week_starts_on ?? Carbon::MONDAY);
    }

    /**
     * What is still owed today, worked out the way payroll works out what to
     * take. The `balance` column is the cache of this; where the two differ
     * it is the column that is behind.
     */
    public function getOutstandingAttribute(): float
    {
        return $this->outstandingOn(now()->toDateString(), self::payWeekStartsOn());
    }

    public function getPaidAmountAttribute(): float
    {
        return round($this->principal - $this->outstanding, 2);
    }

    public function getProgressAttribute(): int
    {
        if ($this->principal <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->paid_amount / $this->principal) * 100));
    }

    /** Settled once nothing is left, whether payroll or the office got there first. */
    public function getSettledAttribute(): bool
    {
        return $this->status === 'paid' || $this->outstanding <= 0;
    }

    /**
     * Write the derived state back to the columns, so the Status filter and
     * the outstanding totals agree with what the schedule says. Saves only
     * when something actually moved.
     */
    public function syncSettlement(): bool
    {
        $left   = $this->outstanding;
        $status = $this->status;

        if ($left <= 0 && in_array($status, ['active', 'on_hold'], true)) {
            $status = 'paid';
        }

        // Both ways for an advance: Fully Paid is read off the schedule, so a
        // schedule that says something is still owed puts it back to Active.
        // An advance marked paid on instalments no payroll ever took would
        // otherwise read Fully Paid while payroll went on collecting it.
        if ($left > 0 && $status === 'paid' && $this->type === self::ADVANCE) {
            $status = 'active';
        }

        if (round((float) $this->balance, 2) === round($left, 2) && $status === $this->status) {
            return false;
        }

        // Without touching updated_at. This is the page keeping a cache in
        // step with the calendar — a week passing, a day clocked in — not
        // anybody changing the advance, and the list is ordered by what was
        // last added or changed. Stamped, every advance would jump to the top
        // the first time the page was opened after its instalment came round.
        $this->timestamps = false;

        try {
            return $this->forceFill(['balance' => $left, 'status' => $status])->save();
        } finally {
            $this->timestamps = true;
        }
    }

    /**
     * What this payroll should take, given the period it covers.
     *
     * Never more than the balance, so the last instalment settles the advance
     * exactly rather than overshooting it. One whose collection has not
     * started by the end of the period is left alone.
     */
    public function installmentFor(string $periodEnd): float
    {
        if ($this->status !== 'active' || $this->balance <= 0) {
            return 0.0;
        }

        if ($this->starts_on && $this->starts_on->toDateString() > $periodEnd) {
            return 0.0;
        }

        return round(min($this->installment, $this->balance), 2);
    }

    // ── The schedule ────────────────────────────────────────────────────────
    //
    // What an advance has collected is worked out from three things that do
    // not move: the sum issued, the instalment the application asked for, and
    // the payments recorded against it. Nothing reads the running balance to
    // decide what a week takes, so a week recomputed next year comes out at
    // the figure that was on the payslip — the same rule the vale advance in
    // Payroll Settings follows.

    /** The first payroll that collects: the date given, or the day it was issued. */
    public function collectionOpensOn(): Carbon
    {
        return ($this->starts_on ?? $this->issued_on)->copy();
    }

    /**
     * The days this worker has pay an instalment can come out of: days clocked
     * in, and days of approved paid leave that have come round. Y-m-d, from
     * the week collection opens.
     *
     * @var list<string>|null  null until loaded
     */
    private ?array $payDates = null;

    /** @return list<string> */
    public function payDates(): array
    {
        if ($this->payDates === null) {
            static::loadPayDates(collect([$this]));
        }

        return $this->payDates;
    }

    /**
     * Load payDates() for many advances in two queries rather than two each.
     * Payroll asks every advance about every week, so it must not query per
     * question.
     *
     * @param  iterable<self>  $advances
     */
    public static function loadPayDates(iterable $advances): void
    {
        $advances = collect($advances)->filter(fn ($l) => $l instanceof self && $l->payDates === null);

        if ($advances->isEmpty()) {
            return;
        }

        $ids   = $advances->pluck('employee_id')->unique()->values()->all();
        $from  = $advances->map(fn (self $l) => $l->collectionOpensOn()->subDays(6)->toDateString())->min();
        $today = now()->toDateString();
        $dates = [];

        Attendance::whereIn('employee_id', $ids)
            ->whereNotNull('time_in')
            ->whereDate('date', '>=', $from)
            ->get(['employee_id', 'date'])
            ->each(function ($a) use (&$dates) {
                $dates[(int) $a->employee_id][Carbon::parse($a->date)->toDateString()] = true;
            });

        LeaveRequest::approved()
            ->whereIn('employee_id', $ids)
            ->where('is_paid', true)
            ->where('days', '>', 0)
            ->whereDate('ends_on', '>=', $from)
            ->whereDate('starts_on', '<=', $today)
            ->get(['employee_id', 'starts_on', 'ends_on'])
            ->each(function (LeaveRequest $l) use (&$dates, $from, $today) {
                $day  = Carbon::parse(max($l->starts_on->toDateString(), $from));
                $last = min($l->ends_on->toDateString(), $today);

                for (; $day->toDateString() <= $last; $day->addDay()) {
                    $dates[(int) $l->employee_id][$day->toDateString()] = true;
                }
            });

        foreach ($advances as $advance) {
            $mine = array_keys($dates[(int) $advance->employee_id] ?? []);
            sort($mine);
            $advance->payDates = $mine;
        }
    }

    /**
     * Every peso taken off this advance up to and including the week that
     * `$throughWeekOpening` falls in, in the order it came off, with what was
     * left after each.
     *
     * One walk serves both the payroll deduction and the history the office
     * is shown, so the figure a worker is quoted and the figure payroll takes
     * cannot disagree. A payment recorded at the office reduces what payroll
     * takes afterwards, and collection stops the moment nothing is left — so
     * the advance can never collect more than was handed over.
     *
     * @return array{lines: list<array{date: string, week: ?string, type: string, label: string, amount: float, balance: float, note: ?string}>, outstanding: float}
     */
    public function walk(string $throughWeekOpening, int $weekStartsOn): array
    {
        $left  = round((float) $this->principal, 2);
        $lines = [];

        if ($this->status === 'cancelled' || $left <= 0) {
            return ['lines' => [], 'outstanding' => $this->status === 'cancelled' ? 0.0 : $left];
        }

        $first = $this->collectionOpensOn()->startOfWeek($weekStartsOn);
        $last  = Carbon::parse($throughWeekOpening)->startOfWeek($weekStartsOn);

        // Payments by the week they fall in, so each is credited before the
        // payroll of the week it was made in — otherwise settling an advance
        // at the counter on Friday would still be deducted on the Sunday.
        //
        // In their own week, even one before collection starts. They used to
        // be moved forward into the first collecting week, and the walk stops
        // at today's — so on an advance set to start collecting next payroll,
        // a payment handed in this week was saved and then never reached:
        // the balance did not move, the history stayed empty, and a payment
        // of the whole sum left it Active. Nothing on screen said it had
        // worked, so the obvious thing was to record it again.
        $paid  = [];
        $opens = $first->copy();

        foreach ($this->deductions as $d) {
            $week = Carbon::parse($d->deducted_on)->startOfWeek($weekStartsOn);

            $paid[$week->toDateString()][] = $d;

            if ($week->lessThan($opens)) {
                $opens = $week->copy();
            }
        }

        // The weeks the worker has pay in. An instalment comes out of a payroll,
        // and a week with no attendance and no paid leave has none: payroll
        // takes nothing from it. The schedule used to charge it anyway, so
        // this tab showed a deduction — "20% paid" — that Payroll Records,
        // Payroll Processing and the payslip had never made. It charges only
        // the weeks payroll can collect in now, and payroll asks it what each
        // week takes, so the two cannot come apart.
        $payWeeks = [];

        foreach ($this->payDates() as $day) {
            $payWeeks[Carbon::parse($day)->startOfWeek($weekStartsOn)->toDateString()] = true;
        }

        for ($week = $opens; $week->lessThanOrEqualTo($last) && $left > 0; $week->addWeek()) {
            $key     = $week->toDateString();
            $byRun   = 0.0;

            foreach ($paid[$key] ?? [] as $d) {
                $take = round(min((float) $d->amount, $left), 2);

                if ($take <= 0) {
                    continue;
                }

                // A row a payroll run posted is that week's instalment already
                // collected, not an extra payment on top of it — older runs
                // wrote those before payroll took the instalment itself.
                $byRun += $d->payroll_run_id ? $take : 0;

                $left   = round($left - $take, 2);
                $lines[] = [
                    'date'    => Carbon::parse($d->deducted_on)->toDateString(),
                    'week'    => null,
                    'type'    => $d->payroll_run_id ? 'payroll' : 'payment',
                    'label'   => $d->payroll_run_id ? 'Payroll deduction' : 'Payment',
                    'amount'  => $take,
                    'balance' => $left,
                    'note'    => $d->note,
                ];
            }

            // A week before collection starts takes the payments made in it
            // and nothing else — and so does a week with no pay to take an
            // instalment out of. What it did not take is still owed, and the
            // next week with pay takes its instalment then.
            if ($week->lessThan($first) || ! isset($payWeeks[$key])) {
                continue;
            }

            // Whatever a payment did not cover, the payroll of that week takes
            // — the instalment the application asked for, or the remainder
            // when that is all there is left to collect.
            $take = round(min(max(0, (float) $this->installment - $byRun), $left), 2);

            if ($left > 0 && $take > 0) {
                $left   = round($left - $take, 2);
                $lines[] = [
                    // Dated to the payroll that takes it: the last day of the
                    // week, six days on from the day it opened.
                    'date'    => $week->copy()->addDays(6)->toDateString(),
                    'week'    => $key,
                    'type'    => 'payroll',
                    'label'   => 'Payroll deduction',
                    'amount'  => $take,
                    'balance' => $left,
                    'note'    => null,
                ];
            }
        }

        return ['lines' => $lines, 'outstanding' => max(0.0, $left)];
    }

    /**
     * What the week opening on a date collects — nothing before the advance
     * starts, and nothing once it is settled, which is what lets payroll ask
     * every advance about every week without knowing which are still running.
     */
    public function dueForWeekOpening(string $weekOpens, int $weekStartsOn): float
    {
        $key = Carbon::parse($weekOpens)->startOfWeek($weekStartsOn)->toDateString();

        foreach ($this->walk($weekOpens, $weekStartsOn)['lines'] as $line) {
            if ($line['week'] === $key) {
                return $line['amount'];
            }
        }

        return 0.0;
    }

    /**
     * What one worker still owes on cash advances today, every advance of
     * theirs counted — payroll instalments and office payments both taken off.
     */
    public static function owedBy(int $employeeId): float
    {
        return round(static::advances()
            ->where('employee_id', $employeeId)
            ->where('status', '!=', 'cancelled')
            ->with(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
            ->get()
            ->tap(fn ($all) => static::loadPayDates($all))
            ->sum(fn (self $l) => $l->outstanding), 2);
    }

    /** How much more one worker may be advanced before reaching the limit. */
    public static function roomFor(int $employeeId): float
    {
        return max(0.0, round(self::LIMIT_PER_EMPLOYEE - static::owedBy($employeeId), 2));
    }

    /** What is still owed as at a date, payments and instalments both counted. */
    public function outstandingOn(string $asOf, int $weekStartsOn): float
    {
        return $this->walk($asOf, $weekStartsOn)['outstanding'];
    }

    /**
     * Every advance that could still be collecting on or before a date, with
     * its ledger loaded.
     *
     * Filtered on where collection begins rather than on the range: one
     * started months ago may still have instalments left. Loaded once for the
     * whole range payroll is computing — a query per employee per week would
     * be thousands for a month of a full crew.
     *
     * @return list<array{advance: self, employee_id: int}>
     */
    public static function upTo(string $to): array
    {
        return static::advances()
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($to) {
                $q->whereDate('starts_on', '<=', $to)
                  ->orWhere(fn (Builder $q2) => $q2->whereNull('starts_on')->whereDate('issued_on', '<=', $to));
            })
            ->with(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
            ->get()
            ->tap(fn ($all) => static::loadPayDates($all))
            ->map(fn (self $l) => ['advance' => $l, 'employee_id' => (int) $l->employee_id])
            ->all();
    }
}
