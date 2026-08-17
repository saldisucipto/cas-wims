<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meson_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('warehouse_id')->nullable();
            $table->string('transaction_id');
            $table->string('transaction_type')->nullable();
            $table->string('doc_type')->nullable();
            $table->string('document_number')->nullable();
            $table->string('doc_line_no')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('transaction_time')->nullable();

            $table->string('customer_id_fm')->nullable();
            $table->string('sku_fm')->nullable();
            $table->string('lotnum_fm')->nullable();
            $table->string('location_fm')->nullable();
            $table->string('fm_muid')->nullable();
            $table->string('id_fm')->nullable();
            $table->string('pack_id_fm')->nullable();
            $table->string('uom_fm')->nullable();
            $table->decimal('qty_fm', 14, 2)->nullable();
            $table->decimal('qty_each_fm', 14, 2)->nullable();

            $table->string('customer_id_to')->nullable();
            $table->string('sku_to')->nullable();
            $table->string('lotnum_to')->nullable();
            $table->string('location_to')->nullable();
            $table->string('to_muid')->nullable();
            $table->string('id_to')->nullable();
            $table->string('pack_id_to')->nullable();
            $table->string('uom_to')->nullable();
            $table->decimal('qty_to', 14, 2)->nullable();
            $table->decimal('qty_each_to', 14, 2)->nullable();

            $table->decimal('total_price', 14, 2)->nullable();
            $table->decimal('total_net_weight', 14, 2)->nullable();
            $table->decimal('total_gross_weight', 14, 2)->nullable();
            $table->decimal('total_cubic', 14, 2)->nullable();

            $table->string('udf01')->nullable();
            $table->string('udf02')->nullable();
            $table->string('udf03')->nullable();
            $table->string('udf04')->nullable();
            $table->string('udf05')->nullable();

            $table->dateTime('system_time')->nullable();

            $table->foreignId('operator_id')->nullable()->constrained('wms_accounts')->nullOnDelete();
            $table->string('operator_username')->nullable();
            $table->string('system_operator')->nullable();

            $table->timestamps();

            $table->unique('transaction_id');
            $table->index('transaction_time');
            $table->index('transaction_type');
            $table->index('document_number');
            $table->index('operator_id');
            $table->index('status');
            $table->index('warehouse_id');
            $table->index(['transaction_type', 'transaction_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meson_transactions');
    }
};
