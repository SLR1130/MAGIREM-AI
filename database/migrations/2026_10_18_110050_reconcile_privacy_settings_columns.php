<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('privacy_settings')) {
            return;
        }

        Schema::table('privacy_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('privacy_settings', 'share_with_counselor')) {
                $table->boolean('share_with_counselor')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'share_analytics')) {
                $table->boolean('share_analytics')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'allow_anonymous_community')) {
                $table->boolean('allow_anonymous_community')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'allow_ai_recommendations')) {
                $table->boolean('allow_ai_recommendations')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'allow_crisis_alerts')) {
                $table->boolean('allow_crisis_alerts')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'allow_reminders')) {
                $table->boolean('allow_reminders')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'data_retention_days')) {
                $table->integer('data_retention_days')->default(365);
            }
            if (!Schema::hasColumn('privacy_settings', 'share_emotion_data')) {
                $table->boolean('share_emotion_data')->default(false);
            }
            if (!Schema::hasColumn('privacy_settings', 'auto_journal_entry')) {
                $table->boolean('auto_journal_entry')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'wellness_reminders_enabled')) {
                $table->boolean('wellness_reminders_enabled')->default(true);
            }
            if (!Schema::hasColumn('privacy_settings', 'reminder_frequency_hours')) {
                $table->integer('reminder_frequency_hours')->default(24);
            }
            if (!Schema::hasColumn('privacy_settings', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: privacy choices are never removed automatically.
    }
};
