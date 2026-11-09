<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_forums', function (Blueprint $table) {
            if (!Schema::hasColumn('discussion_forums', 'slug')) {
                $table->string('slug')->unique()->nullable();
            }
            if (!Schema::hasColumn('discussion_forums', 'icon')) {
                $table->string('icon')->default('💬');
            }
            if (!Schema::hasColumn('discussion_forums', 'threads_count')) {
                $table->integer('threads_count')->default(0);
            }
            if (!Schema::hasColumn('discussion_forums', 'replies_count')) {
                $table->integer('replies_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('discussion_forums', function (Blueprint $table) {
            if (Schema::hasColumn('discussion_forums', 'slug')) {
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('discussion_forums', 'icon')) {
                $table->dropColumn('icon');
            }
            if (Schema::hasColumn('discussion_forums', 'threads_count')) {
                $table->dropColumn('threads_count');
            }
            if (Schema::hasColumn('discussion_forums', 'replies_count')) {
                $table->dropColumn('replies_count');
            }
        });
    }
};
