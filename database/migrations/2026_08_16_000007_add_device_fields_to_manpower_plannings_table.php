<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manpower_plannings', function (Blueprint $table) {
            $table->string('overall_status')->default('FEASIBLE')->after('recommendation');
        });

        Schema::table('manpower_planning_items', function (Blueprint $table) {
            $table->string('device_type')->nullable()->after('manpower_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manpower_plannings', function (Blueprint $table) {
            $table->dropColumn('overall_status');
        });

        Schema::table('manpower_planning_items', function (Blueprint $table) {
            $table->dropColumn('device_type');
        });
    }
};
