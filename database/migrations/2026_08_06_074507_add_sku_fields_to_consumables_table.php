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
            $table->string('sku')->nullable()->unique()->after('id');
            $table->string('sku_barcode')->nullable()->unique()->after('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->dropUnique('consumables_sku_unique');
            $table->dropUnique('consumables_sku_barcode_unique');
            $table->dropColumn(['sku', 'sku_barcode']);
        });
    }
};
