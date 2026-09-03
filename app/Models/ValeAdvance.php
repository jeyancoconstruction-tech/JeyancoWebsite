<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A cash advance, collected across several pay periods.
 *
 * The vale on an attendance row is taken out of one day. This is the other
 * kind: a sum handed over once and taken back a piece at a time, so a worker
 * who borrows a week's wage does not go home with nothing the week after.
 *
 * The schedule is what is stored — an amount, a number of weeks, and the week
 * it starts — rather than a running balance. What any week collects follows
 * from those three, so a period reopened next year computes to the payslip that
 * went with it, and no week depends on the order the others were calculated in.
 *
 * Not edited after the fact. An advance that was wrong is deleted.
 */
class ValeAdvance extends Model
{
    protected $fillable = [
        'amount',
        'weeks',
        'starts_on',
        'all_employees',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'float',
        'weeks'         => 'integer',
        'starts_on'     => 'date',
        'all_employees' => 'boolean',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    /**
     * What one instalment takes.
     *
     * The division rarely comes out even, so the last one carries the
     * remainder — ₱1,000 over 3 weeks is 333.33, 333.33, 333.34. Collecting
     * the rounded figure every week would leave a centavo owed forever.
     */
    public function instalment(int $index): float
    {
        $weeks = max(1, $this->weeks);
        $even  = round($this->amount / $weeks, 2);

        return $index === $weeks - 1
            ? round($this->amount - $even * ($weeks - 1), 2)
            : $even;
    }

    /**
     * What this advance collects from the week that opens on a given date.
     *
     * Zero before it starts and after it is paid off, which is what lets
     * payroll ask every advance about every week without knowing which are
     * still running.
     */
    public function dueForWeekOpening(string $weekOpens, int $weekStartsOn): float
    {
        $first = $this->starts_on->copy()->startOfWeek($weekStartsOn);
        $index = (int) $first->diffInWeeks(Carbon::parse($weekOpens)->startOfWeek($weekStartsOn), false);

        return ($index < 0 || $index >= max(1, $this->weeks)) ? 0.0 : $this->instalment($index);
    }

    /**
     * Every advance that could still be collecting on or before a date, with
     * its recipients loaded.
     *
     * An advance that started months ago may still have instalments left, so
     * the filter is on where the schedule begins, not on the range itself; a
     * week outside its run is answered with zero. Loaded once for the whole
     * range payroll is computing — a query per employee per week would be
     * thousands for a month of a full crew.
     *
     * @return array<int, array{advance: self, all: bool, employees: array<int, int>}>
     */
    public static function upTo(string $to): array
    {
        return static::query()
            ->with('employees:id')
            ->whereDate('starts_on', '<=', $to)
            ->get()
            ->map(fn (self $a) => [
                'advance'   => $a,
                'all'       => (bool) $a->all_employees,
                'employees' => $a->employees->pluck('id')->all(),
            ])
            ->all();
    }
}
