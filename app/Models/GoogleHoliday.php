<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One official holiday as Google Calendar gave it. Written only by
 * App\Support\GoogleHolidays; read through PhilippineHolidays::forYear().
 */
class GoogleHoliday extends Model
{
    protected $fillable = [
        'date',
        'title',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
