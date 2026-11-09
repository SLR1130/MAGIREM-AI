<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflection_prompts', function (Blueprint $table) {
            $table->id();
            $table->text('prompt');
            $table->string('emotion_category'); // for which emotions this prompt is relevant
            $table->string('difficulty')->default('easy'); // easy, medium, deep
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflection_prompts');
    }
};
