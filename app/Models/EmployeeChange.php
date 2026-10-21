<?php

namespace App\Models;

use App\Support\ActivityRecorder;
use App\Support\LaborHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * One worker's site, shift or labor type, changed from a date — or a bonus
 * given to them — as one of the rows a bulk action on Employees wrote.
 *
 * The worker's own row holds what is true today. This holds what it was
 * before and the day it changed, which is what lets payroll price last month
 * as last month was (LaborHistory), lets Undo put everything back, and lets a
 * change dated ahead wait for its day.
 *
 * Nothing here reaches into attendance. A day already worked keeps the shift
 * and the site stamped on it when it was clocked.
 */
class EmployeeChange extends Model
{
    public const SITE  = 'site';
    public const SHIFT = 'shift';
    public const LABOR = 'labor';
    public const BONUS = 'bonus';
    /** Allowed to take overtime, or not: to_id is 1 or 0. Read by App\Support\OvertimePermission. */
    public const OVERTIME = 'overtime';
    /**
     * Another site the worker also works at, added or taken off; to_id is the
     * site. The main site (SITE) is not touched by either (2026-10-21).
     */
    public const SITE_ADD  = 'site_add';
    public const SITE_DROP = 'site_drop';

    /** The worker column each kind of change is written to. */
    public const COLUMNS = [
        self::SITE     => 'site_id',
        self::SHIFT    => 'shift_id',
        self::LABOR    => 'labor_type_id',
        self::OVERTIME => 'overtime_allowed',
    ];

    protected $fillable = [
        'batch', 'employee_id', 'kind', 'effective_on', 'from_id', 'to_id',
        'detail', 'applied_at', 'created_by',
    ];

    protected $casts = [
        'effective_on' => 'date',
        'detail'       => 'array',
        'applied_at'   => 'datetime',
    ];

    /** The day the waiting changes were last looked for, in this process. */
    private static ?string $checkedOn = null;

    protected static function booted(): void
    {
        // A change put on file may be due the next time anybody looks.
        static::created(fn () => self::$checkedOn = null);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** Dated ahead, and not made yet. */
    public function scopeWaiting(Builder $q): Builder
    {
        return $q->whereNull('applied_at');
    }

    public function isWaiting(): bool
    {
        return $this->applied_at === null;
    }

    /**
     * Make the change on the worker.
     *
     * False when there was nothing to change — they are already there, they
     * have left, or what they were to move to is gone — and the row is
     * dropped, so nothing is left on file saying a change happened.
     */
    public function apply(): bool
    {
        $employee = $this->employee;

        if (! $employee || $employee->trashed() || ! $employee->isActive()) {
            $this->delete();
            return false;
        }

        $done = match ($this->kind) {
            self::SITE  => $this->moveTo($employee, 'site_id', $this->to_id === null || Site::whereKey($this->to_id)->exists()),
            self::SHIFT => $this->moveTo($employee, 'shift_id', Shift::whereKey($this->to_id)->exists()),
            self::LABOR => $this->changeLabor($employee),
            self::OVERTIME => $this->moveTo($employee, 'overtime_allowed', true),
            self::SITE_ADD  => $this->addSite($employee),
            self::SITE_DROP => $this->dropSite($employee),
            default     => false,
        };

        if (! $done) {
            $this->delete();
            return false;
        }

        $this->applied_at = now();
        $this->save();

        return true;
    }

    private function moveTo(Employee $employee, string $column, bool $exists): bool
    {
        if (! $exists || (int) $employee->{$column} === (int) $this->to_id) {
            return false;
        }

        $this->from_id = $employee->{$column};
        $employee->update([$column => $this->to_id]);

        return true;
    }

    /** One more site to scan at. Not their main site, and not one they have already. */
    private function addSite(Employee $employee): bool
    {
        if (! Employee::hasOtherSites() || ! Site::whereKey($this->to_id)->exists()
            || (int) $employee->site_id === (int) $this->to_id || $this->hasOther($employee)) {
            return false;
        }

        $employee->otherSites()->attach($this->to_id);
        $employee->unsetRelation('otherSites');

        return true;
    }

    /** Off one of the other sites. The main site is moved by a transfer instead. */
    private function dropSite(Employee $employee): bool
    {
        if (! Employee::hasOtherSites() || ! $this->hasOther($employee)) {
            return false;
        }

        $employee->otherSites()->detach($this->to_id);
        $employee->unsetRelation('otherSites');

        return true;
    }

    private function hasOther(Employee $employee): bool
    {
        return $employee->otherSites()->where('sites.id', $this->to_id)->exists();
    }

    /**
     * The labor type, and with it the position, which is the labor type's
     * name everywhere else a worker is saved. The rate follows the new type
     * unless the office asked for each worker to keep their own.
     */
    private function changeLabor(Employee $employee): bool
    {
        $type = LaborType::find($this->to_id);

        if (! $type || (int) $employee->labor_type_id === (int) $type->id) {
            return false;
        }

        // What a day of theirs is priced at as they stand, before anything moves.
        $history = LaborHistory::load();
        $today   = now()->toDateString();
        $daily   = $history->dailyRateOn($employee, $today) ?? round((float) $employee->rate_per_hour * 8, 2);
        $useRate = (bool) ($this->detail['use_rate'] ?? true);
        $rate    = $useRate ? round((float) $type->daily_rate / 8, 2) : (float) $employee->rate_per_hour;

        $this->from_id = $employee->labor_type_id;
        $this->detail  = [
            'use_rate'       => $useRate,
            'from_rate'      => (float) $employee->rate_per_hour,
            'from_position'  => $employee->position,
            'from_job_title' => $employee->job_title,
            'from_daily'     => $daily,
            'from_pinned'    => $history->pinOf($employee),
            'to_rate'        => $rate,
            'to_pinned'      => $useRate ? null : $daily,
        ];

        $employee->update(Employee::withoutMissingColumns([
            'labor_type_id' => $type->id,
            'position'      => $type->name,
            'job_title'     => $type->name,
            'rate_per_hour' => $rate,
        ]));

        return true;
    }

    /**
     * Put back what this change replaced, and take the row off file.
     *
     * False when the worker has been changed again since: what is on their
     * record now is somebody's later decision, and is left alone.
     */
    public function revert(): bool
    {
        if ($this->isWaiting()) {
            $this->delete();
            return true;
        }

        if ($this->kind === self::BONUS) {
            if ($bonus = Bonus::find($this->to_id)) {
                $bonus->employees()->detach();
                $bonus->delete();
            }
            $this->delete();
            return true;
        }

        $employee = $this->employee;

        // Another site added or taken off: back the other way, unless it has
        // been changed again since (or became their main site by a transfer).
        if ($this->kind === self::SITE_ADD || $this->kind === self::SITE_DROP) {
            $added = $this->kind === self::SITE_ADD;

            if (! $employee || $this->hasOther($employee) !== $added
                || (! $added && (int) $employee->site_id === (int) $this->to_id)) {
                return false;
            }

            $added ? $employee->otherSites()->detach($this->to_id) : $employee->otherSites()->attach($this->to_id);
            $employee->unsetRelation('otherSites');
            $this->delete();

            return true;
        }

        $column = self::COLUMNS[$this->kind] ?? null;

        if (! $employee || ! $column || (int) $employee->{$column} !== (int) $this->to_id) {
            return false;
        }

        $back = [$column => $this->from_id];

        if ($this->kind === self::LABOR) {
            $back += [
                'rate_per_hour' => $this->detail['from_rate'] ?? $employee->rate_per_hour,
                'position'      => $this->detail['from_position'] ?? $employee->position,
                'job_title'     => $this->detail['from_job_title'] ?? null,
            ];
        }

        $employee->update(Employee::withoutMissingColumns($back));
        $this->delete();

        return true;
    }

    /**
     * Make every change whose day has come.
     *
     * Nothing runs on a timer here, so this is asked on every request and
     * answers from memory after the first one of the day: a change dated
     * ahead only becomes due when the date moves. It must never be the reason
     * a page fails, and before the table exists it finds nothing.
     */
    public static function applyDue(): int
    {
        $today = now()->toDateString();

        if (self::$checkedOn === $today) {
            return 0;
        }
        self::$checkedOn = $today;

        try {
            // whereDate: SQLite keeps a date with its midnight, and a plain
            // comparison there puts "the 21st 00:00" after "the 21st".
            $due = static::waiting()->whereDate('effective_on', '<=', $today)->orderBy('id')->get();
        } catch (Throwable $e) {
            return 0;
        }

        $made = 0;

        foreach ($due as $change) {
            try {
                $was = $change->describeTarget();

                if (! $change->apply()) {
                    continue;
                }
                $made++;

                // Nobody pressed anything today: say what took effect, and
                // that it was the date that did it.
                app(ActivityRecorder::class)->noteExplicit('Employees', $change->employee);
                AuditLog::entry([
                    'user_name'    => 'System',
                    'module'       => 'Employees',
                    'action'       => 'updated',
                    'description'  => "{$change->employee->name}: {$was}, as scheduled by " . ($change->created_by ?: 'the office') . '.',
                    'subject_type' => 'Employee',
                    'subject_id'   => $change->employee_id,
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $made;
    }

    /** "moved to Site B", "changed to Night Shift" — what this change does, in words. */
    public function describeTarget(): string
    {
        return match ($this->kind) {
            self::SITE  => $this->to_id ? 'moved to ' . $this->targetName() : 'taken off their site',
            self::SHIFT, self::LABOR => 'changed to ' . $this->targetName(),
            self::OVERTIME => $this->to_id ? 'allowed to take overtime' : 'stopped from taking overtime',
            self::SITE_ADD  => 'also at ' . $this->siteName(),
            self::SITE_DROP => 'taken off ' . $this->siteName(),
            default     => 'changed',
        };
    }

    /** The site, shift or labor type this change is to, by name. */
    public function targetName(): string
    {
        return match ($this->kind) {
            self::SITE  => $this->to_id ? (Site::whereKey($this->to_id)->value('name') ?? 'another site') : 'No site',
            self::SITE_ADD  => 'Also at ' . $this->siteName(),
            self::SITE_DROP => 'Off ' . $this->siteName(),
            self::SHIFT => Shift::whereKey($this->to_id)->value('name') ?? 'another shift',
            self::LABOR => LaborType::whereKey($this->to_id)->value('name') ?? 'another labor type',
            self::OVERTIME => $this->to_id ? 'Overtime allowed' : 'No overtime',
            default     => '',
        };
    }

    private function siteName(): string
    {
        return Site::whereKey($this->to_id)->value('name') ?? 'another site';
    }

    /**
     * The changes still waiting, by worker, for the tag on their row.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, self>>
     */
    public static function waitingByEmployee()
    {
        try {
            return static::waiting()->orderBy('effective_on')->orderBy('id')->get()->groupBy('employee_id');
        } catch (Throwable $e) {
            return collect();
        }
    }

    /** The day a change may be dated from: today, never a day already worked. */
    public static function earliestDate(): string
    {
        return Carbon::today()->toDateString();
    }
}
