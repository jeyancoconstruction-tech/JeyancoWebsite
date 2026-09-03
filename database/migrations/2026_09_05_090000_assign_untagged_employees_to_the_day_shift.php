<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The shifts arrived with nobody on them, so every worker fell back to
        // the office default and both shift cards read "0 workers". The day
        // crew is the larger of the two here, so it is the starting point the
        // night crew gets moved off — a starting point everyone can see beats a
        // blank nobody can act on.
        //
        // Safe to run against worked days: an attendance record is stamped with
        // its shift when it is created, so nothing already recorded moves.
        $day = DB::table('shifts')->where('crosses_midnight', false)->orderBy('id')->first();

        if (! $day) {
            return;
        }

        DB::table('employees')->whereNull('shift_id')->update(['shift_id' => $day->id]);
    }

    public function down(): void
    {
        // Untagging everyone would throw away assignments made since, and there
        // is no record of which were the migration's. The column is nullable
        // and a shift is set per worker, so there is nothing to undo.
    }
};
