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
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->default('New Conversation');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index('last_message_at');
        });
        
        // Add session_id to emotional_entries table (if not already exists)
        if (Schema::hasTable('emotional_entries') && !Schema::hasColumn('emotional_entries', 'session_id')) {
            Schema::table('emotional_entries', function (Blueprint $table) {
                $table->foreignId('session_id')->nullable()->after('user_id')->constrained('chat_sessions')->onDelete('set null');
                $table->index('session_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('emotional_entries') && Schema::hasColumn('emotional_entries', 'session_id')) {
            Schema::table('emotional_entries', function (Blueprint $table) {
                $table->dropForeign(['session_id']);
                $table->dropColumn('session_id');
            });
        }
        
        Schema::dropIfExists('chat_sessions');
    }
};
