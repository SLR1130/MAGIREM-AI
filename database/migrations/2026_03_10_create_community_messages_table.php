<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_approved')->default(false);
            $table->integer('likes_count')->default(0);
            $table->text('moderation_notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_approved');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_messages');
    }
};
