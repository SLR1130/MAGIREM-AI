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
        // Weekly trends are projections of wellbeing_progress and usage_patterns.
        // Do not recreate the legacy materialized table after consolidation.
        if (Schema::hasTable('wellbeing_progress')) {
            return;
        }

        Schema::create('mental_health_trends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->date('week_start');
            $table->decimal('avg_score', 5, 2)->default(50);
            $table->integer('message_count')->default(0);
            $table->integer('late_night_count')->default(0);
            $table->integer('crisis_count')->default(0);
            $table->string('trend_direction')->default('stable');
            $table->timestamps();
            
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['student_id', 'week_start']);
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mental_health_trends');
    }
};
