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
        Schema::create('manpower_planning_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manpower_planning_id')->constrained()->cascadeOnDelete();
            $table->string('division');
            $table->string('name');
            $table->string('code');
            $table->string('workload_source');
            $table->decimal('workload', 14, 2)->default(0);
            $table->string('workload_unit');
            $table->decimal('productivity_per_hour', 12, 4)->default(0);
            $table->string('productivity_unit');
            $table->string('manpower_type');
            $table->unsignedInteger('minimum_manpower')->nullable();
            $table->decimal('shift_duration', 8, 2)->default(8);
            $table->decimal('non_productive_hours', 8, 2)->default(1);
            $table->decimal('effective_working_hours', 8, 2)->default(7);
            $table->unsignedInteger('required_mpp')->default(0);
            $table->unsignedInteger('mpp_per_shift')->default(0);
            $table->unsignedInteger('number_of_shift')->default(1);
            $table->unsignedInteger('available_mpp')->default(0);
            $table->string('feasibility_status');
            $table->boolean('bottleneck')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manpower_planning_items');
    }
};
