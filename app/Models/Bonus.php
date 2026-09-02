<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A bonus given to named people for one pay period.
 *
 * The standing bonus on PayrollRate is the same amount to everybody, every
 * period. This is the other kind: an amount, the day it belongs to, and who
 * gets it — one worker, two, or everybody.
 *
 * Not edited after the fact. A grant that was wrong is deleted, so a period
 * recomputed next year still matches the payslip that went with it.
 */
class Bonus extends Model
{
    protected $fillable = [
        'amount',
        'effective_on',
        'all_employees',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'float',
        'effective_on'  => 'date',
        'all_employees' => 'boolean',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    /**
     * Every grant landing between two dates, with its recipients loaded.
     *
     * Loaded once for the whole range payroll is computing: a query per
     * employee per week would be thousands for a month of a full crew.
     *
     * @return array<int, array{on: string, amount: float, all: bool, employees: array<int, int>}>
     */
    public static function inRange(string $from, string $to): array
    {
        return static::query()
            ->with('employees:id')
            ->whereBetween('effective_on', [$from, $to])
            ->get()
            ->map(fn (self $b) => [
                'on'        => $b->effective_on->toDateString(),
                'amount'    => (float) $b->amount,
                'all'       => (bool) $b->all_employees,
                'employees' => $b->employees->pluck('id')->all(),
            ])
            ->all();
    }
}
