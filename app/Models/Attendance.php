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

    /**
     * The row a time-out belongs to.
     *
     * A day worker clocks out in the same session they clocked into, so the
     * wall clock finds their row. A night worker does not: they clock in at
     * 10pm on one date in the PM session and out at 6am on the next date in
     * the AM session, so the row the clock points at is always empty and the
     * one they actually opened is never looked at.
     *
     * The current session is still tried first — nothing about a day changes.
     * Only when it holds no open row does this reach back for one, and only
     * for a shift meant to cross midnight and only while that shift could
     * plausibly still be running, so a day worker who forgot to clock out
     * yesterday is not silently closed off today.
     */
    public static function openForTimeOut(int $employeeId, Carbon $now): ?self
    {
        $current = static::where('employee_id', $employeeId)
            ->where('date', $now->format('Y-m-d'))
            ->where('session', $now->format('H') < 12 ? 'AM' : 'PM')
            ->first();

        if ($current && $current->time_in && !$current->time_out) {
            return $current;
        }

        $overnight = static::where('employee_id', $employeeId)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->where('time_in', '>=', $now->copy()->subHours(18))
            ->whereHas('shift', fn ($q) => $q->where('crosses_midnight', true))
            ->latest('time_in')
            ->first();

        // Falling back to the current session keeps every existing refusal
        // ("already timed out", "cannot time out before timing in") intact.
        return $overnight ?: $current;
    }
}
