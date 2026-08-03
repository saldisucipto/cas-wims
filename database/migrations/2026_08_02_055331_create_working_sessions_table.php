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
        Schema::create('working_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('packing_station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rf_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wms_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_type');
            $table->string('status')->default('Working');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_sessions');
    }
};
