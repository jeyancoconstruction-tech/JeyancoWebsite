<?php

namespace App\Models;

use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shift: when it starts, how much grace it allows, whether it runs past
 * midnight, and — since the working day was written down — its two sessions.
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
        'am_starts_at',
        'am_ends_at',
        'pm_starts_at',
        'pm_ends_at',
        'time_in_opens_minutes',
        'legacy_starts_at',
    ];

    protected $casts = [
        'grace_period_minutes'  => 'integer',
        'crosses_midnight'      => 'boolean',
        'time_in_opens_minutes' => 'integer',
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
     * The shift a worker lands on when nobody has said which.
     *
     * A blank is worse than a wrong guess here: it shows as "0 workers" on the
     * settings card and quietly falls back to the office default at payroll
     * time, so nobody can see who was never assigned. The day crew is the
     * ordinary case, and moving somebody to nights is one dropdown.
     */
    public static function defaultForNewHire(): ?int
    {
        return static::query()
            ->where('crosses_midnight', false)
            ->orderBy('id')
            ->value('id');
    }

    /** The shift a worker's attendance is held to: their own, else the day crew. */
    public static function forEmployee(?Employee $employee): ?self
    {
        return $employee?->shift ?? static::find(static::defaultForNewHire());
    }

    /**
     * Every shift as a plain array, keyed by id.
     *
     * Payroll resolves a shift per attendance record, so they are loaded once
     * as a lookup rather than a query per row.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function lookup(): array
    {
        return static::query()->get()
            ->mapWithKeys(fn (self $s) => [$s->id => $s->schedule()])
            ->all();
    }

    /** This shift in the shape WorkSchedule and payroll read. */
    public function schedule(): array
    {
        return [
            'name'             => $this->name,
            'starts_at'        => (string) $this->starts_at,
            'grace'            => (int) $this->grace_period_minutes,
            'crosses'          => (bool) $this->crosses_midnight,
            'am_starts_at'     => $this->am_starts_at,
            'am_ends_at'       => $this->am_ends_at,
            'pm_starts_at'     => $this->pm_starts_at,
            'pm_ends_at'       => $this->pm_ends_at,
            'opens'            => (int) ($this->time_in_opens_minutes ?? 120),
            'legacy_starts_at' => $this->legacy_starts_at,
        ];
    }

    public function hasSchedule(): bool
    {
        return WorkSchedule::has($this->schedule());
    }

    /** "AM SESSION", or "NIGHT · FIRST HALF" for a crew whose day crosses midnight. */
    public function sessionLabel(string $session): string
    {
        if ($this->crosses_midnight) {
            return 'NIGHT · ' . ($session === 'AM' ? 'FIRST HALF' : 'SECOND HALF');
        }

        return $session . ' SESSION';
    }

    /** When TIME IN is open, written the way the kiosk says it: "6:00 AM", "5:00 PM". */
    public function timeInWindowLabels(Carbon $at): array
    {
        [$open, $close] = WorkSchedule::timeInWindow($this->schedule(), $at);

        return [WorkSchedule::label($open), WorkSchedule::label($close)];
    }
}
