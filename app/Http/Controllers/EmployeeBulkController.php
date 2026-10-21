<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Support\ActivityRecorder;
use App\Support\OvertimePermission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The bar under a selection on Employees: transfer site, add or take off
 * another site, change shift, change labor type, add bonus — each for
 * everybody ticked, and each with a way back.
 *
 * A change takes effect from a date, today at the earliest. That is the
 * whole of how the past is kept: the days already worked hold the site and
 * shift they were clocked under, payroll prices a day by the labor type in
 * force on it (LaborHistory), and nothing here writes to attendance. A date
 * ahead is kept waiting and made when its day comes (EmployeeChange::applyDue).
 *
 * Only active workers are touched, whatever ids arrive: the bar exists on
 * the Active list, and a pending registration is not on the payroll.
 */
class EmployeeBulkController extends Controller
{
    public function site(Request $request): JsonResponse
    {
        $data = $this->check($request, ['site_id' => 'nullable|integer|exists:sites,id']);
        $site = filled($data['site_id'] ?? null) ? Site::findOrFail($data['site_id']) : null;

        return $this->change($data, EmployeeChange::SITE, $site?->id, [], [
            'to'      => $site?->name ?? 'Unassigned',
            'done'    => ':n moved to :to',
            'later'   => ':n will move to :to on :date',
            'already' => $site ? 'Everybody selected is already at :to.' : 'Nobody selected has a site to be taken off.',
            'log'     => $site ? 'Transferred :n to :to' : 'Took :n off their site',
        ]);
    }

    /**
     * Another site the workers ticked also work at, added or taken off. Their
     * main site stays as it is: the kiosk at either one takes their scans,
     * and lists and payroll still file them under the main site.
     */
    public function otherSite(Request $request): JsonResponse
    {
        if (! Employee::hasOtherSites()) {
            return $this->refuse('Other sites are not ready yet. Please try again in a minute.');
        }

        $data = $this->check($request, [
            'site_id' => 'required|integer|exists:sites,id',
            'mode'    => ['required', Rule::in(['add', 'remove'])],
        ]);
        $site = Site::findOrFail($data['site_id']);
        $id   = (int) $site->id;
        $has  = fn (Employee $e) => $e->otherSiteList()->contains('id', $id);

        if ($data['mode'] === 'add') {
            return $this->change($data, EmployeeChange::SITE_ADD, $id, [], [
                'to'      => $site->name,
                'done'    => ':n can now also scan at :to',
                'later'   => ':n can also scan at :to from :date',
                'already' => 'Everybody selected already works at :to.',
                'log'     => 'Added :to as another site for :n',
            ], fn (Employee $e) => (int) $e->site_id !== $id && ! $has($e));
        }

        return $this->change($data, EmployeeChange::SITE_DROP, $id, [], [
            'to'      => $site->name,
            'done'    => ':n taken off :to',
            'later'   => ':n will be taken off :to on :date',
            'already' => 'Nobody selected has :to as another site. A main site is changed with Transfer site.',
            'log'     => 'Took :n off :to, one of their other sites',
        ], $has);
    }

    public function shift(Request $request): JsonResponse
    {
        $data  = $this->check($request, ['shift_id' => 'required|integer|exists:shifts,id']);
        $shift = Shift::findOrFail($data['shift_id']);

        return $this->change($data, EmployeeChange::SHIFT, $shift->id, [], [
            'to'      => $shift->name,
            'done'    => ':n changed to :to',
            'later'   => ':n will change to :to on :date',
            'already' => 'Everybody selected is already on :to.',
            'log'     => 'Changed the shift of :n to :to',
        ]);
    }

    public function labor(Request $request): JsonResponse
    {
        $data = $this->check($request, [
            'labor_type_id' => 'required|integer|exists:labor_types,id',
            'use_rate'      => 'nullable|boolean',
        ]);
        $type    = LaborType::findOrFail($data['labor_type_id']);
        $useRate = $request->boolean('use_rate', true);

        return $this->change($data, EmployeeChange::LABOR, $type->id, ['use_rate' => $useRate], [
            'to'      => $type->name,
            'done'    => ':n changed to :to',
            'later'   => ':n will change to :to on :date',
            'already' => 'Everybody selected is already :to.',
            'log'     => 'Changed the labor type of :n to :to' . ($useRate
                ? ' at ₱' . number_format($type->daily_rate / 8, 2) . ' / hr'
                : ', each keeping their own rate'),
        ]);
    }

    /**
     * Allow the workers ticked to take overtime, or stop them.
     *
     * From the day the first worker is allowed, only the workers allowed take
     * overtime: for anybody else the hours past their regular hours are not
     * recorded (App\Support\OvertimePermission). Until then there is no rule
     * and overtime is counted for whoever works it, so allowing the first
     * worker is also what begins the choosing. The answer says from which
     * day the rule runs, for the page to say so.
     */
    public function overtime(Request $request): JsonResponse
    {
        $data  = $this->check($request, ['allow' => 'required|boolean']);
        $allow = $request->boolean('allow');

        return $this->change($data, EmployeeChange::OVERTIME, $allow ? 1 : 0, [], [
            'to'      => $allow ? 'allowed overtime' : 'not allowed overtime',
            'done'    => $allow ? ':n allowed to take overtime' : ':n no longer take overtime',
            'later'   => $allow ? ':n will be allowed overtime from :date' : ':n will stop taking overtime from :date',
            'already' => $allow ? 'Everybody selected is already allowed overtime.' : 'Nobody selected is allowed overtime, so there is nothing to stop.',
            'log'     => $allow ? 'Allowed overtime for :n' : 'Stopped overtime for :n',
        ]);
    }

    /**
     * The same grant a worker's own row gives, for each worker ticked: one
     * bonus naming one worker, so each row counts and can take back its own.
     */
    public function bonus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'reason' => 'required|string|max:160',
            'period' => ['required', Rule::in(['current', 'next'])],
        ], [
            'amount.min' => 'A bonus of nothing is not a bonus.',
        ]);

        $workers = Employee::active()->whereIn('id', $data['ids'])->orderBy('name')->get();

        if ($workers->isEmpty()) {
            return $this->refuse('Nobody selected is on the active list any more.');
        }

        // The period running now is paid from today; the next one from the
        // day it opens. Never a week already paid.
        [$opens, $closes] = EmployeeController::payPeriod();
        $on = now()->toDateString();

        if ($data['period'] === 'next') {
            [$opens, $closes] = EmployeeController::payPeriod(Carbon::parse($opens)->addDays(7)->toDateString());
            $on = $opens;
        }

        $batch = (string) Str::uuid();
        $by    = $this->actor();

        DB::transaction(function () use ($workers, $data, $on, $batch, $by) {
            foreach ($workers as $worker) {
                $bonus = Bonus::create([
                    'amount'        => $data['amount'],
                    'effective_on'  => $on,
                    'all_employees' => false,
                    'note'          => $data['reason'],
                    'created_by'    => $by,
                ]);
                $bonus->employees()->sync([$worker->id]);

                EmployeeChange::create([
                    'batch'        => $batch,
                    'employee_id'  => $worker->id,
                    'kind'         => EmployeeChange::BONUS,
                    'effective_on' => $on,
                    'to_id'        => $bonus->id,
                    'detail'       => ['amount' => (float) $data['amount'], 'reason' => $data['reason']],
                    'applied_at'   => now(),
                    'created_by'   => $by,
                ]);
            }
        });

        $n      = $workers->count();
        $amount = '₱' . number_format((float) $data['amount'], 2);
        $period = Carbon::parse($opens)->format('M d') . ' – ' . Carbon::parse($closes)->format('M d, Y');

        // One line for the lot, in place of a line per bonus.
        app(ActivityRecorder::class)->noteExplicit('Payroll Settings', null);
        AuditLog::record('Payroll', 'created',
            "Bonus of {$amount} each for {$this->count($n)}, pay period {$period} — {$data['reason']}: "
            . $workers->pluck('name')->implode(', ') . '.');

        return response()->json([
            'success' => true,
            'changed' => $n,
            'skipped' => 0,
            'batch'   => $batch,
            'message' => "{$amount} bonus added to {$this->count($n)}",
        ]);
    }

    /**
     * Take back what one press of Confirm did.
     *
     * Only while that costs nothing: once a day has gone by on a change,
     * putting it back would reprice the day, so it is refused and the office
     * changes the workers again from today instead. A worker somebody has
     * changed again since is left as they are.
     */
    public function undo(Request $request): JsonResponse
    {
        $data = $request->validate(['batch' => 'required|uuid']);
        $rows = EmployeeChange::where('batch', $data['batch'])->orderByDesc('id')->get();

        if ($rows->isEmpty()) {
            return $this->refuse('That change is no longer on file.', 404);
        }

        $isBonus = $rows->contains('kind', EmployeeChange::BONUS);

        // A bonus is money: whoever may give one is who may take it back.
        abort_if($isBonus && ! auth()->user()->isAdmin(), 403);

        $today     = EmployeeChange::earliestDate();
        [$opens]   = EmployeeController::payPeriod();
        $tooLate   = $rows->contains(fn (EmployeeChange $r) => $r->kind === EmployeeChange::BONUS
            ? $r->effective_on->toDateString() < $opens
            : ! $r->isWaiting() && $r->effective_on->toDateString() < $today);

        if ($tooLate) {
            return $this->refuse($isBonus
                ? 'That pay period has closed, so the bonus stays as it was paid.'
                : 'That change has been in effect since ' . $rows->first()->effective_on->format('M d, Y')
                    . '. Change them again from today instead.');
        }

        $undone = 0;

        DB::transaction(function () use ($rows, &$undone) {
            foreach ($rows as $row) {
                $undone += $row->revert() ? 1 : 0;
            }
        });

        $kept = $rows->count() - $undone;
        $what = $isBonus ? 'bonus' : [
            'site' => 'site transfer', 'shift' => 'shift change', 'labor' => 'labor type change', 'overtime' => 'overtime change',
            EmployeeChange::SITE_ADD => 'added site', EmployeeChange::SITE_DROP => 'site removal',
        ][$rows->first()->kind];

        if ($isBonus) {
            app(ActivityRecorder::class)->noteExplicit('Payroll Settings', null);
        }
        AuditLog::record($isBonus ? 'Payroll' : 'Employees', $isBonus ? 'deleted' : 'updated',
            "Undid the {$what} for {$this->count($undone)}"
            . ($kept ? " ({$kept} changed again since, left as they are)" : '') . '.');

        return response()->json([
            'success' => true,
            'undone'  => $undone,
            // Taking back the first allowance takes the rule back with it.
            'overtime_rule' => OvertimePermission::load()->startsOn(),
            'message' => $undone === 0
                ? 'Nothing to undo: they have been changed again since.'
                : 'Undone for ' . $this->count($undone) . ($kept ? "; {$kept} changed again since and left as they are" : ''),
        ]);
    }

    /** Call off a change that is still waiting for its day (the tag on a worker's row). */
    public function cancel(EmployeeChange $change)
    {
        abort_unless($change->isWaiting(), 404);

        $name = $change->employee?->name ?? 'the worker';
        $what = $change->describeTarget();
        $on   = $change->effective_on->format('M d, Y');
        $change->delete();

        AuditLog::record('Employees', 'updated', "Cancelled a change waiting for {$on}: {$name} would have been {$what}.");

        return back()->with('success', "{$name} stays as they are. The change dated {$on} was cancelled.");
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    /** Who, and from when — the half every change asks the same way. */
    private function check(Request $request, array $rules): array
    {
        return $request->validate($rules + [
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'integer',
            'effective_on' => 'required|date|after_or_equal:' . EmployeeChange::earliestDate(),
        ], [
            'effective_on.after_or_equal' => 'Pick today or a later date. Days already worked are not changed.',
        ]);
    }

    /**
     * Put one change on file for everybody it would actually move, and make
     * it now unless it is dated ahead.
     *
     * @param  array<string, string>  $say    the words for this kind of change
     * @param  \Closure|null           $needs  whom it would change; by default
     *                                         those not on $toId already
     */
    private function change(array $data, string $kind, ?int $toId, array $detail, array $say, ?\Closure $needs = null): JsonResponse
    {
        $on     = Carbon::parse($data['effective_on'])->toDateString();
        $later  = $on > EmployeeChange::earliestDate();
        $other  = in_array($kind, [EmployeeChange::SITE_ADD, EmployeeChange::SITE_DROP], true);
        $needs ??= fn (Employee $e) => (int) $e->{EmployeeChange::COLUMNS[$kind]} !== (int) $toId;

        $workers = Employee::active()->whereIn('id', $data['ids'])->orderBy('name')
            ->when($other, fn ($q) => $q->with('otherSites'))->get();
        $moving  = $workers->filter($needs)->values();

        if ($moving->isEmpty()) {
            return $this->refuse(str_replace(':to', $say['to'], $say['already']));
        }

        $batch = (string) Str::uuid();
        $by    = $this->actor();
        $made  = collect();

        DB::transaction(function () use ($moving, $kind, $toId, $detail, $on, $later, $batch, $by, $made, $other) {
            // A newer decision replaces one still waiting for its day. For
            // another site, that is one about the same site, either way.
            EmployeeChange::waiting()
                ->where(fn ($q) => $other
                    ? $q->whereIn('kind', [EmployeeChange::SITE_ADD, EmployeeChange::SITE_DROP])->where('to_id', $toId)
                    : $q->where('kind', $kind))
                ->whereIn('employee_id', $moving->pluck('id'))->delete();

            foreach ($moving as $worker) {
                $change = EmployeeChange::create([
                    'batch'        => $batch,
                    'employee_id'  => $worker->id,
                    'kind'         => $kind,
                    'effective_on' => $on,
                    'to_id'        => $toId,
                    'detail'       => $detail ?: null,
                    'created_by'   => $by,
                ]);
                $change->setRelation('employee', $worker);

                if ($later || $change->apply()) {
                    $made->push($worker);
                }
            }
        });

        if ($made->isEmpty()) {
            return $this->refuse(str_replace(':to', $say['to'], $say['already']));
        }

        $n    = $made->count();
        $date = Carbon::parse($on)->format('M d, Y');
        $fill = fn (string $line, string $who) => str_replace([':n', ':to', ':date'], [$who, $say['to'], $date], $line);

        AuditLog::record('Employees', 'updated',
            $fill($say['log'], $this->count($n)) . ($later ? ", from {$date} (waiting until then)" : ", effective {$date}")
            . ': ' . $made->pluck('name')->implode(', ') . '.');

        return response()->json([
            'success' => true,
            'changed' => $n,
            'skipped' => $workers->count() - $n,
            'later'   => $later,
            'batch'   => $batch,
            'message' => $fill($later ? $say['later'] : $say['done'], (string) $n),
            // The day from which only the workers allowed take overtime,
            // or null while everybody still does.
            'overtime_rule' => OvertimePermission::load()->startsOn(),
        ]);
    }

    private function refuse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function count(int $n): string
    {
        return $n . ' ' . ($n === 1 ? 'employee' : 'employees');
    }

    private function actor(): string
    {
        return auth()->user()->name ?? auth()->user()->username ?? 'admin';
    }
}
