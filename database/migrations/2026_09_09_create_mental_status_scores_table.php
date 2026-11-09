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
        // wellbeing_progress is the authoritative daily 0-100 record. This late
        // legacy migration must not recreate a table removed by consolidation.
        if (Schema::hasTable('wellbeing_progress')) {
            return;
        }

        Schema::create('mental_status_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->integer('score')->default(50); // 0-100 scale
            $table->string('emotion')->nullable();
            $table->string('crisis_level')->default('none');
            $table->boolean('late_night_usage')->default(false);
            $table->integer('message_count')->default(0);
            $table->datetime('timestamp')->nullable();
            $table->timestamps();
            
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('student_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mental_status_scores');
    }
};
