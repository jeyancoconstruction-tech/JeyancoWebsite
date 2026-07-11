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

];
