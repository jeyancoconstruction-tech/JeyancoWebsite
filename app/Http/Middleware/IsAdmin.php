<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated and is admin
        if (auth()->check() && auth()->user()->isAdmin()) {
            return $next($request);
        }

        // A signed-in account reaching past its role is written to the Audit
        // Log, so the office can see who tried what (2026-09-30).
        if (auth()->check()) {
            AuditLog::record('Security', 'denied',
                auth()->user()->name . ' was refused ' . $request->method() . ' /' . $request->path() . ' (administrators only).');
        }

        abort(403, 'Unauthorized. Admin access required.');
    }
}
