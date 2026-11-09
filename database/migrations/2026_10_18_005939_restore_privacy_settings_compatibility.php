
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('privacy_settings')) {
            return;
        }

        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique();

$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->cascadeOnDelete();

            // Original privacy settings
            $table->boolean('share_with_counselor')->default(true);
            $table->boolean('share_analytics')->default(true);
            $table->boolean('allow_anonymous_community')->default(true);
            $table->boolean('allow_ai_recommendations')->default(true);
            $table->boolean('allow_crisis_alerts')->default(true);
            $table->boolean('allow_reminders')->default(true);
            $table->integer('data_retention_days')->default(365);

            // AI preference
            $table->enum('ai_preference_mode', [
                'companion',
                'counselor'
            ])->default('companion');

            // Additional privacy settings
            $table->boolean('share_emotion_data')->default(false);
            $table->boolean('auto_journal_entry')->default(true);
            $table->boolean('wellness_reminders_enabled')->default(true);
            $table->integer('reminder_frequency_hours')->default(24);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Non-destructive rollback.
        // Privacy data must not be deleted automatically.
    }
};
