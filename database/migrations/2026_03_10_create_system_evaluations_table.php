<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('usability_rating')->nullable();
            $table->integer('ai_helpfulness_rating')->nullable();
            $table->integer('counselor_quality_rating')->nullable();
            $table->integer('overall_satisfaction_rating')->nullable();
            $table->integer('usefulness_rating')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_evaluations');
    }
};
