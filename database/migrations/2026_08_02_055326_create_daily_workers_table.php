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
        Schema::create('daily_workers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('function')->default('Outbound');
            $table->string('division')->default('Packer');
            $table->string('position')->default('Packer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_workers');
    }
};
