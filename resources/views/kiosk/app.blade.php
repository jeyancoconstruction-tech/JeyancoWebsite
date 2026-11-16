<!DOCTYPE html>
{{-- The attendance kiosk, served from the web (KioskAppController). Its CSS
     and scripts are in public/kiosk-app. Four tabs, by the 2026-10-18 RSC:
     ATTENDANCE, MY DTR, REPORT (complaints) and ENROLL — the SUMMARY tab and
     the payroll card are gone; the kiosk shows attendance and the DTR.
     v15 (2026-10-22): light and dark, the tabs in a dock that hides. --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>Jeyanco Construction — Attendance Kiosk</title>
    <base href="{{ asset('kiosk-app') }}/">
    <link rel="icon" href="JeyancoLogo.png">
    {{-- lib/, not vendor/: Railway answers 404 for any path with /vendor/ in it. --}}
    <link rel="stylesheet" href="lib/inter/inter.css">
    <link rel="stylesheet" href="lib/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="kiosk.css?v={{ $v }}">
    <script>
        // Where the web and the Pi's fingerprint service are, and the server's
        // clock at the moment this page was made.
        window.JEYANCO = @json($config);
        window.JEYANCO_API_BASE   = window.JEYANCO.api;
        window.JEYANCO_FLASK_BASE = window.JEYANCO.flask;

        // Light or dark before the first paint (script.js keeps it after):
        // light 6 AM – 6 PM by the server's clock, unless a tap chose otherwise.
        (function () {
            var c = window.JEYANCO, d = new Date();
            if (typeof c.server_time === 'number') {
                d = new Date(c.server_time + (typeof c.tz_offset === 'number' ? (c.tz_offset + d.getTimezoneOffset()) * 60000 : 0));
            }
            var part = d.getHours() >= 6 && d.getHours() < 18 ? 'light' : 'dark', pick = null;
            try { pick = JSON.parse(localStorage.getItem('jeyanco_theme') || 'null'); } catch (e) {}
            document.documentElement.dataset.theme = pick && pick.part === part ? pick.theme : part;
        })();

        // At power-on Chromium opens as a window and only then goes full
        // screen, so the screen was laid out small and jumped to full size —
        // it looked like the kiosk minimised and maximised. Keep the screen
        // hidden until the window covers the display and has stopped
        // resizing (3 s at most), then show it once. Not inside Device
        // Monitoring's live view (an iframe), which is never full screen.
        (function () {
            if (window.top !== window.self) return;
            var root = document.documentElement, done = false, quiet = null;
            var full = function () { return window.innerWidth >= screen.width - 2 && window.innerHeight >= screen.height - 2; };
            var show = function () { if (done) return; done = true; root.classList.remove('k-settling'); window.removeEventListener('resize', settle); };
            var settle = function () { clearTimeout(quiet); if (full()) quiet = setTimeout(show, 350); };
            root.classList.add('k-settling');
            window.addEventListener('resize', settle);
            window.addEventListener('load', settle);
            settle();
            setTimeout(show, 3000);
        })();
    </script>
    <style>
        html.k-settling body > * { opacity: 0; }
        body > * { transition: opacity .25s ease; }
    </style>
</head>
<body>

@include('kiosk._screen')

<script src="script.js?v={{ $v }}"></script>
<script src="kiosk-clock.js?v={{ $v }}"></script>
<script src="kiosk-dtr.js?v={{ $v }}"></script>
<script src="kiosk-report.js?v={{ $v }}"></script>
</body>
</html>
