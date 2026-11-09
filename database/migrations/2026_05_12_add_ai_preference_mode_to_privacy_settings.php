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
        Schema::table('privacy_settings', function (Blueprint $table) {
            // Add AI preference mode: 'companion' or 'counselor'
            $table->enum('ai_preference_mode', ['companion', 'counselor'])->default('companion')->after('allow_reminders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('privacy_settings', function (Blueprint $table) {
            $table->dropColumn('ai_preference_mode');
        });
    }
};
