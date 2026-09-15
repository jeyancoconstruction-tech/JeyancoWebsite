<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Kiosk extends Model
{
    /** Cache key prefix for when a kiosk last read its settings from the web. */
    public const SETTINGS_READ_KEY = 'kiosk_settings_read_';

    protected $fillable = ['name', 'code', 'site_id', 'location', 'is_active', 'last_seen_at'];

    /** When this kiosk last asked the web for its settings, if it ever has. */
    public function settingsReadAt(): ?Carbon
    {
        $at = Cache::get(self::SETTINGS_READ_KEY . $this->code);

        return $at ? Carbon::parse($at) : null;
    }

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
