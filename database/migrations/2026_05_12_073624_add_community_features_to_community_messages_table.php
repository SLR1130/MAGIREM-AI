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
        Schema::table('community_messages', function (Blueprint $table) {
            // Add category for organizing posts
            $table->string('category')->default('general')->after('message');
            
            // Add parent_id for threaded replies
            $table->foreignId('parent_id')->nullable()->constrained('community_messages')->onDelete('cascade')->after('category');
            
            // Enhanced moderation fields
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending')->after('is_approved');
            $table->timestamp('moderated_at')->nullable()->after('moderation_status');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null')->after('moderated_at');
            
            // Enhanced engagement fields
            $table->integer('replies_count')->default(0)->after('likes_count');
            $table->integer('reports_count')->default(0)->after('replies_count');
            $table->boolean('is_pinned')->default(false)->after('reports_count');
            $table->boolean('is_featured')->default(false)->after('is_pinned');
            
            // Content quality indicators
            $table->integer('helpful_votes')->default(0)->after('is_featured');
            $table->integer('not_helpful_votes')->default(0)->after('helpful_votes');
            
            // Add indexes for performance
            $table->index(['category', 'is_approved', 'moderation_status']);
            $table->index(['parent_id']);
            $table->index(['is_pinned', 'created_at']);
            $table->index(['helpful_votes', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['moderated_by']);
            $table->dropIndex(['category', 'is_approved', 'moderation_status']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_pinned', 'created_at']);
            $table->dropIndex(['helpful_votes', 'created_at']);
            
            $table->dropColumn([
                'category',
                'parent_id',
                'moderation_status',
                'moderated_at',
                'moderated_by',
                'replies_count',
                'reports_count',
                'is_pinned',
                'is_featured',
                'helpful_votes',
                'not_helpful_votes'
            ]);
        });
    }
};
