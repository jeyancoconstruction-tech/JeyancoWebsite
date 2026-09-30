<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the modules added alongside it: `module:payslips`.
 *
 * Applied only to the new routes. The existing 'is_admin' middleware is left
 * exactly where it is and is not replaced by this one.
 */
class HasModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (Modules::allows($request->user(), $module)) {
            return $next($request);
        }

        // Written to the Audit Log, like a refusal at the admin gate.
        if ($request->user()) {
            AuditLog::record('Security', 'denied',
                $request->user()->name . ' was refused ' . $request->method() . ' /' . $request->path() . " (no access to {$module}).");
        }

        abort(403, 'You do not have access to this module.');
    }
}

