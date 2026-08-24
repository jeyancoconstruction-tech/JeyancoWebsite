<?php

namespace App\Http\Controllers;

use App\Services\PayrollService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Notifications\PayrollNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Unified Payroll Records module — consolidates the former Pay Periods,
 * Payroll Records and Reports pages into one tabbed page (Reports / By Employee
 * / Pay Periods). One date-range context (weekly Mon–Sun, daily, or custom)
 * plus an optional employee filter drives all tabs. All figures come from
 * PayrollService.
 */
class PayrollRecordsController extends Controller
{
    public function index(Request $request, PayrollService $payroll)
    {
        $period = $this->resolvePeriod($request);

        $data      = $payroll->computeForRange($period['from'], $period['to']);
        $weeks     = $data['weeks'];
        $days      = $data['days'];
        $employees = $data['employees'];

        // Optional employee filter (name or DB id #) — narrows every tab.
        $search = trim((string) $request->input('employee', ''));
        $selectedEmployee = null;

        if ($search !== '') {
            $needle = strtolower(ltrim($search, '#'));
            $match  = fn ($e) => str_contains(strtolower($e['name']), $needle) || (string) $e['employee_id'] === $needle;

            $employees = array_values(array_filter($employees, $match));
            $ids = array_column($employees, 'employee_id');

            foreach ($days as &$day) {
                $day['details'] = array_values(array_filter($day['details'], fn ($d) => in_array($d['employee_id'], $ids)));
                $day['total']   = round(array_sum(array_column($day['details'], 'net')), 2);
            }
            unset($day);
            $days = array_values(array_filter($days, fn ($day) => count($day['details']) > 0));

            foreach ($weeks as &$w) {
                $w['details']        = array_values(array_filter($w['details'], fn ($d) => in_array($d['employee_id'], $ids)));
                $w['employee_count'] = count($w['details']);
                $w['total_payroll']  = round(array_sum(array_column($w['details'], 'net')), 2);
            }
            unset($w);
            $weeks = array_values(array_filter($weeks, fn ($w) => count($w['details']) > 0));

            $selectedEmployee = $employees[0] ?? null;
        }

        $summary = $this->summarize($employees);

        // ── Notifications ──────────────────────────────────────────────────
        if (! empty($employees) && $search === '') {
            $user      = auth()->user();
            $net       = number_format($summary['net'] ?? 0, 2);
            $empCount  = count($employees);

            PayrollNotification::fireOnce($user, 'period_computed',
                'Payroll Computed',
                "Period {$period['label']}: {$empCount} employee" . ($empCount > 1 ? 's' : '') . ", ₱{$net} total net pay."
            );

            // High vale — alert if any employee has a cumulative vale > 5000
            $highValeEmps = collect($employees)->filter(fn ($e) => ($e['totals']['deductions']['vale'] ?? 0) > 5000);
            if ($highValeEmps->isNotEmpty()) {
                $names = $highValeEmps->pluck('name')->join(', ');
                PayrollNotification::fireOnce($user, 'high_vale',
                    'High Vale Balance',
                    "High cash advance this period: {$names}."
                );
            }
        }

        return view('payroll-records', compact('period', 'weeks', 'days', 'employees', 'summary', 'search', 'selectedEmployee'));
    }

    /**
     * Export the current period's per-employee totals as CSV (no dependency).
     */
    /**
     * Export the current period's per-employee totals as a real .xlsx workbook.
     *
     * This used to stream an HTML table named ".xls", which Excel opened only
     * after warning that the file's extension did not match its contents. A
     * genuine xlsx also means the money columns arrive as numbers Excel can sum
     * and reformat, rather than as pre-formatted text.
     */
    public function exportExcel(Request $request, PayrollService $payroll)
    {
        $period    = $this->resolvePeriod($request);
        $employees = $this->filterEmployees(
            $payroll->computeForRange($period['from'], $period['to'])['employees'],
            $request
        );
        $totals = $this->summarize($employees);

        $columns = ['Employee ID', 'Name', 'Position', 'Workdays', 'Hours', 'Gross Pay',
                    'Overtime', 'Holiday Pay', 'Rest Day Pay', 'Bonus', 'Deductions', 'Net Pay'];
        $lastCol = 'L';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Records');

        // Title band.
        $sheet->setCellValue('A1', 'Jeyanco Construction - Payroll Records');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // Meta band.
        $sheet->setCellValue('A2', 'Period (' . ucfirst($period['mode']) . ')');
        $sheet->setCellValue('C2', $period['label']);
        $sheet->setCellValue('J2', 'Generated');
        $sheet->setCellValue('K2', now()->format('M d, Y'));
        $sheet->mergeCells('A2:B2');
        $sheet->mergeCells('C2:I2');
        $sheet->mergeCells('K2:L2');
        $sheet->getStyle("A2:{$lastCol}2")->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('J2')->getFont()->setBold(true);

        // Header row.
        $headerRow = 4;
        $sheet->fromArray($columns, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE3F4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        // Body. Figures stay numeric so Excel can total and reformat them.
        $row = $headerRow + 1;
        foreach ($employees as $e) {
            $t = $e['totals'];

            // Written as text: the leading "#" and zero padding are the ID's
            // format, and Excel would otherwise strip them.
            $sheet->setCellValueExplicit(
                "A{$row}",
                '#' . str_pad((string) $e['employee_id'], 4, '0', STR_PAD_LEFT),
                DataType::TYPE_STRING
            );

            $sheet->fromArray([[
                $e['name'], $e['position'],
                (int) $t['workdays'], (float) $t['hours'], (float) $t['gross'],
                (float) $t['overtime'], (float) $t['holidayPay'], (float) ($t['restDayPay'] ?? 0),
                (float) $t['bonus'], (float) $t['totalDeductions'], (float) $t['net'],
            ]], null, "B{$row}", true);

            $row++;
        }

        $firstBodyRow = $headerRow + 1;
        $lastBodyRow  = $row - 1;

        // Total row.
        $totalRow = $row;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL - ' . $totals['employee_count'] . ' employee(s)');
        $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
        $sheet->fromArray([[
            $totals['workdays'], $totals['hours'], $totals['gross'], $totals['overtime'],
            $totals['holidayPay'], $totals['restDayPay'], $totals['bonus'],
            $totals['totalDeductions'], $totals['net'],
        ]], null, "D{$totalRow}", true);
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);

        // Number formats: peso columns as currency, hours to 2dp, workdays whole.
        // Matches what the on-screen preview shows. An xlsx sheet is UTF-8 XML,
        // so the peso sign survives the round trip into Excel.
        $peso = "\u{20B1}#,##0.00";
        if ($lastBodyRow >= $firstBodyRow) {
            $sheet->getStyle("D{$firstBodyRow}:D{$lastBodyRow}")->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle("E{$firstBodyRow}:E{$lastBodyRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("F{$firstBodyRow}:{$lastCol}{$lastBodyRow}")->getNumberFormat()->setFormatCode($peso);
        }
        $sheet->getStyle("D{$totalRow}")->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F{$totalRow}:{$lastCol}{$totalRow}")->getNumberFormat()->setFormatCode($peso);

        // Borders, widths, and a frozen header so it stays put while scrolling.
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$totalRow}")->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('94A3B8');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A' . ($headerRow + 1));

        $filename = 'payroll-records_' . $period['from'] . '_to_' . $period['to'] . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Narrow the computed rows by the page's "employee (name or ID)" box. */
    private function filterEmployees(array $employees, Request $request): array
    {
        $search = trim((string) $request->input('employee', ''));
        if ($search === '') {
            return $employees;
        }

        $needle = strtolower(ltrim($search, '#'));

        return array_values(array_filter($employees, function ($e) use ($needle) {
            return str_contains(strtolower($e['name']), $needle)
                || (string) $e['employee_id'] === $needle;
        }));
    }

    /**
     * Resolve the reporting period into a from/to range + display metadata.
     * Weekly mode always yields a Monday–Sunday range.
     */
    private function resolvePeriod(Request $request): array
    {
        $mode  = $request->input('mode', 'weekly');
        $today = Carbon::now();

        if ($mode === 'daily') {
            $date  = $request->filled('date') && strtotime($request->date) ? Carbon::parse($request->date) : $today->copy();
            $from  = $to = $date->toDateString();
            $label = $date->format('l, m/d/Y');
        } elseif ($mode === 'custom') {
            $from = $request->filled('from') && strtotime($request->from)
                ? Carbon::parse($request->from)->toDateString()
                : $today->copy()->startOfMonth()->toDateString();
            $to = $request->filled('to') && strtotime($request->to)
                ? Carbon::parse($request->to)->toDateString()
                : $today->toDateString();
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $label = Carbon::parse($from)->format('m/d/Y') . ' – ' . Carbon::parse($to)->format('m/d/Y');
        } else {
            $mode = 'weekly';
            $week = (string) $request->input('week', '');
            if (preg_match('/^(\d{4})-W(\d{1,2})$/', $week, $m)) {
                $monday = Carbon::now()->setISODate((int) $m[1], (int) $m[2], 1)->startOfDay();
            } else {
                $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
            }
            $sunday = $monday->copy()->addDays(6);
            $from   = $monday->toDateString();
            $to     = $sunday->toDateString();
            $label  = $monday->format('m/d/Y') . ' – ' . $sunday->format('m/d/Y');
        }

        return [
            'mode'        => $mode,
            'from'        => $from,
            'to'          => $to,
            'label'       => $label,
            'week'        => $request->input('week', $today->format('o-\WW')),
            'date'        => $request->input('date', $today->toDateString()),
            'custom_from' => $request->input('from', $today->copy()->startOfMonth()->toDateString()),
            'custom_to'   => $request->input('to', $today->toDateString()),
        ];
    }

    private function summarize(array $employees): array
    {
        $sum = fn ($k) => round(array_sum(array_map(fn ($e) => $e['totals'][$k], $employees)), 2);

        return [
            'employee_count'  => count($employees),
            'workdays'        => (int) array_sum(array_map(fn ($e) => $e['totals']['workdays'], $employees)),
            'hours'           => $sum('hours'),
            'gross'           => $sum('gross'),
            'overtime'        => $sum('overtime'),
            'holidayPay'      => $sum('holidayPay'),
            'restDayPay'      => $sum('restDayPay'),
            'bonus'           => $sum('bonus'),
            'totalDeductions' => $sum('totalDeductions'),
            'net'             => $sum('net'),
        ];
    }
}
