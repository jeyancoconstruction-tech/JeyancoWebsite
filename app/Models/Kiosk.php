<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kiosk extends Model
{
    protected $fillable = ['name', 'code', 'site_id', 'location', 'is_active', 'last_seen_at'];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Resolve a kiosk from a request that may send either a numeric kiosk_id or
     * a string kiosk_code. Falls back to the Site A kiosk so the current
     * single-kiosk deployment "just works" without the Pi sending an id.
     */
    public static function resolve($id = null, $code = null): ?self
    {
        if ($id) {
            $kiosk = static::find($id);
            if ($kiosk) return $kiosk;
        }
        if ($code) {
            $kiosk = static::where('code', $code)->first();
            if ($kiosk) return $kiosk;
        }
        return static::where('code', 'SITE_A')->first() ?? static::query()->orderBy('id')->first();
    }
}
