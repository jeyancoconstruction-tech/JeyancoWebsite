<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_id',
        'site_id',
        'kiosk_id',
        'date',
        'session',
        'time_in',
        'time_out',
        'vale',
        'deductions',
        'rest_day_applied',
        'close_type',
        'needs_review',
        'close_reason',
        'reviewed_by',
        'reviewed_at',
        'updated_at',
        'created_at'
    ];

    protected $casts = [
        'vale' => 'float',
        'deductions' => 'float',
        'rest_day_applied' => 'boolean',
        'needs_review' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    /** A day left open this long past its shift's end was forgotten, not still being worked. */
    public const STALE_AFTER_HOURS = 6;

    // 🔥 AUTO FIX: kapag walang session, maglalagay siya automatically
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attendance) {
            if (empty($attendance->session)) {
                $attendance->session = now()->format('H') < 12 ? 'AM' : 'PM';
            }

            // The shift the day was worked under, taken once and kept. Stamped
            // here rather than at each clock-in so no path — the kiosk, the
            // office screen, an import — can leave it off. Reading the
            // employee's shift at payroll time instead would mean moving
            // somebody to the night crew changed how late they were last month.
            if (empty($attendance->shift_id) && $attendance->employee_id) {
                $attendance->shift_id = Employee::whereKey($attendance->employee_id)->value('shift_id');
            }
        });
    }

    /** The shift this day was worked under, not the worker's shift today. */
    public function shift() {
        return $this->belongsTo(Shift::class);
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    /** Where this clock was taken — the kiosk's active site, not the worker's home site. */
    public function site() {
        return $this->belongsTo(Site::class);
    }

    public function kiosk() {
        return $this->belongsTo(Kiosk::class);
    }

    /**
     * Computed attendance status (no DB column — derived automatically):
     *   present – timed in AND out (complete record)
     *   active  – timed in, not yet out, and the sign-out is not due yet
     *   invalid – the shift is over and nobody clocked out
     *   absent  – no time-in recorded
     *
     * A missed sign-out is a question about the shift, not about the date. It
     * is answered by one clock: the end of the shift this day was worked
     * under, plus an hour to walk off site. Before that the worker may still
     * be finishing; after it, nobody is coming back to press the button.
     */
    public function getStatusAttribute(): string
    {
        if (empty($this->time_in)) {
            return 'absent';
        }
        if (!empty($this->time_out)) {
            return 'present';
        }
        return $this->signOutOverdue() ? 'invalid' : 'active';
    }

    /** How long after the shift ends a sign-out is still expected. */
    public const SIGN_OUT_GRACE_HOURS = 1;

    /**
     * When this row's sign-out stops being expected and starts being missing:
     * the end of its shift's day, plus the grace above.
     *
     * Null when the shift it was worked under has no schedule on file — every
     * row from before the working day was written down. Those keep the only
     * rule they ever had.
     */
    public function signOutDueBy(): ?Carbon
    {
        $schedule = $this->shift?->schedule();

        if (! \App\Support\WorkSchedule::has($schedule)) {
            return null;
        }

        $day = Carbon::parse($this->date)->toDateString();

        return \App\Support\WorkSchedule::windows($schedule, $day)['PM'][1]
            ->addHours(self::SIGN_OUT_GRACE_HOURS);
    }

    /** Timed in, never timed out, and the shift is well over. */
    public function signOutOverdue(?Carbon $now = null): bool
    {
        if (empty($this->time_in) || ! empty($this->time_out)) {
            return false;
        }

        $now = $now ?? Carbon::now();
        $due = $this->signOutDueBy();

        return $due === null
            ? ! Carbon::parse($this->date)->isSameDay($now)
            : $now->greaterThan($due);
    }

    /**
     * How many calendar days after the time-in the time-out landed: 1 for a
     * night shift that ends the following morning, 0 for an ordinary day.
     *
     * The tables print clock times only, so without this "8:00 PM – 7:00 AM"
     * reads as a day run backwards rather than a shift that crossed midnight.
     */
    public function getOutDaysLaterAttribute(): int
    {
        if (empty($this->time_in) || empty($this->time_out)) {
            return 0;
        }

        [$in, $out] = \App\Support\WorkSchedule::stretch($this->time_in, $this->time_out, (string) $this->date);

        return (int) $in->copy()->startOfDay()->diffInDays($out->copy()->startOfDay());
    }

    /**
     * Rows falling on one day of the week, in Carbon's numbering (0 = Sunday).
     *
     * There is no portable SQL for this. MySQL has DAYOFWEEK, counting Sunday
     * as 1; SQLite has strftime('%w'), counting it as 0; and the suite runs on
     * SQLite while production runs on MySQL. Written as raw DAYOFWEEK at the
     * call site it threw on every test run — and passed anyway, because the
     * assertions there did not look at the response status.
     */
    public function scopeOnDayOfWeek(Builder $query, int $dayOfWeek): Builder
    {
        $expression = $query->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%w', date) AS INTEGER)"
            : '(DAYOFWEEK(date) - 1)';

        return $query->whereRaw("{$expression} = ?", [$dayOfWeek]);
    }

    /**
     * The workday each shift is on at $now — shift_id => 'Y-m-d', with the
     * plain calendar date under key 0 for rows worked under no shift. It is
     * the line the day view and the history divide on, and the one every
     * figure headed "today" counts by.
     *
     * Both crews work one workday; they just do not agree on when it is. The
     * day shift's 11 AM and the night shift's 2 AM belong to the same date on
     * the payroll, because a shift's day is the date it started on. Attendance
     * is already filed that way — the kiosk stamps `date` from the shift, and
     * payroll counts by it — so asking the calendar instead is what emptied
     * the board at midnight on exactly the crew still working.
     *
     * Usually that is the workday the shift is on. Once that day has run its
     * course it is the next one, which is what moves a finished day off the
     * day view: the night crew's shift ends at 5 AM, but their next workday
     * does not open until 6 PM, so between those hours shiftDayFor() still
     * answers "last night" — correctly, it is the day a clock now would
     * belong to. Used as the boundary it kept last night's finished rows
     * sitting on Today's Attendance all through the following day, beside
     * the day crew's, under a heading naming a date neither of them shared.
     */
    private static function boundariesAt(Carbon $now): array
    {
        $bounds = [0 => $now->toDateString()];

        foreach (Shift::lookup() as $id => $schedule) {
            if (! \App\Support\WorkSchedule::has($schedule)) {
                $bounds[$id] = $now->toDateString();
                continue;
            }

            $day  = \App\Support\WorkSchedule::shiftDayFor($schedule, $now);
            $ends = \App\Support\WorkSchedule::windows($schedule, $day)['PM'][1];

            $bounds[$id] = $now->greaterThanOrEqualTo($ends)
                ? Carbon::parse($day)->addDay()->toDateString()
                : $day;
        }

        return $bounds;
    }

    /**
     * Rows on the workday their own shift is on right now, by the line the
     * day view draws — so every "today" figure counts what Today's
     * Attendance lists.
     *
     * It used to match the day a clock would land on instead. From the end
     * of the night shift until the next one opened in the evening, that kept
     * the crew's finished night as "today" on the dashboard, in analytics and
     * in the assistant, while the Attendance page had already moved it to
     * the history.
     */
    public function scopeOnWorkday(Builder $query, ?Carbon $now = null): Builder
    {
        return self::matchWorkday($query, self::boundariesAt($now ?? Carbon::now()), '=');
    }

    /** Rows from a workday that has already finished — the history. */
    public function scopeBeforeWorkday(Builder $query, ?Carbon $now = null): Builder
    {
        return self::matchWorkday($query, self::boundariesAt($now ?? Carbon::now()), '<');
    }

    /**
     * Rows whose workday has not finished: the current one, and anything dated
     * past it.
     *
     * The day view uses this rather than an exact match so that it and the
     * history together account for every row. The two crews are on different
     * workdays at the same moment — at 2 PM the day shift is working today
     * while the night shift's day is still yesterday's, its next one not open
     * until evening — so a night row dated today is, at 2 PM, ahead of its own
     * shift's workday. Matched exactly it belonged to neither table and simply
     * stopped being shown.
     */
    public function scopeFromWorkday(Builder $query, ?Carbon $now = null): Builder
    {
        return self::matchWorkday($query, self::boundariesAt($now ?? Carbon::now()), '>=');
    }

    /**
     * Compare each row's date against the workday its own shift is on.
     *
     * The fallback arm matters: a row must land on exactly one side of now, so
     * anything pointing at a shift this map does not know — an id left behind
     * by a deleted shift — is measured against the calendar rather than
     * dropping out of both the day view and the history.
     */
    private static function matchWorkday(Builder $query, array $days, string $operator): Builder
    {
        $known = array_values(array_diff(array_keys($days), [0]));

        return $query->where(function (Builder $group) use ($days, $known, $operator) {
            foreach ($days as $shiftId => $day) {
                $group->orWhere(function (Builder $q) use ($shiftId, $day, $operator) {
                    $shiftId === 0 ? $q->whereNull('shift_id') : $q->where('shift_id', $shiftId);
                    $q->where('date', $operator, $day);
                });
            }

            $group->orWhere(fn (Builder $q) => $q->whereNotNull('shift_id')
                ->whereNotIn('shift_id', $known)
                ->where('date', $operator, $days[0]));
        });
    }

    /**
     * The last workday each shift has whose sign-out deadline has passed —
     * shift_id => 'Y-m-d'. Anything on or before it that is still open was
     * never closed; anything after it may yet be.
     */
    private static function overdueThroughAt(Carbon $now): array
    {
        $yesterday = $now->copy()->subDay()->toDateString();
        $through   = [0 => $yesterday];

        foreach (Shift::lookup() as $id => $schedule) {
            if (! \App\Support\WorkSchedule::has($schedule)) {
                $through[$id] = $yesterday;
                continue;
            }

            $day = \App\Support\WorkSchedule::shiftDayFor($schedule, $now);
            $due = \App\Support\WorkSchedule::windows($schedule, $day)['PM'][1]
                       ->addHours(self::SIGN_OUT_GRACE_HOURS);

            $through[$id] = $now->greaterThan($due)
                ? $day
                : Carbon::parse($day)->subDay()->toDateString();
        }

        return $through;
    }

    /**
     * Everything the office still has to resolve: a day left open past the
     * end of its shift, and a day the system had to close because nobody did.
     *
     * The two are one queue. Counting only the open ones meant a missed
     * sign-out stopped being reported the moment closeStale tidied it away,
     * six hours later, and it went back to reading as an ordinary day.
     */
    public function scopeMissedSignOut(Builder $query, ?Carbon $now = null): Builder
    {
        $through = self::overdueThroughAt($now ?? Carbon::now());

        return $query->where(function (Builder $group) use ($through) {
            $group->where(function (Builder $open) use ($through) {
                $open->whereNotNull('time_in')->whereNull('time_out');
                self::matchWorkday($open, $through, '<=');
            })->orWhere('needs_review', true);
        });
    }

    /**
     * Rows belonging to somebody who has finished registering.
     *
     * A pending name cannot clock any more, but rows recorded before that
     * rule existed are still on file and must not be counted as attendance
     * or paid for.
     *
     * whereDoesntHave rather than whereHas, so that a row whose employee was
     * deleted outright stays visible. That row is history: showing it against
     * a name the directory no longer holds is honest, and dropping it out of
     * the totals silently is not.
     */
    public function scopeOfRegistered(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'employee',
            fn ($q) => $q->withTrashed()->where('status', Employee::STATUS_PENDING)
        );
    }

    /** Is this row's day the one its shift is working right now? */
    public function isOnCurrentWorkday(?Carbon $now = null): bool
    {
        $now      = $now ?? Carbon::now();
        $schedule = $this->shift?->schedule();

        $current = \App\Support\WorkSchedule::has($schedule)
            ? \App\Support\WorkSchedule::shiftDayFor($schedule, $now)
            : $now->toDateString();

        return Carbon::parse($this->date)->toDateString() === $current;
    }

    /**
     * Regular minutes this worker's other records of the same workday have
     * already taken out of what the day's rate buys.
     *
     * A day arrives as several records — a morning, an afternoon after
     * lunch, a stretch begun again after a mistaken time-out — and its
     * regular hours are bought once between them. A stretch therefore has to
     * know what the ones before it spent, or a crew that clocks out for
     * lunch collects a day of regular hours twice over.
     */
    public static function regularMinutesUsed(int $employeeId, string $shiftDay, array $schedule, ?int $exceptId = null): int
    {
        $used = 0;

        $rows = static::where('employee_id', $employeeId)
            ->whereDate('date', $shiftDay)
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->orderBy('time_in')
            ->get();

        foreach ($rows as $row) {
            [$in, $out] = \App\Support\WorkSchedule::stretch($row->time_in, $row->time_out, $shiftDay);

            $used += (int) round(
                \App\Support\WorkSchedule::split($schedule, $in, $out, $shiftDay, $used)['regular'] * 60
            );
        }

        return $used;
    }

    /** A day left open longer than this is a broken record, not a running shift. */
    private const OPEN_ROW_HOURS = 18;

    /**
     * The day this worker currently has open, if any.
     *
     * Attendance used to be filed by calendar date and by half of the day, and
     * found again the same way. That only holds for a stretch that begins and
     * ends in the same half of the same date — which a real shift usually does
     * not. A 6am day shift ending at 3pm crosses noon; a 10pm night shift
     * crosses midnight into both the next date and the other half. In each case
     * the clock pointed at an empty slot and the worker was told they had never
     * timed in, so neither shift could close its own day.
     *
     * What matters is not what the clock reads but whether this worker has a
     * day still open, so that is what is looked for. The window keeps it from
     * reaching back to a day somebody forgot to close: an unclosed shift is for
     * the office to fix, not something to settle at the wrong hour days later.
     */
    public static function openRow(int $employeeId, Carbon $now): ?self
    {
        return static::where('employee_id', $employeeId)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->where('time_in', '>=', $now->copy()->subHours(self::OPEN_ROW_HOURS))
            ->latest('time_in')
            ->first();
    }

    /**
     * Close a stretch nobody closed, at the end of its session, and flag it.
     *
     * The time is a guess — nobody knows when the worker actually left — so it
     * is never later than the session's own end (no overtime from a guess) and
     * the row waits in "needs review" for the office to confirm or correct.
     */
    public function autoClose(Carbon $at, string $reason): void
    {
        $in = \App\Support\WorkSchedule::moment($this->time_in, (string) $this->date);

        $this->forceFill([
            'time_out'     => ($at->lessThan($in) ? $in : $at)->format('Y-m-d H:i:s'),
            'close_type'   => 'auto',
            'needs_review' => true,
            'close_reason' => $reason,
        ])->save();
    }

    /**
     * Close every stretch left open well past its shift's end.
     *
     * There is no scheduler on this deployment, so this runs where attendance
     * is read or written — a scan, the board, payroll — which is often enough
     * for nobody to see a worker "on site" at midnight who left at ten.
     * Only rows worked under a shift with a schedule are touched; anything
     * older stays for the office, as before.
     */
    public static function closeStale(?int $employeeId, Carbon $now): int
    {
        $rows = static::with('shift')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->where('time_in', '>=', $now->copy()->subDays(7))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->get();

        $closed = 0;

        foreach ($rows as $row) {
            $s = $row->shift?->schedule();
            if (! \App\Support\WorkSchedule::has($s)) {
                continue;
            }

            $in      = \App\Support\WorkSchedule::moment($row->time_in, (string) $row->date);
            $day     = \App\Support\WorkSchedule::shiftDayFor($s, $in);
            $dayEnds = \App\Support\WorkSchedule::windows($s, $day)['PM'][1];

            if ($now->lessThan($dayEnds->copy()->addHours(self::STALE_AFTER_HOURS))) {
                continue;
            }

            $session = in_array($row->session, ['AM', 'PM'], true)
                ? $row->session
                : \App\Support\WorkSchedule::sessionAt($s, $in);

            $row->autoClose(
                \App\Support\WorkSchedule::sessionEnd($s, $session, $day),
                'No time-out — closed at the end of the session'
            );
            $closed++;
        }

        return $closed;
    }
}
