<?php

namespace App\Services;

use App\Models\DiscussionForum;
use App\Models\ForumThread;
use App\Models\ForumReply;
use App\Models\ForumActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DiscussionForumService
{
    /**
     * Get all active forums
     */
    public function getAllForums($category = null)
    {
        $query = DiscussionForum::where('is_active', true);

        if ($category) {
            $query->where('category', $category);
        }

        return $query->with('threads')->get();
    }

    /**
     * Get forum by slug
     */
    public function getForumBySlug($slug)
    {
        return DiscussionForum::where('slug', $slug)->firstOrFail();
    }

    /**
     * Get forum with threads
     */
    public function getForumWithThreads($forumId, $limit = 20)
    {
        $forum = DiscussionForum::findOrFail($forumId);
        $threads = $forum->threads()
            ->orderBy('is_pinned', 'desc')
            ->orderBy('last_reply_at', 'desc')
            ->paginate($limit);

        return compact('forum', 'threads');
    }

    /**
     * Create a new thread
     */
    public function createThread($forumId, $title, $content)
    {
        $userId = Auth::id();
        
        $thread = ForumThread::create([
            'forum_id' => $forumId,
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'last_reply_at' => now(),
        ]);

        // Update forum thread count
        $forum = DiscussionForum::find($forumId);
        $forum->increment('threads_count');

        // Log activity
        ForumActivityLog::log(
            $userId,
            'created_thread',
            'thread',
            $thread->id,
            "Created thread: {$title}",
            ['forum_id' => $forumId, 'title' => $title]
        );

        return $thread;
    }

    /**
     * Get thread with replies
     */
    public function getThreadWithReplies($threadId, $limit = 20)
    {
        $thread = ForumThread::findOrFail($threadId);
        
        // Debounce view count and activity logging (1-hour window per viewer)
        $viewerIdentifier = (string) (Auth::id() ?: request()->ip());
        $viewCacheKey = 'forum_thread_view_' . $threadId . '_' . md5($viewerIdentifier);
        if (!\Illuminate\Support\Facades\Cache::has($viewCacheKey)) {
            \Illuminate\Support\Facades\Cache::put($viewCacheKey, true, now()->addHours(1));

            // Increment views
            $thread->incrementViews();

            // Log view activity for authenticated members
            if (Auth::check()) {
                ForumActivityLog::log(
                    Auth::id(),
                    'viewed_thread',
                    'thread',
                    $threadId,
                    "Viewed thread: {$thread->title}"
                );
            }
        }

        $replies = $thread->replies()
            ->with('user')
            ->orderBy('is_helpful', 'desc')
            ->orderBy('created_at', 'asc')
            ->paginate($limit);

        return compact('thread', 'replies');
    }

    /**
     * Reply to thread
     */
    public function replyToThread($threadId, $content)
    {
        $userId = Auth::id();
        $thread = ForumThread::findOrFail($threadId);

        $reply = ForumReply::create([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        // Update thread reply count and last reply time
        $thread->increment('replies_count');
        $thread->update(['last_reply_at' => now()]);

        // Update forum reply count
        $forum = $thread->forum;
        $forum->increment('replies_count');

        // Log activity
        ForumActivityLog::log(
            $userId,
            'replied_to_thread',
            'reply',
            $reply->id,
            "Replied to thread: {$thread->title}",
            ['thread_id' => $threadId, 'thread_title' => $thread->title]
        );

        return $reply;
    }

    /**
     * Like a reply
     */
    public function likeReply($replyId)
    {
        $userId = Auth::id();
        $reply = ForumReply::findOrFail($replyId);

        $reply->increment('likes_count');

        // Log activity
        ForumActivityLog::log(
            $userId,
            'liked_reply',
            'reply',
            $replyId,
            "Liked a reply",
            ['reply_id' => $replyId]
        );

        return $reply;
    }

    /**
     * Mark reply as helpful
     */
    public function markReplyAsHelpful($replyId)
    {
        $userId = Auth::id();
        $reply = ForumReply::findOrFail($replyId);

        $reply->update(['is_helpful' => true]);

        // Log activity
        ForumActivityLog::log(
            $userId,
            'marked_helpful',
            'reply',
            $replyId,
            "Marked reply as helpful",
            ['reply_id' => $replyId]
        );

        return $reply;
    }

    /**
     * Get user's forum activity
     */
    public function getUserActivity($userId, $limit = 50)
    {
        return ForumActivityLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get forum activity
     */
    public function getForumActivity($forumId, $limit = 50)
    {
        $threadIds = ForumThread::where('forum_id', $forumId)->pluck('id');
        $replyIds = ForumReply::whereIn('thread_id', $threadIds)->pluck('id');

        return ForumActivityLog::where(function ($query) use ($forumId, $replyIds) {
            $query->where('entity_type', 'forum')
                  ->where('entity_id', $forumId)
                  ->orWhere(function ($q) use ($replyIds) {
                      $q->where('entity_type', 'reply')
                        ->whereIn('entity_id', $replyIds);
                  });
        })
        ->orderBy('created_at', 'desc')
        ->paginate($limit);
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats($forumId = null)
    {
        $query = ForumActivityLog::query();

        if ($forumId) {
            $threadIds = ForumThread::where('forum_id', $forumId)->pluck('id');
            $query->where('entity_id', $forumId)
                  ->orWhereIn('entity_id', $threadIds);
        }

        return [
            'total_activities' => $query->count(),
            'today_activities' => $query->whereDate('created_at', today())->count(),
            'this_week_activities' => $query->where('created_at', '>=', now()->startOfWeek())->count(),
            'by_action' => $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
            'top_contributors' => ForumActivityLog::selectRaw('user_id, COUNT(*) as activity_count')
                ->groupBy('user_id')
                ->orderBy('activity_count', 'desc')
                ->take(10)
                ->with('user')
                ->get(),
        ];
    }

    /**
     * Search threads
     */
    public function searchThreads($query, $forumId = null)
    {
        $search = ForumThread::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('content', 'like', "%{$query}%");
        });

        if ($forumId) {
            $search->where('forum_id', $forumId);
        }

        return $search->with('user', 'forum')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * Get trending threads
     */
    public function getTrendingThreads($forumId = null, $days = 7)
    {
        $query = ForumThread::where('created_at', '>=', now()->subDays($days));

        if ($forumId) {
            $query->where('forum_id', $forumId);
        }

        return $query->orderBy('views_count', 'desc')
            ->orderBy('replies_count', 'desc')
            ->take(10)
            ->get();
    }

    /**
     * Get most helpful replies
     */
    public function getMostHelpfulReplies($threadId)
    {
        return ForumReply::where('thread_id', $threadId)
            ->where('is_helpful', true)
            ->orderBy('likes_count', 'desc')
            ->get();
    }

    /**
     * Initialize default forums
     */
    public function initializeDefaultForums()
    {
        $forums = [
            [
                'name' => 'Academic Stress',
                'slug' => 'academic-stress',
                'description' => 'Exam anxiety, workload management, study tips',
                'category' => 'academic',
                'icon' => '📚',
            ],
            [
                'name' => 'Anxiety & Stress',
                'slug' => 'anxiety-stress',
                'description' => 'Managing anxiety, coping strategies, support',
                'category' => 'anxiety',
                'icon' => '😰',
            ],
            [
                'name' => 'Relationships',
                'slug' => 'relationships',
                'description' => 'Dating, friendships, family, social skills',
                'category' => 'relationships',
                'icon' => '💔',
            ],
            [
                'name' => 'Mental Health',
                'slug' => 'mental-health',
                'description' => 'Depression, self-esteem, personal growth',
                'category' => 'mental-health',
                'icon' => '🧠',
            ],
            [
                'name' => 'General Support',
                'slug' => 'general-support',
                'description' => 'General discussions, advice, encouragement',
                'category' => 'general',
                'icon' => '👥',
            ],
        ];

        foreach ($forums as $forumData) {
            DiscussionForum::firstOrCreate(
                ['slug' => $forumData['slug']],
                $forumData
            );
        }
    }
}
