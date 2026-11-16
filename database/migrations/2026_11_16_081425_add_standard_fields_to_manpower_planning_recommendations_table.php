<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_planning_recommendations', function (Blueprint $table) {
            $table->decimal('standard_rate', 8, 2)->nullable()->after('orders_per_hour');
            $table->decimal('order_rate', 8, 2)->nullable()->after('standard_rate');
            $table->boolean('meets_standard')->default(false)->after('order_rate');
            $table->decimal('gap_percent', 8, 2)->nullable()->after('meets_standard');
        });
    }

    public function down(): void
    {
        Schema::table('manpower_planning_recommendations', function (Blueprint $table) {
            $table->dropColumn(['standard_rate', 'order_rate', 'meets_standard', 'gap_percent']);
        });
    }
};
