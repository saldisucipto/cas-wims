<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_plannings', function (Blueprint $table) {
            $table->date('productivity_period_start')->nullable()->after('vas_volume');
            $table->date('productivity_period_end')->nullable()->after('productivity_period_start');
            $table->unsignedInteger('picker_headcount')->nullable()->after('productivity_period_end');
            $table->unsignedInteger('packer_headcount')->nullable()->after('picker_headcount');
            $table->string('recommendation_source')->nullable()->after('packer_headcount');
        });
    }

    public function down(): void
    {
        Schema::table('manpower_plannings', function (Blueprint $table) {
            $table->dropColumn([
                'productivity_period_start',
                'productivity_period_end',
                'picker_headcount',
                'packer_headcount',
                'recommendation_source',
            ]);
        });
    }
};
