<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meson_transactions', function (Blueprint $table) {
            $table->foreignId('daily_worker_id')->nullable()->after('operator_id')->constrained('daily_workers')->nullOnDelete();
            $table->index('daily_worker_id');
        });
    }

    public function down(): void
    {
        Schema::table('meson_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_worker_id');
        });
    }
};
