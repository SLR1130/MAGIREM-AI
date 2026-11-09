<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coping_strategies');
    }
};
