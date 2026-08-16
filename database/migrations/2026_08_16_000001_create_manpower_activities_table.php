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
        Schema::create('manpower_activities', function (Blueprint $table) {
            $table->id();
            $table->string('division');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('workload_source');
            $table->string('workload_unit');
            $table->decimal('conversion_ratio', 12, 4)->default(1);
            $table->decimal('productivity_per_hour', 12, 4)->default(0);
            $table->string('productivity_unit');
            $table->string('manpower_type')->default('Variable');
            $table->unsignedInteger('minimum_manpower')->nullable();
            $table->unsignedInteger('available_manpower')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manpower_activities');
    }
};
