<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shift: when it starts, how much grace it allows, and whether it runs past
 * midnight.
 *
 * A worker belongs to one. An attendance record keeps the one it was worked
 * under, so moving somebody to the night crew changes what they work next —
 * not how late they were last month.
 */
class Shift extends Model
{
    protected $fillable = [
        'name',
        'starts_at',
        'grace_period_minutes',
        'crosses_midnight',
    ];

    protected $casts = [
        'grace_period_minutes' => 'integer',
        'crosses_midnight'     => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Every shift as a plain array, keyed by id.
     *
     * Payroll resolves a shift per attendance record, so they are loaded once
     * as a lookup rather than a query per row.
     *
     * @return array<int, array{name: string, starts_at: string, grace: int, crosses: bool}>
     */
    public static function lookup(): array
    {
        return static::query()->get()
            ->mapWithKeys(fn (self $s) => [$s->id => [
                'name'      => $s->name,
                'starts_at' => (string) $s->starts_at,
                'grace'     => (int) $s->grace_period_minutes,
                'crosses'   => (bool) $s->crosses_midnight,
            ]])
            ->all();
    }
}
