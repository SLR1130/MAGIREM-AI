<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            // Add is_anonymous if it doesn't exist
            if (!Schema::hasColumn('community_messages', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(true)->after('message');
            }
            
            // Add category if it doesn't exist
            if (!Schema::hasColumn('community_messages', 'category')) {
                $table->string('category')->default('general')->after('is_anonymous');
            }
            
            // Add is_pinned if it doesn't exist
            if (!Schema::hasColumn('community_messages', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('is_approved');
            }
            
            // Add replies_count if it doesn't exist
            if (!Schema::hasColumn('community_messages', 'replies_count')) {
                $table->integer('replies_count')->default(0)->after('likes_count');
            }
            
            // Add approved_at if it doesn't exist
            if (!Schema::hasColumn('community_messages', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('moderation_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_messages', function (Blueprint $table) {
            $table->dropColumn([
                'is_anonymous',
                'category',
                'is_pinned',
                'replies_count',
                'approved_at'
            ]);
        });
    }
};
