<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Read-only. There is no write, update or delete action on this screen — the
 * one thing it writes is a line saying the log was exported.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    /** Where a subject's link goes, when the thing still exists. */
    private const LINKS = [
        'User'              => 'users-roles.index',
        'Employee'          => 'employees.show',
        'Loan'              => 'leave.index',
        'LeaveRequest'      => 'leave.index',
        'Site'              => 'sites.index',
        'Kiosk'             => 'devices.index',
        'SystemSetting'     => 'system-settings.about',
        'Attendance'        => 'attendance',
    ];

    /**
     * The Audit Logs page is a section of System Settings now (Michael,
     * 2026-09-26). Its address, and every link carrying filters, lands there
     * with the same filters.
     */
    public function index(Request $request)
    {
        return redirect()->route('system-settings.about', array_merge($request->query(), ['section' => 'audit']));
    }

    /**
     * What the Audit logs section shows: the entries the filters leave, fifty
     * to a page, newest first — the same period (seven days unless asked),
     * search, area, action, person, quick and record filters as before, and
     * the export takes the same ones.
     */
    public function feed(Request $request): array
    {
        [$from, $to, $range] = $this->period($request);

        $logs = $this->query($request, $from, $to)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withPath(route('system-settings.about'))
            ->appends(array_merge($request->except('page'), ['section' => 'audit']));

        $facetBase = $this->query($request, $from, $to, false);

        return [
            'logs'     => $logs,
            'range'    => $range,
            'from'     => $from,
            'to'       => $to,
            'modules'  => (clone $facetBase)->selectRaw('module as k, COUNT(*) as n')->groupBy('module')->orderByDesc('n')->pluck('n', 'k'),
            'actions'  => (clone $facetBase)->selectRaw('action as k, COUNT(*) as n')->groupBy('action')->orderByDesc('n')->pluck('n', 'k'),
            'people'   => (clone $facetBase)->selectRaw("COALESCE(user_name, 'System') as k, COUNT(*) as n")
                ->groupBy('k')->orderByDesc('n')->limit(20)->pluck('n', 'k'),
            'subjects' => $this->subjects(collect($logs->items())),
            'selected' => [
                'module' => $this->many($request, 'module'),
                'action' => $this->many($request, 'action'),
                'person' => $this->many($request, 'person'),
            ],
            'q'        => (string) $request->query('q', ''),
            'quick'    => (string) $request->query('quick', ''),
            'subject'  => $request->filled('subject_type') && $request->filled('subject_id')
                ? $request->query('subject_type') . ' #' . $request->query('subject_id') : null,
        ];
    }

    /** Exactly the entries the current filters show, as a CSV. */
    public function export(Request $request)
    {
        [$from, $to] = $this->period($request);
        $query = $this->query($request, $from, $to);
        $count = (clone $query)->count();

        AuditLog::record('Audit Logs', 'exported', 'Exported ' . $count . ' ' . Str::plural('entry', $count) . ' as CSV');

        $query->orderByDesc('created_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Person', 'Module', 'Action', 'Description', 'Subject', 'IP address', 'Device']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    fputcsv($out, array_map([$this, 'cell'], [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->user_name ?: 'System',
                        $log->module,
                        $log->action,
                        $log->description,
                        $log->subject_type ? $log->subject_type . ' #' . $log->subject_id : '',
                        $log->ip_address,
                        $log->device,
                    ]));
                }
            });

            fclose($out);
        }, 'audit-log-' . now()->format('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A spreadsheet must not read a worker's name as a formula. */
    public function cell($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    // ── Filters ─────────────────────────────────────────────────────────────

    /** [from, to, range]; seven days unless asked otherwise. */
    private function period(Request $request): array
    {
        $parse = fn ($v) => rescue(fn () => Carbon::parse($v), null, false);

        if ($request->filled('from') || $request->filled('to')) {
            $from = $request->filled('from') ? $parse($request->query('from'))?->startOfDay() : null;
            $to   = $request->filled('to') ? $parse($request->query('to'))?->endOfDay() : null;

            return [$from ?? Carbon::create(2000, 1, 1), $to ?? now()->endOfDay(), 'custom'];
        }

        $range = in_array($request->query('range'), ['today', '7', '30', 'all'], true) ? $request->query('range') : '7';

        $from = match ($range) {
            'today' => now()->startOfDay(),
            '30'    => now()->subDays(29)->startOfDay(),
            'all'   => Carbon::create(2000, 1, 1),
            default => now()->subDays(6)->startOfDay(),
        };

        return [$from, now()->endOfDay(), $range];
    }

    private function query(Request $request, $from, $to, bool $facets = true): Builder
    {
        $q = AuditLog::query()->whereBetween('created_at', [$from, $to]);

        if ($request->filled('q')) {
            $term = '%' . $request->query('q') . '%';
            $q->where(fn ($w) => $w->where('description', 'like', $term)->orWhere('user_name', 'like', $term));
        }

        if ($request->query('quick') === 'sensitive') {
            $q->sensitive();
        } elseif ($request->query('quick') === 'mine') {
            $q->where('user_id', auth()->id());
        }

        if ($request->filled('subject_type') && $request->filled('subject_id')) {
            $q->where('subject_type', $request->query('subject_type'))->where('subject_id', $request->query('subject_id'));
        }

        if ($facets) {
            if ($modules = $this->many($request, 'module')) {
                $q->whereIn('module', $modules);
            }
            if ($actions = $this->many($request, 'action')) {
                $q->whereIn('action', $actions);
            }
            if ($people = $this->many($request, 'person')) {
                $q->where(fn ($w) => $w->whereIn('user_name', $people)
                    ->when(in_array('System', $people, true), fn ($s) => $s->orWhereNull('user_name')));
            }
            if ($request->filled('user_id')) {
                $q->where('user_id', $request->query('user_id'));
            }
        }

        return $q;
    }

    /** A filter that may arrive as one value or as a list. */
    private function many(Request $request, string $key): array
    {
        return array_values(array_filter((array) $request->query($key, []), fn ($v) => is_string($v) && $v !== ''));
    }

    // ── Subjects ────────────────────────────────────────────────────────────

    /** 'Type#id' => [label, gone, url] for the entries on this page. */
    private function subjects(Collection $logs): array
    {
        $out = [];

        foreach ($logs->whereNotNull('subject_type')->groupBy('subject_type') as $type => $rows) {
            $class = 'App\\Models\\' . $type;
            $ids   = $rows->pluck('subject_id')->filter()->unique()->values();

            $found = class_exists($class)
                ? $class::query()->withoutGlobalScopes()->whereKey($ids)->get()->keyBy(fn ($m) => $m->getKey())
                : collect();

            foreach ($ids as $id) {
                $model = $found->get($id);

                $out[$type . '#' . $id] = [
                    'label' => Str::headline($type) . ' #' . $id . ($model ? ' · ' . $this->nameFor($model) : ''),
                    'gone'  => ! $model,
                    'url'   => $model ? $this->urlFor($type, $id) : null,
                ];
            }
        }

        return $out;
    }

    private function nameFor($model): string
    {
        foreach (['name', 'code', 'title', 'username'] as $key) {
            $value = $model->getAttributes()[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return Str::limit($value, 50);
            }
        }

        $employee = $model->getAttributes()['employee_id'] ?? null;

        return $employee ? (string) Employee::withTrashed()->whereKey($employee)->value('name') : 'record';
    }

    private function urlFor(string $type, $id): ?string
    {
        $route = self::LINKS[$type] ?? null;

        if (! $route || ! Route::has($route)) {
            return null;
        }

        return match ($type) {
            'User'                     => route($route, ['account' => $id]),
            'Employee', 'PayrollRun'   => route($route, $id),
            'Loan'                     => route($route, ['tab' => 'advances']),
            default                    => route($route),
        };
    }
}
