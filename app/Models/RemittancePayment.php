<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A month's remittance to one agency, marked paid in the Remittance Tracker.
 *
 * What was owed is never stored: it is the employee contributions payroll
 * deducted (App\Services\RemittanceTracker). This is only what the office
 * paid, when, through what, and the reference the agency gave back.
 */
class RemittancePayment extends Model
{
    protected $fillable = [
        'agency', 'period', 'amount', 'paid_on', 'reference', 'channel', 'recorded_by',
    ];

    protected $casts = [
        'period'  => 'date',
        'paid_on' => 'date',
        'amount'  => 'decimal:2',
    ];

    public function receipt(): HasOne
    {
        // The file itself is loaded only when it is asked for.
        return $this->hasOne(RemittanceReceipt::class)->select(['id', 'remittance_payment_id', 'name', 'mime', 'size']);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
