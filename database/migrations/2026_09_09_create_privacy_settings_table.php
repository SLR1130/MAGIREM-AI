<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('privacy_settings')) {
            return;
        }

        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('ai_preference_mode', ['companion', 'counselor'])->default('companion');
            $table->boolean('share_emotion_data')->default(false);
            $table->boolean('auto_journal_entry')->default(true);
            $table->boolean('wellness_reminders_enabled')->default(true);
            $table->integer('reminder_frequency_hours')->default(24);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
