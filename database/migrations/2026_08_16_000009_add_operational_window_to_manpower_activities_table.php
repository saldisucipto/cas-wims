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
        Schema::table('manpower_activities', function (Blueprint $table) {
            $table->string('allowed_shifts')->default('S1,S2')->after('device_type');
            $table->string('start_time')->default('07:00')->after('allowed_shifts');
            $table->string('end_time')->default('23:00')->after('start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manpower_activities', function (Blueprint $table) {
            $table->dropColumn(['allowed_shifts', 'start_time', 'end_time']);
        });
    }
};
