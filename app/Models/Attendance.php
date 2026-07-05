<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
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
        });
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
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
}