<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who did what, where, and when.
 *
 * Append-only by intent: nothing in the application updates or deletes a row,
 * and the screen over it is read-only for everyone. Writes are best-effort —
 * a failure to log must never be the reason a payroll approval fails — so the
 * logger swallows its own errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Kept even if the account is later deleted, hence the copied name.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 120)->nullable();

            $table->string('module', 60);            // Payroll, Employees, Leave, ...
            $table->string('action', 60);            // created, approved, deleted, ...
            $table->text('description')->nullable();

            // What it happened to, when there is one thing.
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['module', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
