<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The full worker profile captured on the Register Employee form.
 *
 * Until now a worker was a name, a labor type and a rate — enough to run
 * payroll, not enough to be a personnel record. These columns hold what a
 * resume carries: who the person is, how to reach them and their next of kin,
 * where they live, what they studied, where they worked before, and what they
 * can do.
 *
 * Every column is nullable. Workers the kiosk creates from a fingerprint scan
 * have none of this, and the office fills it in later — an incomplete profile
 * must never block someone from being paid.
 *
 * `education`, `work_experience` and `skills` are lists rather than single
 * values, so they are stored as JSON instead of spawning three child tables
 * for data that is only ever read back with the employee.
 */
return new class extends Migration
{
    /** column => closure that defines it, so each is added only if absent. */
    private function columns(): array
    {
        return [
            // ── Personal ────────────────────────────────────────────────────
            'birth_date'    => fn (Blueprint $t) => $t->date('birth_date')->nullable(),
            'birth_place'   => fn (Blueprint $t) => $t->string('birth_place', 180)->nullable(),
            'gender'        => fn (Blueprint $t) => $t->string('gender', 20)->nullable(),
            'civil_status'  => fn (Blueprint $t) => $t->string('civil_status', 20)->nullable(),
            'nationality'   => fn (Blueprint $t) => $t->string('nationality', 60)->nullable(),
            'religion'      => fn (Blueprint $t) => $t->string('religion', 60)->nullable(),
            'blood_type'    => fn (Blueprint $t) => $t->string('blood_type', 5)->nullable(),

            // ── Contact ─────────────────────────────────────────────────────
            'phone'                      => fn (Blueprint $t) => $t->string('phone', 30)->nullable(),
            'email'                      => fn (Blueprint $t) => $t->string('email', 150)->nullable(),
            'emergency_contact_name'     => fn (Blueprint $t) => $t->string('emergency_contact_name', 150)->nullable(),
            'emergency_contact_relation' => fn (Blueprint $t) => $t->string('emergency_contact_relation', 60)->nullable(),
            'emergency_contact_phone'    => fn (Blueprint $t) => $t->string('emergency_contact_phone', 30)->nullable(),

            // ── Address ─────────────────────────────────────────────────────
            'address_street'   => fn (Blueprint $t) => $t->string('address_street', 200)->nullable(),
            'address_barangay' => fn (Blueprint $t) => $t->string('address_barangay', 120)->nullable(),
            'address_city'     => fn (Blueprint $t) => $t->string('address_city', 120)->nullable(),
            'address_province' => fn (Blueprint $t) => $t->string('address_province', 120)->nullable(),
            'address_postal'   => fn (Blueprint $t) => $t->string('address_postal', 20)->nullable(),

            // ── Government IDs — needed for statutory payroll deductions ────
            'sss_number'        => fn (Blueprint $t) => $t->string('sss_number', 40)->nullable(),
            'philhealth_number' => fn (Blueprint $t) => $t->string('philhealth_number', 40)->nullable(),
            'pagibig_number'    => fn (Blueprint $t) => $t->string('pagibig_number', 40)->nullable(),
            'tin_number'        => fn (Blueprint $t) => $t->string('tin_number', 40)->nullable(),

            // ── Job ─────────────────────────────────────────────────────────
            // `position` is derived from the labor type and drives payroll.
            // `job_title` is the descriptive title on the worker's record.
            'job_title'  => fn (Blueprint $t) => $t->string('job_title', 150)->nullable(),
            'date_hired' => fn (Blueprint $t) => $t->date('date_hired')->nullable(),

            // ── Resume lists ────────────────────────────────────────────────
            'education'       => fn (Blueprint $t) => $t->json('education')->nullable(),
            'work_experience' => fn (Blueprint $t) => $t->json('work_experience')->nullable(),
            'skills'          => fn (Blueprint $t) => $t->json('skills')->nullable(),

            // ── Anything else worth keeping on file ─────────────────────────
            'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
        ];
    }

    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach ($this->columns() as $name => $define) {
                if (! Schema::hasColumn('employees', $name)) {
                    $define($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $existing = array_filter(
                array_keys($this->columns()),
                fn ($c) => Schema::hasColumn('employees', $c)
            );

            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
