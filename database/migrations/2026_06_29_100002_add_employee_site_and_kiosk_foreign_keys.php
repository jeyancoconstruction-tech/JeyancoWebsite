<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the two foreign keys that create_employees_table used to declare
 * inline on `site_id` and `registered_kiosk_id`.
 *
 * It could not keep them there: employees is created on 2026_02_20, while
 * `sites` arrives 2026_06_18 and `kiosks` 2026_06_29. SQLite accepts a
 * constraint pointing at a table that does not exist yet, so local installs
 * never noticed — MySQL rejects it and leaves the whole migration unrecorded,
 * which crash-loops a fresh deploy. This runs after both tables exist.
 */
return new class extends Migration {
    public function up(): void
    {
        // SQLite has no ALTER TABLE ADD CONSTRAINT — Laravel would have to
        // rebuild the table — and installs on SQLite already carry these keys
        // from the original create migration. Nothing to do there.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->addForeignKey('site_id', 'sites');
        $this->addForeignKey('registered_kiosk_id', 'kiosks');
    }

    /**
     * Add one constraint, but never at the cost of the deploy.
     *
     * On a database that has been running a while, a column can hold an id
     * whose parent row is long gone — MySQL then refuses the constraint. That
     * is worth knowing about, but it is not worth halting a release over: the
     * key is a tidiness measure, while a failed migration takes the whole site
     * down. Log it and carry on.
     */
    private function addForeignKey(string $column, string $references): void
    {
        if ($this->hasForeignKey('employees', $column)) {
            return;
        }

        try {
            Schema::table('employees', function (Blueprint $table) use ($column, $references) {
                $table->foreign($column)
                      ->references('id')->on($references)
                      ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            Log::warning(
                "Could not add employees.{$column} -> {$references}.id foreign key: "
                . $e->getMessage()
                . ' — most likely an orphaned value. The column still works; the constraint was skipped.'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropForeign(['registered_kiosk_id']);
        });
    }

    /**
     * A fresh MySQL database has neither key, but an install that already ran
     * the old create migration may have one. Check before adding either.
     */
    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
