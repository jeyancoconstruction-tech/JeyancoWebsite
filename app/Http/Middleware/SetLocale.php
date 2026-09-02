<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        try {
            // The table does not exist on a fresh checkout until migrate runs,
            // and a failure here would stop the app booting far enough to run
            // the migration that would fix it.
            if (Schema::hasTable('system_settings')) {
                app()->setLocale(SystemSetting::current()->locale ?: 'en');
            }
        } catch (Throwable) {
            // No database yet. English is what the templates are written in.
        }

        return $next($request);
    }
}
