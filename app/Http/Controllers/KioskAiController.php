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
        $employee = Employee::with(['laborType', 'site'])
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
    /**
     * This worker's own payroll figures, in plain numbers.
     *
     * The chat assistant can only answer when an Anthropic key is configured;
     * without one it returns a canned apology, which left the worker with no
     * way at all to see their pay. These are the same figures PayrollService
     * gives the admin's Payroll Records page, so the kiosk and the web can
     * never quote different numbers for the same week.
     *
     * Addressed by fingerprint: the worker proves who they are by scanning,
     * and can only ever reach their own row.
     */
    public function summary(int $fingerId, PayrollService $payroll): JsonResponse
    {
        $employee = Employee::with('laborType')
            ->where('fingerprint_id', (string) $fingerId)
            ->first();

        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end   = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $totals = [];
        try {
            $rows = $payroll->computeForRange($start->toDateString(), $end->toDateString())['employees'] ?? [];
            foreach ($rows as $row) {
                if ((int) ($row['employee_id'] ?? 0) === (int) $employee->id) {
                    $totals = $row['totals'] ?? [];
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Kiosk payroll summary failed — ' . $e->getMessage());
        }

        $num = fn ($key) => round((float) ($totals[$key] ?? 0), 2);

        // The scans themselves, not just what they add up to.
        //
        // The card used to carry totals alone, so a worker who had just
        // clocked in saw nothing move: payroll only counts a session once it
        // is closed, and an open one contributes no hours and no pay. The
        // person standing at the kiosk had every reason to think their scan
        // had not registered. Their own time-in is the proof it did.
        $records = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('date')
            ->orderBy('session')
            ->get()
            ->map(function (Attendance $a) {
                $in  = $a->time_in  ? Carbon::parse($a->time_in)  : null;
                $out = $a->time_out ? Carbon::parse($a->time_out) : null;

                return [
                    'date'     => Carbon::parse($a->date)->toDateString(),
                    'day'      => Carbon::parse($a->date)->format('D, M d'),
                    'session'  => $a->session,
                    'time_in'  => $in  ? $in->format('g:i A')  : null,
                    'time_out' => $out ? $out->format('g:i A') : null,
                    // Still on site: the hours are real but not yet countable,
                    // and saying so beats showing a silent zero.
                    'open'     => (bool) ($in && ! $out),
                    'hours'    => ($in && $out)
                        ? round(abs($in->diffInMinutes($out)) / 60, 2)
                        : 0.0,
                ];
            })
            ->values();

        $daysPresent = $records->filter(fn ($r) => $r['time_in'] !== null)
            ->pluck('date')->unique()->count();

        return response()->json([
            'success'  => true,
            'employee' => [
                'id'       => $employee->id,
                'name'     => $employee->name,
                'position' => $employee->position ?: ($employee->laborType->name ?? 'Worker'),
                // Recorded only — the figures below are computed the same way
                // for daily and contractual workers until that rule is settled.
                'employment_type'  => $employee->employment_type,
                'employment_label' => $employee->employment_label,
                // Where they are posted, per the office record. The kiosk knows
                // which site it is standing at; only the web knows where this
                // person was actually assigned, and they are not always the same.
                'site'             => $employee->site->name ?? null,
                // The rate PAYROLL ACTUALLY USED, not the stored field.
                //
                // PayrollService takes the labor type's daily_rate and divides
                // by 8 whenever that is set, and only falls back to the
                // employee's own rate_per_hour when it is not. Reporting the
                // stored field meant the card could say "P180 per hour" beside
                // a gross figure computed at P150 — and a worker who multiplies
                // the two numbers and gets a third one has every reason to stop
                // believing the screen. The whole point of this tab is that the
                // kiosk and the office cannot disagree.
                'rate_per_hour'    => round(
                    $employee->laborType?->daily_rate !== null
                        ? ((float) $employee->laborType->daily_rate) / 8
                        : (float) ($employee->rate_per_hour ?? 0),
                    2
                ),
                'daily_rate'       => $employee->laborType?->daily_rate !== null
                    ? round((float) $employee->laborType->daily_rate, 2) : null,
                'contract_rate'    => $employee->contract_rate !== null
                    ? round((float) $employee->contract_rate, 2) : null,
                'fingerprint_id'   => $employee->fingerprint_id,
            ],
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'label' => $start->format('M d') . ' – ' . $end->format('M d, Y'),
            ],
            'totals' => [
                // "Ilang araw na pinasok" — the day count the payroll itself uses.
                'workdays'   => (int) ($totals['workdays'] ?? 0),
                'hours'      => $num('hours'),
                'overtime'   => $num('overtime'),
                'gross'      => $num('gross'),
                'bonus'      => $num('bonus'),
                'deductions' => $num('totalDeductions'),
                'net'        => $num('net'),
                'holiday'    => $num('holidayPay'),
                'rest_day'   => $num('restDayPay'),
            ],

            // Itemised, because "Deductions: P240" invites exactly one question
            // and the kiosk should already be holding the answer.
            'breakdown' => [
                'sss'        => $num('sssDeduction'),
                'philhealth' => $num('philhealthDeduction'),
                'pagibig'    => $num('pagibigDeduction'),
                'tax'        => $num('withholdingTax'),
                'vale'       => $num('vale'),
                'manual'     => $num('manualDeductions'),
            ],

            // Today on its own. It is the thing they just did, and hunting for
            // it inside a week of rows is work the screen should have done.
            'today' => [
                'date'    => Carbon::now()->toDateString(),
                'label'   => Carbon::now()->format('D, M d'),
                'records' => $records->where('date', Carbon::now()->toDateString())->values(),
                'hours'   => round((float) $records->where('date', Carbon::now()->toDateString())
                                        ->sum('hours'), 2),
                'open'    => $records->where('date', Carbon::now()->toDateString())
                                     ->contains(fn ($r) => $r['open']),
            ],
            // Running cash-advance balance, kept off the totals because it is a
            // standing balance rather than part of this week's computation.
            // Kinuha mula sa mismong attendance rows, kaya tumutugma ito sa
            // listahan sa ibaba kahit hindi pa tapos ang isang session.
            'days_present' => $daysPresent,
            'attendance'   => $records,
            'vale' => round((float) ($employee->vale ?? 0), 2),
            // So the kiosk can say why the chat is quiet instead of looking broken.
            'assistant_available' => ! empty(config('services.anthropic.key')),
        ]);
    }

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
