<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Restores the mood_checkins table that was accidentally dropped by cleanup migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('mood_checkins')) {
            return;
        }

        Schema::create('mood_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('mood'); // happy, sad, anxious, stressed, neutral, angry, overwhelmed
            $table->text('notes')->nullable();
            $table->integer('intensity')->nullable(); // 1-10 scale
            $table->timestamp('checked_in_at')->useCurrent();
            $table->timestamps();
            $table->index('user_id');
            $table->index('checked_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mood_checkins');
    }
};
