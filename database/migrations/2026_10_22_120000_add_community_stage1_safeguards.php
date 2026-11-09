<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'community_verified_at')) {
                $table->timestamp('community_verified_at')->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'community_verified_by')) {
                $table->foreignId('community_verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('community_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('community_messages', 'title')) {
                $table->string('title', 140)->nullable()->after('message');
            }
            if (!Schema::hasColumn('community_messages', 'support_label')) {
                $table->string('support_label', 40)->nullable()->after('category');
            }
            if (!Schema::hasColumn('community_messages', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->index();
            }
        });

        if (!Schema::hasTable('post_comments')) {
            Schema::create('post_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('community_messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('post_comments')->nullOnDelete();
                $table->text('content');
                $table->integer('likes_count')->default(0);
                $table->timestamps();
                $table->index(['post_id', 'created_at']);
            });
        } elseif (!Schema::hasColumn('post_comments', 'parent_id')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->constrained('post_comments')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('community_reactions')) {
            Schema::create('community_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('community_message_id')->constrained('community_messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reaction', 32);
                $table->timestamps();
                $table->unique(['community_message_id', 'user_id']);
                $table->index(['community_message_id', 'reaction']);
            });
        }

        if (!Schema::hasTable('community_reports')) {
            Schema::create('community_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->string('reportable_type', 32);
                $table->unsignedBigInteger('reportable_id');
                $table->string('reason', 32);
                $table->string('status', 16)->default('pending');
                $table->text('moderator_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'community_reports_target_unique');
                $table->index(['status', 'created_at'], 'community_reports_status_created_idx');
            });
        }

        if (!Schema::hasTable('community_blocks')) {
            Schema::create('community_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['blocker_id', 'blocked_id']);
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'from_user_id')) {
                    $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_blocks');
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_reactions');
        if (Schema::hasTable('post_comments')) {
            Schema::table('post_comments', function (Blueprint $table) {
                if (Schema::hasColumn('post_comments', 'parent_id')) {
                    $table->dropForeign(['parent_id']);
                    $table->dropColumn('parent_id');
                }
            });
        }
        if (Schema::hasTable('community_messages')) {
            Schema::table('community_messages', function (Blueprint $table) {
                foreach (['title', 'support_label', 'last_activity_at'] as $column) {
                    if (Schema::hasColumn('community_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'community_verified_by')) {
                $table->dropForeign(['community_verified_by']);
                $table->dropColumn('community_verified_by');
            }
            if (Schema::hasColumn('users', 'community_verified_at')) {
                $table->dropColumn('community_verified_at');
            }
        });
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'from_user_id')) {
                    $table->dropForeign(['from_user_id']);
                    $table->dropColumn('from_user_id');
                }
            });
        }
    }
};