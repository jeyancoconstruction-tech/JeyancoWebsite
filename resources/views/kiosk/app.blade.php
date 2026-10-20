<!DOCTYPE html>
{{-- The attendance kiosk, served from the web (KioskAppController). Its CSS
     and scripts are in public/kiosk-app. Four tabs, by the 2026-10-18 RSC:
     ATTENDANCE, MY DTR, REPORT (complaints) and ENROLL — the SUMMARY tab and
     the payroll card are gone; the kiosk shows attendance and the DTR. --}}
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
    </script>
</head>
<body>

@include('kiosk._screen')

<script src="script.js?v={{ $v }}"></script>
<script src="kiosk-clock.js?v={{ $v }}"></script>
<script src="kiosk-dtr.js?v={{ $v }}"></script>
<script src="kiosk-report.js?v={{ $v }}"></script>
</body>
</html>
