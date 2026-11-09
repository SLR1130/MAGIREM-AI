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
            // Drop the existing foreign key constraint that points to chat_sessions
            $table->dropForeign(['session_id']);

            // Add new foreign key constraint that points to ai_conversation_sessions
            $table->foreign('session_id')->references('id')->on('ai_conversation_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emotional_entries', function (Blueprint $table) {
            // Drop the foreign key to ai_conversation_sessions
            $table->dropForeign(['session_id']);

            // Restore foreign key to chat_sessions (original state)
            $table->foreign('session_id')->references('id')->on('chat_sessions')->onDelete('set null');
        });
    }
};
