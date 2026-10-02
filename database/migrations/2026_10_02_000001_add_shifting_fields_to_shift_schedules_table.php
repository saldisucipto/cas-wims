<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('status')->constrained('divisions')->nullOnDelete();
            $table->date('period_start_date')->nullable()->after('year');
            $table->date('period_end_date')->nullable()->after('period_start_date');
            $table->json('selected_employee_ids')->nullable()->after('period_end_date');
            $table->json('initial_shift_map')->nullable()->after('selected_employee_ids');

            $table->index(['division_id', 'period_start_date', 'period_end_date'], 'shift_schedules_division_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropIndex('shift_schedules_division_period_index');
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn([
                'period_start_date',
                'period_end_date',
                'selected_employee_ids',
                'initial_shift_map',
            ]);
        });
    }
};
