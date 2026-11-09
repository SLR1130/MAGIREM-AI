<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counselor_messages')) {
            Schema::table('counselor_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('counselor_messages', 'detected_emotion')) {
                    $table->string('detected_emotion')->nullable()->after('message');
                }
                if (!Schema::hasColumn('counselor_messages', 'emotion_confidence')) {
                    $table->float('emotion_confidence')->nullable()->after('detected_emotion');
                }
                if (!Schema::hasColumn('counselor_messages', 'original_score')) {
                    $table->float('original_score')->nullable()->after('emotion_confidence');
                }
                if (!Schema::hasColumn('counselor_messages', 'weighted_score')) {
                    $table->float('weighted_score')->nullable()->after('original_score');
                }
            });
        }

        if (Schema::hasTable('wellbeing_progress')) {
            Schema::table('wellbeing_progress', function (Blueprint $table) {
                $table->integer('emotional_stability')->default(50)->change();
                $table->integer('stress_level')->default(50)->change();
                $table->integer('wellbeing_score')->default(50)->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: consolidated scores and wellbeing data are preserved.
    }
};
