<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The top bar's search reaches everything (Michael, 2026-10-02: "dapat lahat
 * na a-access"): every page and section, and every kind of record, each
 * linked to where it really is — and nothing an account cannot open.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');

        $this->admin = User::create([
            'name' => 'Olivia Admin', 'username' => 'search.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->hr = User::create([
            'name' => 'Hana Reyes', 'username' => 'search.hr', 'email' => 'hana@jeyanco.test', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(string $name, string $status = Employee::STATUS_ACTIVE): Employee
    {
        return Employee::create([
            'name' => $name, 'position' => 'Mason', 'status' => $status, 'rate_per_hour' => 100,
            'fingerprint_id' => $status === Employee::STATUS_PENDING ? null : (string) random_int(7000, 7999),
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800])->id,
            'site_id' => Site::firstOrCreate(['name' => 'Site A'])->id,
            'shift_id' => Shift::where('crosses_midnight', false)->firstOrFail()->id,
        ]);
    }

    /** The flat list the top bar draws. */
    private function suggest(User $as, string $q): array
    {
        return $this->actingAs($as)->getJson(route('search.suggestions', ['q' => $q]))->assertOk()->json();
    }

    /** category => [title => url], from the full results. */
    private function find(User $as, string $q): array
    {
        $data = $this->actingAs($as)->getJson(route('search', ['q' => $q]))->assertOk()->json('data.categories');

        $out = [];
        foreach ($data ?? [] as $cat) {
            $out[$cat['label']] = array_column($cat['items'], 'url', 'title');
        }

        return $out;
    }

    public function test_the_empty_box_lists_every_page_and_section_and_each_one_opens(): void
    {
        $map    = $this->suggest($this->admin, '');
        $titles = array_column($map, 'text');

        foreach ([
            'Dashboard', 'Attendance', 'Attendance history', 'Needs review',
            'Employees', 'Add employee', 'Pending registration', 'Removed employees', 'Export employees',
            'Cash Advances', 'Leave', 'Sites',
            'Payroll Records', 'Remittance tracker', 'Payroll Reports',
            'Payroll Settings', 'Multipliers & Deductions', 'Work Schedule', 'Labor Types', 'Holidays',
            'Analytics', 'Jeyanco Bot',
            'Users & Roles', 'Create account', 'Device Monitoring',
            'System Settings', 'Company', 'Appearance', 'Security', 'Kiosk settings', 'Audit logs',
        ] as $title) {
            $this->assertContains($title, $titles, "{$title} is not reachable from the search");
        }

        // System Settings has no Notifications section since 2026-10-02.
        $this->assertNotContains('Notifications', $titles);

        // Under the sidebar's own headings, in its order.
        $this->assertSame(
            ['Main', 'Workforce', 'Project', 'Payroll', 'Insights', 'System'],
            array_values(array_unique(array_column($map, 'category')))
        );

        // No dead ends: every address on the list answers.
        foreach ($map as $page) {
            $this->assertNotEmpty($page['icon']);
            $this->actingAs($this->admin)->get($page['url'])->assertOk();
        }
    }

    public function test_hr_is_offered_only_what_hr_can_open(): void
    {
        $map    = $this->suggest($this->hr, '');
        $titles = array_column($map, 'text');

        foreach (['Payroll Settings', 'Labor Types', 'Holidays', 'Users & Roles', 'Create account', 'System Settings', 'Security', 'Audit logs'] as $closed) {
            $this->assertNotContains($closed, $titles, "HR is offered {$closed}");
        }
        foreach (['Dashboard', 'Employees', 'Leave', 'Cash Advances', 'Payroll Records', 'Remittance tracker', 'Payroll Reports', 'Device Monitoring'] as $open) {
            $this->assertContains($open, $titles);
        }
        foreach ($map as $page) {
            $this->actingAs($this->hr)->get($page['url'])->assertOk();
        }

        // Typed, too: the pages stay closed, and so do the records behind them.
        LaborType::firstOrCreate(['name' => 'Welder Grade Z'], ['daily_rate' => 950]);
        AuditLog::create(['user_name' => 'Olivia Admin', 'module' => 'system', 'action' => 'updated', 'description' => 'Welder Grade Z rate changed']);

        $this->assertSame([], array_keys($this->find($this->hr, 'settings')));
        $this->assertArrayNotHasKey('Accounts', $this->find($this->hr, 'Olivia'));
        $this->assertSame([], array_keys($this->find($this->hr, 'Welder Grade Z')));

        $admin = $this->find($this->admin, 'Welder Grade Z');
        $this->assertArrayHasKey('Labor Types', $admin);
        $this->assertArrayHasKey('Audit Logs', $admin);
        $this->assertStringContainsString('section=audit', reset($admin['Audit Logs']));
    }

    public function test_a_name_finds_the_worker_everywhere_they_appear(): void
    {
        $e = $this->worker('Zenaida Quirante');
        Attendance::create([
            'employee_id' => $e->id, 'site_id' => $e->site_id, 'shift_id' => $e->shift_id,
            'date' => '2026-09-15', 'session' => 'AM', 'time_in' => '2026-09-15 08:00:00', 'time_out' => '2026-09-15 12:00:00',
        ]);
        LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type' => 'sick', 'starts_on' => '2026-09-10', 'ends_on' => '2026-09-11',
            'days' => 2, 'is_paid' => true, 'status' => 'approved',
        ]);
        Loan::create([
            'employee_id' => $e->id, 'type' => Loan::ADVANCE, 'principal' => 5000, 'balance' => 5000, 'installment' => 500,
            'schedule' => 'per_payroll', 'issued_on' => '2026-09-01', 'starts_on' => '2026-09-01', 'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $found = $this->find($this->admin, 'quirante');

        $this->assertSame(route('employees.show', $e->id), $found['Employees']['Zenaida Quirante']);
        $this->assertSame(route('payroll-records', ['employee' => $e->id]), $found['Payroll']['Zenaida Quirante']);
        // The row's own list, narrowed to the worker — not the top of Attendance.
        $this->assertSame(
            route('attendance', ['tab' => 'history', 'q' => 'Zenaida Quirante']),
            $found['Attendance']['Zenaida Quirante — 09/15/2026']
        );
        $this->assertSame(route('leave.index', ['q' => 'Zenaida Quirante']), $found['Leave']['Zenaida Quirante — Sick Leave']);
        $this->assertSame(
            route('leave.index', ['tab' => 'advances', 'q' => 'Zenaida Quirante']),
            $found['Cash Advances']['Zenaida Quirante — ₱5,000.00']
        );

        foreach ($found as $items) {
            foreach ($items as $url) {
                $this->actingAs($this->admin)->get($url)->assertOk();
            }
        }

        // A kind of leave, by its name.
        $this->assertArrayHasKey('Zenaida Quirante — Sick Leave', $this->find($this->admin, 'sick leave')['Leave']);
    }

    public function test_sites_accounts_kiosks_and_holidays_are_found(): void
    {
        $site = Site::create(['name' => 'Ridgeview Tower', 'location' => 'Naga City']);
        $kiosk = Kiosk::create(['name' => 'Ridgeview Gate Kiosk', 'code' => 'RIDGE_1', 'site_id' => $site->id, 'is_active' => true]);
        Holiday::create(['date' => '2026-12-25', 'title' => 'Christmas Day', 'type' => 'regular', 'is_official' => true, 'is_active' => true]);

        $found = $this->find($this->admin, 'ridgeview');
        $this->assertSame(route('sites.index'), $found['Sites']['Ridgeview Tower']);
        $this->assertSame(route('devices.index', ['kiosk' => $kiosk->id]), $found['Kiosks']['Ridgeview Gate Kiosk']);

        $this->assertArrayHasKey('Ridgeview Tower', $this->find($this->admin, 'naga')['Sites']);
        $this->assertArrayHasKey('Ridgeview Gate Kiosk', $this->find($this->admin, 'RIDGE_1')['Kiosks']);

        $accounts = $this->find($this->admin, 'search.hr')['Accounts'];
        $this->assertSame(route('users-roles.index', ['account' => $this->hr->id]), $accounts['Hana Reyes']);

        $this->assertSame(route('settings.index', ['tab' => 'holiday']), $this->find($this->admin, 'christmas')['Holidays']['Christmas Day']);
    }

    public function test_a_worker_is_linked_to_where_they_stand(): void
    {
        $pending = $this->worker('Ximena Pendiente', Employee::STATUS_PENDING);
        $removed = $this->worker('Ximena Tinanggal');
        $removed->delete();
        $gone = $this->worker('Ximena Wala');
        $gone->delete();
        $gone->deleteForGood();

        $found = $this->find($this->admin, 'ximena');

        $this->assertSame(route('employees.register', ['tab' => 'pending']), $found['Employees']['Ximena Pendiente']);
        $this->assertSame(route('employees.register', ['tab' => 'removed']), $found['Employees']['Ximena Tinanggal']);
        $this->assertArrayNotHasKey('Ximena Wala', $found['Employees'], 'deleted for good is off every list');

        // Payroll is for the workforce: a pending name has no pay to open.
        $this->assertArrayNotHasKey('Ximena Pendiente', $found['Payroll'] ?? []);
    }

    public function test_a_date_typed_the_office_way_finds_that_day(): void
    {
        $e = $this->worker('Yolanda Petsa');
        Attendance::create([
            'employee_id' => $e->id, 'site_id' => $e->site_id, 'shift_id' => $e->shift_id,
            'date' => '2026-09-15', 'session' => 'AM', 'time_in' => '2026-09-15 08:00:00',
        ]);

        foreach (['09/15/2026', '9/15/2026', 'Sep 15, 2026', 'september 15 2026', '2026-09-15'] as $typed) {
            $this->assertArrayHasKey('Yolanda Petsa — 09/15/2026', $this->find($this->admin, $typed)['Attendance'] ?? [], $typed);
        }
    }

    public function test_pages_are_found_by_any_word_in_any_order_and_come_first(): void
    {
        $found = $this->find($this->admin, 'settings payroll');
        $this->assertSame('Pages', array_key_first($found));
        $this->assertSame('Payroll Settings', array_key_first($found['Pages']));

        // By what the office calls it, not only by its title.
        $this->assertArrayHasKey('Remittance tracker', $this->find($this->admin, 'philhealth')['Pages']);
        $this->assertArrayHasKey('Cash Advances', $this->find($this->admin, 'vale')['Pages']);
        $this->assertArrayHasKey('Needs review', $this->find($this->admin, 'missed')['Pages']);

        // One character narrows the map; records wait for the second.
        $one = array_column($this->suggest($this->admin, 'a'), 'text');
        $this->assertContains('Attendance', $one);
        $this->assertNotContains('Sites', $one);
    }

    public function test_the_top_bar_is_wired_to_it(): void
    {
        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-suggest-url="' . route('search.suggestions') . '"', $html);
        $this->assertStringContainsString('data-search-url="' . route('search') . '"', $html);
        $this->assertStringContainsString('js/global-search.js', $html);
        $this->assertStringContainsString('global-search.css', $html);
        $this->assertStringContainsString('placeholder="Search anything..."', $html);

        // Names go in escaped, never as markup.
        $js = file_get_contents(public_path('js/global-search.js'));
        $this->assertStringContainsString('esc(item.text)', $js);
        $this->assertStringContainsString('esc(item.url)', $js);
        $this->assertStringNotContainsString('${item.text}', $html);
    }
}
