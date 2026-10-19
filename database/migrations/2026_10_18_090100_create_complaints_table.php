<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complaints a worker posts at the kiosk (RSC 2026-10-18: "Add feature in
 * kiosk for posting complaints of employee").
 *
 * The worker proves who they are with a finger, picks what it is about and
 * says it in a line or two. The office reads them on the web (Complaints),
 * answers and closes them; the worker sees the answer at the kiosk.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaints')) {
            return;
        }

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('kiosk_id')->nullable()->constrained('kiosks')->nullOnDelete();
            $table->string('category', 20);
            $table->text('message');
            $table->string('status', 12)->default('open');   // open | resolved
            $table->text('response')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
