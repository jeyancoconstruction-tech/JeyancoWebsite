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
     * What this worker's pay could give a cash advance, payroll week by
     * payroll week: week opening (Y-m-d) => the pay left once every other
     * deduction is off. A week they have no payroll row in is not in it.
     *
     * @var array<string, float>|null  null until loaded
     */
    private ?array $payRoom = null;

    /**
     * This worker's other advances that come first — issued earlier, or the
     * same day and entered first. A week's pay goes to them before this one.
     *
     * @var list<self>
     */
    private array $olderAdvances = [];

    /** The last day $payRoom reaches (Y-m-d). */
    private ?string $payRoomThrough = null;

    /**
     * @param  string|null  $through  the last day the answer has to reach;
     *                                 the end of this week when not given
     * @return array<string, float>
     */
    public function payRoom(?string $through = null): array
    {
        if ($this->payRoom === null || ($through !== null && $through > $this->payRoomThrough)) {
            $this->payRoom = null;
            static::loadPayRoom(collect([$this]), $through);
        }

        return $this->payRoom;
    }

    /** Reloaded from the table, so what was worked out from it is dropped too. */
    public function refresh()
    {
        $this->payRoom        = null;
        $this->payRoomThrough = null;
        $this->olderAdvances  = [];

        return parent::refresh();
    }

    /**
     * Load payRoom() for many advances with one payroll computation between
     * them. Payroll asks every advance about every week, so it must not
     * compute per question.
     *
     * The pay is worked out by the payroll engine itself with cash advances
     * left out (PayrollService::cashAdvanceRoom()), for just these workers,
     * from the week the earliest of their advances starts collecting to the
     * end of this week.
     *
     * @param  iterable<self>  $advances
     * @param  string|null      $through  the last day it has to reach, when that is past this week
     */
    public static function loadPayRoom(iterable $advances, ?string $through = null): void
    {
        $advances = collect($advances)->filter(fn ($l) => $l instanceof self && $l->payRoom === null)->values();

        if ($advances->isEmpty()) {
            return;
        }

        $ids = $advances->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        // Every advance these workers have, not only the ones asked about: an
        // older one that is not on this page still comes out of the same pay
        // first. The instances asked about stand in for their own rows.
        $all = static::advances()
            ->whereIn('employee_id', $ids)
            ->where('status', '!=', 'cancelled')
            ->with(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
            ->get()
            ->keyBy('id');

        foreach ($advances as $advance) {
            $all[$advance->id] = $advance;
        }

        $weekStartsOn = static::payWeekStartsOn();
        $from = $all->map(fn (self $l) => $l->collectionOpensOn()->startOfWeek($weekStartsOn)->toDateString())->min();
        $to   = max(
            now()->startOfWeek($weekStartsOn)->addDays(6)->toDateString(),
            $through ? Carbon::parse($through)->startOfWeek($weekStartsOn)->addDays(6)->toDateString() : ''
        );

        $room = $from <= $to
            ? app(\App\Services\PayrollService::class)->cashAdvanceRoom($ids, $from, $to)
            : [];

        foreach ($all->groupBy('employee_id') as $employeeId => $theirs) {
            $ordered = $theirs
                ->sort(fn (self $a, self $b) => [$a->issued_on->toDateString(), $a->id] <=> [$b->issued_on->toDateString(), $b->id])
                ->values();

            foreach ($ordered as $i => $advance) {
                $advance->payRoom        = $room[(int) $employeeId] ?? [];
                $advance->payRoomThrough = $to;
                $advance->olderAdvances  = $ordered->slice(0, $i)->values()->all();
            }
        }
    }

    /**
     * Give instances what other instances of the same rows already loaded —
     * so a page holding a list and its totals does not compute payroll twice.
     *
     * @param  iterable<self>  $loaded
     * @param  iterable<self>  $into
     */
    public static function sharePayRoom(iterable $loaded, iterable $into): void
    {
        $byId = collect($loaded)->keyBy('id');

        foreach ($into as $advance) {
            $source = $byId->get($advance->id);

            if ($source !== null && $source->payRoom !== null) {
                $advance->payRoom        = $source->payRoom;
                $advance->payRoomThrough = $source->payRoomThrough;
                $advance->olderAdvances  = $source->olderAdvances;
            }
        }
    }

    /**
     * Every peso taken off this advance up to and including the week that
     * `$throughWeekOpening` falls in, in the order it came off, with what was
     * left after each — and every instalment deferred because the pay was too
     * low.
     *
     * One walk serves both the payroll deduction and the history the office
     * is shown, so the figure a worker is quoted and the figure payroll takes
     * cannot disagree. A payment recorded at the office reduces what payroll
     * takes afterwards, and collection stops the moment nothing is left — so
     * the advance can never collect more than was handed over.
     *
     * An instalment is taken only from a payroll whose pay, after every other
     * deduction, covers it — whole, never in part. When it does not, the week
     * takes nothing, the instalment is deferred and carried forward, and it
     * stays in the balance. The next payroll with pay takes the instalment
     * and everything carried when its pay covers both; when it covers only
     * the instalment, it takes that and the carried amount waits for a
     * payroll that can; when it covers neither, it defers again. Offering the
     * instalment alone matters: carried amounts only grow, and a worker whose
     * pay covers one instalment but never two would otherwise never pay off
     * the advance at all.
     *
     * @return array{lines: list<array{date: string, week: ?string, type: string, label: string, amount: float, deferred: float, balance: float, note: ?string}>, outstanding: float}
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
        $peso  = fn (float $n) => '₱' . number_format($n, 2);

        // Payments by the week they fall in, so each is credited before the
        // payroll of the week it was made in — otherwise settling an advance
        // at the counter on Friday would still be deducted on the Sunday.
        //
        // In their own week, even one before collection starts. They used to
        // be moved forward into the first collecting week, and the walk stops
        // at today's — so on an advance set to start collecting next payroll,
        // a payment handed in this week was saved and then never reached.
        $paid  = [];
        $opens = $first->copy();

        foreach ($this->deductions as $d) {
            $week = Carbon::parse($d->deducted_on)->startOfWeek($weekStartsOn);

            $paid[$week->toDateString()][] = $d;

            if ($week->lessThan($opens)) {
                $opens = $week->copy();
            }
        }

        // What each payroll week could pay, and how much of it this worker's
        // older advances took first. A week with no payroll row for the worker
        // is not in $room at all: payroll has nothing to take an instalment
        // from, so the schedule charges nothing there — this tab once showed
        // deductions no payroll had made because it charged such weeks anyway.
        $room  = $this->payRoom($last->copy()->addDays(6)->toDateString());
        $older = [];

        foreach ($this->olderAdvances as $advance) {
            foreach ($advance->walk($throughWeekOpening, $weekStartsOn)['lines'] as $line) {
                if ($line['type'] === 'payroll' && $line['week'] !== null) {
                    $older[$line['week']] = round(($older[$line['week']] ?? 0) + $line['amount'], 2);
                }
            }
        }

        $carried = 0.0;
        $today   = now()->toDateString();

        for ($week = $opens; $week->lessThanOrEqualTo($last) && $left > 0; $week->addWeek()) {
            $key   = $week->toDateString();
            $byRun = 0.0;

            foreach ($paid[$key] ?? [] as $d) {
                $take = round(min((float) $d->amount, $left), 2);

                if ($take <= 0) {
                    continue;
                }

                // A row a payroll run posted is that week's instalment already
                // collected, not an extra payment on top of it — older runs
                // wrote those before payroll took the instalment itself.
                $byRun += $d->payroll_run_id ? $take : 0;

                $left    = round($left - $take, 2);
                $carried = min($carried, $left);
                $lines[] = [
                    'date'     => Carbon::parse($d->deducted_on)->toDateString(),
                    'week'     => null,
                    'type'     => $d->payroll_run_id ? 'payroll' : 'payment',
                    'label'    => $d->payroll_run_id ? 'Payroll deduction' : 'Payment',
                    'amount'   => $take,
                    'deferred' => 0.0,
                    'balance'  => $left,
                    'note'     => $d->note,
                ];
            }

            // Before collection starts a week takes only the payments made in
            // it, and a week with no payroll takes nothing at all.
            if ($week->lessThan($first) || ! array_key_exists($key, $room) || $left <= 0) {
                continue;
            }

            // The instalment the application asked for, or the remainder when
            // that is all there is left.
            $regular = round(min(max(0, (float) $this->installment - $byRun), $left), 2);

            if ($regular <= 0) {
                continue;
            }

            $avail   = round($room[$key] - ($older[$key] ?? 0), 2);
            $catchUp = round(min($regular + $carried, $left), 2);
            $closes  = $week->copy()->addDays(6)->toDateString();

            if ($avail >= $catchUp) {
                $take = $catchUp;
            } elseif ($avail >= $regular) {
                $take = $regular;
            } else {
                // The pay cannot cover it: nothing is taken, and it is carried.
                $carried = round(min($carried + $regular, $left), 2);
                $lines[] = [
                    'date'     => $closes,
                    'week'     => $key,
                    'type'     => 'deferred',
                    'label'    => 'Deferred',
                    'amount'   => 0.0,
                    'deferred' => $regular,
                    'balance'  => $left,
                    // A week still running may yet earn enough.
                    'note'     => $closes >= $today
                        ? 'Pay so far this week is too low for the ' . $peso($regular) . " instalment — it is taken if the week's pay covers it."
                        : 'Pay too low for the ' . $peso($regular) . ' instalment — ' . $peso($carried) . ' carried forward.',
                ];

                continue;
            }

            $caughtUp = round($take - $regular, 2);
            $left     = round($left - $take, 2);
            $carried  = round(min(max(0, $carried - $caughtUp), $left), 2);

            $lines[] = [
                // Dated to the payroll that takes it: the last day of the
                // week, six days on from the day it opened.
                'date'     => $closes,
                'week'     => $key,
                'type'     => 'payroll',
                'label'    => 'Payroll deduction',
                'amount'   => $take,
                'deferred' => 0.0,
                'balance'  => $left,
                'note'     => $caughtUp > 0
                    ? 'Includes ' . $peso($caughtUp) . ' carried forward.'
                    : ($carried > 0 ? $peso($carried) . ' still carried forward — this pay covered the instalment, not both.' : null),
            ];
        }

        return ['lines' => $lines, 'outstanding' => max(0.0, $left)];
    }

    /**
     * What the payroll week opening on a date does with this advance: what it
     * takes, what it defers because the pay was too low, and what was due —
     * the one or the other. Nothing before the advance starts and nothing
     * once it is settled, which is what lets payroll ask every advance about
     * every week without knowing which are still running.
     *
     * @return array{due: float, taken: float, deferred: float}
     */
    public function weekFor(string $weekOpens, int $weekStartsOn): array
    {
        $key = Carbon::parse($weekOpens)->startOfWeek($weekStartsOn)->toDateString();
        $out = ['due' => 0.0, 'taken' => 0.0, 'deferred' => 0.0];

        foreach ($this->walk($weekOpens, $weekStartsOn)['lines'] as $line) {
            if ($line['week'] !== $key) {
                continue;
            }

            if ($line['type'] === 'payroll') {
                $out['taken'] += $line['amount'];
                $out['due']   += $line['amount'];
            } elseif ($line['type'] === 'deferred') {
                $out['deferred'] += $line['deferred'];
                $out['due']      += $line['deferred'];
            }
        }

        return array_map(fn (float $v) => round($v, 2), $out);
    }

    /** What the week opening on a date takes off this advance. */
    public function dueForWeekOpening(string $weekOpens, int $weekStartsOn): float
    {
        return $this->weekFor($weekOpens, $weekStartsOn)['taken'];
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
            ->tap(fn ($all) => static::loadPayRoom($all))
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
            ->tap(fn ($all) => static::loadPayRoom($all, $to))
            ->map(fn (self $l) => ['advance' => $l, 'employee_id' => (int) $l->employee_id])
            ->all();
    }
}
