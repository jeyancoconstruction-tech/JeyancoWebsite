<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The receipt a remittance was marked paid with, stored in the database.
 *
 * The app's disk does not survive a deploy, and a receipt is the office's
 * proof of payment, so the file lives in `data`, base64-encoded.
 */
class RemittanceReceipt extends Model
{
    protected $fillable = ['remittance_payment_id', 'name', 'mime', 'size', 'data'];

    protected $hidden = ['data'];

    /** The file's bytes. */
    public function contents(): string
    {
        return (string) base64_decode((string) $this->data, true);
    }
}
