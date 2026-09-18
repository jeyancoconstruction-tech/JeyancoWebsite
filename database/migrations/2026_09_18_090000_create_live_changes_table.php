<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What has changed, and how many times.
 *
 * One row per topic — attendance, employees, payroll, advances — holding a
 * number that goes up by one every time anything under that topic is written,
 * from the web or from the kiosk. Nothing about the change itself is kept
 * here: a page that sees the number move asks the server for its own current
 * contents, so the row stays a few bytes no matter how much data moved.
 *
 * It is read once a second by every open stream, so it must stay small: a
 * dozen rows, addressed by their primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_changes')) {
            return;
        }

        Schema::create('live_changes', function (Blueprint $table) {
            $table->string('topic', 40)->primary();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamp('changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_changes');
    }
};
