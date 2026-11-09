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
        Schema::table('emotional_entries', function (Blueprint $table) {
            // Add conversation_context field to store conversation history
            $table->json('conversation_context')->nullable()->after('detected_contexts');
            
            // Add feedback fields for ML improvement
            $table->integer('feedback_rating')->nullable()->after('conversation_context');
            $table->text('feedback_text')->nullable()->after('feedback_rating');
            
            // Add crisis category field
            $table->string('crisis_category')->nullable()->after('crisis_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emotional_entries', function (Blueprint $table) {
            $table->dropColumn(['conversation_context', 'feedback_rating', 'feedback_text', 'crisis_category']);
        });
    }
};
