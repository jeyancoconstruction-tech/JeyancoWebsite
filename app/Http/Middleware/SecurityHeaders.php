<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (OWASP ZAP baseline scan, 2026-11-16).
 *
 * - X-Frame-Options SAMEORIGIN, not DENY: Device Monitoring shows the kiosk
 *   screen in a frame on the same site.
 * - Content-Security-Policy, two strengths:
 *   - The public pages (sign-in, forgot / reset password, error pages and
 *     redirects) get a strict, ENFORCED policy: only the site's own files,
 *     and inline <script>/<style> only with this request's nonce
 *     ($cspNonce, shared with every view). No unsafe-inline, no unsafe-eval,
 *     no wildcard.
 *   - The signed-in pages and the kiosk keep a broader policy in REPORT-ONLY
 *     mode: they load maps, charts and CDN files and the kiosk talks to the
 *     fingerprint service on 127.0.0.1, so it is checked before enforcing.
 * - Cross-Origin-Opener / Resource-Policy only on pages (HTML), so images and
 *   files linked from e-mails keep loading.
 * - No Cross-Origin-Embedder-Policy: it would block the map tiles and fonts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

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

        if (! str_contains((string) $h->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');

        $kiosk  = $request->is('kiosk', 'kiosk/*', 'kiosk-app/*', 'device-monitoring/*/screen');
        $public = ! $kiosk && (
            $request->is('/', 'login', 'forgot-password', 'forgot-password/*', 'reset-password', 'reset-password/*')
            || $response->getStatusCode() >= 300
        );

        if ($public) {
            $h->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "style-src 'self' 'nonce-{$nonce}'",
                "style-src-attr 'unsafe-inline'",
                "script-src 'self' 'nonce-{$nonce}'",
                "connect-src 'self'",
            ]));

            return $response;
        }

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

        return $response;
    }
}
