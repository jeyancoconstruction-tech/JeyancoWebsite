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
     * a string kiosk_code.
     *
     * The fallback only applies when the caller identified nothing at all, so a
     * single-kiosk deployment still "just works" without the Pi sending an id.
     * It deliberately does NOT apply to an id or code that simply did not match:
     * that used to quietly resolve "SITE_B" to the Site A kiosk, filing every
     * scan taken at another site under Site A with no error anywhere.
     */
    public static function resolve($id = null, $code = null): ?self
    {
        if ($id) {
            return static::find($id);
        }
        if ($code) {
            return static::where('code', $code)->first();
        }
        return static::where('code', 'SITE_A')->first() ?? static::query()->orderBy('id')->first();
    }
}
