<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The receipt a remittance payment was marked paid with.
     *
     * Kept in the database, not on disk. The app's disk is wiped on every
     * deploy (Railway has no volume for it; uploaded photos and the logo
     * already vanish that way), and a receipt is the office's proof that the
     * money went to the agency. The file is stored base64-encoded in a
     * LONGTEXT, which MySQL and SQLite both hold without an extension or a
     * binary column type Laravel does not size. Its own table, so listing
     * payments never reads the files.
     */
    public function up(): void
    {
        if (Schema::hasTable('remittance_receipts')) {
            return;
        }

        Schema::create('remittance_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remittance_payment_id')->unique()
                  ->constrained('remittance_payments')->cascadeOnDelete();
            $table->string('name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->longText('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_receipts');
    }
};
