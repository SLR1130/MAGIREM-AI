<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add crisis assessment fields to AI conversation sessions
        if (!Schema::hasColumn('ai_conversation_sessions', 'crisis_assessment_status')) {
            Schema::table('ai_conversation_sessions', function (Blueprint $table) {
                $table->string('crisis_assessment_status')
                    ->default('none')
                    ->after('session_token')
                    ->comment('Assessment state: none | awaiting_confirmation | confirmed_safe | confirmed_high_risk');
                
                $table->json('session_metadata')
                    ->nullable()
                    ->after('crisis_assessment_status')
                    ->comment('Stores assessment history and confirmation flow data');
                
                $table->index('crisis_assessment_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ai_conversation_sessions', function (Blueprint $table) {
            $table->dropIndex(['crisis_assessment_status']);
            $table->dropColumn(['crisis_assessment_status', 'session_metadata']);
        });
    }
};
