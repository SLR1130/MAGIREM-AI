<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create Compressed Database Schema
     */
    public function up()
    {
        // Create compressed_users table
        Schema::create('compressed_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['student', 'counselor', 'admin'])->default('student');
            $table->string('student_id', 50)->unique()->nullable();
            $table->string('program')->nullable();
            $table->string('year_level', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('privacy_settings')->nullable(); // Combines privacy_settings table
            $table->json('notification_preferences')->nullable(); // Combines notifications table
            $table->timestamps();
            
            $table->index(['role']);
            $table->index(['student_id']);
            $table->index(['is_active']);
        });

        // Create compressed_emotional_data table
        Schema::create('compressed_emotional_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('compressed_users')->onDelete('cascade');
            $table->string('session_id')->nullable(); // Groups related conversations
            $table->enum('entry_type', ['journal', 'ai_chat', 'mood_checkin']);
            $table->text('content'); // User input
            $table->text('ai_response')->nullable(); // AI generated response
            $table->string('detected_emotion', 50)->nullable(); // ML detected emotion
            $table->decimal('confidence_score', 5, 2)->nullable(); // ML confidence (0-100)
            $table->enum('crisis_level', ['none', 'low', 'moderate', 'high', 'critical'])->default('none');
            $table->boolean('needs_counselor')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->json('metadata')->nullable(); // Additional ML analysis data
            $table->timestamps();
            
            $table->index(['user_id', 'session_id']);
            $table->index(['entry_type']);
            $table->index(['detected_emotion']);
            $table->index(['crisis_level']);
            $table->index(['created_at']);
        });

        // Create compressed_counseling_services table
        Schema::create('compressed_counseling_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('compressed_users')->onDelete('cascade');
            $table->foreignId('counselor_id')->nullable()->constrained('compressed_users')->onDelete('set null');
            $table->enum('service_type', ['appointment', 'message', 'alert', 'crisis']);
            $table->enum('category', ['academic', 'personal', 'crisis', 'general'])->default('general');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('message_content')->nullable(); // For messages and communications
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'resolved'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamp('scheduled_at')->nullable(); // For appointments
            $table->timestamp('resolved_at')->nullable();
            $table->json('attachments')->nullable(); // File attachments metadata
            $table->timestamps();
            
            $table->index(['student_id']);
            $table->index(['counselor_id']);
            $table->index(['service_type']);
            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['scheduled_at']);
        });

        // Create compressed_mental_health_monitoring table
        Schema::create('compressed_mental_health_monitoring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('compressed_users')->onDelete('cascade');
            $table->enum('monitoring_type', ['mood', 'wellbeing', 'trend', 'pattern', 'alert']);
            $table->date('date');
            $table->integer('mood_score')->nullable()->check('mood_score BETWEEN 1 AND 10');
            $table->integer('stress_level')->nullable()->check('stress_level BETWEEN 1 AND 10');
            $table->integer('anxiety_level')->nullable()->check('anxiety_level BETWEEN 1 AND 10');
            $table->integer('emotional_stability')->nullable()->check('emotional_stability BETWEEN 1 AND 10');
            $table->integer('sleep_quality')->nullable()->check('sleep_quality BETWEEN 1 AND 10');
            $table->integer('social_connection')->nullable()->check('social_connection BETWEEN 1 AND 10');
            $table->integer('academic_performance')->nullable()->check('academic_performance BETWEEN 1 AND 10');
            $table->decimal('overall_wellbeing', 4, 2)->nullable(); // Calculated overall score
            $table->enum('trend_direction', ['improving', 'stable', 'declining'])->nullable();
            $table->boolean('alert_triggered')->default(false);
            $table->json('pattern_data')->nullable(); // Stores pattern analysis results
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['user_id', 'date']);
            $table->index(['monitoring_type']);
            $table->index(['overall_wellbeing']);
            $table->index(['alert_triggered']);
            $table->unique(['user_id', 'date', 'monitoring_type'], 'cmhm_user_date_type_unique');
        });

        // Create compressed_support_resources table
        Schema::create('compressed_support_resources', function (Blueprint $table) {
            $table->id();
            $table->enum('resource_type', ['article', 'strategy', 'hotline', 'prompt', 'community', 'activity']);
            $table->enum('category', ['coping', 'emergency', 'reflection', 'peer_support', 'educational']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable(); // Full content/instructions
            $table->string('contact_info')->nullable(); // Phone/email for hotlines
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->string('target_emotion', 100)->nullable(); // anxiety, depression, stress, general
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->decimal('effectiveness_rating', 3, 2)->nullable(); // Average user rating (1-5)
            $table->json('metadata')->nullable(); // Additional resource-specific data
            $table->foreignId('created_by')->nullable()->constrained('compressed_users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['resource_type']);
            $table->index(['category']);
            $table->index(['target_emotion']);
            $table->index(['is_active']);
            $table->index(['effectiveness_rating']);
        });

        // Create compressed_system_analytics table
        Schema::create('compressed_system_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('compressed_users')->onDelete('cascade');
            $table->enum('analytics_type', ['assessment', 'evaluation', 'ml_model', 'usage', 'reminder']);
            $table->string('assessment_category', 100)->nullable(); // stress, wellbeing, anxiety, depression, usability
            $table->json('questions')->nullable(); // Assessment questions
            $table->json('responses')->nullable(); // User responses
            $table->json('calculated_scores')->nullable(); // Calculated metrics and scores
            $table->string('model_version', 50)->nullable(); // ML model version
            $table->json('model_metrics')->nullable(); // Model performance data
            $table->decimal('system_rating', 3, 2)->nullable(); // Overall system rating (1-5)
            $table->text('feedback_text')->nullable(); // User feedback
            $table->json('usage_metrics')->nullable(); // Usage statistics and patterns
            $table->json('metadata')->nullable(); // Additional analytics data
            $table->timestamps();
            
            $table->index(['analytics_type']);
            $table->index(['assessment_category']);
            $table->index(['model_version']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down()
    {
        Schema::dropIfExists('compressed_system_analytics');
        Schema::dropIfExists('compressed_support_resources');
        Schema::dropIfExists('compressed_mental_health_monitoring');
        Schema::dropIfExists('compressed_counseling_services');
        Schema::dropIfExists('compressed_emotional_data');
        Schema::dropIfExists('compressed_users');
    }
};