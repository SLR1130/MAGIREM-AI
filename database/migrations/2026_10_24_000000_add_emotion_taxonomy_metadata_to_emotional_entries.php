<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('emotional_entries')) {
            return;
        }

        Schema::table('emotional_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_key')) {
                $table->string('detailed_emotion_key', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('emotional_entries', 'emotion_category_key')) {
                $table->string('emotion_category_key', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('emotional_entries', 'emotion_classification_source')) {
                $table->string('emotion_classification_source', 64)->nullable();
            }
            if (! Schema::hasColumn('emotional_entries', 'emotion_taxonomy_version')) {
                $table->string('emotion_taxonomy_version', 32)->nullable();
            }
            if (! Schema::hasColumn('emotional_entries', 'emotion_uncertainty')) {
                $table->decimal('emotion_uncertainty', 5, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('emotional_entries')) {
            return;
        }

        Schema::table('emotional_entries', function (Blueprint $table): void {
            foreach ([
                'detailed_emotion_key',
                'emotion_category_key',
                'emotion_classification_source',
                'emotion_taxonomy_version',
                'emotion_uncertainty',
            ] as $column) {
                if (Schema::hasColumn('emotional_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
