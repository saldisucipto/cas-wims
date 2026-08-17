<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // S1, S2, S1_SAT, S2_SAT
            $table->string('name');
            $table->string('start_time')->default('08:00');
            $table->string('end_time')->default('16:00');
            $table->string('break_start')->nullable();
            $table->string('break_end')->nullable();
            $table->unsignedInteger('break_minutes')->default(60);
            $table->decimal('effective_hours', 5, 2)->default(7);
            $table->boolean('is_short_day')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_definitions');
    }
};
