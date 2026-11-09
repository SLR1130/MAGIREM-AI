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
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->decimal('total_score', 6, 1);
            $table->enum('status', ['Stable', 'Slightly Low', 'Low', 'Critical']);
            $table->integer('entry_count');
            $table->enum('reliability_flag', ['Low', 'Medium', 'High']);
            $table->timestamp('created_at')->useCurrent();
            
            // Unique constraint
            $table->unique(['user_id', 'date'], 'unique_user_date');
            
            // Indexes
            $table->index(['user_id', 'date'], 'idx_daily_summaries_user_date');
            $table->index('status', 'idx_status');
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
