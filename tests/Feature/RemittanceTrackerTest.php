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

    public function test_the_due_date_and_status_come_from_payroll_settings(): void
    {
        $rows = $this->page()->viewData('rows');

        // 26 September: SSS for August is due on the 30th, PhilHealth was due on the 15th.
        $this->assertSame('2026-09-30', $rows['sss']['due']->toDateString());
        $this->assertSame('pend', $rows['sss']['status']);
        $this->assertSame('2026-09-15', $rows['philhealth']['due']->toDateString());
        $this->assertSame('late', $rows['philhealth']['status']);

        // Moving SSS to the 20th makes it overdue.
        $this->actingAs($this->admin)->put(route('settings.remittance-due.update'), [
            'sss_due_day' => 20, 'philhealth_due_day' => 15, 'pagibig_due_day' => 15, 'bir_due_day' => 10,
        ])->assertRedirect(route('settings.index', ['tab' => 'payroll']))->assertSessionHas('success');

        $this->assertSame(20, SystemSetting::current()->sss_due_day);
        $rows = $this->page()->viewData('rows');
        $this->assertSame('2026-09-20', $rows['sss']['due']->toDateString());
        $this->assertSame('late', $rows['sss']['status']);

        // A day past the end of a short month is its last day.
        $this->actingAs($this->admin)->put(route('settings.remittance-due.update'), [
            'sss_due_day' => 31, 'philhealth_due_day' => 15, 'pagibig_due_day' => 15, 'bir_due_day' => 10,
        ]);
        $this->assertSame('2026-09-30', $this->page()->viewData('rows')['sss']['due']->toDateString());

        // And a due date is a day of the month.
        $this->actingAs($this->admin)->put(route('settings.remittance-due.update'), [
            'sss_due_day' => 0, 'philhealth_due_day' => 32, 'pagibig_due_day' => 15, 'bir_due_day' => 10,
        ])->assertSessionHasErrors(['sss_due_day', 'philhealth_due_day']);
    }

    public function test_a_month_with_nothing_deducted_owes_nothing_and_the_current_month_is_not_due(): void
    {
        $rows = $this->page(['month' => '2026-08'])->viewData('rows');
        $grid = $this->page(['month' => '2026-08'])->viewData('grid');

        // July had no pay at all.
        $this->assertSame('none', $grid['sss'][7]['status']);
        // September is still going.
        $this->assertSame('fut', $grid['sss'][9]['status']);
        $this->assertFalse($grid['sss'][9]['tracked'], 'and cannot be picked yet');
        $this->assertSame('pend', $rows['sss']['status']);
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
        $this->assertSame('late', $this->page()->viewData('rows')['philhealth']['status']);
    }

    private function rail(string $url): string
    {
        $html  = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
        $start = strpos($html, '<nav class="nav-menu">');

        return substr($html, $start, strpos($html, '</nav>', $start) - $start);
    }

    public function test_the_sidebar_lists_the_tracker_under_payroll_records_with_what_is_due(): void
    {
        // SSS pending; PhilHealth, Pag-IBIG (and BIR, if anything was withheld) overdue.
        $expected = (string) collect($this->page()->viewData('rows'))->whereIn('status', ['pend', 'late'])->count();

        // In Payroll Records or the tracker, its pages open out under it.
        foreach (['/payroll-records', '/remittances'] as $url) {
            $rail    = $this->rail($url);
            $records = strpos($rail, 'href="' . url('/payroll-records') . '"');
            $sub     = strpos($rail, 'class="nav-sub"');
            $tracker = strpos($rail, 'href="' . route('remittances.index') . '"');

            $this->assertNotFalse($sub, "{$url}: the sub-items are open");
            $this->assertTrue($records < $sub && $sub < $tracker, "{$url}: under Payroll Records");
            $this->assertStringContainsString('Remittance tracker', $rail);
            preg_match('/nav-sub-badge[^>]*>(\d+)</', $rail, $m);
            $this->assertSame($expected, $m[1] ?? null, "{$url}: the count sits on the tracker");

            // Payroll Records itself folds them, and its own count waits
            // there for when they are folded.
            $this->assertStringContainsString('data-sub-toggle="navSubRecords" aria-controls="navSubRecords" aria-expanded="true"', $rail);
            $this->assertStringContainsString('id="navSubRecords"', $rail);
            $this->assertMatchesRegularExpression('/nav-sub-badge nav-parent-badge"[^>]*>' . $expected . '</', $rail);
        }

        // Anywhere else they fold away, and the count rides on Payroll Records.
        $rail = $this->rail('/dashboard');
        $this->assertStringNotContainsString('class="nav-sub"', $rail);
        $this->assertStringNotContainsString('Remittance tracker', $rail);
        $this->assertStringNotContainsString('aria-expanded', $rail);
        $this->assertStringContainsString("sessionStorage.removeItem('jeyanco-nav-records')", $rail, 'leaving forgets the fold');
        $this->assertMatchesRegularExpression(
            '/href="' . preg_quote(url('/payroll-records'), '/') . '">.*?Payroll Records.*?nav-sub-badge[^>]*>' . $expected . '</s', $rail);
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
