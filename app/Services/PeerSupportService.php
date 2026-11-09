<?php

namespace App\Services;

use App\Models\DiscussionForum;
use App\Models\ForumThread;
use App\Models\ForumReply;
use App\Models\GroupActivity;
use App\Models\ActivityParticipant;
use App\Models\PeerConnection;

class PeerSupportService
{
    // Discussion Forum Methods
    public function getForums($category = null)
    {
        $query = DiscussionForum::where('is_active', true);
        
        if ($category) {
            $query->where('category', $category);
        }
        
        return $query->with('threads')->get();
    }

    public function getForumById($forumId)
    {
        return DiscussionForum::with('threads.replies')->findOrFail($forumId);
    }

    public function createThread($forumId, $userId, $title, $content)
    {
        return ForumThread::create([
            'forum_id' => $forumId,
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ]);
    }

    public function getThreadById($threadId)
    {
        $thread = ForumThread::with('replies.user')->findOrFail($threadId);
        $thread->incrementViews();
        return $thread;
    }

    public function replyToThread($threadId, $userId, $content)
    {
        return ForumReply::create([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'content' => $content,
        ]);
    }

    // Group Activities Methods
    public function getUpcomingActivities()
    {
        return GroupActivity::where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->with('facilitator', 'participants')
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getActivitiesByType($type)
    {
        return GroupActivity::where('type', $type)
            ->where('status', 'scheduled')
            ->with('facilitator', 'participants')
            ->get();
    }

    public function registerForActivity($activityId, $userId)
    {
        $activity = GroupActivity::findOrFail($activityId);
        
        if ($activity->getAvailableSpots() <= 0) {
            throw new \Exception('Activity is full');
        }

        return ActivityParticipant::create([
            'activity_id' => $activityId,
            'user_id' => $userId,
            'status' => 'registered',
        ]);
    }

    public function cancelActivityRegistration($activityId, $userId)
    {
        return ActivityParticipant::where('activity_id', $activityId)
            ->where('user_id', $userId)
            ->update(['status' => 'cancelled']);
    }

    public function getUserActivities($userId)
    {
        return ActivityParticipant::where('user_id', $userId)
            ->where('status', 'registered')
            ->with('activity.facilitator')
            ->get();
    }

    // Peer Connection Methods
    public function connectWithPeer($userId, $connectedUserId, $connectionType)
    {
        return PeerConnection::create([
            'user_id' => $userId,
            'connected_user_id' => $connectedUserId,
            'connection_type' => $connectionType,
            'is_active' => true,
        ]);
    }

    public function getUserConnections($userId)
    {
        return PeerConnection::where('user_id', $userId)
            ->where('is_active', true)
            ->with('connectedUser')
            ->get();
    }

    public function disconnectPeer($userId, $connectedUserId)
    {
        return PeerConnection::where('user_id', $userId)
            ->where('connected_user_id', $connectedUserId)
            ->update(['is_active' => false]);
    }

    // Statistics
    public function getForumStats($forumId)
    {
        $forum = DiscussionForum::findOrFail($forumId);
        
        return [
            'total_threads' => $forum->threads()->count(),
            'total_replies' => $forum->getTotalRepliesCount(),
            'active_members' => $forum->threads()->distinct('user_id')->count('user_id'),
        ];
    }

    public function getActivityStats($activityId)
    {
        $activity = GroupActivity::findOrFail($activityId);
        
        return [
            'registered' => $activity->getParticipantCount(),
            'available_spots' => $activity->getAvailableSpots(),
            'total_capacity' => $activity->max_participants,
        ];
    }
}
