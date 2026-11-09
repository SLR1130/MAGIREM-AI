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
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_status')) {
                $table->string('detailed_emotion_status', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_secondary_keys')) {
                $table->json('detailed_emotion_secondary_keys')->nullable();
            }
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_other_keys')) {
                $table->json('detailed_emotion_other_keys')->nullable();
            }
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_category_keys')) {
                $table->json('detailed_emotion_category_keys')->nullable();
            }
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_attribution_scope')) {
                $table->string('detailed_emotion_attribution_scope', 24)->nullable();
            }
            if (! Schema::hasColumn('emotional_entries', 'detailed_emotion_uncertainty_reason')) {
                $table->string('detailed_emotion_uncertainty_reason', 160)->nullable();
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
                'detailed_emotion_status',
                'detailed_emotion_secondary_keys',
                'detailed_emotion_other_keys',
                'detailed_emotion_category_keys',
                'detailed_emotion_attribution_scope',
                'detailed_emotion_uncertainty_reason',
            ] as $column) {
                if (Schema::hasColumn('emotional_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
