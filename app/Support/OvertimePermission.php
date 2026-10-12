<?php

namespace App\Support;

use App\Models\EmployeeChange;
use Throwable;

/**
 * Who may take overtime, on which day.
 *
 * The office chooses the workers who may (the Allow overtime action on the
 * Employees page). For everybody else the hours past their regular hours are
 * not recorded: worked or not, they are neither overtime nor pay.
 *
 * Two things keep that from reaching into what was already paid:
 *
 *   - There is no rule until somebody is allowed. Before the day the first
 *     allowance took effect, overtime is counted for anybody who worked it,
 *     as it always was — so nothing changed for anyone on the day this was
 *     built, and nothing before that day ever will.
 *   - Each change is dated. A worker allowed from the 20th is not allowed on
 *     the 19th, and one stopped from the 20th keeps the overtime of the 19th.
 *
 * It reads the dated record of the changes (EmployeeChange, kind "overtime"),
 * not the worker's own row, so a day is answered by what was true on it.
 */
final class OvertimePermission
{
    /** The day the first allowance took effect, or null while there is no rule. */
    private ?string $startsOn = null;

    /** @var array<int, list<array{on: string, allowed: bool}>> oldest first */
    private array $changes = [];

    public static function load(): self
    {
        $me = new self;

        try {
            $rows = EmployeeChange::query()
                ->where('kind', EmployeeChange::OVERTIME)
                ->whereNotNull('applied_at')
                ->orderBy('effective_on')->orderBy('id')
                ->get(['employee_id', 'effective_on', 'to_id']);
        } catch (Throwable $e) {
            // A deploy is live for the minutes before its migration is run.
            // No table is no rule, and overtime is counted as it always was.
            return $me;
        }

        foreach ($rows as $row) {
            $on = $row->effective_on->toDateString();

            $me->startsOn ??= $on;
            $me->changes[$row->employee_id][] = ['on' => $on, 'allowed' => (bool) $row->to_id];
        }

        return $me;
    }

    /** The day from which only the workers allowed take overtime; null while everybody still does. */
    public function startsOn(): ?string
    {
        return $this->startsOn;
    }

    /** Whether hours past the regular hours count as overtime for this worker on this day. */
    public function allowedOn(int $employeeId, string $date): bool
    {
        if ($this->startsOn === null || $date < $this->startsOn) {
            return true;   // before there was a rule
        }

        $allowed = false;   // nobody is, until they are allowed

        foreach ($this->changes[$employeeId] ?? [] as $change) {
            if ($change['on'] > $date) {
                break;
            }
            $allowed = $change['allowed'];
        }

        return $allowed;
    }
}
