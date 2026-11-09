<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crisis_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('counselor_id')->constrained('users')->onDelete('cascade');
            $table->enum('crisis_level', ['critical', 'severe', 'moderate'])->default('moderate');
            $table->string('crisis_category')->nullable();
            $table->longText('message_content');
            $table->longText('ai_response');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('student_id');
            $table->index('counselor_id');
            $table->index('is_resolved');
            $table->index('crisis_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crisis_alerts');
    }
};
