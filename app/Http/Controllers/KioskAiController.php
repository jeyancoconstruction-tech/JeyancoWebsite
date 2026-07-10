<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Payroll assistant for the Raspberry Pi kiosk. A worker scans a finger, we
 * resolve the employee, then answer questions about THAT employee's own
 * payroll, attendance, and overtime.
 *
 * The kiosk must never crash on our account: every failure path returns HTTP
 * 200 with a friendly Taglish message instead of an error status.
 */
class KioskAiController extends Controller
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL    = 'claude-haiku-4-5-20251001';

    private const FALLBACK = 'Pasensya na, hindi ko ma-check ang payroll mo ngayon. '
        . 'Pakisubukan ulit mamaya, o tanungin ang admin.';

    /**
     * GET /api/employees/by-finger/{fingerId}
     *
     * The fingerprint slot is stored on employees.fingerprint_id (a string
     * column — the kiosk sends the sensor's slot number).
     */
    public function byFinger(int $fingerId): JsonResponse
    {
        $employee = Employee::with('laborType')
            ->where('fingerprint_id', (string) $fingerId)
            ->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        return response()->json([
            'id'       => $employee->id,
            'name'     => $employee->name,
            'position' => $employee->position ?: ($employee->laborType->name ?? ''),
        ]);
    }

    /** POST /api/kiosk/ask  {employee_id, kiosk_id, question} */
    public function ask(Request $request, PayrollService $payroll): JsonResponse
    {
        $data = $request->validate([
            // Employee uses SoftDeletes: a bare exists:employees,id also matches
            // trashed rows, which the model can no longer load — that would blow
            // up as an HTML 404 the kiosk can't parse. Require a live row.
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'kiosk_id'    => ['nullable', 'string', 'max:50'],
            'question'    => ['required', 'string', 'max:500'],
        ]);

        $employee = Employee::with('laborType')->find($data['employee_id']);
        if (! $employee) {
            return response()->json(['answer' => self::FALLBACK]);
        }

        $key = config('services.anthropic.key');
        if (empty($key)) {
            Log::warning('Kiosk AI: services.anthropic.key is not configured.');
            return response()->json(['answer' => self::FALLBACK]);
        }

        try {
            $context = $this->buildContext($employee, $payroll);

            $response = Http::withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                ->timeout(20)
                ->post(self::ENDPOINT, [
                    'model'      => self::MODEL,
                    'max_tokens' => 600,
                    'system'     => $this->systemPrompt($context),
                    'messages'   => [
                        ['role' => 'user', 'content' => $data['question']],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Kiosk AI: Anthropic API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return response()->json(['answer' => self::FALLBACK]);
            }

            $answer = collect($response->json('content') ?? [])
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            $answer = trim($answer);

            return response()->json(['answer' => $answer !== '' ? $answer : self::FALLBACK]);
        } catch (\Throwable $e) {
            Log::error('Kiosk AI: request failed — ' . $e->getMessage());
            return response()->json(['answer' => self::FALLBACK]);
        }
    }

    /**
     * Payroll context for THIS employee only. Cutoffs run Monday–Sunday, the
     * same weeks PayrollService uses.
     */
    private function buildContext(Employee $employee, PayrollService $payroll): array
    {
        $cutoffStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $cutoffEnd   = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Attendance is stored one row per session (AM/PM), not as am_in/pm_in
        // columns, so a single workday can produce two rows.
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$cutoffStart->toDateString(), $cutoffEnd->toDateString()])
            ->orderBy('date')
            ->orderBy('session')
            ->get()
            ->map(function (Attendance $rec): array {
                $hours = $this->hoursWorked($rec->time_in, $rec->time_out);

                return [
                    'date'        => Carbon::parse($rec->date)->toDateString(),
                    'session'     => $rec->session,
                    'time_in'     => $rec->time_in  ? Carbon::parse($rec->time_in)->format('H:i')  : null,
                    'time_out'    => $rec->time_out ? Carbon::parse($rec->time_out)->format('H:i') : null,
                    'total_hours' => $hours,
                    'ot_hours'    => round(max(0, $hours - 8), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'employee' => [
                'id'           => $employee->id,
                'name'         => $employee->name,
                'position'     => $employee->position ?: ($employee->laborType->name ?? null),
                'daily_rate'   => round((float) $employee->getDailyRate(), 2),
                'ot_rate'      => round((float) $employee->getOTRate(), 2),
                'vale_balance' => round((float) ($employee->vale ?? 0), 2),
            ],
            'current_cutoff' => [
                'period_start' => $cutoffStart->toDateString(),
                'period_end'   => $cutoffEnd->toDateString(),
                'attendance'   => $attendance,
            ],
            'last_payslips' => $this->lastPayslips($employee, $payroll),
        ];
    }

    /**
     * Payslips are not stored — PayrollService computes them per Monday–Sunday
     * week. Compute the recent weeks and keep this employee's last three.
     */
    private function lastPayslips(Employee $employee, PayrollService $payroll): array
    {
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks(4)->toDateString();
        $to   = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        try {
            $weeks = $payroll->computeForRange($from, $to)['weeks'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('Kiosk AI: payroll compute failed — ' . $e->getMessage());
            return [];
        }

        $slips = [];

        foreach ($weeks as $week) {
            foreach ($week['details'] ?? [] as $row) {
                if ((int) ($row['employee_id'] ?? 0) !== (int) $employee->id) {
                    continue;
                }

                [$start, $end] = array_pad(explode(' - ', (string) $week['week_range']), 2, null);

                $slips[] = [
                    'period_start' => $this->toIsoDate($start),
                    'period_end'   => $this->toIsoDate($end),
                    'gross'        => $row['gross'],
                    'deductions'   => $row['totalDeductions'],
                    'net'          => $row['net'],
                ];
            }
        }

        usort($slips, fn (array $a, array $b) => strcmp((string) $b['period_end'], (string) $a['period_end']));

        return array_slice($slips, 0, 3);
    }

    private function hoursWorked(mixed $in, mixed $out): float
    {
        if (! $in || ! $out) {
            return 0.0;
        }

        try {
            $timeIn  = Carbon::parse($in);
            $timeOut = Carbon::parse($out);

            return $timeOut->lessThanOrEqualTo($timeIn)
                ? 0.0
                : round($timeOut->floatDiffInHours($timeIn), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** PayrollService labels weeks as "m/d/Y - m/d/Y". */
    private function toIsoDate(?string $mdy): ?string
    {
        if (! $mdy) {
            return null;
        }

        try {
            return Carbon::createFromFormat('m/d/Y', trim($mdy))->toDateString();
        } catch (\Throwable) {
            return $mdy;
        }
    }

    private function systemPrompt(array $context): string
    {
        $json = json_encode(
            $context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
        Ikaw ang payroll assistant ng Jeyanco Construction, nakalagay sa kiosk sa construction site.

        PAANO SUMAGOT
        - Sumagot sa Taglish. Maikli at diretso — parang kausap mo ang manggagawa sa site.
        - Piso (₱) ang gamit, laging 2 decimal places. Halimbawa: ₱3,000.00
        - Kapag may kinukuwenta, ipakita ang breakdown.
          Halimbawa: "5 days × ₱600.00 = ₱3,000.00 + OT 4 hrs × ₱93.75 = ₱375.00"

        HANGGANAN
        - Ang payroll, attendance, at overtime NG EMPLOYEE NA ITO LANG ang sasagutin mo.
        - Kung tungkol sa ibang tao ang tanong, o ibang topic (balita, panahon, kahit ano pa),
          magpaumanhin nang maikli at ibalik ang usapan sa payroll o attendance niya.

        DATA
        - Gamitin LANG ang data sa JSON sa ibaba. HUWAG MAG-IMBENTO ng kahit anong numero.
        - Kung walang data para sa tinatanong, sabihing wala pang naitalang record at i-refer siya sa admin.
        - Ang attendance ay naka-record kada session (AM o PM), kaya isang araw ay pwedeng may dalawang record.
        - Ang "last_payslips" ay kada linggo (Lunes hanggang Linggo).

        EMPLOYEE PAYROLL CONTEXT (JSON):
        {$json}
        PROMPT;
    }
}
