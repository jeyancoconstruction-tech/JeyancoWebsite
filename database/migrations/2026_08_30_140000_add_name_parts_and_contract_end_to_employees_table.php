<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Name split into parts, plus the date a contract ends.
 *
 * `name` stays exactly where it is and keeps its meaning. It is what payroll,
 * attendance, payslips, the kiosk and every search read, and rewriting all of
 * that to compose three columns would be a large change for no gain. The parts
 * are captured for the personnel record and `name` is composed from them on
 * save, so the two never disagree.
 *
 * Workers the kiosk creates from a fingerprint scan still arrive with only a
 * name and no parts — which is why these are nullable, and why nothing may
 * assume they are filled in.
 */
return new class extends Migration
{
    private function columns(): array
    {
        return [
            'first_name'  => fn (Blueprint $t) => $t->string('first_name', 100)->nullable()->after('name'),
            'middle_name' => fn (Blueprint $t) => $t->string('middle_name', 100)->nullable()->after('first_name'),
            'last_name'   => fn (Blueprint $t) => $t->string('last_name', 100)->nullable()->after('middle_name'),

            // Contractual only: when the engagement finishes.
            'end_of_contract' => fn (Blueprint $t) => $t->date('end_of_contract')->nullable(),
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
            $existing = array_values(array_filter(
                array_keys($this->columns()),
                fn ($c) => Schema::hasColumn('employees', $c)
            ));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
