<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restores the coping_strategies table that was accidentally dropped by cleanup migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('coping_strategies')) {
            return;
        }

        Schema::create('coping_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('category'); // breathing, mindfulness, grounding, stress-management, etc
            $table->text('instructions');
            $table->integer('duration_minutes')->nullable(); // estimated duration
            $table->string('difficulty')->default('easy'); // easy, medium, hard
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('category');
            $table->index('difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coping_strategies');
    }
};
