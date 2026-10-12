<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\LaborType;
use Throwable;

/**
 * What a day of a worker's was priced at on a given date.
 *
 * Payroll reads the daily rate off the worker's labor type as it stands
 * today, for every day it computes. That is right until the type changes:
 * a laborer made a mason on Monday would have last month repriced as a
 * mason's. So a labor type changed from the Employees page leaves a dated
 * record (EmployeeChange), and a day is priced by what was in force on it:
 *
 *   - before a change, the type the worker held then, at that type's rate on
 *     file — the same figure everybody still on that type is paid;
 *   - from the latest change on, the worker as they stand.
 *
 * A change may keep the worker's own rate rather than take the new type's
 * ("Use this labor type's rate" unticked). That rate is held here as a pin,
 * and it lapses by itself the moment anything rewrites the worker's rate —
 * the Edit form, or a new rate on the type in Payroll Settings — so the
 * rate the Employees list shows and the rate payroll pays cannot part.
 *
 * A worker with no changes on file is priced exactly as before.
 */
final class LaborHistory
{
    /** @var array<int, list<array{on: string, from: ?int, to: ?int, detail: array}>> oldest first */
    private array $changes = [];

    /** @var array<int, float> labor type id => the daily rate on file */
    private array $rates = [];

    public static function load(): self
    {
        $me = new self;

        try {
            $rows = EmployeeChange::query()
                ->where('kind', EmployeeChange::LABOR)
                ->whereNotNull('applied_at')
                ->orderBy('effective_on')->orderBy('id')
                ->get(['employee_id', 'effective_on', 'from_id', 'to_id', 'detail']);
        } catch (Throwable $e) {
            // A deploy is live for the minutes before its migration is run.
            // No table is no history, and payroll prices as it always has.
            return $me;
        }

        if ($rows->isEmpty()) {
            return $me;
        }

        foreach ($rows as $row) {
            $me->changes[$row->employee_id][] = [
                'on'     => $row->effective_on->toDateString(),
                'from'   => $row->from_id,
                'to'     => $row->to_id,
                'detail' => $row->detail ?? [],
            ];
        }

        $me->rates = LaborType::query()->pluck('daily_rate', 'id')->map(fn ($v) => (float) $v)->all();

        return $me;
    }

    /**
     * The daily rate the worker's labor type gave them on a date, or null
     * when they had no labor type then (payroll falls back to their hourly).
     */
    public function dailyRateOn(Employee $employee, string $date): ?float
    {
        foreach ($this->changes[$employee->id] ?? [] as $change) {
            if ($change['on'] > $date) {
                return $this->before($change);
            }
        }

        return $this->pinOf($employee) ?? $this->live($employee);
    }

    /**
     * The rate a worker was let keep at their latest change, while it still
     * stands: same labor type, and nothing has rewritten their rate since.
     */
    public function pinOf(Employee $employee): ?float
    {
        $all  = $this->changes[$employee->id] ?? [];
        $last = $all ? $all[count($all) - 1] : null;
        $pin  = $last['detail']['to_pinned'] ?? null;

        if ($pin === null
            || (int) $employee->labor_type_id !== (int) $last['to']
            || abs((float) $employee->rate_per_hour - (float) ($last['detail']['to_rate'] ?? -1)) >= 0.005) {
            return null;
        }

        return (float) $pin;
    }

    /** What a day was priced at before this change took effect. */
    private function before(array $change): ?float
    {
        $detail = $change['detail'];

        if (($detail['from_pinned'] ?? null) !== null) {
            return (float) $detail['from_pinned'];
        }

        if ($change['from'] !== null && isset($this->rates[$change['from']])) {
            return $this->rates[$change['from']];
        }

        // The old type has been deleted since: the figure it paid that day.
        return isset($detail['from_daily']) ? (float) $detail['from_daily'] : null;
    }

    private function live(Employee $employee): ?float
    {
        $rate = $employee->laborType?->daily_rate;

        return $rate === null ? null : (float) $rate;
    }
}
