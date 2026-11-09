<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('assessment_type', ['stress', 'wellbeing', 'anxiety', 'depression', 'general'])->default('general');
            $table->json('questions')->nullable();
            $table->json('answers')->nullable();
            $table->integer('score')->nullable();
            $table->text('interpretation')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('assessment_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_assessments');
    }
};
