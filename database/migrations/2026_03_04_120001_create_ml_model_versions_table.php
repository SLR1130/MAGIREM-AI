<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('model_type'); // 'emotion', 'crisis', 'context'
            $table->string('model_path')->nullable();
            $table->integer('training_samples');
            $table->decimal('accuracy', 5, 2)->nullable();
            $table->decimal('precision', 5, 2)->nullable();
            $table->decimal('recall', 5, 2)->nullable();
            $table->decimal('f1_score', 5, 2)->nullable();
            $table->json('training_config')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('trained_at');
            $table->foreignId('trained_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('model_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_model_versions');
    }
};
