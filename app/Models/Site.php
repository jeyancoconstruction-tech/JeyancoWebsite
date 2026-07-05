<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = ['name', 'location', 'latitude', 'longitude'];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function kiosks()
    {
        return $this->hasMany(Kiosk::class);
    }
}
