<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('community_messages')) {
            return;
        }

        Schema::table('community_messages', function (Blueprint $table): void {
            if (!Schema::hasColumn('community_messages', 'post_format')) {
                $table->string('post_format', 16)->default('text')->index()->after('message');
            }
            if (!Schema::hasColumn('community_messages', 'visual_text')) {
                $table->text('visual_text')->nullable()->after('post_format');
            }
            if (!Schema::hasColumn('community_messages', 'visual_layout')) {
                $table->json('visual_layout')->nullable()->after('visual_text');
            }
            if (!Schema::hasColumn('community_messages', 'visual_media_path')) {
                $table->string('visual_media_path', 255)->nullable()->after('visual_layout');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('community_messages')) {
            return;
        }

        Schema::table('community_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('community_messages', 'post_format')) {
                $table->dropIndex(['post_format']);
            }
            $columns = array_values(array_filter(
                ['post_format', 'visual_text', 'visual_layout', 'visual_media_path'],
                static fn (string $column): bool => Schema::hasColumn('community_messages', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};