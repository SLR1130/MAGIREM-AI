<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emotional_entry_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('text_input');
            $table->string('predicted_emotion');
            $table->decimal('prediction_confidence', 5, 2);
            $table->string('actual_emotion')->nullable(); // Corrected by counselor
            $table->string('predicted_crisis_level')->default('none');
            $table->string('actual_crisis_level')->nullable(); // Corrected by counselor
            $table->json('predicted_contexts')->nullable();
            $table->json('actual_contexts')->nullable(); // Corrected by counselor
            $table->boolean('is_validated')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();
            $table->boolean('used_for_training')->default(false);
            $table->string('model_version')->default('v1.0');
            $table->timestamps();
            
            $table->index('is_validated');
            $table->index('used_for_training');
            $table->index('predicted_emotion');
            $table->index('actual_emotion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_training_data');
    }
};
