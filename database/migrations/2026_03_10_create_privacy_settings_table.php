<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->unique();
            $table->boolean('share_with_counselor')->default(true);
            $table->boolean('share_analytics')->default(true);
            $table->boolean('allow_anonymous_community')->default(true);
            $table->boolean('allow_ai_recommendations')->default(true);
            $table->boolean('allow_crisis_alerts')->default(true);
            $table->boolean('allow_reminders')->default(true);
            $table->integer('data_retention_days')->default(365); // how long to keep data
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
