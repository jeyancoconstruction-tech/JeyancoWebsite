<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A deduction the office applies. `source` says who owns the figure, so this
 * catalogue can never quietly override PayrollService or a loan balance.
 */
class DeductionType extends Model
{
    public const CATEGORIES = [
        'statutory' => 'Statutory',
        'loan'      => 'Loan / Advance',
        'company'   => 'Company',
        'other'     => 'Other',
    ];

    public const SOURCES = [
        'settings' => 'Payroll Settings',
        'ledger'   => 'Balance / Ledger',
        'manual'   => 'Fixed here',
    ];

    protected $fillable = [
        'code', 'name', 'category', 'source', 'amount', 'percentage',
        'is_active', 'sort_order', 'description',
    ];

    protected $casts = [
        'amount'     => 'float',
        'percentage' => 'float',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst((string) $this->source);
    }

    /** Only a manual row states its own figure; the rest are owned elsewhere. */
    public function getRuleLabelAttribute(): string
    {
        if ($this->source !== 'manual') {
            return $this->source_label;
        }

        if ($this->percentage) {
            return rtrim(rtrim(number_format($this->percentage, 3), '0'), '.') . '% of gross';
        }

        return $this->amount ? '₱' . number_format($this->amount, 2) : '—';
    }

    /**
     * The peso figure a manual deduction produces for a given gross. Rows owned
     * by settings or a ledger answer zero: their owner supplies the number.
     */
    public function amountFor(float $gross): float
    {
        if (! $this->is_active || $this->source !== 'manual') {
            return 0.0;
        }

        if ($this->percentage) {
            return round($gross * ($this->percentage / 100), 2);
        }

        return round((float) $this->amount, 2);
    }
}

