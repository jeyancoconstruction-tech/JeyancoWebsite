<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A worker's second or later finger: one sensor slot. The first is employees.fingerprint_id. */
class EmployeeFingerprint extends Model
{
    protected $fillable = ['employee_id', 'slot'];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
