<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'daily_rate',
        'ot_multiplier',
        'holiday_multiplier',
        'bonus',
        'sunday_rest_day_enabled',
        'sss',
        'philhealth',
        'pagibig',
    ];

    protected $casts = [
        'daily_rate'               => 'float',
        'ot_multiplier'            => 'float',
        'holiday_multiplier'       => 'float',
        'bonus'                    => 'float',
        'sunday_rest_day_enabled'  => 'boolean',
        'sss'                      => 'float',
        'philhealth'               => 'float',
        'pagibig'                  => 'float',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
    ];

    public static function getSettings()
    {
        return self::first();
    }
}