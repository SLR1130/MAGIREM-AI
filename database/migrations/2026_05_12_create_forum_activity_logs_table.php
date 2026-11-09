<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // created_thread, replied_to_thread, liked_reply, viewed_thread, etc.
            $table->string('entity_type'); // forum, thread, reply
            $table->unsignedBigInteger('entity_id');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional data like old/new values
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_activity_logs');
    }
};
