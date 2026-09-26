<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When each agency's monthly remittance falls due (Payroll Settings).
     *
     * A day of the month after the one the contributions are for: August's
     * SSS is due on the 30th of September. The defaults are the ones the
     * Remittance Tracker was designed with; an office whose employer number
     * puts it on another day changes them in Payroll Settings.
     */
    private const COLUMNS = [
        'sss_due_day'        => 30,
        'philhealth_due_day' => 15,
        'pagibig_due_day'    => 15,
        'bir_due_day'        => 10,
    ];

    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $day) {
                if (! Schema::hasColumn('system_settings', $column)) {
                    $table->unsignedTinyInteger($column)->default($day);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
