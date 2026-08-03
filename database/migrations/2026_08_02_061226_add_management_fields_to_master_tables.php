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
        Schema::table('consumables', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('unit');
        });

        Schema::table('rf_devices', function (Blueprint $table) {
            $table->foreignId('wms_account_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        Schema::table('packing_stations', function (Blueprint $table) {
            $table->string('station_number')->nullable()->after('code');
            $table->string('qr_code')->nullable()->after('station_number');
            $table->foreignId('wms_account_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        Schema::table('daily_workers', function (Blueprint $table) {
            $table->string('employee_code')->nullable()->unique()->after('id');
            $table->string('status')->default('Active')->after('position');
        });

        Schema::table('wms_accounts', function (Blueprint $table) {
            $table->string('function')->default('Outbound')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('rf_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wms_account_id');
        });

        Schema::table('packing_stations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wms_account_id');
            $table->dropColumn(['station_number', 'qr_code']);
        });

        Schema::table('daily_workers', function (Blueprint $table) {
            $table->dropUnique('daily_workers_employee_code_unique');
            $table->dropColumn(['employee_code', 'status']);
        });

        Schema::table('wms_accounts', function (Blueprint $table) {
            $table->dropColumn('function');
        });
    }
};
