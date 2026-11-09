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
        Schema::create('emotional_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('content_type', ['message', 'journal']);
            $table->unsignedBigInteger('content_id');
            $table->string('emotion_label', 50);
            $table->integer('original_score');
            $table->decimal('weighted_score', 3, 1);
            $table->timestamp('detected_at');
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['user_id', 'detected_at'], 'idx_user_date');
            $table->index(['content_type', 'content_id'], 'idx_content');
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emotional_scores');
    }
};
