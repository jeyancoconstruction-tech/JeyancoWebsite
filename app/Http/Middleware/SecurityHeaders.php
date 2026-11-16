<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (OWASP ZAP baseline scan, 2026-11-16).
 *
 * - X-Frame-Options SAMEORIGIN, not DENY: Device Monitoring shows the kiosk
 *   screen in a frame on the same site.
 * - The Content-Security-Policy is REPORT-ONLY: the browser lists what it
 *   would block without blocking it, so the kiosk (which talks to the
 *   fingerprint service on 127.0.0.1), the maps and Google sign-in keep
 *   working. It is made enforcing once no page reports a violation.
 * - Cross-Origin-Opener / Resource-Policy only on pages (HTML), so images and
 *   files linked from e-mails keep loading.
 * - No Cross-Origin-Embedder-Policy: it would block the map tiles and fonts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }
        $h = $response->headers;
        $h->remove('X-Powered-By');

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if (str_contains((string) $h->get('Content-Type'), 'text/html')) {
            $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
            $h->set('Cross-Origin-Resource-Policy', 'same-origin');
            $h->set('Content-Security-Policy-Report-Only', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self' https://accounts.google.com",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "img-src 'self' data: blob: https:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net",
                "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net",
                "connect-src 'self' http://127.0.0.1:5000 http://localhost:5000 https:",
                "frame-src 'self'",
            ]));
        }

        return $response;
    }
}
