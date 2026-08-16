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
        Schema::table('manpower_planning_items', function (Blueprint $table) {
            $table->string('allowed_shifts')->nullable()->after('device_type');
            $table->string('start_time')->nullable()->after('allowed_shifts');
            $table->string('end_time')->nullable()->after('start_time');
            $table->unsignedInteger('minimum_shift')->default(1)->after('end_time');
            $table->string('division_reason')->nullable()->after('minimum_shift');
            $table->unsignedInteger('mpp_shift_1')->nullable()->after('required_mpp');
            $table->unsignedInteger('mpp_shift_2')->nullable()->after('mpp_shift_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manpower_planning_items', function (Blueprint $table) {
            $table->dropColumn(['allowed_shifts', 'start_time', 'end_time', 'minimum_shift', 'division_reason', 'mpp_shift_1', 'mpp_shift_2']);
        });
    }
};
