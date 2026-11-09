<?php

namespace App\Services;

use App\Models\CommunityMessage;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserFollow;
use App\Models\PostComment;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SocialPlatformService
{
    /**
     * Create or update user social profile
     */
    public function createOrUpdateProfile($userId, $data)
    {
        return UserProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'bio' => $data['bio'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'interests' => $data['interests'] ?? null,
                'location' => $data['location'] ?? null,
                'is_public' => $data['is_public'] ?? true,
            ]
        );
    }

    /**
     * Get user's social profile with stats
     */
    public function getUserProfile($userId)
    {
        $user = User::findOrFail($userId);
        $profile = UserProfile::firstOrCreate(['user_id' => $userId]);
        
        return [
            'user' => $user,
            'profile' => $profile,
            'followers_count' => UserFollow::where('following_id', $userId)->where('is_active', true)->count(),
            'following_count' => UserFollow::where('user_id', $userId)->where('is_active', true)->count(),
            'posts_count' => CommunityMessage::where('user_id', $userId)->where('is_approved', true)->count(),
            'total_likes' => CommunityMessage::where('user_id', $userId)->where('is_approved', true)->sum('likes_count'),
        ];
    }

    /**
     * Follow a user
     */
    public function followUser($userId, $followingId)
    {
        if ($userId === $followingId) {
            throw new \Exception('Cannot follow yourself');
        }

        return UserFollow::updateOrCreate(
            ['user_id' => $userId, 'following_id' => $followingId],
            ['is_active' => true]
        );
    }

    /**
     * Unfollow a user
     */
    public function unfollowUser($userId, $followingId)
    {
        return UserFollow::where('user_id', $userId)
            ->where('following_id', $followingId)
            ->update(['is_active' => false]);
    }

    /**
     * Get user's followers
     */
    public function getFollowers($userId)
    {
        return UserFollow::where('following_id', $userId)
            ->where('is_active', true)
            ->with('user')
            ->paginate(20);
    }

    /**
     * Get user's following
     */
    public function getFollowing($userId)
    {
        return UserFollow::where('user_id', $userId)
            ->where('is_active', true)
            ->with('followingUser')
            ->paginate(20);
    }

    /**
     * Check if user follows another user
     */
    public function isFollowing($userId, $followingId)
    {
        return UserFollow::where('user_id', $userId)
            ->where('following_id', $followingId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Create a post (community message)
     */
    public function createPost($userId, $message, $category = 'general', $isAnonymous = true)
    {
        return CommunityMessage::create([
            'user_id' => $userId,
            'message' => $message,
            'category' => $category,
            'is_anonymous' => $isAnonymous,
            'is_approved' => false, // Requires moderation
        ]);
    }

    /**
     * Get user's activity feed (posts from followed users)
     */
    public function getActivityFeed($userId, $limit = 20)
    {
        // Get IDs of users being followed
        $followingIds = UserFollow::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('following_id')
            ->toArray();

        // Add user's own posts
        $followingIds[] = $userId;

        return CommunityMessage::whereIn('user_id', $followingIds)
            ->where('is_approved', true)
            ->with('user')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get trending posts (most liked in last 7 days)
     */
    public function getTrendingPosts($limit = 20)
    {
        return CommunityMessage::where('is_approved', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('likes_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get recommended posts for user
     */
    public function getRecommendedPosts($userId, $limit = 20)
    {
        // Get user's interests from profile
        $profile = UserProfile::where('user_id', $userId)->first();
        $interests = $profile ? json_decode($profile->interests, true) : [];

        $query = CommunityMessage::where('is_approved', true)
            ->where('user_id', '!=', $userId);

        // Filter by interests if available
        if (!empty($interests)) {
            $query->whereIn('category', $interests);
        }

        return $query->orderBy('likes_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Add comment to a post
     */
    public function addComment($postId, $userId, $content)
    {
        $post = CommunityMessage::findOrFail($postId);

        $comment = PostComment::create([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        // Increment replies count
        $post->increment('replies_count');

        // Notify post author
        if ($post->user_id !== $userId) {
            $this->createNotification(
                $post->user_id,
                $userId,
                'comment',
                "User commented on your post",
                "/student/community/{$postId}"
            );
        }

        return $comment;
    }

    /**
     * Get post comments
     */
    public function getPostComments($postId)
    {
        return PostComment::where('post_id', $postId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    /**
     * Delete comment
     */
    public function deleteComment($commentId)
    {
        $comment = PostComment::findOrFail($commentId);
        $post = CommunityMessage::find($comment->post_id);

        if ($post) {
            $post->decrement('replies_count');
        }

        return $comment->delete();
    }

    /**
     * Share a post
     */
    public function sharePost($postId, $userId)
    {
        $post = CommunityMessage::findOrFail($postId);
        
        // Create a new post that references the original
        return CommunityMessage::create([
            'user_id' => $userId,
            'message' => "Shared: {$post->message}",
            'category' => $post->category,
            'is_anonymous' => false,
            'is_approved' => true,
            'shared_from_id' => $postId,
        ]);
    }

    /**
     * Create notification
     */
    public function createNotification($userId, $fromUserId, $type, $message, $link = null)
    {
        return Notification::create([
            'user_id' => $userId,
            'from_user_id' => $fromUserId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'is_read' => false,
        ]);
    }

    /**
     * Get user notifications
     */
    public function getNotifications($userId, $unreadOnly = false)
    {
        $query = Notification::where('user_id', $userId);

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        return $query->with('fromUser')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead($notificationId)
    {
        return Notification::findOrFail($notificationId)->update(['is_read' => true]);
    }

    /**
     * Search posts
     */
    public function searchPosts($query, $category = null)
    {
        $search = CommunityMessage::where('is_approved', true)
            ->where(function ($q) use ($query) {
                $q->where('message', 'like', "%{$query}%");
            });

        if ($category && $category !== 'all') {
            $search->where('category', $category);
        }

        return $search->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Get user's posts
     */
    public function getUserPosts($userId)
    {
        return CommunityMessage::where('user_id', $userId)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Get social statistics
     */
    public function getSocialStats()
    {
        return [
            'total_users' => User::count(),
            'total_posts' => CommunityMessage::where('is_approved', true)->count(),
            'total_comments' => PostComment::count(),
            'total_follows' => UserFollow::where('is_active', true)->count(),
            'active_users_today' => User::where('last_seen_at', '>=', now()->subHours(24))->count(),
        ];
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats()
    {
        return CommunityMessage::where('is_approved', true)
            ->selectRaw('category, COUNT(*) as count, SUM(likes_count) as total_likes')
            ->groupBy('category')
            ->get()
            ->map(function ($stat) {
                return [
                    'category' => $stat->category,
                    'count' => $stat->count,
                    'total_likes' => $stat->total_likes,
                    'avg_likes' => round($stat->total_likes / $stat->count, 2),
                ];
            });
    }

    /**
     * Get suggested users to follow
     */
    public function getSuggestedUsers($userId, $limit = 10)
    {
        // Get users with similar interests
        $userProfile = UserProfile::where('user_id', $userId)->first();
        $userInterests = $userProfile ? json_decode($userProfile->interests, true) : [];

        // Get already following
        $followingIds = UserFollow::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('following_id')
            ->toArray();

        $followingIds[] = $userId;

        $query = User::whereNotIn('id', $followingIds)
            ->where('role', 'student');

        if (!empty($userInterests)) {
            $query->whereHas('profile', function ($q) use ($userInterests) {
                $q->where(function ($subQ) use ($userInterests) {
                    foreach ($userInterests as $interest) {
                        $subQ->orWhereRaw("JSON_CONTAINS(interests, ?)", [json_encode($interest)]);
                    }
                });
            });
        }

        return $query->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * Get user's mutual connections
     */
    public function getMutualConnections($userId, $otherUserId)
    {
        $userFollowing = UserFollow::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('following_id')
            ->toArray();

        $otherFollowing = UserFollow::where('user_id', $otherUserId)
            ->where('is_active', true)
            ->pluck('following_id')
            ->toArray();

        $mutual = array_intersect($userFollowing, $otherFollowing);

        return User::whereIn('id', $mutual)->get();
    }
}
