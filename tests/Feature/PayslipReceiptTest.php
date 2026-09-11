<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the payslip receipt is for.
 *
 * It answers "why is this figure what it is" for one worker and one period.
 * It carried a Rates applied table underneath — the office-wide multipliers
 * and contribution percentages, repeated on every receipt — which answers a
 * different question, one Payroll Settings already owns.
 *
 * Nothing is lost by dropping it: every line of the receipt already names
 * the multiplier that produced it, which is the version that is actually
 * about this payslip.
 */
class PayslipReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $admin = User::create([
            'name' => 'Admin', 'username' => 'admin.receipt', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        return $this->actingAs($admin)->get('/payroll-records')->assertOk()->getContent();
    }

    public function test_the_receipt_no_longer_repeats_the_office_wide_rates(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('Rates applied', $html);
        $this->assertStringNotContainsString('statutory defaults', $html);
        $this->assertStringNotContainsString('Wage floor', $html);
        $this->assertStringNotContainsString('rc-rates', $html, 'and its CSS went with it');
    }

    public function test_nor_the_footnote_that_explained_that_table(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('Night differential is 10:00 PM', $html);
        $this->assertStringNotContainsString('rc-note', $html);
    }

    /**
     * The arithmetic strip is three label-and-figure pairs, and each pair
     * has to stay together.
     *
     * It was six children of a three-column grid, so the grid laid them
     * out across the rows rather than beside their own figures: the slip
     * read "Gross · 932.50 · − Deductions" on one line and the remaining
     * three cells on the next.
     */
    public function test_each_figure_stays_beside_its_own_label(): void
    {
        $html = $this->page();

        $this->assertSame(3, substr_count($html, 'class="rc-math-item"'),
            'one cell per pair, so a pair cannot be split across rows');
    }

    /** Each line still names the multiplier behind it, which is the point. */
    public function test_every_line_still_says_what_produced_it(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('RATES.ot_multiplier', $html);
        $this->assertStringContainsString('RATES.night_diff_multiplier', $html);
        $this->assertStringContainsString('RATES.sss_rate', $html);
    }
}
