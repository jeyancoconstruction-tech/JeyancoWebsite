<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A time out the office settled can be undone (Michael, 2026-10-03).
 *
 * Settling overwrites the time the system guessed, or fills one that was
 * never there. What the stretch was before — its time out, close type,
 * review flag and reason — is kept here as JSON, so Undo puts back exactly
 * that rather than a guess at the guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->text('settled_from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('settled_from');
        });
    }
};
