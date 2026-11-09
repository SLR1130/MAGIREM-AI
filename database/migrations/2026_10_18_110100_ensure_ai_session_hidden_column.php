<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_conversation_sessions') || Schema::hasColumn('ai_conversation_sessions', 'is_hidden')) {
            return;
        }

        Schema::table('ai_conversation_sessions', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('session_token');
            $table->index('is_hidden');
            $table->index(['user_id', 'is_hidden']);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive for compatibility with existing session history.
    }
};
