<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Complaints workers posted at the kiosk, for the office to read and answer
 * (Complaints, under Leave & Advances in the sidebar). Grouped by site or by employee; the one
 * picked opens beside the list with its answer box. The worker sees the
 * answer and the status the next time they scan in the kiosk's REPORT tab.
 */
class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $status   = in_array($request->query('status'), ['resolved', 'all'], true) ? $request->query('status') : 'open';
        $group    = $request->query('group') === 'employee' ? 'employee' : 'site';
        $category = array_key_exists((string) $request->query('category'), Complaint::CATEGORIES) ? (string) $request->query('category') : null;
        $siteId   = $request->filled('site') ? (int) $request->query('site') : null;
        $q        = trim((string) $request->query('q'));

        $list = Complaint::with(['employee:id,name,position,site_id', 'employee.site:id,name', 'site:id,name', 'kiosk:id,name,code', 'resolver:id,name'])
            ->when($status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($category, fn ($w) => $w->where('category', $category))
            ->when($siteId, fn ($w) => $w->where('site_id', $siteId))
            ->when($q !== '', fn ($w) => $w->whereHas('employee', fn ($e) => $e->where('name', 'like', '%' . $q . '%')))
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        // Grouped as asked: by the site it came from, or by the worker.
        $groups = $list->groupBy(fn (Complaint $c) => $group === 'site'
                ? ($c->site->name ?? $c->employee?->site?->name ?? __('No site'))
                : ($c->employee->name ?? __('Unknown')))
            ->sortKeys();

        $selected = $request->filled('c')
            ? Complaint::with(['employee.site', 'site', 'kiosk', 'resolver'])->find((int) $request->query('c'))
            : $list->first();

        $answered = Complaint::where('status', Complaint::RESOLVED)->whereNotNull('resolved_at');

        return view('complaints.index', [
            'groups'     => $groups,
            'total'      => $list->count(),
            'selected'   => $selected,
            'status'     => $status,
            'group'      => $group,
            'category'   => $category,
            'siteId'     => $siteId,
            'q'          => $q,
            'categories' => Complaint::CATEGORIES,
            'sites'      => Site::orderBy('name')->get(['id', 'name']),
            'counts'     => [
                'open'     => Complaint::where('status', Complaint::OPEN)->count(),
                'resolved' => Complaint::where('status', Complaint::RESOLVED)->count(),
                'all'      => Complaint::count(),
            ],
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

        $back = fn (string $msg) => redirect()->route('complaints.index', array_filter($request->only(['status', 'group', 'category', 'site', 'q']) + ['c' => $complaint->id]))
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
