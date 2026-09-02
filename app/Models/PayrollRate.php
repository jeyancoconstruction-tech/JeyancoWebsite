<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One dated set of payroll numbers: the premiums, the daily wage floor, the
 * period bonus, and the employee-share contribution rates.
 *
 * A change is a new row, not an edit. Payroll asks for the row in force on the
 * day it is computing, so reopening an old period recomputes it at the numbers
 * that applied then and still agrees with the payslip that was issued.
 *
 * @property \Illuminate\Support\Carbon $effective_from
 */
class PayrollRate extends Model
{
    protected $fillable = [
        'effective_from',
        'ot_multiplier',
        'night_diff_multiplier',
        'rest_day_multiplier',
        'regular_holiday_multiplier',
        'daily_rate',
        'bonus',
        'uses_defaults',
        'sss_rate',
        'philhealth_rate',
        'pagibig_rate',
        'created_by',
    ];

    protected $casts = [
        'effective_from'             => 'date',
        'ot_multiplier'              => 'float',
        'night_diff_multiplier'      => 'float',
        'rest_day_multiplier'        => 'float',
        'regular_holiday_multiplier' => 'float',
        'daily_rate'                 => 'float',
        'bonus'                      => 'float',
        'uses_defaults'              => 'boolean',
        'sss_rate'                   => 'float',
        'philhealth_rate'            => 'float',
        'pagibig_rate'               => 'float',
    ];

    /** The DOLE statutory minimums, and the fallback when no row exists yet. */
    public const DEFAULTS = [
        'ot_multiplier'              => 1.25,
        'night_diff_multiplier'      => 1.10,
        'rest_day_multiplier'        => 1.30,
        'regular_holiday_multiplier' => 2.00,
    ];

    /**
     * Employee-share contribution rates, percent of gross: SSS 5% of the MSC
     * bracket, PhilHealth 2.5% of basic, Pag-IBIG 2%. Used to fill the form
     * before the first rate is entered — never to fill in a row that has a
     * rate, because 0% is a real answer an office may have chosen.
     */
    public const DEDUCTION_DEFAULTS = [
        'sss_rate'        => 5.00,
        'philhealth_rate' => 2.50,
        'pagibig_rate'    => 2.00,
    ];

    /**
     * Every rate set, newest effectivity first — the history the settings page
     * shows and the order the resolver reads.
     */
    public function scopeNewestFirst($query)
    {
        return $query->orderByDesc('effective_from')->orderByDesc('id');
    }

    /** The set in force right now. */
    public static function current(): ?self
    {
        return static::effectiveOn(now()->toDateString());
    }

    /**
     * The set in force on a given date: the newest one that had already taken
     * effect. Two rows sharing a date is not an error — the later insert wins,
     * which is what makes correcting a rate you just entered possible.
     */
    public static function effectiveOn(string $date): ?self
    {
        return static::query()
            ->whereDate('effective_from', '<=', $date)
            ->newestFirst()
            ->first();
    }

    /**
     * Multipliers as a plain array, filled out with the statutory minimums for
     * anything missing, so callers never have to null-check one. A multiplier
     * below 1 would pay less than the plain rate, so it is treated as missing.
     *
     * A row on defaults answers with the constants rather than its own columns,
     * so raising a statutory figure here reaches every such row at once. That
     * is the whole point of the switch: an office that never chose its own
     * numbers should not be left on last year's.
     *
     * @return array<string, float>
     */
    public function toMultipliers(): array
    {
        if ($this->uses_defaults) {
            return self::DEFAULTS;
        }

        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = $this->{$key};
            $out[$key] = ($value === null || $value <= 0) ? $default : (float) $value;
        }

        return $out;
    }

    /**
     * Contribution rates as a plain array. Unlike a multiplier, 0 is kept: an
     * office that deducts nothing has said so, and substituting 5% would take
     * money off a payslip nobody agreed to — unless the row is on defaults, in
     * which case nobody chose 0 and the circular's rate is the answer.
     *
     * @return array<string, float>
     */
    public function toDeductionRates(): array
    {
        if ($this->uses_defaults) {
            return self::DEDUCTION_DEFAULTS;
        }

        $out = [];
        foreach (self::DEDUCTION_DEFAULTS as $key => $default) {
            $value = $this->{$key};
            $out[$key] = $value === null ? $default : max(0.0, (float) $value);
        }

        return $out;
    }

    /**
     * Everything payroll needs for one day, in one array.
     *
     * @return array<string, float|null>
     */
    public function toRates(): array
    {
        // A floor of 0 is no floor. The opening row is seeded from a settings
        // table whose daily_rate defaults to 0.00, and a zero read as a real
        // floor would show on screen as one somebody had chosen.
        $floor = (float) ($this->daily_rate ?? 0);

        // Unlike the floor, a bonus of 0 is a real answer: an office that pays
        // none has said so, and inventing one would put money on a payslip
        // nobody agreed to.
        return $this->toMultipliers()
            + $this->toDeductionRates()
            + [
                'daily_rate' => $floor > 0 ? $floor : null,
                'bonus'      => max(0.0, (float) ($this->bonus ?? 0)),
            ];
    }

    /**
     * The shape toRates() returns when no row covers the date being computed,
     * which only happens if the opening row is deleted. The premiums fall back
     * to the statutory minimums, but the contributions fall back to zero: with
     * no set on file nobody has decided what to withhold, and guessing takes
     * money off a payslip. No wage floor either, for the same reason.
     */
    public static function fallbackRates(): array
    {
        return self::DEFAULTS
            + ['sss_rate' => 0.0, 'philhealth_rate' => 0.0, 'pagibig_rate' => 0.0, 'daily_rate' => null, 'bonus' => 0.0];
    }

    /**
     * Load every rate set once, newest first, so payroll can resolve a date
     * without a query per attendance row.
     *
     * @return array<int, array{from: string, rates: array<string, float|null>}>
     */
    public static function timeline(): array
    {
        return static::query()->newestFirst()->get()
            ->map(fn (self $r) => [
                'from'  => $r->effective_from->toDateString(),
                'rates' => $r->toRates(),
            ])
            ->all();
    }
}
