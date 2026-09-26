<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payroll Records, laid out after the jeyanco-payroll-records.html mockup.
 *
 * The redesign changed how the page looks, not what it does: the same
 * figures from PayrollService, the same week/day and employee filters, and
 * the same Excel export and payslip print. These hold the new layout to
 * that — the controls still reach the old routes and parameters, every row
 * still carries its worker's payslip, and a summary never contradicts the
 * rows under it.
 */
class PayrollRecordsDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $worker;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-26 09:00:00', 'Asia/Manila'));

        $this->admin = User::create([
            'name' => 'Records Admin', 'username' => 'records.admin', 'password' => 'secret123',
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

        foreach (['2026-09-08', '2026-09-09'] as $day) {
            Attendance::create([
                'employee_id' => $this->worker->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
                'date' => $day, 'time_in' => $day . ' 08:00:00', 'time_out' => $day . ' 17:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A value as Blade's @json writes it into the page's script. */
    private function js(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    private function page(array $query): string
    {
        return $this->actingAs($this->admin)->get(route('payroll-records', $query))->assertOk()->getContent();
    }

    public function test_the_week_bar_steps_a_week_either_way_and_keeps_the_search(): void
    {
        $html = $this->page(['mode' => 'weekly', 'week' => '2026-W37', 'employee' => 'juan']);

        $this->assertStringContainsString('Week 37, 2026', $html);
        $this->assertStringContainsString('09/07 – 09/13/2026', $html);
        $this->assertStringContainsString(e(route('payroll-records', ['mode' => 'weekly', 'week' => '2026-W36', 'employee' => 'juan'])), $html);
        $this->assertStringContainsString(e(route('payroll-records', ['mode' => 'weekly', 'week' => '2026-W38', 'employee' => 'juan'])), $html);

        // The search shows as a chip that clears it.
        $this->assertStringContainsString('class="prx-chip"', $html);
        $this->assertStringContainsString(e(route('payroll-records', ['mode' => 'weekly', 'week' => '2026-W37'])), $html);
    }

    public function test_switching_to_daily_stays_in_the_week_on_screen(): void
    {
        // A past week opens on its Monday; the week containing today, on today.
        $this->assertStringContainsString('name="date" value="2026-09-07"', $this->page(['mode' => 'weekly', 'week' => '2026-W37']));
        $this->assertStringContainsString('name="date" value="2026-09-26"', $this->page(['mode' => 'weekly', 'week' => '2026-W39']));

        // And a day switches to the week it is in.
        $this->assertStringContainsString('name="week" value="2026-W37"', $this->page(['mode' => 'daily', 'date' => '2026-09-09']));
    }

    public function test_every_row_carries_its_workers_payslip(): void
    {
        $html = $this->page(['mode' => 'weekly', 'week' => '2026-W37']);

        $this->assertSame(1, preg_match('/<tr class="pr-row" tabindex="0" data-slip="([^"]+)"/', $html, $m));
        $slip = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);

        $this->assertSame('Juan Dela Cruz', $slip['name']);
        $this->assertSame('#' . str_pad($this->worker->id, 4, '0', STR_PAD_LEFT), $slip['code']);
        $this->assertCount(7, $slip['days'], 'Monday to Sunday, for the panel');
        $this->assertSame(2, $slip['workdays']);
        // Its lines add up the way the payslip does.
        $this->assertEqualsWithDelta($slip['gross'],
            $slip['regular'] + $slip['overtime'] + $slip['night'] + $slip['holiday'] + $slip['rest'] + $slip['leave'], 0.011);
        $this->assertEqualsWithDelta($slip['net'], $slip['gross'] - $slip['ded'] + $slip['bonus'], 0.011);

        // The panel it opens, and the page it prints from.
        $this->assertStringContainsString('id="prSlip"', $html);
        $this->assertStringContainsString('offcanvas offcanvas-end', $html);
        $this->assertStringContainsString($this->js(route('payslip.batch', ['from' => '2026-09-07', 'to' => '2026-09-13'])), $html);
    }

    public function test_preview_offers_a_payslip_every_payslip_and_the_register(): void
    {
        $html = $this->page(['mode' => 'weekly', 'week' => '2026-W37']);

        foreach (['single', 'all', 'register'] as $option) {
            $this->assertStringContainsString('class="prx-opt" data-pv="' . $option . '"', $html);
        }
        $this->assertStringContainsString('data-prx-preview="register"', $html);   // Export register
        $this->assertStringContainsString('data-prx-preview="single"', $html);     // Preview & Download
        $this->assertStringContainsString($this->js(route('payroll-records.export.excel', ['mode' => 'weekly', 'week' => '2026-W37'])), $html);
        $this->assertStringContainsString('This is exactly what the Excel file will contain.', $html);

        // "Every payslip" prints the whole period, so it is not offered while
        // the list is narrowed by a search.
        $this->assertStringNotContainsString('class="prx-opt" data-pv="all"', $this->page(['mode' => 'weekly', 'week' => '2026-W37', 'employee' => 'juan']));
    }

    public function test_the_summary_and_the_weekly_totals_are_the_same_figures(): void
    {
        $response = $this->actingAs($this->admin)->get(route('payroll-records', ['mode' => 'weekly', 'week' => '2026-W37']))->assertOk();
        $summary  = $response->viewData('summary');
        $html     = $response->getContent();

        $net = '₱' . number_format($summary['net'], 2);
        $this->assertStringContainsString('<div class="v">' . $net . '</div>', $html);   // the hero
        $this->assertMatchesRegularExpression('/<tfoot>.*' . preg_quote($net, '/') . '.*<\/tfoot>/s', $html);  // the totals row
        foreach (['Gross pay', 'Deductions', 'Overtime', 'Holiday pay', 'Rest day pay', 'Bonus', 'Employees', 'Hours / days'] as $tile) {
            $this->assertStringContainsString('<span>' . $tile . '</span>', $html);
        }
    }

    public function test_a_days_totals_add_up_its_earnings_only(): void
    {
        // A cash advance comes off the period's pay, not a shift's, so a day's
        // rows cannot total its deductions; the row says what it does total.
        $html = $this->page(['mode' => 'daily', 'date' => '2026-09-08']);

        $this->assertStringContainsString('Day total before deductions &amp; bonus', $html);
        $this->assertStringContainsString('class="prx-dategrp"', $html);
        $this->assertStringContainsString(e(route('payroll-records', ['mode' => 'daily', 'date' => '2026-09-07'])), $html);
        $this->assertStringContainsString(e(route('payroll-records', ['mode' => 'daily', 'date' => '2026-09-09'])), $html);
    }
}
