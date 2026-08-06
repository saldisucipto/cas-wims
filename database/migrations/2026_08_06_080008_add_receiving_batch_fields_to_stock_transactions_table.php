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
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->string('transaction_group')->nullable()->after('transaction_type')->index();
            $table->string('purchase_request_number')->nullable()->after('transaction_group');
            $table->string('received_by_name')->nullable()->after('purchase_request_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropIndex('stock_transactions_transaction_group_index');
            $table->dropColumn(['transaction_group', 'purchase_request_number', 'received_by_name']);
        });
    }
};
