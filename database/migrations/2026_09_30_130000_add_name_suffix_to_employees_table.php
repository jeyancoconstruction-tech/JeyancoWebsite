<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jr., Sr., II… beside the last name on Register and Edit Employee
 * (2026-09-30). Kept apart from the last name so "Dela Cruz" stays the
 * surname, and added to the end of `name` when the name is composed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name_suffix', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('name_suffix');
        });
    }
};
