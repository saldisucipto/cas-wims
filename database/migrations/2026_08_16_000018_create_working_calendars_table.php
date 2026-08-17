<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('day_of_week')->unique(); // 1=Monday .. 7=Sunday (ISO)
            $table->string('day_name');
            $table->boolean('is_working_day')->default(true);
            $table->decimal('working_hours', 5, 2)->default(8);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_calendars');
    }
};
