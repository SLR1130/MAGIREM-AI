<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restores the emergency_hotlines table that was accidentally dropped by cleanup migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('emergency_hotlines')) {
            return;
        }

        Schema::create('emergency_hotlines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phone_number');
            $table->string('website')->nullable();
            $table->string('country')->default('Philippines');
            $table->string('availability')->default('24/7'); // 24/7, Business hours, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_hotlines');
    }
};
