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
        Schema::table('atk_stock_transactions', function (Blueprint $table) {
            $table->string('taken_by_name')->nullable()->after('performed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('atk_stock_transactions', function (Blueprint $table) {
            $table->dropColumn('taken_by_name');
        });
    }
};
