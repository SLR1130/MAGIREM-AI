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
        Schema::create('pattern_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('alert_type', ['consecutive_low', 'consecutive_slightly_low', 'sudden_drop']);
            $table->enum('severity', ['Medium', 'High']);
            $table->text('pattern_description');
            $table->date('detection_date');
            $table->enum('status', ['Unresolved', 'Acknowledged', 'In Progress', 'Resolved'])->default('Unresolved');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('counselor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('severity', 'idx_severity');
            $table->index('detection_date', 'idx_detection_date');
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('counselor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pattern_alerts');
    }
};
