<!DOCTYPE html>
{{-- The kiosk's own screen, run inside the web for the monitor in Device
     Monitoring. The CSS and scripts are the kiosk's (public/kiosk-app) and so
     is the layout (kiosk/_screen); kiosk-monitor.js stands in for the Pi (the
     sensor and the window service) and answers the kiosk's reads from this
     system — so it draws exactly what the device draws, from the same data.
     View only: nothing here records a scan, enrols a finger, opens a DTR or
     sends a complaint. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $kiosk->name }} — Kiosk screen</title>
    <base href="{{ asset('kiosk-app') }}/">
    <link rel="icon" href="JeyancoLogo.png">
    <link rel="stylesheet" href="lib/inter/inter.css">
    <link rel="stylesheet" href="lib/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="kiosk.css?v={{ $v }}">
    <style>
        /* The monitor's own mark, so a photo of it is never taken for the kiosk. */
        .mon-viewonly { position: fixed; right: 8px; bottom: 6px; z-index: 50; font: 800 10px Inter, sans-serif; letter-spacing: .12em;
                        color: #8b98ab; background: rgba(10,14,20,.85); border: 1px solid #2b3644; border-radius: 5px; padding: 3px 7px; pointer-events: none; }
        /* View only: the card's buttons show what the worker sees, and do nothing here. */
        .scan-pop .clock-btn, .scan-pop .sp-cancel, .preset-btn, #reg-start-btn { pointer-events: none; }
    </style>
    <script>
        window.KIOSK_MONITOR = @json(['api' => $api, 'kiosk' => $kiosk->code]);
    </script>
    <script src="{{ asset('js/kiosk-monitor.js') }}?v={{ $v }}"></script>
</head>
<body>

@include('kiosk._screen')

<span class="mon-viewonly">VIEW ONLY · MONITOR</span>

<script src="script.js?v={{ $v }}"></script>
<script src="kiosk-clock.js?v={{ $v }}"></script>
<script src="kiosk-dtr.js?v={{ $v }}"></script>
<script src="kiosk-report.js?v={{ $v }}"></script>
</body>
</html>
