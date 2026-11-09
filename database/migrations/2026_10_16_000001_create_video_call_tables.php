<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caller_id');
            $table->unsignedBigInteger('callee_id');
            $table->string('status', 24)->default('ringing');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['callee_id', 'status']);
            $table->index(['caller_id', 'status']);
            $table->foreign('caller_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('callee_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('video_call_signals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_call_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 16); // offer, answer, ice
            $table->longText('payload');
            $table->timestamps();

            $table->index(['video_call_id', 'id']);
            $table->foreign('video_call_id')->references('id')->on('video_calls')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_call_signals');
        Schema::dropIfExists('video_calls');
    }
};
