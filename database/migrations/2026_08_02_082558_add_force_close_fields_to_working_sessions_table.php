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
        Schema::table('working_sessions', function (Blueprint $table) {
            $table->string('close_type')->default('Normal')->after('ended_at');
            $table->foreignId('force_closed_by')->nullable()->after('close_type')->constrained('users')->nullOnDelete();
            $table->dateTime('force_closed_at')->nullable()->after('force_closed_by');
            $table->string('force_close_reason')->nullable()->after('force_closed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('working_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('force_closed_by');
            $table->dropColumn(['close_type', 'force_closed_at', 'force_close_reason']);
        });
    }
};
