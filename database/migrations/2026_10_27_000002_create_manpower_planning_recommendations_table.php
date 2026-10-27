<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_planning_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manpower_planning_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('daily_worker_id')->nullable()->constrained('daily_workers')->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('name');
            $table->string('function')->nullable();
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('days_worked')->default(0);
            $table->decimal('orders_per_hour', 8, 2)->nullable();
            $table->decimal('capacity', 10, 2)->nullable();
            $table->unsignedInteger('allocated_orders')->default(0);
            $table->decimal('utilization', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['manpower_planning_id', 'role', 'sort_order'], 'mpr_planning_role_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_planning_recommendations');
    }
};
