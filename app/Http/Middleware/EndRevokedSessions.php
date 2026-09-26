<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * System Settings → Security → "Sign out all sessions" (2026-09-27).
 *
 * Each session remembers when it began. One that began before the moment an
 * admin signed everybody out ends on its next request, whatever the session
 * store — the database rows are also deleted at the time, but a file or cache
 * store has no such list to clear.
 */
class EndRevokedSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $session = $request->session();

        // A session from before this existed, or one a remember-me cookie
        // has just restored, counts from now. Remember tokens are cleared at
        // the sign-out, so a cookie that still works was issued after it.
        if (! $session->has('signed_in_at')) {
            $session->put('signed_in_at', now()->timestamp);

            return $next($request);
        }

        $revoked = SystemSetting::current()->sessions_revoked_at;

        if ($revoked && (int) $session->get('signed_in_at') < $revoked->timestamp) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Signed out.'], 401);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'An administrator signed everyone out. Please sign in again.',
            ]);
        }

        return $next($request);
    }
}
