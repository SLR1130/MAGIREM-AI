<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: legacy text and visual posts remain untouched. Rich post
     * data is structured JSON, never stored HTML or executable client styling.
     */
    public function up(): void
    {
        if (!Schema::hasTable('community_messages')) {
            return;
        }

        Schema::table('community_messages', function (Blueprint $table): void {
            if (!Schema::hasColumn('community_messages', 'rich_content')) {
                $table->json('rich_content')->nullable()->after('message');
            }
            if (!Schema::hasColumn('community_messages', 'post_style')) {
                $table->json('post_style')->nullable()->after('rich_content');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('community_messages')) {
            return;
        }

        $columns = array_values(array_filter(
            ['rich_content', 'post_style'],
            static fn (string $column): bool => Schema::hasColumn('community_messages', $column),
        ));

        if ($columns !== []) {
            Schema::table('community_messages', static function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
