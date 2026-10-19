<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only a kiosk Pi may call this.
 *
 * Guards the API calls that take a fingerprint slot ("this finger was read")
 * or hand out one worker's records. The Pi's fingerprint service sends the
 * shared secret in X-Kiosk-Key on every call; the kiosk screen in the browser
 * never sees it — it reaches these through the Pi.
 *
 * Nothing is checked while config('kiosk.device_key') is empty, so a setup
 * without a key works exactly as before.
 */
class KioskDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('kiosk.device_key');

        if ($key !== '' && ! hash_equals($key, (string) $request->header('X-Kiosk-Key'))) {
            return response()->json([
                'success' => false,
                'code'    => 'device_key',
                'message' => 'Only a registered kiosk can do this. Check JEYANCO_DEVICE_KEY on the Pi.',
            ], 401);
        }

        return $next($request);
    }
}
