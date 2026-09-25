<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    /** The on-site radius the Sites page offers, in metres. */
    public const RADIUS_MIN = 50;
    public const RADIUS_MAX = 500;

    protected $fillable = ['name', 'location', 'latitude', 'longitude', 'geofence_radius'];

    protected $casts = [
        'latitude'        => 'float',
        'longitude'       => 'float',
        'geofence_radius' => 'integer',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function kiosks()
    {
        return $this->hasMany(Kiosk::class);
    }

    /** Whether the site has a pin the GPS check can measure against. */
    public function isPinned(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * How far from the pin still counts as on-site, in metres. A site never
     * given its own radius uses the office-wide one, which is what every site
     * used before they could differ.
     */
    public function geofenceRadius(): int
    {
        return $this->geofence_radius ?: (int) config('kiosk.geofence_radius');
    }

    /** Great-circle metres from the pin to a point, or null with no pin. */
    public function metresFrom(float $lat, float $lng): ?float
    {
        if (! $this->isPinned()) {
            return null;
        }

        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return 6_371_000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Whether a point is inside this site's range. */
    public function holds(float $lat, float $lng): bool
    {
        $m = $this->metresFrom($lat, $lng);

        return $m !== null && $m <= $this->geofenceRadius();
    }
}
