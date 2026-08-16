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
        Schema::create('manpower_planning_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manpower_planning_id')->constrained()->cascadeOnDelete();
            $table->string('device_type');
            $table->unsignedInteger('ready_quantity')->default(0);
            $table->unsignedInteger('required_one_shift')->default(0);
            $table->unsignedInteger('required_per_shift')->default(0);
            $table->unsignedInteger('physical_required')->default(0);
            $table->unsignedInteger('shortage')->default(0);
            $table->string('status')->default('FEASIBLE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manpower_planning_devices');
    }
};
