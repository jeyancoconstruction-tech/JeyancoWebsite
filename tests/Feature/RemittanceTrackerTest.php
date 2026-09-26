<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\RemittancePayment;
use App\Models\RemittanceReceipt;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The Remittance Tracker, under Payroll Records.
 *
 * What each agency is owed is the employee contributions payroll deducted,
 * from the pay weeks that end in the month; when it is due comes from
 * Payroll Settings; and whether it is paid — with the reference, the date,
 * the channel and the receipt — is what the office recorded.
 */
class RemittanceTrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $worker;

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

        Carbon::setTestNow(Carbon::parse('2026-09-26 09:00:00', 'Asia/Manila'));

        $this->admin = User::create([
            'name' => 'Remit Admin', 'username' => 'remit.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        PayrollRate::create([
            'effective_from' => '2026-01-01', 'ot_multiplier' => 1.25, 'night_diff_multiplier' => 1.10,
            'rest_day_multiplier' => 1.30, 'regular_holiday_multiplier' => 2.00, 'bonus' => 0,
            'uses_defaults' => false, 'withholding_tax' => true, 'vale_ceiling_percent' => 50,
            'sss_rate' => 5.00, 'philhealth_rate' => 2.50, 'pagibig_rate' => 2.00, 'created_by' => 'test',
        ]);

        $site  = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);
        $labor = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 100]);
        $shift = Shift::where('crosses_midnight', false)->firstOrFail();

        $this->worker = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason', 'rate_per_hour' => 100,
            'labor_type_id' => $labor->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'status' => Employee::STATUS_ACTIVE, 'fingerprint_id' => '1',
            'sss_number' => '34-1234567-8',
        ]);

        // The week of 24–30 August ends in August; the week of 31 August –
        // 6 September ends in September, though it starts in August.
        foreach (['2026-08-26', '2026-08-27', '2026-08-31', '2026-09-01'] as $day) {
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

    /** One week's deductions for the worker, as Payroll Records computes them. */
    private function week(string $from, string $to, string $field): float
    {
        $row = collect(app(PayrollService::class)->computeForRange($from, $to)['employees'])
            ->firstWhere('employee_id', $this->worker->id);

        return round((float) collect($row['periods'] ?? [])->sum($field), 2);
    }

    private function page(array $query = ['month' => '2026-08'])
    {
        return $this->actingAs($this->admin)->get(route('remittances.index', $query))->assertOk();
    }

    public function test_a_month_owes_what_payroll_deducted_in_the_weeks_that_end_in_it(): void
    {
        $rows = $this->page()->viewData('rows');

        $sss = $this->week('2026-08-24', '2026-08-30', 'sssDeduction');
        $this->assertGreaterThan(0, $sss);

        $this->assertEqualsWithDelta($sss, $rows['sss']['total'], 0.001, 'the week ending 30 August, to the centavo');
        $this->assertEqualsWithDelta($this->week('2026-08-24', '2026-08-30', 'philhealthDeduction'), $rows['philhealth']['total'], 0.001);
        $this->assertEqualsWithDelta($this->week('2026-08-24', '2026-08-30', 'pagibigDeduction'), $rows['pagibig']['total'], 0.001);
        $this->assertEqualsWithDelta($this->week('2026-08-24', '2026-08-30', 'withholdingTax'), $rows['bir']['total'], 0.001);

        // The week that starts on 31 August is September's, not August's.
        $this->assertGreaterThan(0, $this->week('2026-08-31', '2026-09-06', 'sssDeduction'));
        $this->assertCount(1, $rows['sss']['people']);
        $this->assertSame('34-1234567-8', $rows['sss']['people'][0]['id_number']);
    }

    /**
     * Michael, 2026-09-26: no set due date. A month is brought up in the last
     * week of the month after, every agency alike, and it is a reminder, not
     * a deadline: it can still be sent later, so nothing turns "overdue".
     */
    public function test_a_month_is_brought_up_in_the_last_week_of_the_month_after(): void
    {
        // 26 September: August's reminder week is 24–30 September, and it has come.
        $rows = $this->page()->viewData('rows');
        foreach (['sss', 'philhealth', 'pagibig', 'bir'] as $agency) {
            [$from, $to] = $rows[$agency]['remind'];
            $this->assertSame('2026-09-24', $from->toDateString(), "{$agency} is brought up from the 24th");
            $this->assertSame('2026-09-30', $to->toDateString());
            if ($rows[$agency]['total'] > 0) {
                $this->assertSame('due', $rows[$agency]['status'], "{$agency} is to remit");
            }
        }
        $this->page()->assertSee('To remit')->assertSee('Sep 24 – 30')->assertDontSee('Overdue');

        // A week earlier it was only upcoming, and nothing on the rail said so.
        Carbon::setTestNow(Carbon::parse('2026-09-20 09:00:00', 'Asia/Manila'));
        $rows = $this->page()->viewData('rows');
        $this->assertSame('pend', $rows['sss']['status']);
        $this->assertSame(4, $rows['sss']['days'], 'four days to the reminder');

        // Past the week it is still just to remit — never late.
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'Asia/Manila'));
        $this->assertSame('due', $this->page()->viewData('rows')['sss']['status']);

        // February's is the last seven days of a short March too.
        [$from, $to] = app(\App\Services\RemittanceTracker::class)->reminder(Carbon::parse('2027-01-01', 'Asia/Manila'));
        $this->assertSame(['2027-02-22', '2027-02-28'], [$from->toDateString(), $to->toDateString()]);

        // And nothing is set in Payroll Settings any more.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('settings.remittance-due.update'));
        $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'payroll']))
             ->assertOk()->assertDontSee('Remittance due dates')->assertDontSee('Save Due Dates');
    }

    public function test_a_month_with_nothing_deducted_owes_nothing_and_the_current_month_is_not_due(): void
    {
        $rows = $this->page(['month' => '2026-08'])->viewData('rows');
        $grid = $this->page(['month' => '2026-08'])->viewData('grid');

        // July had no pay at all.
        $this->assertSame('none', $grid['sss'][7]['status']);
        // September is still going: it is on the tracker with what payroll has
        // deducted so far (Michael, 2026-09-26 — pay only began in September
        // on the live site, so a tracker of ended months sat empty).
        $this->assertSame('fut', $grid['sss'][9]['status']);
        $this->assertTrue($grid['sss'][9]['tracked'], 'it can be picked');
        $sep = $this->page(['month' => '2026-09']);
        $this->assertTrue($sep->viewData('running'));
        $this->assertEqualsWithDelta($this->week('2026-08-31', '2026-09-06', 'sssDeduction'), $sep->viewData('rows')['sss']['total'], 0.001, 'so far');
        $this->assertSame('fut', $sep->viewData('rows')['sss']['status']);
        $sep->assertSee('so far, month in progress')->assertDontSee('data-rmt-pay="sss"', false);
        // With no month asked for, it opens on this one.
        $this->assertSame('2026-09', $this->page([])->viewData('month')->format('Y-m'));
        // And it cannot be marked paid until it has ended.
        $this->actingAs($this->admin)->post(route('remittances.store'), [
            'agency' => 'sss', 'month' => '2026-09', 'amount' => '10', 'paid_on' => '2026-09-25',
            'reference' => 'X-1', 'channel' => 'Online (My.SSS PRN)',
        ])->assertSessionHasErrors('month');
        $this->assertSame('due', $rows['sss']['status']);
    }

    public function test_marking_paid_keeps_the_reference_date_channel_and_receipt(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('sss-aug.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");

        $this->actingAs($this->admin)->post(route('remittances.store'), [
            'agency' => 'sss', 'month' => '2026-08', 'amount' => '123.45', 'paid_on' => '2026-09-25',
            'reference' => 'SS-202608-4821', 'channel' => 'GCash / Maya', 'receipt' => $pdf,
        ])->assertRedirect(route('remittances.index', ['month' => '2026-08']))
          ->assertSessionHas('success', 'SSS Aug 2026 marked as paid.');

        $payment = RemittancePayment::sole();
        $this->assertSame('sss', $payment->agency);
        $this->assertSame('2026-08-01', $payment->period->toDateString());
        $this->assertSame('2026-09-25', $payment->paid_on->toDateString());
        $this->assertSame('123.45', (string) $payment->amount);
        $this->assertSame('SS-202608-4821', $payment->reference);
        $this->assertSame($this->admin->id, $payment->recorded_by);

        // The receipt is in the database, and comes back as it went in.
        $receipt = RemittanceReceipt::sole();
        $this->assertSame('sss-aug.pdf', $receipt->name);
        $res = $this->actingAs($this->admin)->get(route('remittances.receipt', $payment))->assertOk();
        $this->assertStringStartsWith('application/pdf', $res->headers->get('Content-Type'));
        $this->assertSame("%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n", $res->getContent());

        $page = $this->page();
        $this->assertSame('paid', $page->viewData('rows')['sss']['status']);
        $page->assertSee('SS-202608-4821')->assertSee('GCash / Maya');
    }

    public function test_a_payment_needs_a_reference_a_closed_month_and_a_date_that_has_happened(): void
    {
        $base = ['agency' => 'sss', 'month' => '2026-08', 'amount' => '100', 'paid_on' => '2026-09-25',
                 'reference' => 'SS-1', 'channel' => 'Online (My.SSS PRN)'];

        $this->actingAs($this->admin)->post(route('remittances.store'), ['reference' => ''] + $base)
             ->assertSessionHasErrors('reference');
        $this->actingAs($this->admin)->post(route('remittances.store'), ['month' => '2026-09'] + $base)
             ->assertSessionHasErrors('month');
        $this->actingAs($this->admin)->post(route('remittances.store'), ['paid_on' => '2026-09-27'] + $base)
             ->assertSessionHasErrors('paid_on');
        $this->actingAs($this->admin)->post(route('remittances.store'), ['channel' => 'Carrier pigeon'] + $base)
             ->assertSessionHasErrors('channel');
        $this->actingAs($this->admin)->post(route('remittances.store'), ['receipt' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')] + $base)
             ->assertSessionHasErrors('receipt');
        $this->assertSame(0, RemittancePayment::count());

        // Once, not twice.
        $this->actingAs($this->admin)->post(route('remittances.store'), $base)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('remittances.store'), ['reference' => 'SS-2'] + $base)
             ->assertSessionHasErrors('reference');
        $this->assertSame(1, RemittancePayment::count());
    }

    public function test_removing_a_payment_takes_its_receipt_with_it(): void
    {
        $this->actingAs($this->admin)->post(route('remittances.store'), [
            'agency' => 'philhealth', 'month' => '2026-08', 'amount' => '50', 'paid_on' => '2026-09-14',
            'reference' => 'PH-1', 'channel' => 'Bank (Landbank)',
            'receipt' => UploadedFile::fake()->createWithContent('ph.pdf', "%PDF-1.4\n%%EOF\n"),
        ]);

        $payment = RemittancePayment::sole();
        $this->actingAs($this->admin)->delete(route('remittances.destroy', $payment))
             ->assertRedirect(route('remittances.index', ['month' => '2026-08']));

        $this->assertSame(0, RemittancePayment::count());
        $this->assertSame(0, RemittanceReceipt::count());
        $this->assertSame('due', $this->page()->viewData('rows')['philhealth']['status']);
    }

    private function rail(string $url): string
    {
        $html  = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $start = strpos($html, '<nav class="nav-menu">');

        return substr($html, $start, strpos($html, '</nav>', $start) - $start);
    }

    public function test_the_sidebar_lists_the_tracker_under_payroll_records_with_what_is_due(): void
    {
        // August's reminder week (24–30 September) has come: every agency that owes is to remit.
        $expected = (string) collect($this->page()->viewData('rows'))->where('status', 'due')->count();

        // Set up as Michael's jeyanco-sidebar-submenu mockup: Payroll Records
        // is a toggle with a chevron, its pages sit under it on a guide line.
        // In Payroll Records or the tracker they are open, the open page is
        // lit, and the parent is never the solid blue pill.
        foreach (['/payroll-records' => 'Records', '/remittances' => 'Remittance tracker'] as $url => $lit) {
            $rail    = $this->rail($url);
            $parent  = strpos($rail, 'id="navRecordsBtn"');
            $sub     = strpos($rail, 'id="navSubRecords"');
            $tracker = strpos($rail, 'href="' . route('remittances.index') . '"');

            $this->assertMatchesRegularExpression('~<button type="button" class="nav-link nav-parent has-on" id="navRecordsBtn" aria-controls="navSubRecords" aria-expanded="true">~', $rail, "{$url}: open, and not the solid pill");
            $this->assertStringNotContainsString('nav-link active', $rail, "{$url}: nothing on the rail is the solid pill");
            $this->assertMatchesRegularExpression('~<div class="nav-sub\s*" id="navSubRecords">~', $rail, "{$url}: the pages are open");
            $this->assertTrue($parent < $sub && $sub < $tracker, "{$url}: under Payroll Records");
            $this->assertStringContainsString('class="nav-chev"', $rail);
            $this->assertMatchesRegularExpression('~class="nav-sub-link on"[^>]*aria-current="page"\s*>\s*(<span>)?' . preg_quote($lit, '~') . '~', $rail, "{$url}: {$lit} is lit");
            preg_match('/nav-sub-badge[^>]*>(\d+)</', $rail, $m);
            $this->assertSame($expected, $m[1] ?? null, "{$url}: the count sits on the tracker");
        }

        // Anywhere else they are folded, and a red dot on Payroll Records says
        // something inside is due; the parent still opens them in place.
        $rail = $this->rail('/dashboard');
        $this->assertStringContainsString('<button type="button" class="nav-link nav-parent " id="navRecordsBtn" aria-controls="navSubRecords" aria-expanded="false">', $rail);
        $this->assertStringContainsString('<div class="nav-sub folded" id="navSubRecords">', $rail);
        $this->assertStringContainsString('class="nav-dot" title="' . $expected . ' remittance(s) to remit"', $rail);
        $this->assertStringNotContainsString('class="nav-sub-link on"', $rail);
        $this->assertStringContainsString("jeyancoNavGroup('navRecordsBtn', 'navSubRecords', 'jeyanco-nav-records', false)", $rail, 'leaving the section forgets the fold');
    }

    /** Each employee's share sits beside their number at that agency. */
    public function test_the_per_employee_list_shows_each_ones_id_number(): void
    {
        $this->page()
             ->assertSeeInOrder(['<table class="rmt-ppl">', 'Employee', 'SSS No.', 'Amount'], false)
             ->assertSee('<span class="rmt-idn">34-1234567-8</span>', false);
    }

    public function test_the_reports_download_each_agency_with_the_id_numbers(): void
    {
        $all = $this->actingAs($this->admin)->get(route('remittances.report', ['month' => '2026-08']))->assertOk();
        $this->assertStringContainsString('remittances_all_2026-08.xls', $all->headers->get('Content-Disposition'));
        foreach (['SSS', 'PhilHealth', 'Pag-IBIG', 'BIR'] as $agency) {
            $all->assertSee($agency . ' —', false);
        }
        $all->assertSee('34-1234567-8');

        $one = $this->actingAs($this->admin)->get(route('remittances.report', ['month' => '2026-08', 'agency' => 'sss']))->assertOk();
        $this->assertStringContainsString('remittances_sss_2026-08.xls', $one->headers->get('Content-Disposition'));
        $one->assertDontSee('PhilHealth —', false);
    }

    public function test_hr_opens_it_too(): void
    {
        $hr = User::create([
            'name' => 'People Office', 'username' => 'hr.remit', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        $this->actingAs($hr)->get(route('remittances.index'))->assertOk()->assertSee('Remittance Tracker');
    }
}
