<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restores the reflection_prompts table that was accidentally dropped by cleanup migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('reflection_prompts')) {
            return;
        }

        Schema::create('reflection_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('prompt');
            $table->text('description')->nullable();
            $table->string('emotion_category'); // positive, sadness, anxiety, stress, neutral, anger, overwhelm
            $table->string('difficulty')->default('easy'); // easy, medium, deep
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('emotion_category');
            $table->index('difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reflection_prompts');
    }
};
