<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mental Status Scores
        Schema::create('mental_status_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->integer('score')->default(100); // 0-100
            $table->string('emotion')->nullable();
            $table->string('crisis_level')->nullable();
            $table->boolean('late_night_usage')->default(false);
            $table->integer('message_count')->default(0);
            $table->dateTime('timestamp')->nullable();
            $table->timestamps();
            $table->index('student_id');
            $table->index('created_at');
        });

        // Usage Patterns
        Schema::create('usage_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->date('message_date');
            $table->time('message_time');
            $table->integer('message_count')->default(1);
            $table->string('time_of_day')->nullable(); // morning, afternoon, evening, night
            $table->string('emotion_detected')->nullable();
            $table->string('crisis_level')->nullable();
            $table->boolean('is_late_night')->default(false);
            $table->timestamps();
            $table->index('student_id');
            $table->index('message_date');
        });

        // Mental Health Trends
        Schema::create('mental_health_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->date('week_start');
            $table->integer('avg_score')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('late_night_count')->default(0);
            $table->integer('crisis_count')->default(0);
            $table->string('trend_direction')->nullable(); // improving, stable, declining
            $table->timestamps();
            $table->index('student_id');
            $table->index('week_start');
        });

        // Counselor Alerts
        Schema::create('counselor_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('counselor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('alert_type'); // late_night_usage, score_drop, crisis, etc
            $table->string('severity'); // low, moderate, high, critical
            $table->text('message');
            $table->string('status')->default('pending'); // pending, acknowledged, resolved
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->index('student_id');
            $table->index('counselor_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_alerts');
        Schema::dropIfExists('mental_health_trends');
        Schema::dropIfExists('usage_patterns');
        Schema::dropIfExists('mental_status_scores');
    }
};
