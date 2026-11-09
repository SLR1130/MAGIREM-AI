<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Discussion Forums/Spaces
        Schema::create('discussion_forums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category'); // academic, anxiety, relationships, etc.
            $table->unsignedBigInteger('moderator_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('moderator_id')->references('id')->on('users')->onDelete('set null');
        });

        // Forum Discussions/Threads
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forum_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->foreign('forum_id')->references('id')->on('discussion_forums')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Forum Replies
        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            $table->foreign('thread_id')->references('id')->on('forum_threads')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Group Activities
        Schema::create('group_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('type'); // mindfulness, study_circle, support_group, etc.
            $table->unsignedBigInteger('facilitator_id');
            $table->dateTime('scheduled_at');
            $table->integer('max_participants')->default(20);
            $table->string('status')->default('scheduled'); // scheduled, ongoing, completed, cancelled
            $table->timestamps();
            $table->foreign('facilitator_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Activity Participants
        Schema::create('activity_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('registered'); // registered, attended, cancelled
            $table->timestamps();
            $table->foreign('activity_id')->references('id')->on('group_activities')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['activity_id', 'user_id']);
        });

        // Peer Connections
        Schema::create('peer_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('connected_user_id');
            $table->string('connection_type'); // similar_challenges, study_buddy, support_partner
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('connected_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'connected_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_connections');
        Schema::dropIfExists('activity_participants');
        Schema::dropIfExists('group_activities');
        Schema::dropIfExists('forum_replies');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('discussion_forums');
    }
};
