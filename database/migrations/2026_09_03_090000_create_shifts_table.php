<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two shifts, and who works them.
     *
     * The shift was one value for the whole office, which cannot describe a
     * crew split across day and night. It belongs to the worker instead — and,
     * once worked, to the record.
     *
     * Both matter. If lateness read the employee's current shift, moving
     * somebody to the night crew in October would change how late they were in
     * September, and payroll already handed out would stop agreeing with the
     * payslip. So the shift is stamped on the attendance record when it is
     * created, and payroll reads it from there.
     *
     * `employees` and `attendances` are older than this migration and `shifts`
     * is created here before either is altered, so every foreign key points
     * backwards — nothing for MySQL to reject.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);

            // When the shift is meant to start, and how long after it before
            // the day counts as late.
            $table->time('starts_at');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);

            // A shift that runs past midnight: a clock-in long before the start
            // is the small hours of the next morning, not an arrival most of a
            // day early.
            $table->boolean('crosses_midnight')->default(false);

            $table->timestamps();
        });

        // Seeded from the settings the office is already running, so nothing
        // changes the day this lands.
        $current = DB::table('system_settings')->first();

        DB::table('shifts')->insert([
            [
                'name'                 => 'Day',
                'starts_at'            => $current->expected_time_in ?? '08:00:00',
                'grace_period_minutes' => $current->grace_period_minutes ?? 15,
                'crosses_midnight'     => false,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'name'                 => 'Night',
                'starts_at'            => '22:00:00',
                'grace_period_minutes' => $current->grace_period_minutes ?? 15,
                'crosses_midnight'     => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
        ]);

        Schema::table('employees', function (Blueprint $table) {
            // Null means the office default — a worker nobody has assigned is
            // not thereby put on the night crew.
            $table->foreignId('shift_id')->nullable()->after('labor_type_id')->constrained()->nullOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table) {
            // What the worker was on when this day was worked. Null on every
            // record that predates this, which payroll reads as the office
            // setting it was computed under.
            $table->foreignId('shift_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::dropIfExists('shifts');
    }
};
