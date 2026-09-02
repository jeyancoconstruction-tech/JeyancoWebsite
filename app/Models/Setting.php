<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The one settings row.
 *
 * Only `sunday_rest_day_enabled` is still written here. The premiums, the
 * period bonus and the contribution rates live on PayrollRate, where a change
 * is dated and cannot rewrite a period already paid; the daily wage belongs to
 * the labour type, which carries its own. Their columns are left in place
 * because the opening rate row was seeded from them, and nothing reads them.
 */
class Setting extends Model
{
    protected $fillable = [
        'daily_rate',
        'ot_multiplier',
        'ot_premium_multiplier',
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
        'ot_premium_multiplier'    => 'float',
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