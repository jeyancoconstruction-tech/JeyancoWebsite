<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\Kiosk;
use App\Support\KioskFeed;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes each scan a kiosk sends, once the web has answered it, in KioskFeed —
 * the live monitor in System Settings reads it from there.
 *
 * It only reads the request and the answer. The answer goes back to the kiosk
 * exactly as the controller wrote it, and a failure in here never reaches the
 * kiosk: a monitor that misses a scan is better than a scan that fails.
 */
class RecordKioskEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($response instanceof JsonResponse) {
                $this->record($request, $response->getData(true));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function record(Request $request, array $data): void
    {
        $kiosk = Kiosk::resolve($request->input('kiosk_id'), $request->input('kiosk_code'));
        if (! $kiosk) {
            return;
        }

        $who  = $data['employee'] ?? null;
        $name = is_array($who) ? ($who['name'] ?? null) : null;
        if (! $name && $request->filled('employee_id')) {
            $name = Employee::whereKey($request->input('employee_id'))->value('name');
        }
        $position = is_array($who) ? ($who['position'] ?? null) : null;

        // Identifying a finger (scan-attendance) records nothing by itself:
        // the kiosk then records it with the button pressed, or turns it away.
        if ($request->is('api/kiosk/scan-attendance')) {
            KioskFeed::push($kiosk, match (true) {
                ! empty($data['not_found']) => ['kind' => 'unknown', 'name' => null, 'message' => 'Fingerprint not recognised'],
                // Turned away at the scan itself — another site's worker, or
                // a kiosk with no site — so the monitor shows it as refused.
                empty($data['success']) && ! empty($data['code']) => [
                    'kind' => 'rej', 'name' => $name, 'position' => $position,
                    'code' => $data['code'], 'message' => $data['message'] ?? null,
                ],
                default => ['kind' => 'scan', 'name' => $name, 'position' => $position],
            });

            return;
        }

        if (! empty($data['success']) && in_array($data['type'] ?? null, ['time_in', 'time_out'], true)) {
            $att = $data['attendance'] ?? [];
            KioskFeed::push($kiosk, [
                'kind'     => $data['type'] === 'time_in' ? 'in' : 'out',
                'name'     => $name,
                'position' => $position,
                'session'  => $data['session'] ?? null,
                'clock'    => $data['type'] === 'time_in' ? ($att['time_in'] ?? null) : ($att['time_out'] ?? null),
                'auto'     => $request->input('type') === 'auto',
            ]);

            return;
        }

        KioskFeed::push($kiosk, [
            'kind'     => in_array($data['code'] ?? '', ['already_in', 'no_open', 'just_timed_in', 'just_timed_out', 'session_done'], true) ? 'warn' : 'rej',
            'name'     => $name,
            'position' => $position,
            'code'     => $data['code'] ?? null,
            'message'  => $data['message'] ?? null,
        ]);
    }
}
