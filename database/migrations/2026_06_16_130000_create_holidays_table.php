<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the per-record `attendances.is_holiday` flag with a global,
     * date-based holiday overlay. Any dates that were previously flagged are
     * preserved by copying them into the new `holidays` table before the
     * column is dropped — no attendance data is lost.
     */
    public function up(): void
    {
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique();
                $table->string('title')->nullable();
                $table->timestamps();
            });
        }

        // Preserve any previously flagged holiday dates as global holidays.
        if (Schema::hasColumn('attendances', 'is_holiday')) {
            $dates = DB::table('attendances')
                ->where('is_holiday', 1)
                ->distinct()
                ->pluck('date');

            foreach ($dates as $date) {
                DB::table('holidays')->updateOrInsert(
                    ['date' => $date],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('is_holiday');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'is_holiday')) {
                $table->boolean('is_holiday')->default(false)->after('deductions');
            }
        });

        Schema::dropIfExists('holidays');
    }
};
