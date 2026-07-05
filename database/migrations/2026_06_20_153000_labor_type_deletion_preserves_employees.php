<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Deleting a labor type must NOT delete its employees. Switch the
            // foreign key from cascade-delete to set-null so the employee record
            // survives with no labor type assigned.
            $table->dropForeign(['labor_type_id']);
            $table->foreign('labor_type_id')
                  ->references('id')->on('labor_types')
                  ->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            // Allow the position/title to be cleared when an employee is left
            // without a labor type, flagging them for re-assignment.
            $table->string('position')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['labor_type_id']);
            $table->foreign('labor_type_id')
                  ->references('id')->on('labor_types')
                  ->cascadeOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('position')->nullable(false)->change();
        });
    }
};
