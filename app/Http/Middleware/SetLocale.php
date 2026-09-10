<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language the system speaks, set per request.
 *
 * It was in the service provider first, which is only read once when the
 * application boots — right for a long-lived process, wrong the moment the
 * setting changes underneath it, and untestable because a test boots the app
 * once and then makes many requests against it.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // English, always. The system_settings.locale column is still there
        // and still writable, but nothing reads it any more: the app is
        // English only, and a stored 'tl' must not be able to change that.
        // The hook stays for the day a second language is actually wanted —
        // it just does not take its answer from the database today.
        app()->setLocale('en');

        return $next($request);
    }
}
