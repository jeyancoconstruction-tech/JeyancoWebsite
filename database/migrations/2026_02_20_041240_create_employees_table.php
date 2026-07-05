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
            $table->unsignedBigInteger('site_id')->nullable();

            // KIOSK TRACKING
            $table->unsignedBigInteger('registered_kiosk_id')->nullable();

            // BIOMETRIC
            $table->string('fingerprint_id')->nullable()->unique();

            // PHOTO
            $table->string('photo')->nullable();

            $table->timestamps();

            // FOREIGN KEYS
            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('set null');

            $table->foreign('registered_kiosk_id')
                ->references('id')
                ->on('kiosks')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};