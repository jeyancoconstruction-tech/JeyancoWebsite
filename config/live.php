<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live updates
    |--------------------------------------------------------------------------
    | Pages keep themselves up to date over one open connection per tab
    | (Server-Sent Events). The server writes a line down that connection the
    | moment something changes — attendance filed at the kiosk, an employee
    | registered, a cash advance paid — and the page fetches only the part of
    | itself that the change touched.
    |
    | An open connection holds a PHP worker for as long as it lasts, and this
    | app runs on a small container. So a stream is short-lived and reconnects
    | (the browser does that itself), and the number of streams open at once is
    | capped: past the cap, a tab is told to ask for the revision list every
    | few seconds instead — one short question, and still no page reloads.
    */

    // Turn the stream off to put every tab on the revision check instead.
    'stream' => filter_var(env('LIVE_STREAM', true), FILTER_VALIDATE_BOOL),

    // How long one stream lives before it closes and the browser opens the
    // next. Keep it under any proxy's idle timeout (Railway's is 60s).
    'seconds' => (int) env('LIVE_STREAM_SECONDS', 30),

    // How often the stream looks for changes. One small query per look.
    'tick_ms' => (int) env('LIVE_TICK_MS', 1000),

    // How many streams may be open at once, across everybody. Leave room for
    // ordinary page loads: a container serving four workers should not spend
    // all four holding streams open.
    'max_streams' => (int) env('LIVE_MAX_STREAMS', 4),

    // How often a tab without a stream asks what has changed.
    'poll_ms' => (int) env('LIVE_POLL_MS', 8000),

];
