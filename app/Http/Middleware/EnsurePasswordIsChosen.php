<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account whose password the admin set — and asked them to change — opens
 * nothing until they have chosen their own.
 *
 * On the whole web group rather than on each set of routes, so a page added
 * later cannot be reached around it. The page that asks, and signing out,
 * are the only ways through.
 */
class EnsurePasswordIsChosen
{
    /** Where a person who still has to choose may go. */
    private const THROUGH = ['account.password', 'account.password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password || $request->routeIs(...self::THROUGH)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Choose your own password first.'], 403);
        }

        return redirect()->route('account.password');
    }
}
