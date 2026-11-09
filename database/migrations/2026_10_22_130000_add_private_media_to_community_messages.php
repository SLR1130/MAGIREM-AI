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

        Schema::table('community_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('community_messages', 'image_path')) {
                $table->string('image_path', 255)->nullable()->after('support_label');
            }
            if (!Schema::hasColumn('community_messages', 'image_hash')) {
                $table->string('image_hash', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('community_messages', 'image_width')) {
                $table->unsignedInteger('image_width')->nullable();
            }
            if (!Schema::hasColumn('community_messages', 'image_height')) {
                $table->unsignedInteger('image_height')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('community_messages')) {
            return;
        }

        Schema::table('community_messages', function (Blueprint $table) {
            if (Schema::hasColumn('community_messages', 'image_hash')) {
                $table->dropIndex(['image_hash']);
            }
            $columns = array_values(array_filter(
                ['image_path', 'image_hash', 'image_width', 'image_height'],
                fn (string $column): bool => Schema::hasColumn('community_messages', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};