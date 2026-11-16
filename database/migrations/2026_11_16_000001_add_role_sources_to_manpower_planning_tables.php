<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_plannings', function (Blueprint $table) {
            $table->string('picker_source')->nullable()->after('recommendation_source');
            $table->string('packer_source')->nullable()->after('picker_source');
        });

        Schema::table('manpower_planning_recommendations', function (Blueprint $table) {
            $table->string('unit', 20)->default('order')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('manpower_planning_recommendations', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('manpower_plannings', function (Blueprint $table) {
            $table->dropColumn(['picker_source', 'packer_source']);
        });
    }
};
