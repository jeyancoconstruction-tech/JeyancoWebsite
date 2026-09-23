<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the two tables that grow every day.
 *
 * Attendance gains two rows per worker per working day, and nearly every page
 * reads it by date: the dashboard, Attendance, Payroll Records, Analytics and
 * every payroll run. With only the foreign keys indexed, each of those reads
 * walked the whole table, and got slower every week the history got longer.
 * The open-shift lookup (timed in, not out) runs before every payroll
 * computation and on every kiosk scan, and gets its own.
 *
 * The audit log gains a row on every save anybody makes, and its page reads
 * it by date and nothing else unless a filter is set.
 *
 * Nothing reads these by name. A server that has not run this yet is slower,
 * not broken.
 */
return new class extends Migration
{
    private const INDEXES = [
        'attendances' => [
            'attendances_date_index'              => ['date'],
            'attendances_time_out_time_in_index'  => ['time_out', 'time_in'],
        ],
        'audit_logs' => [
            'audit_logs_created_at_index' => ['created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
