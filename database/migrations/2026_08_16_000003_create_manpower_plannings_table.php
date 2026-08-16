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
        Schema::create('manpower_plannings', function (Blueprint $table) {
            $table->id();
            $table->string('planning_number')->unique();
            $table->date('planning_date');
            $table->unsignedInteger('inbound_volume')->default(0);
            $table->unsignedInteger('outbound_volume')->default(0);
            $table->unsignedInteger('vas_volume')->default(0);
            $table->decimal('shift_duration', 8, 2)->default(8);
            $table->decimal('non_productive_hours', 8, 2)->default(1);
            $table->decimal('effective_working_hours', 8, 2)->default(7);
            $table->unsignedInteger('total_mpp')->default(0);
            $table->string('recommendation')->default('1 Shift');
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('revision')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'planning_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manpower_plannings');
    }
};
