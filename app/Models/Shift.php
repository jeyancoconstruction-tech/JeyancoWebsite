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
        'regular_minutes',
        'break_minutes',
    ];

    protected $casts = [
        'grace_period_minutes'  => 'integer',
        'crosses_midnight'      => 'boolean',
        'time_in_opens_minutes' => 'integer',
        'regular_minutes'       => 'integer',
        'break_minutes'         => 'integer',
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

    /**
     * Lay a shift out from the four times the office actually knows: when the
     * crew arrives, when they break, when they come back, and when they go
     * home.
     *
     * The break used to be a length, dropped into the middle of the stretch.
     * That is a guess, and it was wrong in a way that cost people money: a
     * twelve-hour day put lunch at half past one, so somebody starting at one
     * had an hour taken off a break they had not been there for. Lunch is at
     * noon because the office says it is at noon, not because noon is halfway
     * through.
     *
     * The two break times ARE the inner session boundaries — the gap between
     * the morning and the afternoon is the meal period — so nothing is
     * derived here but the total and the direction.
     *
     * An end at or before the start is the next morning; that is also the
     * only thing that decides whether a shift crosses midnight, because it is
     * the only thing that can.
     *
     * @return array<string, mixed> columns ready to write
     */
    public static function layOut(string $startsAt, string $endsAt, string $breakFrom, string $breakTo): array
    {
        [$start, $end] = self::boundsOf($startsAt, $endsAt);

        // Each boundary follows the one before it, rolling into the next
        // morning when the clock reads earlier — the same way a shift's own
        // hours do.
        $from = self::after($start, $breakFrom);
        $to   = self::after($from, $breakTo);

        return [
            'starts_at'        => $start->format('H:i:s'),
            'am_starts_at'     => $start->format('H:i:s'),
            'am_ends_at'       => $from->format('H:i:s'),
            'pm_starts_at'     => $to->format('H:i:s'),
            'pm_ends_at'       => $end->format('H:i:s'),
            'break_minutes'    => (int) $from->diffInMinutes($to),
            'crosses_midnight' => $end->day !== $start->day,
        ];
    }

    /** The next moment reading $time at or after $after. */
    private static function after(Carbon $after, string $time): Carbon
    {
        $at = $after->copy()->setTimeFromTimeString($time);

        if ($at->lessThan($after)) {
            $at->addDay();
        }

        return $at;
    }

    /**
     * Where the meal period falls inside a shift, as minutes from its start.
     *
     * @return array{0: int, 1: int}
     */
    public static function breakOffsets(string $startsAt, string $breakFrom, string $breakTo): array
    {
        $start = Carbon::parse('2026-01-05 ' . $startsAt);
        $from  = self::after($start, $breakFrom);
        $to    = self::after($from, $breakTo);

        return [(int) $start->diffInMinutes($from), (int) $start->diffInMinutes($to)];
    }

    /** How long a crew is on site, in minutes, break included. */
    public static function spanMinutes(string $startsAt, string $endsAt): int
    {
        [$start, $end] = self::boundsOf($startsAt, $endsAt);

        return (int) $start->diffInMinutes($end);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function boundsOf(string $startsAt, string $endsAt): array
    {
        $start = Carbon::parse('2026-01-05 ' . $startsAt);
        $end   = Carbon::parse('2026-01-05 ' . $endsAt);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /** When the crew goes home — the end of the second session. */
    public function endsAt(): ?string
    {
        return $this->pm_ends_at ? substr((string) $this->pm_ends_at, 0, 5) : null;
    }

    /** When the meal period starts — the gap between the two sessions. */
    public function breakStartsAt(): ?string
    {
        return $this->am_ends_at ? substr((string) $this->am_ends_at, 0, 5) : null;
    }

    /** When the crew is back from it. */
    public function breakEndsAt(): ?string
    {
        return $this->pm_starts_at ? substr((string) $this->pm_starts_at, 0, 5) : null;
    }

    /** The paid hours the daily rate buys, before overtime begins. */
    public function regularHours(): ?float
    {
        return $this->regular_minutes === null ? null : $this->regular_minutes / 60;
    }

    /** Hours on site, break included. */
    public function spanHours(): float
    {
        return self::spanMinutes(substr((string) $this->starts_at, 0, 5), $this->endsAt() ?: '00:00') / 60;
    }

    /** Hours the crew is paid for: the stretch less their own meal period. */
    public function paidHours(): float
    {
        return max(0, self::spanMinutes(substr((string) $this->starts_at, 0, 5), $this->endsAt() ?: '00:00')
                      - (int) $this->break_minutes) / 60;
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
            'regular_minutes'  => $this->regular_minutes,
            'break_minutes'    => (int) $this->break_minutes,
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
