<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RemittancePayment;
use App\Models\RemittanceReceipt;
use App\Services\RemittanceTracker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Remittance Tracker: each month's SSS, PhilHealth, Pag-IBIG and BIR
 * remittances — what payroll deducted, when it is brought up, and whether the
 * office has paid it. See App\Services\RemittanceTracker for the figures.
 */
class RemittanceController extends Controller
{
    public function __construct(private RemittanceTracker $tracker) {}

    public function index(Request $request)
    {
        $months  = $this->tracker->months();
        $month   = $this->pick($request->query('month'), $months);
        $year    = (int) $month->year;
        $inYear  = array_values(array_filter($months, fn (Carbon $m) => $m->year === $year));

        $totals   = $this->tracker->totals($inYear, $month);
        $payments = RemittancePayment::with(['receipt', 'recorder:id,name'])
            // Half-open: a date column can come back with a time on it.
            ->where('period', '>=', Carbon::create($year, 1, 1)->toDateString())
            ->where('period', '<', Carbon::create($year + 1, 1, 1)->toDateString())
            ->get()
            ->keyBy(fn ($p) => $p->agency . '|' . $p->period->format('Y-m'));

        $key    = $month->format('Y-m');
        $today  = $this->tracker->today();
        // Brought up in the last week of the month after: a reminder, not a deadline.
        $remind = $this->tracker->reminder($month);

        // Everyone with a contribution this month, for their ID numbers.
        $ids = collect($totals[$key]['agencies'] ?? [])->flatMap(fn ($a) => array_column($a['people'], 'id'))->unique();
        $staff = Employee::withTrashed()->whereIn('id', $ids)
            ->get(['id', 'name', 'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'])
            ->keyBy('id');

        $rows = [];
        foreach (RemittanceTracker::AGENCIES as $agency => $a) {
            $total   = (float) ($totals[$key]['agencies'][$agency]['total'] ?? 0);
            $payment = $payments[$agency . '|' . $key] ?? null;

            $rows[$agency] = [
                'agency'  => $agency,
                'a'       => $a,
                'total'   => $total,
                'people'  => array_map(fn ($p) => $p + [
                    'code'      => '#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT),
                    'id_number' => trim((string) ($staff[$p['id']]->{$a['id']} ?? '')),
                ], $totals[$key]['agencies'][$agency]['people'] ?? []),
                'remind'  => $remind,
                'days'    => (int) $today->diffInDays($remind[0], false),
                'status'  => $this->tracker->status($agency, $month, $total, $payment),
                'payment' => $payment,
            ];
        }

        // The year at a glance: every agency, every month of the year.
        $firstTracked = $months[0];
        $grid = [];
        foreach (RemittanceTracker::AGENCIES as $agency => $a) {
            for ($m = 1; $m <= 12; $m++) {
                $cell  = Carbon::create($year, $m, 1, 0, 0, 0, 'Asia/Manila');
                $k     = $cell->format('Y-m');
                $total = (float) ($totals[$k]['agencies'][$agency]['total'] ?? 0);
                $status = $cell->lt($firstTracked) && ! isset($payments[$agency . '|' . $k])
                    ? 'none'
                    : $this->tracker->status($agency, $cell, $total, $payments[$agency . '|' . $k] ?? null);
                $grid[$agency][$m] = ['key' => $k, 'status' => $status, 'tracked' => isset($totals[$k])];
            }
        }

        return view('remittances.index', [
            'months'   => $months,
            'month'    => $month,
            'rows'     => $rows,
            'grid'     => $grid,
            'year'     => $year,
            'weeks'    => $this->tracker->weeksOf($month),
            'running'  => $month->gte($this->tracker->thisMonth()),
            'remind'   => $remind,
            'today'    => $today,
            'channels' => RemittanceTracker::CHANNELS,
        ]);
    }

    /** Mark a month's remittance to one agency as paid. */
    public function store(Request $request)
    {
        $months = array_map(fn (Carbon $m) => $m->format('Y-m'), $this->tracker->closedMonths());

        $data = $request->validate([
            'agency'    => ['required', Rule::in(array_keys(RemittanceTracker::AGENCIES))],
            'month'     => ['required', 'date_format:Y-m', Rule::in($months)],
            'amount'    => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'paid_on'   => ['required', 'date', 'before_or_equal:today', 'after_or_equal:2000-01-01'],
            'reference' => ['required', 'string', 'max:64'],
            'channel'   => ['required', 'string', 'max:64'],
            'receipt'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:2048'],
        ], [
            'month.in'               => 'Only a month that has ended can be marked paid.',
            'reference.required'     => 'Enter the reference or PRN number.',
            'paid_on.before_or_equal' => 'The date paid cannot be in the future.',
            'receipt.mimes'          => 'The receipt has to be a photo (JPG, PNG, WebP) or a PDF.',
            'receipt.max'            => 'The receipt can be at most 2 MB.',
        ]);

        $agency  = $data['agency'];
        $channel = trim($data['channel']);
        if (! in_array($channel, array_merge([RemittanceTracker::AGENCIES[$agency]['channel']], RemittanceTracker::CHANNELS), true)) {
            return back()->withInput()->withErrors(['channel' => 'Choose how it was paid from the list.']);
        }

        $period = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01', 'Asia/Manila')->toDateString();
        if (RemittancePayment::where('agency', $agency)->whereDate('period', $period)->exists()) {
            return back()->withInput()->withErrors(['reference' => 'This month is already marked paid for ' . RemittanceTracker::AGENCIES[$agency]['name'] . '.']);
        }

        $payment = RemittancePayment::create([
            'agency'      => $agency,
            'period'      => $period,
            'amount'      => $data['amount'],
            'paid_on'     => $data['paid_on'],
            'reference'   => trim($data['reference']),
            'channel'     => $channel,
            'recorded_by' => $request->user()?->id,
        ]);

        if ($file = $request->file('receipt')) {
            RemittanceReceipt::create([
                'remittance_payment_id' => $payment->id,
                'name'                  => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime'                  => $file->getMimeType() ?: 'application/octet-stream',
                'size'                  => (int) $file->getSize(),
                'data'                  => base64_encode((string) file_get_contents($file->getRealPath())),
            ]);
        }

        $this->tracker->forgetBadge();
        $name = RemittanceTracker::AGENCIES[$agency]['name'];

        return redirect()->route('remittances.index', ['month' => $data['month']])
            ->with('success', "{$name} " . Carbon::parse($period)->format('M Y') . ' marked as paid.');
    }

    /** Take a payment back off, receipt and all — for one marked by mistake. */
    public function destroy(RemittancePayment $payment)
    {
        $month = $payment->period->format('Y-m');
        $name  = RemittanceTracker::AGENCIES[$payment->agency]['name'] ?? strtoupper($payment->agency);

        RemittanceReceipt::where('remittance_payment_id', $payment->id)->delete();
        $payment->delete();
        $this->tracker->forgetBadge();

        return redirect()->route('remittances.index', ['month' => $month])
            ->with('success', "{$name} " . Carbon::parse($month . '-01')->format('M Y') . ' is no longer marked paid.');
    }

    /** The receipt, opened in the browser. */
    public function receipt(RemittancePayment $payment)
    {
        $receipt = RemittanceReceipt::where('remittance_payment_id', $payment->id)->firstOrFail();
        $name    = str_replace(['"', "\r", "\n"], '', $receipt->name);

        return response($receipt->contents(), 200, [
            'Content-Type'           => $receipt->mime,
            'Content-Disposition'    => 'inline; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=0',
        ]);
    }

    /**
     * A month's contributions per employee, for Excel — one agency, or all
     * four one under the other. An HTML table under an .xls name, the way
     * Payroll Records exports: a real .xlsx needs ext-zip, which the
     * deployment image does not have.
     */
    public function report(Request $request)
    {
        $months = $this->tracker->months();
        $month  = $this->pick($request->query('month'), $months);
        $only   = $request->query('agency');
        $only   = array_key_exists((string) $only, RemittanceTracker::AGENCIES) ? $only : null;

        $totals = $this->tracker->totals([$month], $month)[$month->format('Y-m')] ?? ['agencies' => []];
        $ids    = collect($totals['agencies'])->flatMap(fn ($a) => array_column($a['people'], 'id'))->unique();
        $staff  = Employee::withTrashed()->whereIn('id', $ids)
            ->get(['id', 'name', 'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'])
            ->keyBy('id');

        $sections = [];
        foreach (RemittanceTracker::AGENCIES as $agency => $a) {
            if ($only && $only !== $agency) {
                continue;
            }
            $sections[] = [
                'a'      => $a,
                'remind' => $this->tracker->reminder($month),
                'total'  => (float) ($totals['agencies'][$agency]['total'] ?? 0),
                'people' => array_map(fn ($p) => $p + [
                    'id_number' => trim((string) ($staff[$p['id']]->{$a['id']} ?? '')),
                ], $totals['agencies'][$agency]['people'] ?? []),
            ];
        }

        [$from, $to] = $this->tracker->weeksOf($month);
        $html = view('remittances.report', [
            'sections' => $sections, 'month' => $month, 'from' => $from, 'to' => $to,
        ])->render();

        $file = 'remittances_' . ($only ?: 'all') . '_' . $month->format('Y-m') . '.xls';

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
    }

    /** The month asked for, if the tracker covers it; otherwise the latest. */
    private function pick(?string $asked, array $months): Carbon
    {
        foreach ($months as $m) {
            if ($m->format('Y-m') === $asked) {
                return $m->copy();
            }
        }

        return end($months)->copy();
    }
}
