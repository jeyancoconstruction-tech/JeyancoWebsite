{{-- The error pages (404, 403, 500…). Laravel's own error views extend
     errors::minimal; this one replaces it so the page's style carries the
     CSP nonce (SecurityHeaders) and stays styled under the strict policy. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        html, body { height: 100%; margin: 0; }
        body { display: flex; align-items: center; justify-content: center; background: #0b1220; color: #cbd5e1;
               font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .box { display: flex; align-items: center; gap: 18px; }
        .code { font-size: 22px; font-weight: 700; color: #f1f5f9; padding-right: 18px; border-right: 1px solid #334155; }
        .msg { font-size: 15px; letter-spacing: .02em; text-transform: uppercase; }
        a { display: block; margin-top: 6px; color: #60a5fa; font-size: 13px; text-transform: none; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code">@yield('code')</div>
        <div class="msg">@yield('message')<a href="{{ url('/') }}">Back to Jeyanco</a></div>
    </div>
</body>
</html>
