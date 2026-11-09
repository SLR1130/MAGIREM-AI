<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('community_messages', 'category')) {
                $table->string('category')->default('general')->after('message'); // academic, mental-health, relationships, general
            }
            if (!Schema::hasColumn('community_messages', 'likes_count')) {
                $table->integer('likes_count')->default(0)->after('category');
            }
            if (!Schema::hasColumn('community_messages', 'replies_count')) {
                $table->integer('replies_count')->default(0)->after('likes_count');
            }
            if (!Schema::hasColumn('community_messages', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('is_approved');
            }
            if (!Schema::hasColumn('community_messages', 'moderation_notes')) {
                $table->text('moderation_notes')->nullable()->after('is_pinned');
            }
            if (!Schema::hasColumn('community_messages', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('moderation_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            $columnsToDelete = [];
            if (Schema::hasColumn('community_messages', 'category')) {
                $columnsToDelete[] = 'category';
            }
            if (Schema::hasColumn('community_messages', 'replies_count')) {
                $columnsToDelete[] = 'replies_count';
            }
            if (Schema::hasColumn('community_messages', 'is_pinned')) {
                $columnsToDelete[] = 'is_pinned';
            }
            if (Schema::hasColumn('community_messages', 'approved_at')) {
                $columnsToDelete[] = 'approved_at';
            }
            if (!empty($columnsToDelete)) {
                $table->dropColumn($columnsToDelete);
            }
        });
    }
};
