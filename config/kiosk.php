<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kiosk anti-theft / heartbeat tuning
    |--------------------------------------------------------------------------
    | The Pi posts a heartbeat every ~30s (SEND_INTERVAL in gps_tracker.py).
    | A kiosk is considered OFFLINE once we haven't heard from it for longer
    | than `offline_after` seconds — set it to a few missed heartbeats so a
    | single dropped packet doesn't trip a false alarm.
    |
    | The geofence anchor ("home") is the first good GPS fix a kiosk reports.
    | If a later fix is more than `geofence_radius` metres from home, we treat
    | it as moved — the core anti-theft signal.
    */

    'offline_after'   => (int) env('KIOSK_OFFLINE_AFTER', 120),   // seconds
    'geofence_radius' => (int) env('KIOSK_GEOFENCE_RADIUS', 150), // metres

    /*
    |--------------------------------------------------------------------------
    | GPS attendance validation (anti-fraud)
    |--------------------------------------------------------------------------
    | When enabled, a fingerprint clock-in/out is accepted only if the kiosk is
    | within `geofence_radius` metres of its assigned site's coordinates (the
    | designated location the admin sets on the dashboard map). If the kiosk has
    | GPS turned off / no fix, the attendance is rejected. A kiosk whose site has
    | no coordinates set is left ungated. Turn this off to disable the check.
    */
    'enforce_location' => filter_var(env('KIOSK_ENFORCE_LOCATION', true), FILTER_VALIDATE_BOOL),

    // How stale a cached GPS fix may be (seconds) before it can no longer prove
    // where the kiosk is at clock time. Falls back to `offline_after`.
    'location_max_age' => (int) env('KIOSK_LOCATION_MAX_AGE', 120),

];
