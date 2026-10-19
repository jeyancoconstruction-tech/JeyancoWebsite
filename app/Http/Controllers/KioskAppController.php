<?php

namespace App\Http\Controllers;

use App\Support\KioskScan;

/**
 * The kiosk screen, served from the web (RSC 2026-10-18: host the kiosk
 * online). Its files are in public/kiosk-app; this page is their layout.
 *
 * The Pi's Chromium opens https://<site>/kiosk. The page reads the web for
 * everything and the Pi's fingerprint service (http://127.0.0.1:5000, on the
 * Pi itself) for the sensor. It carries the server's clock from the first
 * paint, so the kiosk never shows the Pi's own time.
 */
class KioskAppController extends Controller
{
    /**
     * Which build of the web this is. Every kiosk settings answer carries it;
     * a kiosk opened on an older build reloads itself when it is idle, so a
     * deploy reaches every Pi without anybody touching it.
     */
    public static function build(): string
    {
        $id = (string) env('RAILWAY_DEPLOYMENT_ID', '');
        if ($id !== '') {
            return substr($id, 0, 12);
        }

        $files = array_merge(glob(public_path('kiosk-app/*.js')) ?: [], glob(public_path('kiosk-app/*.css')) ?: []);

        return (string) ($files ? max(array_map('filemtime', $files)) : 1);
    }

    public function show()
    {
        // Not GLOB_BRACE: it does not exist on every server's C library.
        $files = array_merge(glob(public_path('kiosk-app/*.js')) ?: [], glob(public_path('kiosk-app/*.css')) ?: []);

        return response()
            ->view('kiosk.app', [
                // The deploy, so the browser fetches new scripts after one.
                'v'      => self::build(),
                'config' => [
                    'api'         => url('/api/kiosk'),
                    // Overridable per device: /kiosk?flask=http://127.0.0.1:5001
                    'flask'       => $this->flaskBase(),
                    'server_time' => now()->getTimestampMs(),
                    'tz_offset'   => now()->utcOffset(),
                    'secured'     => KioskScan::enforced(),
                    'fingers'     => (int) config('kiosk.fingers_required', 2),
                    'build'       => self::build(),
                ],
            ])
            // Always the newest screen after a deploy.
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /** Only a loopback address: the page must never be pointed at another machine. */
    private function flaskBase(): string
    {
        $asked = (string) request()->query('flask', '');

        return preg_match('#^http://(127\.0\.0\.1|localhost):\d{2,5}$#', $asked) ? $asked : 'http://127.0.0.1:5000';
    }
}
