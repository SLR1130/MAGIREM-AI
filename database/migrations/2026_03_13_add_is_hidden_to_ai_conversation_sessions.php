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
        // The original timestamp predates creation of the target table on clean installs.
        // A forward repair migration applies the column after table creation.
        if (!Schema::hasTable('ai_conversation_sessions')) {
            return;
        }

        Schema::table('ai_conversation_sessions', function (Blueprint $table) {
            // Add is_hidden column to mark conversations as archived/hidden from sidebar
            // This is a soft delete - conversations remain visible in history/journal
            if (!Schema::hasColumn('ai_conversation_sessions', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('session_token');
                $table->index('is_hidden');
                $table->index(['user_id', 'is_hidden']); // For quick filtering
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('ai_conversation_sessions')) {
            return;
        }

        Schema::table('ai_conversation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('ai_conversation_sessions', 'is_hidden')) {
                $table->dropIndex(['is_hidden']);
                $table->dropIndex(['user_id', 'is_hidden']);
                $table->dropColumn('is_hidden');
            }
        });
    }
};
