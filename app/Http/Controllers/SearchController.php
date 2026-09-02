<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LaborType;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Unified global search across all modules. A single gather() powers both the
 * topbar autocomplete (suggestions) and the full results page (search), so they
 * always stay consistent. Uses "contains" matching and links every result to
 * its correct, live destination.
 */
class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if (strlen($q) < 2) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Type at least 2 characters']);
            }
            return view('search.results', ['query' => $q, 'results' => ['categories' => [], 'total' => 0]]);
        }

        $categories = $this->gather($q, 9);
        $total = array_sum(array_map(fn ($c) => count($c['items']), $categories));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['categories' => $categories, 'total' => $total]]);
        }

        return view('search.results', ['query' => $q, 'results' => ['categories' => $categories, 'total' => $total]]);
    }

    /**
     * Real-time autocomplete suggestions for the topbar (flat list).
     */
    public function suggestions(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $categories = $this->gather($q, 5);

        $out = [];
        foreach ($categories as $cat) {
            foreach ($cat['items'] as $item) {
                $out[] = [
                    'text'     => $item['title'],
                    'category' => $cat['label'],
                    'url'      => $item['url'],
                    'icon'     => $cat['icon'],
                ];
            }
        }

        return response()->json(array_slice($out, 0, 12));
    }

    /**
     * Search every module once and return grouped, correctly-linked results.
     */
    private function gather(string $q, int $perType): array
    {
        $like    = '%' . $q . '%';
        $idQuery = ltrim($q, '#');
        $isId    = ctype_digit($idQuery);
        $idVal   = (int) $idQuery;

        $categories = [];

        // ===== EMPLOYEES (name, position, or Employee ID) =====
        $employees = Employee::where(function ($w) use ($like, $isId, $idVal) {
                $w->where('name', 'LIKE', $like)
                  ->orWhere('position', 'LIKE', $like);
                if ($isId) {
                    $w->orWhere('id', $idVal);
                }
            })
            ->orderBy('name')
            ->limit($perType)
            ->get(['id', 'name', 'position']);

        if ($employees->isNotEmpty()) {
            $categories['employees'] = [
                'label' => 'Employees',
                'icon'  => 'users',
                'items' => $employees->map(fn ($e) => [
                    'title'    => $e->name,
                    'subtitle' => 'Employee #' . $e->id . ($e->position ? ' · ' . $e->position : ''),
                    'url'      => route('employees.edit', $e->id),
                ])->all(),
            ];

            // ===== PAYROLL (each matched employee → their payroll records) =====
            $categories['payroll'] = [
                'label' => 'Payroll',
                'icon'  => 'wallet',
                'items' => $employees->map(fn ($e) => [
                    'title'    => $e->name,
                    'subtitle' => 'Payroll records & payslip',
                    'url'      => route('payroll-records', ['employee' => $e->id]),
                ])->all(),
            ];
        }

        // ===== ATTENDANCE (employee, date, or session) =====
        $attendance = Attendance::with('employee:id,name')
            ->where(function ($w) use ($like) {
                $w->whereHas('employee', fn ($e) => $e->where('name', 'LIKE', $like)->orWhere('position', 'LIKE', $like))
                  ->orWhere('date', 'LIKE', $like)
                  ->orWhere('session', 'LIKE', $like);
            })
            ->orderBy('date', 'desc')
            ->limit($perType)
            ->get();

        if ($attendance->isNotEmpty()) {
            $categories['attendance'] = [
                'label' => 'Attendance',
                'icon'  => 'calendar-check',
                'items' => $attendance->map(fn ($a) => [
                    'title'    => ($a->employee->name ?? 'Unknown') . ' — ' . Carbon::parse($a->date)->format('m/d/Y'),
                    'subtitle' => 'Attendance · ' . ($a->session ?? '—') . ' · ' . ($a->time_in ? 'Present' : 'Absent'),
                    'url'      => route('attendance'),
                ])->all(),
            ];
        }

        // ===== LABOR TYPES =====
        $labor = LaborType::where('name', 'LIKE', $like)->limit($perType)->get(['id', 'name', 'daily_rate']);
        if ($labor->isNotEmpty()) {
            $categories['labor'] = [
                'label' => 'Labor Types',
                'icon'  => 'tag',
                'items' => $labor->map(fn ($l) => [
                    'title'    => $l->name,
                    'subtitle' => 'Labor type · ₱' . number_format($l->daily_rate, 2) . '/day',
                    'url'      => route('settings.index', ['tab' => 'labor']),
                ])->all(),
            ];
        }

        // ===== HOLIDAYS =====
        $holidays = Holiday::where('date', 'LIKE', $like)
            ->orWhere('title', 'LIKE', $like)
            ->orderBy('date', 'desc')
            ->limit($perType)
            ->get();
        if ($holidays->isNotEmpty()) {
            $categories['holidays'] = [
                'label' => 'Holidays',
                'icon'  => 'calendar',
                'items' => $holidays->map(fn ($h) => [
                    'title'    => Carbon::parse($h->date)->format('m/d/Y'),
                    'subtitle' => 'Holiday' . ($h->title ? ' · ' . $h->title : ''),
                    'url'      => route('settings.index', ['tab' => 'holiday']),
                ])->all(),
            ];
        }

        // ===== PAGES / MODULE SHORTCUTS (command-palette style) =====
        $needle = strtolower($q);
        $pages = array_values(array_filter($this->pageIndex(), function ($p) use ($needle) {
            return str_contains(strtolower($p['title']), $needle) || str_contains($p['keywords'], $needle);
        }));
        if ($pages) {
            $categories['pages'] = [
                'label' => 'Pages',
                'icon'  => 'layout-dashboard',
                'items' => array_map(
                    fn ($p) => ['title' => $p['title'], 'subtitle' => $p['subtitle'], 'url' => $p['url']],
                    array_slice($pages, 0, $perType)
                ),
            ];
        }

        return $categories;
    }

    /**
     * Static index of navigable modules so a search like "payroll" or
     * "settings" jumps straight to the page.
     */
    private function pageIndex(): array
    {
        return [
            ['title' => 'Dashboard',       'subtitle' => 'Overview & live stats',           'url' => url('/dashboard'),                          'keywords' => 'home overview main dashboard'],
            ['title' => 'Attendance',      'subtitle' => 'Attendance monitoring',           'url' => route('attendance'),                        'keywords' => 'attendance time in out present absent kiosk holiday'],
            ['title' => 'Employees',       'subtitle' => 'Employee directory',              'url' => route('employees.index'),                   'keywords' => 'employees workers staff personnel directory'],
            ['title' => 'Payroll Records', 'subtitle' => 'Reports, payroll & pay periods',  'url' => route('payroll-records'),                   'keywords' => 'payroll reports payslip pay period weekly daily salary records gross net deductions overtime bonus'],
            ['title' => 'Analytics',       'subtitle' => 'Insights & charts',               'url' => route('analytics'),                         'keywords' => 'analytics insights charts graphs'],
            ['title' => 'Jeyanco AI',      'subtitle' => 'AI assistant',                    'url' => route('ai-assistant'),                      'keywords' => 'ai assistant chatbot jeyanco intelligence'],
            ['title' => 'Settings',        'subtitle' => 'Payroll config, labor, holidays', 'url' => route('settings.index'),                    'keywords' => 'settings config sss philhealth pagibig overtime bonus holiday labor rate multiplier'],
        ];
    }
}
