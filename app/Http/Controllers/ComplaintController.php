<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Employee;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Complaints workers posted at the kiosk, for the office to read and answer.
 * The page lists the workers themselves — every active worker, whether or not
 * they have written — with the ones waiting for an answer on top. Picking a
 * worker opens everything they have reported, and the one picked has the
 * answer box. The worker sees the answer the next time they scan in the
 * kiosk's REPORT tab.
 */
class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $show     = in_array($request->query('show'), ['open', 'reported'], true) ? $request->query('show') : 'all';
        $category = array_key_exists((string) $request->query('category'), Complaint::CATEGORIES) ? (string) $request->query('category') : null;
        $siteId   = $request->filled('site') ? (int) $request->query('site') : null;
        $q        = trim((string) $request->query('q'));

        // What each worker has written: how many, how many still open, the
        // oldest open one (how long they have waited) and its topic.
        $agg = Complaint::query()
            ->when($category, fn ($w) => $w->where('category', $category))
            ->selectRaw('employee_id, count(*) as total_n, sum(case when status = ? then 1 else 0 end) as open_n, max(created_at) as last_at', [Complaint::OPEN])
            ->groupBy('employee_id')->get()->keyBy('employee_id');
        $oldestOpen = Complaint::where('status', Complaint::OPEN)
            ->when($category, fn ($w) => $w->where('category', $category))
            ->orderBy('created_at')->get(['id', 'employee_id', 'category', 'created_at', 'message'])
            ->groupBy('employee_id')->map->first();
        // The last report of each worker, for the line under their name.
        $latest = Complaint::query()
            ->when($category, fn ($w) => $w->where('category', $category))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'employee_id', 'category', 'message', 'response', 'status', 'created_at', 'resolved_at'])
            ->unique('employee_id')->keyBy('employee_id');

        // Every active worker, and anyone else who has written.
        $people = Employee::with('site:id,name')
            ->where(fn ($w) => $w->where('status', Employee::STATUS_ACTIVE)->orWhereIn('id', $agg->keys()))
            ->when($siteId, fn ($w) => $w->where('site_id', $siteId))
            ->when($q !== '', fn ($w) => $w->where('name', 'like', '%' . $q . '%'))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'position', 'site_id', 'status'])
            ->each(function (Employee $e) use ($agg, $oldestOpen, $latest) {
                $a = $agg->get($e->id);
                $l = $latest->get($e->id);
                $e->setAttribute('open_n', (int) ($a->open_n ?? 0));
                $e->setAttribute('total_n', (int) ($a->total_n ?? 0));
                $e->setAttribute('last_at', isset($a->last_at) ? Carbon::parse($a->last_at) : null);
                $e->setAttribute('oldest_open', $oldestOpen->get($e->id));
                $e->setAttribute('latest', $l);
                // When the conversation last moved: their report, or the answer to it.
                $e->setAttribute('active_at', $l ? collect([$l->created_at, $l->resolved_at])->filter()->max() : null);
            });

        $counts = [
            'all'      => $people->count(),
            'open'     => $people->where('open_n', '>', 0)->count(),
            'reported' => $people->where('total_n', '>', 0)->count(),
        ];
        $shown = $people->filter(fn ($e) => match ($show) {
            'open'     => $e->open_n > 0,
            'reported' => $e->total_n > 0,
            default    => true,
        });

        // As a chat app lists them: the conversations waiting for an answer
        // on top, then the rest by when they last moved; the workers who have
        // never written below, by name.
        $waiting = $shown->where('open_n', '>', 0)->sortBy(fn ($e) => $e->oldest_open?->created_at)->values();
        $chats   = $waiting->concat(
            $shown->where('open_n', 0)->where('total_n', '>', 0)->sortByDesc(fn ($e) => $e->active_at)->values()
        );
        $others  = $shown->where('total_n', 0)->values();

        // The worker picked: asked for, the owner of the report asked for, or
        // the one who has waited longest. Nobody waiting: the month at a glance.
        $picked = null;
        if ($request->filled('c') && ($c = Complaint::find((int) $request->query('c')))) {
            $picked = $c;
        }
        $employeeId = $request->filled('e') ? (int) $request->query('e') : ($picked->employee_id ?? $waiting->first()?->id);
        $employee   = $employeeId ? ($people->firstWhere('id', $employeeId) ?? Employee::withTrashed()->with('site:id,name')->find($employeeId)) : null;

        $thread = collect();
        $selected = null;
        if ($employee) {
            // The conversation, oldest at the top and newest at the bottom.
            $thread = Complaint::with(['site:id,name', 'kiosk:id,name,code', 'resolver:id,name'])
                ->where('employee_id', $employee->id)->latest()->orderByDesc('id')->limit(100)->get()
                ->reverse()->values();
            // The one the answer box replies to: asked for, else the oldest
            // still open, else the last one.
            $selected = ($picked && (int) $picked->employee_id === (int) $employee->id ? $thread->firstWhere('id', $picked->id) : null)
                ?? $thread->first(fn ($c) => $c->isOpen())
                ?? $thread->last();
        }

        $answered = Complaint::where('status', Complaint::RESOLVED)->whereNotNull('resolved_at');

        return view('complaints.index', [
            'chats'      => $chats,
            'others'     => $others,
            'employee'   => $employee,
            'thread'     => $thread,
            'selected'   => $selected,
            'recent'     => $employee ? collect() : Complaint::with('employee:id,name')->where('status', Complaint::RESOLVED)
                ->whereNotNull('resolved_at')->latest('resolved_at')->limit(5)->get(),
            'topics'     => $employee ? collect() : $this->topics(),
            'show'       => $show,
            'category'   => $category,
            'siteId'     => $siteId,
            'q'          => $q,
            'categories' => Complaint::CATEGORIES,
            'sites'      => Site::orderBy('name')->get(['id', 'name']),
            'counts'     => $counts,
            'stats'      => [
                'open'     => Complaint::where('status', Complaint::OPEN)->count(),
                'week'     => (clone $answered)->where('resolved_at', '>=', now()->startOfWeek())->count(),
                'safety'   => Complaint::where('status', Complaint::OPEN)->where('category', 'safety')->count(),
                'avgHours' => $this->averageHours((clone $answered)->latest('resolved_at')->limit(50)->get(['created_at', 'resolved_at'])),
            ],
        ]);
    }

    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'action'   => ['required', Rule::in(['resolve', 'save', 'reopen'])],
            'response' => 'nullable|string|max:1000',
        ]);

        $back = fn (string $msg) => redirect()->route('complaints.index', array_filter($request->only(['show', 'category', 'site', 'q'])
                + ['e' => $complaint->employee_id, 'c' => $complaint->id]))
            ->with('success', $msg);

        if ($data['action'] === 'reopen') {
            $complaint->update(['status' => Complaint::OPEN, 'resolved_by' => null, 'resolved_at' => null]);

            return $back('Complaint #' . $complaint->id . ' reopened.');
        }

        if ($data['action'] === 'save') {
            $complaint->update(['response' => $data['response'] ?? null]);

            return $back('Answer saved. The worker sees it the next time they scan.');
        }

        $complaint->update([
            'status'      => Complaint::RESOLVED,
            'response'    => $data['response'] ?? $complaint->response,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return $back('Complaint #' . $complaint->id . ' answered and resolved.');
    }

    /** What the workers wrote about over the last 30 days, the most first. */
    private function topics(): Collection
    {
        return Complaint::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')
            ->map(fn ($n) => (int) $n)->sortDesc();
    }

    /** Average hours from posted to answered, over the latest answers. */
    private function averageHours($rows): ?float
    {
        $rows = $rows->filter(fn ($c) => $c->created_at && $c->resolved_at);
        if ($rows->isEmpty()) {
            return null;
        }

        return round($rows->avg(fn ($c) => $c->created_at->diffInMinutes($c->resolved_at, true) / 60), 1);
    }
}
