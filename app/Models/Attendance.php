<?php

namespace App\Models;

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
        'updated_at',
        'created_at'
    ];

    protected $casts = [
        'vale' => 'float',
        'deductions' => 'float',
        'rest_day_applied' => 'boolean',
    ];

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
}
