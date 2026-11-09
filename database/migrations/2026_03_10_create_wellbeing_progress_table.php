<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellbeing_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('emotional_stability')->default(5); // 1-10
            $table->integer('stress_level')->default(5); // 1-10
            $table->integer('wellbeing_score')->default(5); // 1-10
            $table->integer('entries_count')->default(0);
            $table->integer('counselor_interactions')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellbeing_progress');
    }
};
