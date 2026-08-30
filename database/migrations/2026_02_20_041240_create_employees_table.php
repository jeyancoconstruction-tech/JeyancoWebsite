<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // BASIC INFO
            $table->string('name');
            $table->string('position');

            // PAYROLL
            $table->decimal('rate_per_hour', 8, 2);

            // PROJECT
            $table->foreignId('project_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('vale', 8, 2)->default(0);

            // SITE SYSTEM
            // The foreign key lives in a later migration: `sites` is not
            // created until 2026_06_18, so constraining it here would point at
            // a table that does not exist yet.
            $table->unsignedBigInteger('site_id')->nullable();

            // KIOSK TRACKING
            // Same story — `kiosks` is not created until 2026_06_29.
            $table->unsignedBigInteger('registered_kiosk_id')->nullable();

            // BIOMETRIC
            $table->string('fingerprint_id')->nullable()->unique();

            // PHOTO
            $table->string('photo')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};