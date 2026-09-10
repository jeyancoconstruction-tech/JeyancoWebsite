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
     *   active  – timed in today, not yet out (day still in progress)
     *   invalid – timed in but never timed out and the day has ended
     *   absent  – no time-in recorded
     */
    public function getStatusAttribute(): string
    {
        if (empty($this->time_in)) {
            return 'absent';
        }
        if (!empty($this->time_out)) {
            return 'present';
        }
        return Carbon::parse($this->date)->isToday() ? 'active' : 'invalid';
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
