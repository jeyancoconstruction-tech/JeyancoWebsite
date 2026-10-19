<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Kiosk;
use App\Models\User;
use App\Notifications\KioskAlert;
use App\Services\DtrService;
use App\Support\KioskScan;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * What a worker opens at the kiosk with their own finger (RSC 2026-10-18):
 *
 *   MY DTR   — their Daily Time Record for this week or last, the same
 *              figures as Payroll Reports › Employees DTR (DtrService).
 *   REPORT   — a complaint for the office, and the office's answers.
 *
 * Both are addressed by a scan token (App\Support\KioskScan), never by an
 * employee id or a fingerprint slot: only somebody whose finger was read at
 * the kiosk a moment ago sees anything, and only their own.
 */
class KioskPortalController extends Controller
{
    /** GET /api/kiosk/my-dtr?scan_token=…&week=0|1 */
    public function dtr(Request $request, DtrService $dtr): JsonResponse
    {
        $employee = KioskScan::employee($request->query('scan_token'));
        if (! $employee) {
            return $this->stale();
        }

        $week = (int) $request->query('week', 0) === 1 ? 1 : 0;
        $now  = Carbon::now('Asia/Manila');
        $from = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($week)->startOfDay();
        $to   = $from->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        try {
            $sheet = $dtr->range($from->toDateString(), $to->toDateString())[$employee->id] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Kiosk DTR failed — ' . $e->getMessage());

            return response()->json(['success' => false, 'code' => 'dtr_failed',
                'message' => 'The DTR could not be read right now. Please try again, or ask the office.']);
        }

        $dur   = fn ($m) => $m === null ? null : ($m > 0 ? WorkSchedule::duration($m) : '—');
        $today = $now->toDateString();

        $days = collect($sheet['days'] ?? [])->map(function (array $d) use ($dur, $today) {
            $on = Carbon::parse($d['date']);

            return [
                'date'   => $d['date'],
                'day'    => $on->format('D'),
                'label'  => $on->format('M j'),
                'today'  => $d['date'] === $today,
                'future' => $d['date'] > $today,
                'in1'    => $d['in1'] ?? null,
                'out1'   => $d['out1'] ?? null,
                'in2'    => $d['in2'] ?? null,
                'out2'   => $d['out2'] ?? null,
                'miss'   => array_values($d['miss'] ?? []),
                'worked' => $dur($d['worked'] ?? null),
                'late'   => ($d['late'] ?? 0) > 0 ? $dur($d['late']) : null,
                'ot'     => ($d['ot'] ?? 0) > 0 ? $dur($d['ot']) : null,
                'kind'   => $d['kind'] ?? 'none',
                'remark' => $d['remark'] ?? '',
                'tone'   => $d['tone'] ?? '',
            ];
        })->values();

        $t = $sheet['totals'] ?? [];

        return response()->json([
            'success'  => true,
            'employee' => [
                'id'       => $employee->id,
                'name'     => $employee->name,
                'position' => $employee->position ?: ($employee->laborType->name ?? 'Worker'),
                'site'     => $employee->site?->name,
                'shift'    => $employee->shift?->name,
            ],
            'week'     => $week,
            'period'   => [
                'from'  => $from->toDateString(),
                'to'    => $to->toDateString(),
                'label' => DtrService::label($from, $to),
                'hours' => DtrService::hoursOf($employee->shift, $from->toDateString()),
            ],
            'days'     => $days,
            'totals'   => [
                'days'    => (int) ($t['days'] ?? 0),
                'worked'  => $dur((int) ($t['worked'] ?? 0)),
                'ot'      => ($t['ot'] ?? 0) > 0 ? $dur((int) $t['ot']) : '—',
                'late'    => ($t['late'] ?? 0) > 0 ? $dur((int) $t['late']) : '—',
                'lates'   => (int) ($t['lates'] ?? 0),
                'absent'  => (int) ($t['absent'] ?? 0),
                'missing' => (int) ($t['missing'] ?? 0),
            ],
        ]);
    }

    /** GET /api/kiosk/complaints?scan_token=… — the worker and their last few complaints. */
    public function complaints(Request $request): JsonResponse
    {
        $employee = KioskScan::employee($request->query('scan_token'));
        if (! $employee) {
            return $this->stale();
        }

        return response()->json([
            'success'    => true,
            'employee'   => ['id' => $employee->id, 'name' => $employee->name,
                             'position' => $employee->position ?: 'Worker'],
            'categories' => Complaint::CATEGORIES,
            'complaints' => $this->recent($employee->id),
            'left_today' => $this->leftToday($employee->id),
        ]);
    }

    /** POST /api/kiosk/complaints {scan_token, category, message} */
    public function storeComplaint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scan_token' => 'required|string|max:64',
            'category'   => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'message'    => 'required|string|min:3|max:1000',
            'kiosk_id'   => 'nullable',
            'kiosk_code' => 'nullable|string',
        ]);

        $employee = KioskScan::employee($data['scan_token']);
        if (! $employee) {
            return $this->stale();
        }

        if ($this->leftToday($employee->id) <= 0) {
            return response()->json([
                'success' => false,
                'code'    => 'limit',
                'message' => 'You have sent ' . Complaint::DAILY_LIMIT . ' reports today. Please talk to the office directly.',
            ]);
        }

        $kiosk = Kiosk::resolve($request->input('kiosk_id'), $request->input('kiosk_code'));

        $complaint = Complaint::create([
            'employee_id' => $employee->id,
            'site_id'     => $kiosk?->site_id ?? $employee->site_id,
            'kiosk_id'    => $kiosk?->id,
            'category'    => $data['category'],
            'message'     => trim(strip_tags($data['message'])),
            'status'      => Complaint::OPEN,
        ]);

        // Tell the office. An alert that fails to send never loses the complaint.
        try {
            foreach (User::where('is_active', true)->get() as $user) {
                if ($user->isAdmin() || $user->role === User::ROLE_HR) {
                    KioskAlert::fire($user, 'kiosk_complaint', 'New complaint from ' . $employee->name,
                        $complaint->categoryLabel() . ': ' . \Illuminate\Support\Str::limit($complaint->message, 120));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Sent to the office. Reference #' . $complaint->id . '.',
            'id'         => $complaint->id,
            'complaints' => $this->recent($employee->id),
            'left_today' => $this->leftToday($employee->id),
        ]);
    }

    private function recent(int $employeeId): array
    {
        return Complaint::where('employee_id', $employeeId)
            ->latest()->limit(5)->get()
            ->map(fn (Complaint $c) => [
                'id'       => $c->id,
                'category' => $c->categoryLabel(),
                'message'  => $c->message,
                'status'   => $c->isOpen() ? 'open' : 'resolved',
                'response' => $c->response,
                'when'     => $c->created_at?->format('M j, g:i A'),
            ])->all();
    }

    private function leftToday(int $employeeId): int
    {
        $sent = Complaint::where('employee_id', $employeeId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, Complaint::DAILY_LIMIT - $sent);
    }

    private function stale(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => 'stale_scan',
            'message' => 'Scan your finger again.',
        ]);
    }
}
