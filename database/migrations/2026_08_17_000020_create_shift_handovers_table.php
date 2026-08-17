<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_schedule_id')->constrained('shift_schedules')->cascadeOnDelete();
            $table->date('handover_date');
            $table->string('shift_from'); // S1, S1_SAT, S2, S2_SAT
            $table->string('shift_to'); // S2, S2_SAT, S1, S1_SAT
            $table->string('job_type'); // Picking, Packing, QC, ReadyToShip, BCO, Other
            $table->text('description');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('status')->default('OPEN'); // OPEN, TRANSFERRED, CLOSED
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shift_schedule_id', 'handover_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
