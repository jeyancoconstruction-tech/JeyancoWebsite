<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a worker's pay for a period that leaves the company — a
 * contribution to remit, or the net pay itself — and how far it has got.
 *
 * Keyed by worker and period, not by a payroll run: see the migration that
 * moved it. The period's dates are kept as plain Y-m-d strings rather than
 * cast, so that looking a line up by them matches on SQLite as on MySQL.
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
        'employee_id', 'period_start', 'period_end', 'kind', 'status', 'amount',
        'submitted_by', 'submitted_at', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'amount'       => 'float',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** What a worker's pay for the period comes to on this line. */
    public static function amountOf(array $row, string $kind): float
    {
        return (float) match ($kind) {
            'sss'        => $row['sss'] ?? 0,
            'philhealth' => $row['philhealth'] ?? 0,
            'pagibig'    => $row['pagibig'] ?? 0,
            'bir'        => $row['tax'] ?? 0,
            'net_pay'    => $row['net'] ?? 0,
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
     * Whether the table is there in its current shape. Migrations are run by
     * hand on this deployment, so the page has to open in the gap between a
     * push and the migrate that follows it. Asked per request, never cached:
     * a long-lived worker would otherwise keep answering "no" after it.
     */
    public static function available(): bool
    {
        return Schema::hasColumn('payroll_remittances', 'employee_id');
    }
}
