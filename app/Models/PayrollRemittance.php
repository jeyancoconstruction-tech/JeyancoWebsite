<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * One payslip line that leaves the company — a contribution to remit, or the
 * net pay itself — and how far it has got. The migration says why.
 */
class PayrollRemittance extends Model
{
    /** What is tracked, in the order the tracker lists it. */
    public const KINDS = [
        'sss'        => 'SSS',
        'philhealth' => 'PhilHealth',
        'pagibig'    => 'Pag-IBIG',
        'bir'        => 'BIR (withholding tax)',
        'net_pay'    => 'Net pay',
    ];

    public const PENDING   = 'pending';
    public const SUBMITTED = 'submitted';
    public const DONE      = 'done';

    protected $fillable = [
        'payroll_run_item_id', 'kind', 'status', 'amount',
        'submitted_by', 'submitted_at', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'amount'       => 'float',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollRunItem::class, 'payroll_run_item_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** What the payslip line for this kind comes to. */
    public static function amountFor(PayrollRunItem $item, string $kind): float
    {
        return (float) match ($kind) {
            'sss'        => $item->sss,
            'philhealth' => $item->philhealth,
            'pagibig'    => $item->pagibig,
            'bir'        => $item->tax,
            'net_pay'    => $item->net_pay,
            default      => 0,
        };
    }

    /**
     * The status an action moves a line to, or null when it cannot from here.
     *
     * A contribution is submitted to its agency and then confirmed remitted.
     * Net pay has no middle step. Undo walks back one step, for the click
     * that landed on the wrong row.
     */
    public static function after(string $kind, string $status, string $action): ?string
    {
        if ($kind === 'net_pay') {
            return match ([$status, $action]) {
                [self::PENDING, 'done'] => self::DONE,
                [self::DONE, 'undo']    => self::PENDING,
                default                 => null,
            };
        }

        return match ([$status, $action]) {
            [self::PENDING, 'submit']  => self::SUBMITTED,
            [self::SUBMITTED, 'done']  => self::DONE,
            [self::SUBMITTED, 'undo']  => self::PENDING,
            [self::DONE, 'undo']       => self::SUBMITTED,
            default                    => null,
        };
    }

    /**
     * Whether the table is there yet. Migrations are run by hand on this
     * deployment, so the page has to open in the gap between a push and the
     * migrate that follows it. Asked per request, never cached: a long-lived
     * worker would otherwise keep answering "no" after the migrate.
     */
    public static function available(): bool
    {
        return Schema::hasTable('payroll_remittances');
    }
}
