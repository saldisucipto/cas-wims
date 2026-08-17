<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wms_accounts', function (Blueprint $table) {
            $table->foreignId('daily_worker_id')->nullable()->after('status')->constrained('daily_workers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wms_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_worker_id');
        });
    }
};
