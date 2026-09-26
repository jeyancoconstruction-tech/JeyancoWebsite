<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every page opens with the same header.
 *
 * One component (components/page-header.blade.php): the page's name on the
 * left, its actions on the right, and nothing under the title. Pages used to
 * build their own — ten shapes, titles from 19px to 32px, subtitles, eyebrows
 * and breadcrumbs — so the title sat somewhere different on each and the
 * content started anywhere from 52px to 151px down. This keeps a page from
 * drifting back to a header of its own.
 */
class PageHeaderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $worker;
    private PayrollRun $run;
    private PayrollRunItem $slip;

    protected function setUp(): void
    {
        parent::setUp();

        // Holiday.php uses MySQL's YEAR() in raw SQL; taught to SQLite the
        // same way ModuleSmokeTest does.
        $pdo = \DB::connection()->getPdo();
        if (\DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR', fn ($d) => $d ? (int) date('Y', strtotime($d)) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) date('n', strtotime($d)) : null, 1);
        }

        $this->admin = User::create([
            'name' => 'Header Admin', 'username' => 'header.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $site  = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);
        $labor = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 100]);
        $shift = Shift::where('crosses_midnight', false)->firstOrFail();

        $this->worker = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason', 'rate_per_hour' => 100,
            'labor_type_id' => $labor->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'status' => Employee::STATUS_ACTIVE, 'fingerprint_id' => '1',
        ]);

        $day = now()->subDay()->startOfDay();
        Attendance::create([
            'employee_id' => $this->worker->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'date' => $day->toDateString(),
            'time_in' => $day->copy()->setTime(8, 0)->toDateTimeString(),
            'time_out' => $day->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        $this->run = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => '2026-09-07',
            'period_end' => '2026-09-13', 'status' => 'approved',
        ]);
        $this->slip = PayrollRunItem::create([
            'payroll_run_id' => $this->run->id, 'employee_id' => $this->worker->id,
            'employee_name' => $this->worker->name, 'gross_pay' => 800, 'net_pay' => 800,
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'dashboard'               => ['/dashboard'],
            'attendance'              => ['/attendance'],
            'attendance history'      => ['/attendance?tab=history'],
            'employees'               => ['/employees/register'],
            'register employee'       => ['/employees/create'],
            'employee profile'        => ['/employees/{worker}'],
            'edit employee'           => ['/employees/{worker}/edit'],
            'leave'                   => ['/leave-advances'],
            'cash advances'           => ['/leave-advances?tab=advances'],
            'sites'                   => ['/sites'],
            'payroll records'         => ['/payroll-records'],
            'remittance tracker'      => ['/remittances'],
            'payroll reports'         => ['/payroll-reports'],
            'payslips'                => ['/payslips'],
            'payslip'                 => ['/payslips/{slip}'],
            'payroll settings'        => ['/settings'],
            'analytics'               => ['/analytics'],
            'ai assistant'            => ['/ai-assistant'],
            'users & roles'           => ['/users-roles'],
            'create account'          => ['/accounts/create'],
            'edit account'            => ['/accounts/{admin}/edit'],
            'audit logs'              => ['/audit-logs'],
            'device monitoring'       => ['/device-monitoring'],
            'system: company'         => ['/system-settings'],
            'system: appearance'      => ['/system-settings/appearance'],
            'system: kiosk'           => ['/system-settings/kiosk'],
            'system: security'        => ['/system-settings/security'],
            'search'                  => ['/search?q=juan'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_opens_with_the_shared_header_and_nothing_under_its_title(string $url): void
    {
        $url = strtr($url, [
            '{worker}' => $this->worker->id, '{run}' => $this->run->id,
            '{slip}' => $this->slip->id, '{admin}' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $page = $this->content($html);

        // One header, and nothing of the page's own shows before it.
        $this->assertSame(1, preg_match_all('/<header\b[^>]*\bclass="page-head\b/', $page), "$url: one shared header");
        $before = strstr($this->markupOnly($page), '<header class="page-head', true);
        $this->assertSame('', trim(strip_tags($before)), "$url: nothing is shown above the header");

        // One title, and it is the page's only h1.
        $this->assertSame(1, substr_count($page, '<h1 class="page-head-title">'), "$url: title");
        $this->assertSame(1, preg_match_all('/<h1\b/', $page), "$url: no second page title");

        // Nothing but the title and its actions: no sub-line, eyebrow or crumb.
        preg_match('/<header\b[^>]*\bclass="page-head\b.*?<\/header>/s', $page, $m);
        $this->assertDoesNotMatchRegularExpression('/<p[\s>]/', $m[0], "$url: no subtitle under the title");
        foreach (['sx-eyebrow', 'pp-crumb', 'ca-crumb', 'rmx-sub', 'sx-sub', 'ana-sub'] as $old) {
            $this->assertStringNotContainsString($old, $page, "$url: $old");
        }
    }

    public function test_the_header_is_styled_in_one_place_and_loaded_on_every_page(): void
    {
        $css = file_get_contents(public_path('page-header.css'));

        $this->assertMatchesRegularExpression('/\.page-head-title\s*\{[^}]*font-size:\s*20px !important;[^}]*font-weight:\s*700 !important;/s', $css);
        $this->assertMatchesRegularExpression('/\.page-head\s*\{[^}]*margin:\s*0 0 12px !important;/s', $css);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('page-header.css', false);
    }

    /** The page's own markup: what the layout puts in its content container. */
    private function content(string $html): string
    {
        $start = strpos($html, '<!-- PAGE CONTENT -->');
        $end   = strpos($html, '<!-- FLOATING CHATBOT -->');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /** Markup without comments, styles, scripts, links or hidden elements. */
    private function markupOnly(string $page): string
    {
        return preg_replace([
            '/<!--.*?-->/s', '/<style\b.*?<\/style>/s', '/<script\b.*?<\/script>/s',
            '/<link\b[^>]*>/', '/<template\b.*?<\/template>/s',
        ], '', $page);
    }
}
