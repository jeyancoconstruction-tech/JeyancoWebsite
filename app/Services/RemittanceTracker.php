<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\RemittancePayment;
use App\Models\SystemSetting;
use App\Support\Live;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * What the office owes SSS, PhilHealth, Pag-IBIG and the BIR each month, and
 * whether it has paid.
 *
 * The amounts are the employee contributions payroll deducted — the same
 * weekly figures Payroll Records and the payslip show — added up by month.
 * Payroll runs in pay weeks and the agencies bill by month, so a week belongs
 * to the month it ends in: the week of Monday 31 August to Sunday 6 September
 * is September's. Due dates are a day of the following month, set per agency
 * in Payroll Settings. Payments are what the office marked paid.
 *
 * A month's figures are cached. The month on screen is worked out again
 * whenever payroll has moved at all since. The others — the year at a glance
 * and the sidebar's count — are worked out again when something that reaches
 * back into closed months moves: the rates (which are dated, and a new row can
 * start in the past), an employee, leave or an advance. A clock-in, which
 * happens all day and only touches today, does not make every month over.
 */
final class RemittanceTracker
{
    /** The four agencies, in the order the tracker lists them. */
    public const AGENCIES = [
        'sss' => [
            'name' => 'SSS', 'full' => 'Social Security System', 'form' => 'Contribution list · PRN',
            'color' => '#1d4ed8', 'channel' => 'Online (My.SSS PRN)',
            'field' => 'sssDeduction', 'due' => 'sss_due_day', 'default_due' => 30,
            'id' => 'sss_number', 'id_label' => 'SSS No.',
        ],
        'philhealth' => [
            'name' => 'PhilHealth', 'full' => 'PhilHealth premiums', 'form' => 'RF-1 · SPA',
            'color' => '#059669', 'channel' => 'Bank (Landbank)',
            'field' => 'philhealthDeduction', 'due' => 'philhealth_due_day', 'default_due' => 15,
            'id' => 'philhealth_number', 'id_label' => 'PhilHealth No.',
        ],
        'pagibig' => [
            'name' => 'Pag-IBIG', 'full' => 'Pag-IBIG Fund (HDMF)', 'form' => 'MCRF · PRN',
            'color' => '#d97706', 'channel' => 'Virtual Pag-IBIG',
            'field' => 'pagibigDeduction', 'due' => 'pagibig_due_day', 'default_due' => 15,
            'id' => 'pagibig_number', 'id_label' => 'Pag-IBIG MID No.',
        ],
        'bir' => [
            'name' => 'BIR', 'full' => 'Withholding tax on compensation', 'form' => 'BIR Form 1601-C',
            'color' => '#7c3aed', 'channel' => 'eFPS',
            'field' => 'withholdingTax', 'due' => 'bir_due_day', 'default_due' => 10,
            'id' => 'tin_number', 'id_label' => 'TIN',
        ],
    ];

    /** Ways to pay offered beside each agency's own. */
    public const CHANNELS = ['Bank over-the-counter', 'GCash / Maya', 'Online banking'];

    /** What a month's payroll figures depend on — PayrollService's own list. */
    private const READS = ['employees', 'attendance', 'leave', 'advances', 'payroll', 'settings'];

    /** The part of that which reaches back into months already closed. */
    private const REACHES_BACK = ['employees', 'leave', 'advances', 'settings'];

    private const MONTH_KEY = 'remittances.month.';
    private const BADGE_KEY = 'remittances.badge';

    /** How far back the tracker reaches. */
    private const MAX_MONTHS = 24;

    public function __construct(private PayrollService $payroll) {}

    // ── Months ───────────────────────────────────────────────────────────────

    public function today(): Carbon
    {
        return Carbon::now('Asia/Manila')->startOfDay();
    }

    /** The latest month whose contributions are complete: last month. */
    public function lastClosed(): Carbon
    {
        return $this->today()->startOfMonth()->subMonthNoOverflow();
    }

    /**
     * Every month the tracker covers, oldest first: from the first month with
     * anything in payroll to last month, and no more than two years of it.
     *
     * @return list<Carbon>
     */
    public function months(): array
    {
        $last  = $this->lastClosed();
        $first = $this->firstPayrollMonth() ?? $last->copy();
        $floor = $last->copy()->subMonthsNoOverflow(self::MAX_MONTHS - 1);

        if ($first->lt($floor)) {
            $first = $floor;
        }
        if ($first->gt($last)) {
            $first = $last->copy();
        }

        $months = [];
        for ($m = $first->copy(); $m->lte($last); $m->addMonthNoOverflow()) {
            $months[] = $m->copy();
        }

        return $months;
    }

    private function firstPayrollMonth(): ?Carbon
    {
        $first = collect([
            Attendance::min('date'),
            LeaveRequest::where('is_paid', true)->min('starts_on'),
        ])->filter()->min();

        return $first ? Carbon::parse($first, 'Asia/Manila')->startOfMonth() : null;
    }

    /**
     * The pay weeks that end in a month, as one date range: from the start of
     * the first to the end of the last. Where a week starts is Payroll
     * Settings' "Week starts"; it ends six days later.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function weeksOf(Carbon $month): array
    {
        $endsOn = ((int) (SystemSetting::current()->week_starts_on ?? Carbon::MONDAY) + 6) % 7;

        $first = $month->copy()->startOfMonth();
        while ($first->dayOfWeek !== $endsOn) {
            $first->addDay();
        }

        $last = $month->copy()->endOfMonth()->startOfDay();
        while ($last->dayOfWeek !== $endsOn) {
            $last->subDay();
        }

        return [$first->copy()->subDays(6), $last];
    }

    /** When a month's remittance to an agency is due: its day in the month after. */
    public function dueDate(string $agency, Carbon $month): Carbon
    {
        $a    = self::AGENCIES[$agency];
        $day  = (int) (SystemSetting::current()->{$a['due']} ?? $a['default_due']);
        $next = $month->copy()->startOfMonth()->addMonthNoOverflow();

        return $next->day(max(1, min($day, $next->daysInMonth)));
    }

    // ── What each month owed ─────────────────────────────────────────────────

    /**
     * Each month's contributions by agency: a total, and each employee's share.
     *
     * Cached months are used as they are, except $fresh — the month on screen
     * — which is worked out again if payroll has moved since it was cached.
     *
     * @param  list<Carbon>  $months
     * @return array<string, array{agencies: array<string, array{total: float, people: list<array{id: int, name: string, amount: float}>}>}>
     */
    public function totals(array $months, ?Carbon $fresh = null): array
    {
        $keys  = array_map(fn (Carbon $m) => $m->format('Y-m'), $months);
        $stamp = Live::stamp(...self::READS);
        $back  = Live::stamp(...self::REACHES_BACK);
        $fresh = $fresh?->format('Y-m');

        try {
            $cached = Cache::many(array_map(fn ($k) => self::MONTH_KEY . $k, $keys));
        } catch (\Throwable) {
            $cached = [];
        }

        $out = $missing = [];
        foreach ($months as $i => $month) {
            $entry = $cached[self::MONTH_KEY . $keys[$i]] ?? null;
            $stale = $keys[$i] === $fresh
                ? ($stamp === null || ($entry['stamp'] ?? null) !== $stamp)
                : ($back !== null && ($entry['back'] ?? null) !== $back);

            if (is_array($entry) && ! $stale) {
                $out[$keys[$i]] = $entry;
            } else {
                $missing[] = $month;
            }
        }

        if ($missing) {
            foreach ($this->compute($missing) as $key => $entry) {
                $entry['stamp'] = $stamp;
                $entry['back']  = $back;
                try {
                    Cache::put(self::MONTH_KEY . $key, $entry, now()->addDays(30));
                } catch (\Throwable) {
                }
                if (in_array($key, $keys, true)) {
                    $out[$key] = $entry;
                }
            }
            $this->forgetBadge();
        }

        return $out;
    }

    /**
     * Work months out from payroll: one pass per run of consecutive months,
     * over the pay weeks that end in them.
     *
     * @param  list<Carbon>  $months
     */
    private function compute(array $months): array
    {
        usort($months, fn (Carbon $a, Carbon $b) => $a <=> $b);

        $runs = [];
        foreach ($months as $m) {
            $last = end($runs);
            if ($last && end($last)->copy()->addMonthNoOverflow()->equalTo($m)) {
                $runs[array_key_last($runs)][] = $m;
            } else {
                $runs[] = [$m];
            }
        }

        $out = [];
        foreach ($runs as $run) {
            foreach ($run as $m) {
                $out[$m->format('Y-m')] = $this->blank();
            }

            [$from] = $this->weeksOf($run[0]);
            [, $to] = $this->weeksOf(end($run));
            if ($to->lt($from)) {
                continue;
            }

            $people = [];
            foreach ($this->payroll->computeForRange($from->toDateString(), $to->toDateString())['employees'] as $emp) {
                foreach ($emp['periods'] as $p) {
                    // "09/01/2026 - 09/07/2026": the week belongs to the month it ends in.
                    $end = trim((string) (explode(' - ', (string) ($p['week_range'] ?? ''))[1] ?? ''));
                    if ($end === '') {
                        continue;
                    }
                    $key = Carbon::createFromFormat('m/d/Y', $end, 'Asia/Manila')->format('Y-m');
                    if (! isset($out[$key])) {
                        continue;
                    }
                    foreach (self::AGENCIES as $agency => $a) {
                        $people[$key][$agency][$emp['employee_id']]['name'] = $emp['name'];
                        $people[$key][$agency][$emp['employee_id']]['amount'] =
                            ($people[$key][$agency][$emp['employee_id']]['amount'] ?? 0) + (float) ($p[$a['field']] ?? 0);
                    }
                }
            }

            foreach ($people as $key => $byAgency) {
                foreach ($byAgency as $agency => $rows) {
                    $list = [];
                    foreach ($rows as $id => $row) {
                        $amount = round($row['amount'], 2);
                        if ($amount > 0) {
                            $list[] = ['id' => (int) $id, 'name' => $row['name'], 'amount' => $amount];
                        }
                    }
                    usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
                    $out[$key]['agencies'][$agency] = [
                        'total'  => round(array_sum(array_column($list, 'amount')), 2),
                        'people' => $list,
                    ];
                }
            }
        }

        return $out;
    }

    private function blank(): array
    {
        return ['agencies' => array_map(fn () => ['total' => 0.0, 'people' => []], self::AGENCIES)];
    }

    // ── Where each one stands ────────────────────────────────────────────────

    /**
     * paid · pend (due, not yet late) · late (past its due date) · none (it
     * owed nothing) · fut (the month is not over, so nothing is due yet).
     */
    public function status(string $agency, Carbon $month, float $total, ?RemittancePayment $payment): string
    {
        if ($payment) {
            return 'paid';
        }
        if ($month->copy()->startOfMonth()->gte($this->today()->startOfMonth())) {
            return 'fut';
        }
        if ($total <= 0) {
            return 'none';
        }

        return $this->today()->gt($this->dueDate($agency, $month)) ? 'late' : 'pend';
    }

    /**
     * How many agency-months are due or overdue, for the sidebar.
     *
     * Read from the cached months only — working payroll out on every page
     * would slow every page — so it counts what the tracker last saw. Before
     * the tables exist (a deploy is live before its migration is run) it is
     * nothing rather than an error on every page.
     */
    public function badge(): int
    {
        try {
            return (int) Cache::remember(self::BADGE_KEY, now()->addMinutes(5), function () {
                $months = $this->months();
                $cached = Cache::many(array_map(fn (Carbon $m) => self::MONTH_KEY . $m->format('Y-m'), $months));
                $paid   = RemittancePayment::query()
                    // Half-open: a date column can come back with a time on it.
                    ->where('period', '>=', $months[0]->toDateString())
                    ->where('period', '<', end($months)->copy()->addMonthNoOverflow()->toDateString())
                    ->get(['agency', 'period'])
                    ->mapWithKeys(fn ($p) => [$p->agency . '|' . $p->period->format('Y-m') => true]);

                $count = 0;
                foreach ($months as $month) {
                    $entry = $cached[self::MONTH_KEY . $month->format('Y-m')] ?? null;
                    if (! is_array($entry)) {
                        continue;
                    }
                    foreach (array_keys(self::AGENCIES) as $agency) {
                        $total  = (float) ($entry['agencies'][$agency]['total'] ?? 0);
                        $status = $this->status($agency, $month, $total, null);
                        if (! isset($paid[$agency . '|' . $month->format('Y-m')]) && in_array($status, ['pend', 'late'], true)) {
                            $count++;
                        }
                    }
                }

                return $count;
            });
        } catch (\Throwable) {
            return 0;
        }
    }

    public function forgetBadge(): void
    {
        try {
            Cache::forget(self::BADGE_KEY);
        } catch (\Throwable) {
        }
    }
}
