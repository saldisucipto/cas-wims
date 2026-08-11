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
        Schema::create('atk_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atk_item_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_number')->nullable();
            $table->string('transaction_type');
            $table->string('reference')->nullable();
            $table->string('supplier')->nullable();
            $table->unsignedInteger('quantity_in')->default(0);
            $table->unsignedInteger('quantity_out')->default(0);
            $table->unsignedInteger('balance');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('transaction_at');
            $table->timestamps();

            $table->index(['transaction_type', 'transaction_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atk_stock_transactions');
    }
};
