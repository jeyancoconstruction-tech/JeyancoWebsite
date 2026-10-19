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

    /*
    |--------------------------------------------------------------------------
    | Real-time attendance only (2026-10-18 RSC)
    |--------------------------------------------------------------------------
    | `device_key` is a secret shared by the web and every Pi (JEYANCO_DEVICE_KEY
    | in the Pi's kiosk.env). When it is set:
    |
    |   • only a Pi can say "this finger was just read" — the endpoints that
    |     take a fingerprint slot or hand out a worker's records answer 401
    |     without the X-Kiosk-Key header (App\Http\Middleware\KioskDevice);
    |   • every scan the Pi reports is answered with a one-use scan token, and
    |     POST /api/kiosk/attendance records nothing without a token for that
    |     same worker, read within `scan_token_ttl` seconds.
    |
    | So attendance can only be the scan of a real finger, now, at a kiosk —
    | never a request typed by hand with somebody's employee id. The time is
    | always the server's (Carbon::now()), never the device's.
    |
    | Left empty, everything works as before (local testing, the test suite).
    */
    'device_key'     => (string) env('KIOSK_DEVICE_KEY', ''),
    'scan_token_ttl' => (int) env('KIOSK_SCAN_TOKEN_TTL', 180),   // seconds

    /*
    | Fingers per worker. The kiosk keeps a worker on its ENROLL list until
    | `fingers_required` are on file, and takes no more than `fingers_max` —
    | a cut or bandaged finger must never lock somebody out.
    */
    'fingers_required' => max(1, (int) env('KIOSK_FINGERS_REQUIRED', 2)),
    'fingers_max'      => max(1, (int) env('KIOSK_FINGERS_MAX', 3)),

];
