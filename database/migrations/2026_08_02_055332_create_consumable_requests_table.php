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
        Schema::create('consumable_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('working_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('daily_worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rf_device_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('status')->default('Pending');
            $table->dateTime('requested_at');
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_requests');
    }
};
