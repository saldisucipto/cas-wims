<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_schedule_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('week_number');
            $table->string('shift'); // S1, S2, OFF, LEAVE
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->decimal('working_hours', 5, 2)->default(0);
            $table->string('assignment_type')->default('ROTATION');
            $table->boolean('is_override')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shift_schedule_id', 'employee_id', 'date']);
            $table->index(['date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedule_details');
    }
};
