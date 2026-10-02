<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A payslip printed on its own takes the whole sheet (Michael, 2026-10-02).
 * It used to print as the same cut-out the batch uses, small in the corner
 * of an otherwise empty page. Several slips are still cut-outs, two across.
 *
 * The view is rendered directly, as SystemSettingsTest does: what is under
 * test is the sheet, not the payroll behind it.
 */
class PayslipPrintLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function slip(int $id, array $over = []): array
    {
        return $over + [
            'employee_id' => $id, 'name' => 'Worker ' . $id, 'position' => 'Mason',
            'workdays' => 2, 'hours' => 12, 'minutes' => 720,
            'regular' => 1200, 'overtime' => 0, 'holidayPay' => 0, 'restDayPay' => 0, 'nightDiffPay' => 0,
            'leavePay' => 0, 'leaveDays' => 0, 'bonus' => 0, 'gross' => 1200,
            'ded' => ['sss' => 64, 'philhealth' => 32, 'pagibig' => 25.6, 'tax' => 0, 'vale' => 0, 'other' => 0],
            'totalDeductions' => 121.6, 'net' => 1078.4, 'advanceDeferred' => 0,
        ];
    }

    private function sheet(array $slips): string
    {
        return view('payslips-batch', [
            'slips'       => collect($slips),
            'periodLabel' => '09/28/2026 – 10/04/2026',
            'from'        => '2026-09-28',
            'to'          => '2026-10-04',
        ])->render();
    }

    public function test_one_payslip_takes_the_whole_sheet(): void
    {
        $html = $this->sheet([$this->slip(1)]);

        $this->assertStringContainsString('<div class="slips one">', $html);
        // Full width, no cut guide, and the type scaled up with it.
        $this->assertStringContainsString('.slips.one { display: block; }', $html);
        $this->assertStringContainsString('.slips.one .slip::before { content: none; }', $html);
        $this->assertStringContainsString('one payslip, the full page', $html);
        $this->assertStringNotContainsString('cut along the dashed line', $html);
    }

    public function test_several_payslips_are_still_cut_outs(): void
    {
        $html = $this->sheet([$this->slip(1), $this->slip(2)]);

        $this->assertStringContainsString('<div class="slips">', $html);
        $this->assertStringNotContainsString('<div class="slips one">', $html);
        $this->assertStringContainsString('cut along the dashed line', $html);
    }

    /**
     * Night work is inside the gross. Without a line of its own, a night
     * shift's earnings (₱1,200 regular) did not add up to its gross (₱1,280).
     */
    public function test_night_differential_has_its_own_line_when_there_is_any(): void
    {
        $night = $this->sheet([$this->slip(1, ['nightDiffPay' => 80, 'gross' => 1280])]);
        $this->assertStringContainsString('Night Diff', $night);
        $this->assertStringContainsString('&#8369;80.00', $night);

        $this->assertStringNotContainsString('Night Diff', $this->sheet([$this->slip(2)]), 'a day shift has no such line');
    }
}
