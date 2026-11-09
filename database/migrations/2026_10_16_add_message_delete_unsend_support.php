<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add unsend and delete support for counselor messages.
     *
     * Unsend for everyone: marks message as unsent (visible as tombstone)
     * Delete for me: records user-specific deletion (private hiding)
     */
    public function up(): void
    {
        // Add columns to counselor_messages for global unsend
        Schema::table('counselor_messages', function (Blueprint $table) {
            // Unsend for everyone support
            if (!Schema::hasColumn('counselor_messages', 'unsent_at')) {
                $table->timestamp('unsent_at')->nullable()->after('is_read');
            }
            if (!Schema::hasColumn('counselor_messages', 'unsent_by')) {
                $table->foreignId('unsent_by')->nullable()->constrained('users')->onDelete('set null')->after('unsent_at');
            }
        });

        // Create table for delete-for-me (user-specific deletion)
        if (!Schema::hasTable('counselor_message_deletions')) {
            Schema::create('counselor_message_deletions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('counselor_messages')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamp('deleted_at')->useCurrent();

                // Unique constraint: one deletion record per user per message
                $table->unique(['message_id', 'user_id']);

                $table->index(['user_id', 'deleted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('counselor_messages', function (Blueprint $table) {
            if (Schema::hasColumn('counselor_messages', 'unsent_by')) {
                $table->dropForeignKeyIfExists(['unsent_by']);
                $table->dropColumn('unsent_by');
            }
            if (Schema::hasColumn('counselor_messages', 'unsent_at')) {
                $table->dropColumn('unsent_at');
            }
        });

        if (Schema::hasTable('counselor_message_deletions')) {
            Schema::dropIfExists('counselor_message_deletions');
        }
    }
};
