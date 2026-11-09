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
    | GPS turned off / no live satellite fix, the attendance is rejected — the
    | last known position is never used (real-time attendance only). A site
    | with no coordinates set is refused until the office sets them. The office
    | can turn the check off in System Settings → Kiosks ("Require live GPS");
    | this flag is the deploy-level master switch.
    */
    'enforce_location' => filter_var(env('KIOSK_ENFORCE_LOCATION', true), FILTER_VALIDATE_BOOL),

    // How old the GPS's last heartbeat may be (seconds) for its fix to count as
    // live at clock time; older and attendance is refused. Falls back to `offline_after`.
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

    /*
    | Workers registered before this day count as done once one finger is on
    | file, so they leave the ENROLL list (2026-11-09: the crew already
    | working was cleared off it). Everyone registered from this day on needs
    | `fingers_required`, as before. Empty: everybody needs the full number.
    */
    'fingers_required_from' => env('KIOSK_FINGERS_REQUIRED_FROM', '2026-11-09'),
    'fingers_max'      => max(1, (int) env('KIOSK_FINGERS_MAX', 3)),

];
