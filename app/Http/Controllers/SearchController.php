<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\Site;
use App\Models\User;
use App\Support\Modules;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The top bar's search: every page, section and record of the system, from
 * one box (Michael, 2026-10-02: "dapat lahat na a-access").
 *
 * A single gather() powers both the topbar suggestions and the full results
 * page, so they always agree. Two rules hold for every result:
 *
 *  - it links to where the thing really is — the tab, the section, the row's
 *    own filter — not to the top of a page that has to be searched again;
 *  - it is offered only to an account that can open it. An HR account is not
 *    shown a link that would answer 403, nor the name of an account, a rate
 *    or an audit entry it could not read on the page itself.
 */
class SearchController extends Controller
{
    /** Open to an Administrator only (the 'is_admin' routes). */
    private const ADMIN = 'admin';

    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Type at least 2 characters']);
            }
            return view('search.results', ['query' => $q, 'results' => ['categories' => [], 'total' => 0]]);
        }

        $categories = $this->gather($q, 9, $request->user());
        $total = array_sum(array_map(fn ($c) => count($c['items']), $categories));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['categories' => $categories, 'total' => $total]]);
        }

        return view('search.results', ['query' => $q, 'results' => ['categories' => $categories, 'total' => $total]]);
    }

    /**
     * What the topbar shows as you type (a flat list, in category order).
     *
     * With nothing typed it is the map of the system: every page and section
     * this account can open, under the sidebar's own headings. One character
     * narrows that map; records are searched from the second character on.
     */
    public function suggestions(Request $request)
    {
        $q    = trim((string) $request->input('q', ''));
        $user = $request->user();

        if (mb_strlen($q) < 2) {
            $pages = $this->matchPages($q, $user);

            return response()->json(array_map(fn ($p) => [
                'text'     => $p['title'],
                'subtitle' => $p['subtitle'],
                'category' => $p['group'],
                'url'      => $p['url'],
                'icon'     => $p['icon'],
            ], $pages));
        }

        $out = [];
        foreach ($this->gather($q, 4, $user) as $cat) {
            foreach ($cat['items'] as $item) {
                $out[] = [
                    'text'     => $item['title'],
                    'subtitle' => $item['subtitle'],
                    'category' => $cat['label'],
                    'url'      => $item['url'],
                    'icon'     => $item['icon'] ?? $cat['icon'],
                ];
            }
        }

        return response()->json(array_slice($out, 0, 24));
    }

    /**
     * Search every module once and return grouped, correctly-linked results.
     */
    private function gather(string $q, int $perType, ?User $user): array
    {
        $like    = '%' . $q . '%';
        $idQuery = ltrim($q, '#');
        $isId    = ctype_digit($idQuery);
        $idVal   = (int) $idQuery;
        $date    = $this->asDate($q);
        $can     = fn (?string $gate) => $this->allows($user, $gate);

        $categories = [];

        // ===== PAGES, SECTIONS AND ACTIONS =====
        // First: "settings" or "remittance" is a place to go, and the list
        // under the box is short.
        $pages = $this->matchPages($q, $user);
        if ($pages) {
            $categories['pages'] = [
                'label' => 'Pages',
                'icon'  => 'layout-dashboard',
                'items' => array_map(
                    fn ($p) => ['title' => $p['title'], 'subtitle' => $p['subtitle'], 'url' => $p['url'], 'icon' => $p['icon']],
                    array_slice($pages, 0, max($perType, 6))
                ),
            ];
        }

        // ===== EMPLOYEES (name, position, or Employee ID) =====
        // Everybody still on file: the workforce, somebody waiting on a
        // fingerprint, somebody removed who can be restored. Not a worker
        // deleted for good — that one is off every list.
        $employees = Employee::withTrashed()
            ->where('status', '!=', Employee::STATUS_DELETED)
            ->where(function ($w) use ($like, $isId, $idVal) {
                $w->where('name', 'LIKE', $like)
                  ->orWhere('position', 'LIKE', $like);
                if ($isId) {
                    $w->orWhere('id', $idVal);
                }
            })
            ->orderBy('name')
            ->limit($perType)
            ->get(['id', 'name', 'position', 'status', 'deleted_at']);

        if ($employees->isNotEmpty()) {
            $categories['employees'] = [
                'label' => 'Employees',
                'icon'  => 'users',
                'items' => $employees->map(fn (Employee $e) => [
                    'title'    => $e->name,
                    'subtitle' => 'Employee #' . $e->id . ($e->position ? ' · ' . $e->position : '') . $this->standing($e),
                    'url'      => match (true) {
                        $e->trashed()   => route('employees.register', ['tab' => 'removed']),
                        $e->isPending() => route('employees.register', ['tab' => 'pending']),
                        default         => route('employees.show', $e->id),
                    },
                ])->all(),
            ];

            // ===== PAYROLL (each matched worker → their payroll records) =====
            // The workforce proper: a pending name has no pay to show.
            $paid = $employees->reject(fn (Employee $e) => $e->isPending());
            if ($paid->isNotEmpty()) {
                $categories['payroll'] = [
                    'label' => 'Payroll',
                    'icon'  => 'wallet',
                    'items' => $paid->map(fn (Employee $e) => [
                        'title'    => $e->name,
                        'subtitle' => 'Payroll records & payslip',
                        'url'      => route('payroll-records', ['employee' => $e->id]),
                    ])->values()->all(),
                ];
            }
        }

        // ===== ATTENDANCE (employee, date, or session) =====
        $attendance = Attendance::with('employee:id,name')
            ->where(function ($w) use ($like, $date) {
                $w->whereHas('employee', fn ($e) => $e->where('name', 'LIKE', $like)->orWhere('position', 'LIKE', $like))
                  ->orWhere('date', 'LIKE', $like)
                  ->orWhere('session', 'LIKE', $like);
                if ($date) {
                    $w->orWhere('date', $date);
                }
            })
            ->orderBy('date', 'desc')
            ->limit($perType)
            ->get();

        if ($attendance->isNotEmpty()) {
            $today = Carbon::today()->toDateString();

            $categories['attendance'] = [
                'label' => 'Attendance',
                'icon'  => 'calendar-check',
                'items' => $attendance->map(function ($a) use ($today) {
                    $name = $a->employee->name ?? 'Unknown';
                    $day  = Carbon::parse($a->date);

                    return [
                        'title'    => $name . ' — ' . $day->format('m/d/Y'),
                        'subtitle' => 'Attendance · ' . ($a->session ?? '—') . ' · ' . ($a->time_in ? 'Present' : 'Absent'),
                        // The row itself: the list it is on, narrowed to the worker.
                        'url'      => route('attendance', array_filter([
                            'tab' => $day->toDateString() === $today ? null : 'history',
                            'q'   => $a->employee->name ?? null,
                        ])),
                    ];
                })->all(),
            ];
        }

        // ===== SITES =====
        $sites = Site::where('name', 'LIKE', $like)->orWhere('location', 'LIKE', $like)
            ->orderBy('name')->limit($perType)->get(['id', 'name', 'location']);
        if ($sites->isNotEmpty()) {
            $categories['sites'] = [
                'label' => 'Sites',
                'icon'  => 'map-pin',
                'items' => $sites->map(fn (Site $s) => [
                    'title'    => $s->name,
                    'subtitle' => 'Site' . ($s->location ? ' · ' . $s->location : ''),
                    'url'      => route('sites.index'),
                ])->all(),
            ];
        }

        // ===== LEAVE =====
        if ($can(Modules::LEAVE)) {
            $types = array_keys(array_filter(LeaveRequest::TYPES, fn ($label) => stripos($label, $q) !== false));

            $leave = LeaveRequest::with('employee:id,name')
                ->where(function ($w) use ($like, $types, $date) {
                    $w->whereHas('employee', fn ($e) => $e->where('name', 'LIKE', $like))
                      ->orWhere('reason', 'LIKE', $like);
                    if ($types) {
                        $w->orWhereIn('leave_type', $types);
                    }
                    if ($date) {
                        $w->orWhere(fn ($d) => $d->where('starts_on', '<=', $date)->where('ends_on', '>=', $date));
                    }
                })
                ->orderByDesc('starts_on')
                ->limit($perType)
                ->get();

            if ($leave->isNotEmpty()) {
                $categories['leave'] = [
                    'label' => 'Leave',
                    'icon'  => 'calendar-days',
                    'items' => $leave->map(fn (LeaveRequest $l) => [
                        'title'    => ($l->employee->name ?? 'Unknown') . ' — ' . (LeaveRequest::TYPES[$l->leave_type] ?? 'Leave'),
                        'subtitle' => 'Leave · ' . $this->span($l->starts_on, $l->ends_on) . ' · ' . (LeaveRequest::STATUSES[$l->status] ?? ucfirst((string) $l->status)),
                        'url'      => route('leave.index', array_filter(['q' => $l->employee->name ?? null])),
                    ])->all(),
                ];
            }
        }

        // ===== CASH ADVANCES =====
        if ($can(Modules::LEAVE) && $can(Modules::ADVANCES)) {
            $advances = Loan::listed()->with('employee:id,name')
                ->where(function ($w) use ($like) {
                    $w->whereHas('employee', fn ($e) => $e->where('name', 'LIKE', $like))
                      ->orWhere('reference', 'LIKE', $like);
                })
                ->orderByDesc('updated_at')
                ->limit($perType)
                ->get();

            if ($advances->isNotEmpty()) {
                $categories['advances'] = [
                    'label' => 'Cash Advances',
                    'icon'  => 'hand-coins',
                    'items' => $advances->map(fn (Loan $a) => [
                        'title'    => ($a->employee->name ?? 'Unknown') . ' — ₱' . number_format((float) $a->principal, 2),
                        'subtitle' => 'Cash advance' . ($a->reference ? ' · ' . $a->reference : '') . ' · ' . (Loan::STATUSES[$a->status] ?? ucfirst((string) $a->status)),
                        'url'      => route('leave.index', array_filter(['tab' => 'advances', 'q' => $a->employee->name ?? null])),
                    ])->all(),
                ];
            }
        }

        // ===== ACCOUNTS (Users & Roles is the Administrator's) =====
        if ($can(self::ADMIN)) {
            $accounts = User::where(fn ($w) => $w->where('name', 'LIKE', $like)
                    ->orWhere('username', 'LIKE', $like)
                    ->orWhere('email', 'LIKE', $like))
                ->orderBy('name')->limit($perType)->get();

            if ($accounts->isNotEmpty()) {
                $categories['accounts'] = [
                    'label' => 'Accounts',
                    'icon'  => 'shield-check',
                    'items' => $accounts->map(fn (User $u) => [
                        'title'    => $u->name,
                        'subtitle' => 'Account · ' . $u->username . ' · ' . $u->role_label . ($u->is_active ? '' : ' · Disabled'),
                        'url'      => route('users-roles.index', ['account' => $u->id]),
                    ])->all(),
                ];
            }
        }

        // ===== KIOSKS =====
        if ($can(Modules::DEVICES)) {
            $kiosks = Kiosk::with('site:id,name')
                ->where(fn ($w) => $w->where('name', 'LIKE', $like)
                    ->orWhere('code', 'LIKE', $like)
                    ->orWhere('location', 'LIKE', $like))
                ->orderBy('name')->limit($perType)->get();

            if ($kiosks->isNotEmpty()) {
                $categories['kiosks'] = [
                    'label' => 'Kiosks',
                    'icon'  => 'monitor-smartphone',
                    'items' => $kiosks->map(fn (Kiosk $k) => [
                        'title'    => $k->name,
                        'subtitle' => 'Kiosk · ' . ($k->site->name ?? 'Unassigned'),
                        'url'      => route('devices.index', ['kiosk' => $k->id]),
                    ])->all(),
                ];
            }
        }

        // ===== LABOR TYPES and HOLIDAYS (Payroll Settings: Administrator) =====
        if ($can(self::ADMIN)) {
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

            $holidays = Holiday::where('is_official', true)
                ->where(function ($w) use ($like, $date) {
                    $w->where('date', 'LIKE', $like)->orWhere('title', 'LIKE', $like);
                    if ($date) {
                        $w->orWhere('date', $date);
                    }
                })
                ->orderBy('date', 'desc')
                ->limit($perType)
                ->get();
            if ($holidays->isNotEmpty()) {
                $categories['holidays'] = [
                    'label' => 'Holidays',
                    'icon'  => 'calendar',
                    'items' => $holidays->map(fn ($h) => [
                        'title'    => $h->title ?: Carbon::parse($h->date)->format('m/d/Y'),
                        'subtitle' => 'Holiday · ' . Carbon::parse($h->date)->format('M j, Y'),
                        'url'      => route('settings.index', ['tab' => 'holiday']),
                    ])->all(),
                ];
            }

            // ===== AUDIT LOGS =====
            // One line, not the entries: the section has the filters, the
            // paging and the export, and opens already searched.
            $mentions = AuditLog::where(fn ($w) => $w->where('description', 'LIKE', $like)->orWhere('user_name', 'LIKE', $like))->count();
            if ($mentions > 0) {
                $categories['audit'] = [
                    'label' => 'Audit Logs',
                    'icon'  => 'scroll-text',
                    'items' => [[
                        'title'    => $mentions . ' audit log ' . ($mentions === 1 ? 'entry' : 'entries') . ' for "' . $q . '"',
                        'subtitle' => 'System Settings · Audit logs',
                        'url'      => route('system-settings.about', ['section' => 'audit', 'q' => $q]),
                    ]],
                ];
            }
        }

        return $categories;
    }

    /** May this account open what the gate guards? null is every signed-in account. */
    private function allows(?User $user, ?string $gate): bool
    {
        return match (true) {
            $gate === null       => true,
            $user === null       => false,
            $gate === self::ADMIN => $user->isAdmin(),
            default              => $user->canAccessModule($gate),
        };
    }

    /** What to say after a worker's position when they are not on the active list. */
    private function standing(Employee $e): string
    {
        return match (true) {
            $e->trashed()    => ' · Removed',
            $e->isPending()  => ' · Pending registration',
            $e->isArchived() => ' · Archived',
            default          => '',
        };
    }

    private function span($from, $to): string
    {
        $from = Carbon::parse($from);
        $to   = Carbon::parse($to);

        return $from->equalTo($to)
            ? $from->format('M j, Y')
            : $from->format('M j') . ' – ' . $to->format('M j, Y');
    }

    /**
     * The query as a calendar day, when it is written as one. The date
     * columns hold 2026-09-15; the office types 09/15/2026 or Sep 15, 2026.
     */
    private function asDate(string $q): ?string
    {
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'M j, Y', 'M j Y', 'F j, Y', 'F j Y'] as $format) {
            try {
                $day = Carbon::createFromFormat('!' . $format, $q);
            } catch (\Throwable $e) {
                continue;
            }
            // Strict: createFromFormat rolls 02/31 over into March.
            if ($day && strcasecmp($day->format($format), $q) === 0) {
                return $day->toDateString();
            }
        }

        return null;
    }

    /**
     * The pages this account can open that the query names, best match first:
     * a title that starts with it, then a title that holds every word, then
     * one found by where it lives or its keywords. Every word typed has to be
     * found, in any order, so "settings payroll" finds Payroll Settings. An
     * empty query is every page, in the sidebar's order.
     */
    private function matchPages(string $q, ?User $user): array
    {
        $pages = array_values(array_filter($this->pageIndex(), fn ($p) => $this->allows($user, $p['gate'])));

        $needle = mb_strtolower(trim($q));
        if ($needle === '') {
            return $pages;
        }
        $words = preg_split('/\s+/', $needle);
        // A single letter is in nearly every title; it has to start a word of one.
        $short = mb_strlen($needle) < 2;

        $ranked = [];
        foreach ($pages as $i => $p) {
            $title = mb_strtolower($p['title']);
            $rest  = mb_strtolower($p['subtitle']) . ' ' . $p['keywords'];

            $inTitle = true;
            foreach ($words as $word) {
                $starts = '/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '/u';

                if ($short ? preg_match($starts, $title) : str_contains($title, $word)) {
                    continue;
                }
                $inTitle = false;
                // Elsewhere a word has to begin with it: "ot" is overtime's
                // keyword, not the middle of "notifications".
                if ($short || ! preg_match($starts, $rest)) {
                    continue 2;
                }
            }

            $rank = match (true) {
                str_starts_with($title, $needle) => 0,
                $inTitle                         => 1,
                default                          => 2,
            };
            $ranked[] = [$rank, $i, $p];
        }

        usort($ranked, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($ranked, 2);
    }

    /**
     * Every place in the system, in the sidebar's order: each page, each
     * section a page is split into, and the few actions that have an address
     * of their own. 'gate' is who may open it — null for every account,
     * ADMIN, or a module key from App\Support\Modules.
     *
     * A new page or section belongs here as well as on the rail; the test
     * (GlobalSearchTest) opens every address on this list.
     */
    private function pageIndex(): array
    {
        $page = fn (string $group, string $title, string $subtitle, string $url, string $icon, string $keywords, ?string $gate = null)
            => compact('group', 'title', 'subtitle', 'url', 'icon', 'keywords', 'gate');

        $settings = fn (string $tab) => route('settings.index', ['tab' => $tab]);
        $system   = fn (string $section) => route('system-settings.about', ['section' => $section]);
        $report   = fn (string $report) => route('payroll-reports.index', ['report' => $report]);

        return [
            // ── MAIN ─────────────────────────────────────────────────────
            $page('Main', 'Dashboard', 'Overview, live stats and the site map', route('dashboard'), 'layout-dashboard',
                'home overview main dashboard summary map stats today'),

            // ── WORKFORCE ────────────────────────────────────────────────
            $page('Workforce', 'Attendance', 'Who is in today', route('attendance'), 'calendar-check',
                'attendance time in out present absent kiosk scan today clock working break'),
            $page('Workforce', 'Attendance history', 'Attendance · every past day', route('attendance', ['tab' => 'history']), 'history',
                'attendance history past days records log dtr time record'),
            $page('Workforce', 'Needs review', 'Attendance · missed sign-outs and days with no break scans', route('attendance', ['view' => 'missed']), 'alert-triangle',
                'attendance needs review missed sign out time out no break not recorded invalid held unpaid'),
            $page('Workforce', 'Employees', 'The workforce: register, edit, export, bonus', route('employees.register'), 'users',
                'employees workers staff personnel directory register manage list crew fingerprint'),
            $page('Workforce', 'Add employee', 'Employees · register a new worker', route('employees.create'), 'user-plus',
                'add new employee worker register create hire enroll fingerprint'),
            $page('Workforce', 'Pending registration', 'Employees · detected by the kiosk, waiting to be completed', route('employees.register', ['tab' => 'pending']), 'user-check',
                'employees pending kiosk detected registration fingerprint enroll waiting'),
            $page('Workforce', 'Removed employees', 'Employees · removed workers, restore or delete', route('employees.register', ['tab' => 'removed']), 'user-x',
                'employees removed deleted restore archive trash former'),
            $page('Workforce', 'Export employees', 'Employees · download the list as Excel', route('employees.export'), 'download',
                'employees export excel download xls spreadsheet list'),
            $page('Workforce', 'Cash Advances', 'Leave & Advances · advances and their instalments', route('leave.index', ['tab' => 'advances']), 'hand-coins',
                'cash advance advances vale loan instalment installment balance deduction borrow', Modules::ADVANCES),
            $page('Workforce', 'Leave', 'Leave & Advances · filed leave', route('leave.index'), 'calendar-days',
                'leave vacation sick emergency maternity paternity bereavement absence day off paid unpaid file', Modules::LEAVE),

            // ── PROJECT ──────────────────────────────────────────────────
            $page('Project', 'Sites', 'Project sites, map pins and geofence', route('sites.index'), 'map-pin',
                'sites project location map geofence radius pin address area'),

            // ── PAYROLL ──────────────────────────────────────────────────
            $page('Payroll', 'Payroll Records', 'Daily breakdown of pay, payslips', route('payroll-records'), 'receipt',
                'payroll records payslip pay period weekly daily salary gross net deductions overtime bonus receipt print wage'),
            $page('Payroll', 'Remittance tracker', 'Payroll Records · SSS, PhilHealth, Pag-IBIG and BIR by month', route('remittances.index'), 'landmark',
                'remittance remittances tracker sss philhealth pagibig pag-ibig bir tax contribution government monthly receipt remit'),
            $page('Payroll', 'Payroll Reports', 'Payroll Records · summary reports and Excel', route('payroll-reports.index'), 'file-bar-chart',
                'payroll reports summary export excel totals labor cost', Modules::REPORTS),
            $page('Payroll', 'Payroll by Employee', 'Payroll Reports', $report('employee'), 'file-bar-chart',
                'report payroll per employee worker totals', Modules::REPORTS),
            $page('Payroll', 'Labor Cost by Site', 'Payroll Reports', $report('site'), 'file-bar-chart',
                'report labor cost site project', Modules::REPORTS),
            $page('Payroll', 'Overtime report', 'Payroll Reports', $report('overtime'), 'file-bar-chart',
                'report overtime ot hours', Modules::REPORTS),
            $page('Payroll', 'Deductions report', 'Payroll Reports', $report('deductions'), 'file-bar-chart',
                'report deductions sss philhealth pagibig tax contributions', Modules::REPORTS),
            $page('Payroll', 'Cash Advances report', 'Payroll Reports', $report('advances'), 'file-bar-chart',
                'report cash advances vale collected', Modules::REPORTS),
            $page('Payroll', 'Payroll Settings', 'Multipliers, deductions, schedule, labor types, holidays', route('settings.index'), 'settings',
                'payroll settings config configuration setup rates', self::ADMIN),
            $page('Payroll', 'Multipliers & Deductions', 'Payroll Settings · overtime and holiday rates, SSS, PhilHealth, Pag-IBIG, bonus', $settings('payroll'), 'percent',
                'settings multiplier multipliers deductions overtime rate night differential sss philhealth pagibig pag-ibig tax bonus 13th', self::ADMIN),
            $page('Payroll', 'Work Schedule', 'Payroll Settings · shifts, hours, break and grace period', $settings('attendance'), 'clock',
                'settings work schedule shift shifts hours break grace period late night day time in opens regular', self::ADMIN),
            $page('Payroll', 'Labor Types', 'Payroll Settings · positions and their daily rates', $settings('labor'), 'tag',
                'settings labor types position daily rate wage skilled mason carpenter helper', self::ADMIN),
            $page('Payroll', 'Holidays', 'Payroll Settings · the holiday calendar', $settings('holiday'), 'calendar',
                'settings holidays holiday calendar regular special non-working google sync', self::ADMIN),

            // ── INSIGHTS ─────────────────────────────────────────────────
            $page('Insights', 'Analytics', 'Charts on attendance, overtime and labor cost', route('analytics'), 'bar-chart-3',
                'analytics insights charts graphs trends statistics'),
            $page('Insights', 'Jeyanco Bot', 'Ask about payroll, attendance and the workforce', route('ai-assistant'), 'bot',
                'ai assistant chatbot chat bot jeyanco intelligence ask question'),

            // ── SYSTEM ───────────────────────────────────────────────────
            $page('System', 'Users & Roles', 'Accounts and what each role may open', route('users-roles.index'), 'shield-check',
                'users roles accounts permissions access admin hr login manage', self::ADMIN),
            $page('System', 'Create account', 'Users & Roles · a new login', route('accounts.create'), 'user-plus',
                'create add new account user login hr admin invite', self::ADMIN),
            $page('System', 'Device Monitoring', 'Kiosks: status, map and live scans', route('devices.index'), 'monitor-smartphone',
                'device devices monitoring kiosk kiosks fingerprint scanner online offline live console', Modules::DEVICES),
            $page('System', 'System Settings', 'Company, appearance, security, kiosk, notifications', route('system-settings.about'), 'sliders-horizontal',
                'system settings config configuration', self::ADMIN),
            $page('System', 'Company', 'System Settings · company name and details', $system('company'), 'building-2',
                'system settings company name address logo about identity', self::ADMIN),
            $page('System', 'Appearance', 'System Settings · theme and the sign-in intro', $system('appearance'), 'palette',
                'system settings appearance theme dark light mode intro loading', self::ADMIN),
            $page('System', 'Security', 'System Settings · session length, sign-in limit, Google sign-in', $system('security'), 'lock',
                'system settings security password session timeout failed sign-in login limit google sign out all sessions', self::ADMIN),
            $page('System', 'Kiosk settings', 'System Settings · kiosk rules, devices and their sites', $system('kiosk'), 'fingerprint',
                'system settings kiosk device location check scan add remove site', self::ADMIN),
            $page('System', 'Notifications', 'System Settings · what the admins are told', $system('notif'), 'bell',
                'system settings notifications alerts email missing scans remittances payroll', self::ADMIN),
            $page('System', 'Audit logs', 'System Settings · who changed what, and when', $system('audit'), 'scroll-text',
                'audit logs log activity history trail changes who export', self::ADMIN),
        ];
    }
}
