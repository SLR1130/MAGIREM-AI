<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_messages') && Schema::hasTable('resources') && !Schema::hasColumn('community_messages', 'resource_id')) {
            Schema::table('community_messages', function (Blueprint $table): void {
                $table->foreignId('resource_id')->nullable()->after('shared_from_id')->constrained('resources')->nullOnDelete();
            });
        }

        if (Schema::hasTable('community_moderation_audits')) {
            return;
        }

        Schema::create('community_moderation_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_message_id')->nullable()->constrained('community_messages')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->boolean('from_pinned')->nullable();
            $table->boolean('to_pinned')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['community_message_id', 'created_at'], 'community_mod_audit_post_created_index');
            $table->index(['moderator_id', 'created_at'], 'community_mod_audit_actor_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_moderation_audits');
        if (Schema::hasTable('community_messages') && Schema::hasColumn('community_messages', 'resource_id')) {
            Schema::table('community_messages', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('resource_id');
            });
        }
    }
};
