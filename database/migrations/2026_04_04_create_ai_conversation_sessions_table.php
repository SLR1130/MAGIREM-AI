<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_conversation_sessions')) {
            Schema::create('ai_conversation_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('title')->nullable();
                $table->string('session_token')->unique()->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                
                $table->index(['user_id', 'last_message_at']);
            });
        }

        // Add session_id to emotional_entries table if not exists
        if (!Schema::hasColumn('emotional_entries', 'session_id')) {
            Schema::table('emotional_entries', function (Blueprint $table) {
                $table->foreignId('session_id')->nullable()->after('user_id')->constrained('ai_conversation_sessions')->onDelete('set null');
                $table->index('session_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('emotional_entries', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropColumn('session_id');
        });
        
        Schema::dropIfExists('ai_conversation_sessions');
    }
};
