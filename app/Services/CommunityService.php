<?php

namespace App\Services;

use App\Models\CommunityBlock;
use App\Models\CommunityModerationAudit;
use App\Models\CommunityMessage;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\Notification;
use App\Models\PostComment;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunityService
{
    public const CATEGORIES = ['academic', 'mental-health', 'relationships', 'motivation', 'general'];
    public const SUPPORT_LABELS = ['advice', 'encouragement', 'sharing', 'listen'];
    public const REACTIONS = ['support', 'heard', 'helpful', 'encouragement', 'like', 'heart'];
    public const REPORT_REASONS = ['harassment', 'discrimination', 'harmful', 'privacy', 'spam', 'other'];
    private const RICH_NODE_TYPES = ['doc', 'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'text', 'hardBreak'];
    private const RICH_MARK_TYPES = ['bold', 'italic', 'strike', 'code', 'textStyle'];
    private const RICH_BACKGROUNDS = ['canvas', 'sage-grid', 'sunrise-wash', 'forest-dots', 'lavender-wave'];
    private const RICH_FONTS = ['jakarta', 'lora', 'manrope', 'dm-sans', 'fraunces', 'caveat', 'playfair-display'];
    private const RICH_FONT_FAMILIES = ['Plus Jakarta Sans Variable', 'Lora Variable', 'Manrope Variable', 'DM Sans Variable', 'Fraunces Variable', 'Caveat Variable', 'Playfair Display Variable'];
    private const RICH_FONT_SIZES = ['14px', '16px', '18px', '22px', '28px', '32px'];
    private const RICH_TEXT_COLORS = ['#173e32', '#2f6b4f', '#805000', '#633f7a', '#4b5563', '#111827', '#ffffff'];
    private const RICH_TEXT_ALIGNMENTS = ['left', 'center', 'right'];
    private const RICH_SIZES = ['compact', 'standard', 'display'];

    public function postMessage($message, $category = 'general', $isAnonymous = false, ?string $title = null, ?string $supportLabel = null, ?UploadedFile $image = null, ?int $resourceId = null)
    {
        $this->requireCommunityAccess();
        $isAnonymous = $this->studentRequestedAnonymity((bool) $isAnonymous);
        if ($resourceId !== null) {
            abort_unless(in_array(Auth::user()?->role, ['counselor', 'admin'], true), 403, 'Only authorized staff can share curated resources.');
            Resource::where('is_active', true)->findOrFail($resourceId);
        }
        $this->validateCategoryAndLabel($category, $supportLabel);

        $body = trim((string) $message);
        $title = $title !== null ? trim($title) : null;
        $media = $image ? $this->prepareCommunityImage($image) : null;
        $decision = $this->moderationDecision(trim(implode("\n", array_filter([$title, $body], static fn ($value) => trim((string) $value) !== ''))));

        try {
            $result = DB::transaction(function () use ($body, $category, $isAnonymous, $title, $supportLabel, $media, $decision, $resourceId) {
                $duplicateQuery = CommunityMessage::where('user_id', Auth::id())
                    ->where('message', $body)
                    ->where('category', $category)
                    ->where('created_at', '>=', now()->subSeconds(30));

                if ($media) {
                    $duplicateQuery->where('image_hash', $media['hash']);
                } else {
                    $duplicateQuery->whereNull('image_hash');
                }
                if ($resourceId !== null) {
                    $duplicateQuery->where('resource_id', $resourceId);
                } else {
                    $duplicateQuery->whereNull('resource_id');
                }

                $duplicate = $duplicateQuery->first();
                if ($duplicate) {
                    return ['message' => $duplicate, 'created' => false];
                }

                $messageRecord = CommunityMessage::create([
                        'user_id' => Auth::id(),
                        'title' => $title !== '' ? $title : null,
                        'message' => $body,
                        'category' => $category,
                        'support_label' => $supportLabel,
                        'resource_id' => $resourceId,
                        'image_path' => $media['path'] ?? null,
                        'image_hash' => $media['hash'] ?? null,
                        'image_width' => $media['width'] ?? null,
                        'image_height' => $media['height'] ?? null,
                        'is_anonymous' => (bool) $isAnonymous,
                        'is_approved' => $decision['approved'],
                        'moderation_status' => $decision['status'],
                        'moderation_notes' => $decision['notes'],
                        'approved_at' => $decision['approved'] ? now() : null,
                        'last_activity_at' => now(),
                    ]);
                $this->recordModerationAudit($messageRecord, (int) Auth::id(), null, 'automatic_review', null, $decision['status'], null, null, $decision['notes']);

                return ['message' => $messageRecord, 'created' => true];
            });

            if (!$result['created'] && $media) {
                $this->deleteCommunityMedia($media['path']);
            }

            return $result['message'];
        } catch (\Throwable $exception) {
            if ($media) {
                $this->deleteCommunityMedia($media['path']);
            }
            throw $exception;
        }
    }

    /**
     * Persist the allow-listed Tiptap document, its readable text projection and
     * a tightly controlled presentation choice. The browser never supplies HTML
     * or arbitrary CSS and moderation continues to screen the readable text.
     */
    public function postRichMessage(array $richContent, array $postStyle, string $category = 'general', bool $isAnonymous = false, ?string $title = null, ?string $supportLabel = null, ?UploadedFile $image = null, ?int $resourceId = null): CommunityMessage
    {
        $this->requireCommunityAccess();
        $isAnonymous = $this->studentRequestedAnonymity($isAnonymous);
        if ($resourceId !== null) {
            abort_unless(in_array(Auth::user()?->role, ['counselor', 'admin'], true), 403, 'Only authorized staff can share curated resources.');
            Resource::where('is_active', true)->findOrFail($resourceId);
        }
        $this->validateCategoryAndLabel($category, $supportLabel);
        [$document, $body] = $this->normalizeRichContent($richContent);
        $style = $this->normalizeRichStyle($postStyle);
        $title = $title !== null ? trim($title) : null;
        $media = $image ? $this->prepareCommunityImage($image) : null;
        $decision = $this->moderationDecision(trim(implode("\n", array_filter([$title, $body], static fn ($value) => trim((string) $value) !== ''))));

        try {
            $result = DB::transaction(function () use ($document, $style, $body, $category, $isAnonymous, $title, $supportLabel, $media, $decision, $resourceId) {
                $duplicate = CommunityMessage::where('user_id', Auth::id())
                    ->where('post_format', 'rich')
                    ->where('message', $body)
                    ->where('category', $category)
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->when($media, fn ($query) => $query->where('image_hash', $media['hash']), fn ($query) => $query->whereNull('image_hash'))
                    ->first();
                if ($duplicate) {
                    return ['message' => $duplicate, 'created' => false];
                }

                $message = CommunityMessage::create([
                    'user_id' => Auth::id(),
                    'title' => $title !== '' ? $title : null,
                    'message' => $body,
                    'post_format' => 'rich',
                    'rich_content' => $document,
                    'post_style' => $style,
                    'category' => $category,
                    'support_label' => $supportLabel,
                    'resource_id' => $resourceId,
                    'image_path' => $media['path'] ?? null,
                    'image_hash' => $media['hash'] ?? null,
                    'image_width' => $media['width'] ?? null,
                    'image_height' => $media['height'] ?? null,
                    'is_anonymous' => $isAnonymous,
                    'is_approved' => $decision['approved'],
                    'moderation_status' => $decision['status'],
                    'moderation_notes' => $decision['notes'],
                    'approved_at' => $decision['approved'] ? now() : null,
                    'last_activity_at' => now(),
                ]);
                $this->recordModerationAudit($message, (int) Auth::id(), null, 'automatic_review', null, $decision['status'], null, null, $decision['notes']);
                return ['message' => $message, 'created' => true];
            });
            if (!$result['created'] && $media) {
                $this->deleteCommunityMedia($media['path']);
            }
            return $result['message'];
        } catch (\Throwable $exception) {
            if ($media) {
                $this->deleteCommunityMedia($media['path']);
            }
            throw $exception;
        }
    }

    /** A rich edit remains owner-only and returns the post to existing moderation. */
    public function updateRichMessage(int $messageId, int $userId, array $richContent, array $postStyle, array $data, ?UploadedFile $image = null): CommunityMessage
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        [$document, $body] = $this->normalizeRichContent($richContent);
        $style = $this->normalizeRichStyle($postStyle);
        $this->validateCategoryAndLabel($data['category'], $data['support_label'] ?? null);

        $existing = CommunityMessage::findOrFail($messageId);
        abort_unless((int) $existing->user_id === $userId, 403);
        abort_unless(in_array((string) ($existing->post_format ?: 'text'), ['text', 'rich'], true), 422, 'Visual posts use the visual editor.');
        $replacement = $image ? $this->prepareCommunityImage($image) : null;
        $oldPath = $existing->image_path;

        try {
            $updated = DB::transaction(function () use ($messageId, $userId, $document, $style, $body, $data, $replacement) {
                $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
                abort_unless((int) $message->user_id === $userId, 403);
                $fromStatus = $message->moderation_status;
                $wasPinned = (bool) $message->is_pinned;
                $message->update([
                    'title' => isset($data['title']) ? trim((string) $data['title']) ?: null : null,
                    'message' => $body,
                    'post_format' => 'rich',
                    'rich_content' => $document,
                    'post_style' => $style,
                    'category' => $data['category'],
                    'support_label' => $data['support_label'] ?? null,
                    'image_path' => $replacement['path'] ?? $message->image_path,
                    'image_hash' => $replacement['hash'] ?? $message->image_hash,
                    'image_width' => $replacement['width'] ?? $message->image_width,
                    'image_height' => $replacement['height'] ?? $message->image_height,
                    'is_approved' => false,
                    'moderation_status' => 'pending',
                    'approved_at' => null,
                    'moderation_notes' => null,
                    'last_activity_at' => now(),
                ]);
                $this->recordModerationAudit($message, $userId, null, 'author_edit', $fromStatus, 'pending', $wasPinned, $wasPinned, null);
                return $message->fresh();
            });
            if ($replacement && $oldPath && $oldPath !== $replacement['path']) {
                $this->deleteCommunityMedia($oldPath);
            }
            return $updated;
        } catch (\Throwable $exception) {
            if ($replacement) {
                $this->deleteCommunityMedia($replacement['path']);
            }
            throw $exception;
        }
    }
    /**
     * Create a visual post. The server renders the final composition and keeps
     * the editable text/layout separate from the rendered media.
     */
    public function postVisualMessage(string $visualText, array $layout, bool $isAnonymous = false, ?UploadedFile $image = null, ?array $captionContent = null, string $category = 'general', ?string $supportLabel = null, ?int $resourceId = null): CommunityMessage
    {
        $this->requireCommunityAccess();
        $isAnonymous = $this->studentRequestedAnonymity($isAnonymous);
        $visualText = trim($visualText);
        $this->validateCategoryAndLabel($category, $supportLabel);
        if ($resourceId !== null) {
            abort_unless(in_array(Auth::user()?->role, ['counselor', 'admin'], true), 403, 'Only authorized staff can share curated resources.');
            Resource::where('is_active', true)->findOrFail($resourceId);
        }
        $sourceMedia = $image ? $this->prepareCommunityImage($image) : null;
        $sourcePath = $sourceMedia['path'] ?? null;
        $normalizedLayout = $this->normalizeVisualLayout($layout, $sourceMedia['width'] ?? null, $sourceMedia['height'] ?? null);
        $caption = $captionContent ? $this->normalizeRichContent($captionContent, 1) : null;
        $visualText = $this->compositionText($normalizedLayout, $visualText);
        $messageText = trim(implode("\n", array_filter([$visualText, $caption[1] ?? ''], static fn ($text) => trim((string) $text) !== '')));
        if ($messageText === '' && !$sourcePath) {
            throw ValidationException::withMessages(['visual_layout' => 'Add a photo or at least one text or sticker layer.']);
        }
        $renderedPath = null;

        try {
            $renderedPath = $this->storeCommunityMedia(
                $this->renderVisualComposition($sourcePath, $normalizedLayout, $visualText),
                'community_visuals',
            );
            $decision = $this->moderationDecision($messageText, true);

            $result = DB::transaction(function () use ($visualText, $messageText, $caption, $normalizedLayout, $isAnonymous, $sourceMedia, $sourcePath, $renderedPath, $decision, $category, $supportLabel, $resourceId) {
                $duplicate = CommunityMessage::where('user_id', Auth::id())
                    ->where('post_format', 'visual')
                    ->where('message', $messageText)
                    ->where('image_hash', $sourceMedia['hash'] ?? null)
                    ->where('resource_id', $resourceId)
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->first();

                if ($duplicate) {
                    return ['message' => $duplicate, 'created' => false];
                }

                $messageRecord = CommunityMessage::create([
                        'user_id' => Auth::id(),
                        'message' => $messageText !== '' ? $messageText : 'Shared a visual post.',
                        'rich_content' => $caption[0] ?? null,
                        'post_format' => 'visual',
                        'visual_text' => $visualText,
                        'visual_layout' => $normalizedLayout,
                        'visual_media_path' => $renderedPath,
                        'title' => null,
                        'category' => $category,
                        'support_label' => $supportLabel,
                        'resource_id' => $resourceId,
                        'image_path' => $sourcePath,
                        'image_hash' => $sourceMedia['hash'] ?? null,
                        'image_width' => $sourceMedia['width'] ?? null,
                        'image_height' => $sourceMedia['height'] ?? null,
                        'is_anonymous' => $isAnonymous,
                        'is_approved' => $decision['approved'],
                        'moderation_status' => $decision['status'],
                        'moderation_notes' => $decision['notes'],
                        'approved_at' => $decision['approved'] ? now() : null,
                        'last_activity_at' => now(),
                    ]);
                $this->recordModerationAudit($messageRecord, (int) Auth::id(), null, 'automatic_review', null, $decision['status'], null, null, $decision['notes']);

                return ['message' => $messageRecord, 'created' => true];
            });

            if (!$result['created']) {
                $this->deleteCommunityMedia($renderedPath);
                if ($sourcePath) {
                    $this->deleteCommunityMedia($sourcePath);
                }
            }

            return $result['message'];
        } catch (\Throwable $exception) {
            $this->deleteCommunityMedia($renderedPath);
            $this->deleteCommunityMedia($sourcePath);
            throw $exception;
        }
    }

    public function updateVisualMessage(int $messageId, int $userId, string $visualText, array $layout, ?UploadedFile $image = null, ?array $captionContent = null, ?string $category = null, ?string $supportLabel = null, ?int $resourceId = null): CommunityMessage
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        $visualText = trim($visualText);
        $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
        abort_unless((int) $message->user_id === $userId, 403);
        abort_unless(in_array((string) $message->post_format, ['text', 'rich', 'visual'], true), 422);
        $category ??= $message->category;
        $supportLabel ??= $message->support_label;
        $this->validateCategoryAndLabel($category, $supportLabel);
        if ($resourceId !== null) {
            abort_unless(in_array(Auth::user()?->role, ['counselor', 'admin'], true), 403, 'Only authorized staff can share curated resources.');
            Resource::where('is_active', true)->findOrFail($resourceId);
        }

        $oldSourcePath = $message->image_path;
        $oldRenderedPath = $message->visual_media_path;
        $sourceMedia = $image ? $this->prepareCommunityImage($image) : null;
        $sourcePath = $sourceMedia['path'] ?? $oldSourcePath;
        $isLayered = ($layout['version'] ?? null) === 2;
        $compositionHasImage = $isLayered && collect($layout['layers'] ?? [])->contains(fn ($layer) => is_array($layer) && ($layer['type'] ?? null) === 'image');
        if ($isLayered && !$compositionHasImage) {
            $sourcePath = null;
        }
        $normalizedLayout = $this->normalizeVisualLayout(
            $layout,
            $sourcePath ? ($sourceMedia['width'] ?? $message->image_width) : null,
            $sourcePath ? ($sourceMedia['height'] ?? $message->image_height) : null,
        );
        $caption = $captionContent ? $this->normalizeRichContent($captionContent, 1) : null;
        $visualText = $this->compositionText($normalizedLayout, $visualText);
        $messageText = trim(implode("\n", array_filter([$visualText, $caption[1] ?? ''], static fn ($text) => trim((string) $text) !== '')));
        if ($messageText === '' && !$sourcePath) {
            throw ValidationException::withMessages(['visual_layout' => 'Add a photo or at least one text or sticker layer.']);
        }
        $renderedPath = null;

        try {
            $renderedPath = $this->storeCommunityMedia(
                $this->renderVisualComposition($sourcePath, $normalizedLayout, $visualText),
                'community_visuals',
            );
            $decision = $this->moderationDecision($messageText, true);
            $updated = DB::transaction(function () use ($messageId, $userId, $visualText, $messageText, $caption, $normalizedLayout, $sourceMedia, $sourcePath, $renderedPath, $decision, $category, $supportLabel, $resourceId) {
                $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
                abort_unless((int) $message->user_id === $userId, 403);
                $message->update([
                    'message' => $messageText !== '' ? $messageText : 'Shared a visual post.',
                    'rich_content' => $caption[0] ?? null,
                    'post_format' => 'visual',
                    'visual_text' => $visualText,
                    'visual_layout' => $normalizedLayout,
                    'visual_media_path' => $renderedPath,
                    'category' => $category,
                    'support_label' => $supportLabel,
                    'resource_id' => $resourceId,
                    'image_path' => $sourcePath,
                    'image_hash' => $sourcePath ? ($sourceMedia['hash'] ?? $message->image_hash) : null,
                    'image_width' => $sourcePath ? ($sourceMedia['width'] ?? $message->image_width) : null,
                    'image_height' => $sourcePath ? ($sourceMedia['height'] ?? $message->image_height) : null,
                    'is_approved' => $decision['approved'],
                    'moderation_status' => $decision['status'],
                    'moderation_notes' => $decision['notes'],
                    'approved_at' => $decision['approved'] ? now() : null,
                    'moderated_at' => null,
                    'moderated_by' => null,
                    'last_activity_at' => now(),
                ]);
                return $message->fresh();
            });

            $this->deleteCommunityMedia($oldRenderedPath);
            if ($oldSourcePath && $oldSourcePath !== $sourcePath) {
                $this->deleteCommunityMedia($oldSourcePath);
            }
            return $updated;
        } catch (\Throwable $exception) {
            $this->deleteCommunityMedia($renderedPath);
            if ($image && $sourcePath !== $oldSourcePath) {
                $this->deleteCommunityMedia($sourcePath);
            }
            throw $exception;
        }
    }
    public function getApprovedMessages($category = null, $sortBy = 'recent', ?int $viewerId = null, ?string $search = null, ?string $campus = null)
    {
        $viewerId ??= Auth::id();
        $query = CommunityMessage::query()
            ->where('is_approved', true)
            ->whereHas('user', fn ($userQuery) => $this->applyEligibleAuthorConstraints($userQuery))
            ->with([
                'user:id,name,role',
                'resource:id,title,description,category,is_active',
                'reactions' => fn ($query) => $query->where('user_id', $viewerId),
            ])
            ->withCount([
                'reactions as support_reactions_count' => fn ($query) => $query->where('reaction', 'support'),
                'reactions as heard_reactions_count' => fn ($query) => $query->where('reaction', 'heard'),
                'reactions as helpful_reactions_count' => fn ($query) => $query->where('reaction', 'helpful'),
                'reactions as encouragement_reactions_count' => fn ($query) => $query->where('reaction', 'encouragement'),
                'reactions as like_reactions_count' => fn ($query) => $query->where('reaction', 'like'),
                'reactions as heart_reactions_count' => fn ($query) => $query->where('reaction', 'heart'),
            ]);

        if ($viewerId) {
            $blockedIds = CommunityBlock::where('blocker_id', $viewerId)->pluck('blocked_id');
            $blockedByIds = CommunityBlock::where('blocked_id', $viewerId)->pluck('blocker_id');
            $hiddenIds = $blockedIds->merge($blockedByIds)->unique()->values();
            if ($hiddenIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $hiddenIds);
            }
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('message', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('support_label', 'like', '%' . $search . '%');
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($campus !== null && $campus !== '') {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('campus', $campus));
        }

        if ($sortBy === 'popular') {
            $query->orderByDesc('likes_count')->orderByDesc('created_at');
        } elseif ($sortBy === 'active') {
            $query->orderByDesc('last_activity_at')->orderByDesc('created_at');
        } elseif ($sortBy === 'unanswered') {
            $query->where('replies_count', 0)->orderByDesc('created_at');
        } elseif ($sortBy === 'trending') {
            $query->where('created_at', '>=', now()->subDays(7))->orderByDesc('likes_count')->orderByDesc('created_at');
        } else {
            $query->orderByDesc('is_pinned')->orderByDesc('created_at')->orderByDesc('id');
        }

        return $query->paginate(15)->withQueryString();
    }

    /** Admin-only moderation lens over the same Community Circle records. */
    public function getModeratorFeed(string $moderationView = 'all', $category = null, $sortBy = 'recent', ?int $viewerId = null, ?string $search = null, ?string $campus = null)
    {
        abort_unless(Auth::user()?->role === 'admin', 403, 'Administrator access is required.');
        $viewerId ??= Auth::id();
        $hidden = function ($query): void {
            $query->where('is_approved', false)->where('moderation_status', 'rejected')
                ->whereExists(function ($audit) {
                    $audit->selectRaw('1')->from('community_moderation_audits as latest_hide')
                        ->whereColumn('latest_hide.community_message_id', 'community_messages.id')
                        ->where('latest_hide.action', 'hide')
                        ->whereRaw('latest_hide.id = (select max(a.id) from community_moderation_audits as a where a.community_message_id = community_messages.id)');
                });
        };

        $query = CommunityMessage::query()->where(function ($visible) use ($hidden): void {
            $visible->where('is_approved', true)
                ->orWhere(function ($review) {
                    $review->where('is_approved', false)->whereIn('moderation_status', ['pending', 'flagged']);
                })
                ->orWhere($hidden);
        })->whereHas('user', fn ($author) => $this->applyEligibleAuthorConstraints($author))
            ->with(['user:id,name,role', 'resource:id,title,description,category,is_active', 'reactions' => fn ($q) => $q->where('user_id', $viewerId)])
            ->withCount([
                'reactions as support_reactions_count' => fn ($q) => $q->where('reaction', 'support'),
                'reactions as heard_reactions_count' => fn ($q) => $q->where('reaction', 'heard'),
                'reactions as helpful_reactions_count' => fn ($q) => $q->where('reaction', 'helpful'),
                'reactions as encouragement_reactions_count' => fn ($q) => $q->where('reaction', 'encouragement'),
                'reactions as like_reactions_count' => fn ($q) => $q->where('reaction', 'like'),
                'reactions as heart_reactions_count' => fn ($q) => $q->where('reaction', 'heart'),
            ]);

        if ($moderationView === 'needs_review') {
            $query->where('is_approved', false)->whereIn('moderation_status', ['pending', 'flagged']);
        } elseif ($moderationView === 'hidden') {
            $hidden($query);
        } elseif ($moderationView === 'published') {
            $query->where('is_approved', true);
        }

        if ($viewerId) {
            $hiddenAuthorIds = CommunityBlock::where('blocker_id', $viewerId)->pluck('blocked_id')
                ->merge(CommunityBlock::where('blocked_id', $viewerId)->pluck('blocker_id'))->unique();
            if ($hiddenAuthorIds->isNotEmpty()) $query->whereNotIn('user_id', $hiddenAuthorIds);
        }
        $search = trim((string) $search);
        if ($search !== '') $query->where(fn ($q) => $q->where('message', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')->orWhere('support_label', 'like', '%'.$search.'%'));
        if ($category && $category !== 'all') $query->where('category', $category);
        if ($campus) $query->whereHas('user', fn ($q) => $q->where('campus', $campus));
        if ($sortBy === 'popular') $query->orderByDesc('likes_count')->orderByDesc('created_at');
        else $query->orderByDesc('is_pinned')->orderByDesc('created_at')->orderByDesc('id');

        return $query->paginate(15)->withQueryString();
    }

    public function getMyMessages(int $userId, string $status = 'all')
    {
        $query = CommunityMessage::where('user_id', $userId);

        if ($status === 'published') {
            $query->where('is_approved', true);
        } elseif ($status === 'pending') {
            $query->where('is_approved', false)->where(function ($builder) {
                $builder->where('moderation_status', 'pending')
                    ->orWhereNull('moderation_status');
            });
        } elseif ($status === 'flagged') {
            $query->whereIn('moderation_status', ['flagged', 'rejected']);
        }

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'my_page')
            ->withQueryString();
    }

    public function getPendingMessages()
    {
        return CommunityMessage::with('user:id,name')->where(function ($query) {
            $query->whereIn('moderation_status', ['pending', 'flagged'])
                ->orWhere(function ($legacy) {
                    $legacy->where('is_approved', false)->whereNull('moderation_status');
                });
        })->orderBy('created_at')->orderBy('id')->paginate(20);
    }

    public function approveMessage($messageId, $notes = null, ?int $moderatorId = null)
    {
        $this->requireCommunityModerator($moderatorId);
        return DB::transaction(function () use ($messageId, $notes, $moderatorId) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            $from = $message->moderation_status;
            $wasPinned = (bool) $message->is_pinned;
            $message->update([
                'is_approved' => true,
                'moderation_status' => 'approved',
                'approved_at' => now(),
                'moderated_at' => now(),
                'moderated_by' => $moderatorId,
                'moderation_notes' => $notes,
            ]);
            $this->recordModerationAudit($message, $moderatorId, $moderatorId, 'approve', $from, 'approved', $wasPinned, $wasPinned, $notes);
            return $message;
        });
    }

    public function rejectMessage($messageId, $reason = null, ?int $moderatorId = null)
    {
        $this->requireCommunityModerator($moderatorId);
        $mediaPath = null;
        $visualMediaPath = null;
        $message = DB::transaction(function () use ($messageId, $reason, $moderatorId, &$mediaPath, &$visualMediaPath) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            $from = $message->moderation_status;
            $wasPinned = (bool) $message->is_pinned;
            $mediaPath = $message->image_path;
            $visualMediaPath = $message->visual_media_path;
            $message->update([
                'is_approved' => false,
                'moderation_status' => 'rejected',
                'moderated_at' => now(),
                'moderated_by' => $moderatorId,
                'moderation_notes' => $reason,
                'image_path' => null,
                'image_hash' => null,
                'image_width' => null,
                'image_height' => null,
            ]);
            $this->recordModerationAudit($message, $moderatorId, $moderatorId, 'reject', $from, 'rejected', $wasPinned, false, $reason);
            return $message;
        });
        $this->deleteCommunityMedia($mediaPath);
        $this->deleteCommunityMedia($visualMediaPath);
        return $message;
    }

    /** Remove a published post from the feed without destroying its content or media. */
    public function hidePublishedMessage(int $messageId, int $moderatorId, string $reason): CommunityMessage
    {
        $this->requireCommunityModerator($moderatorId);
        return DB::transaction(function () use ($messageId, $moderatorId, $reason) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            abort_unless($message->is_approved, 409, 'Only published posts can be removed from the feed.');
            $from = $message->moderation_status;
            $wasPinned = (bool) $message->is_pinned;
            $message->update([
                'is_approved' => false,
                'moderation_status' => 'rejected',
                'is_pinned' => false,
                'moderated_at' => now(),
                'moderated_by' => $moderatorId,
                'moderation_notes' => $reason,
            ]);
            $this->recordModerationAudit($message, $moderatorId, $moderatorId, 'hide', $from, 'rejected', $wasPinned, false, $reason);
            return $message->fresh();
        });
    }

    /** Restore only posts whose latest moderation action was a moderator hide. */
    public function restoreHiddenMessage(int $messageId, int $moderatorId): CommunityMessage
    {
        $this->requireCommunityModerator($moderatorId);
        return DB::transaction(function () use ($messageId, $moderatorId) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            $latestAction = CommunityModerationAudit::where('community_message_id', $message->id)->orderByDesc('id')->value('action');
            abort_unless(!$message->is_approved && $message->moderation_status === 'rejected' && $latestAction === 'hide', 409, 'This post is not available for restoration.');
            $message->update([
                'is_approved' => true,
                'moderation_status' => 'approved',
                'approved_at' => now(),
                'moderated_at' => now(),
                'moderated_by' => $moderatorId,
                'moderation_notes' => 'Restored after moderator review',
            ]);
            $this->recordModerationAudit($message, $moderatorId, $moderatorId, 'restore', 'rejected', 'approved', false, false, null);
            return $message->fresh();
        });
    }

    public function pinMessage(int $messageId, int $moderatorId, bool $pinned): CommunityMessage
    {
        $this->requireCommunityModerator($moderatorId);
        return DB::transaction(function () use ($messageId, $moderatorId, $pinned) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            abort_unless($message->is_approved, 409, 'Only published posts can be pinned.');
            $wasPinned = (bool) $message->is_pinned;
            if ($wasPinned !== $pinned) {
                $message->update(['is_pinned' => $pinned, 'moderated_at' => now(), 'moderated_by' => $moderatorId]);
                $this->recordModerationAudit($message, $moderatorId, $moderatorId, $pinned ? 'pin' : 'unpin', $message->moderation_status, $message->moderation_status, $wasPinned, $pinned, null);
            }
            return $message->fresh();
        });
    }

    public function reactMessage(int $messageId, int $userId, string $reaction): array
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        if (!in_array($reaction, self::REACTIONS, true)) {
            throw ValidationException::withMessages(['reaction' => 'Choose a supported reaction.']);
        }

        return DB::transaction(function () use ($messageId, $userId, $reaction) {
            $message = CommunityMessage::where('is_approved', true)->lockForUpdate()->findOrFail($messageId);
            $this->ensureNoBlockedInteraction($userId, (int) $message->user_id);
            $existing = CommunityReaction::where('community_message_id', $messageId)->where('user_id', $userId)->first();
            if ($existing) {
                $existing->update(['reaction' => $reaction]);
            } else {
                CommunityReaction::create(['community_message_id' => $messageId, 'user_id' => $userId, 'reaction' => $reaction]);
            }
            $supportCount = CommunityReaction::where('community_message_id', $messageId)->where('reaction', 'support')->count();
            $message->update(['likes_count' => $supportCount]);
            if ((int) $message->user_id !== $userId) {
                $this->notifyOnce((int) $message->user_id, $userId, 'community_reaction', 'Someone supported your community post.', '/student/community');
            }
            $counts = $this->reactionCounts($messageId);
            return ['reaction' => $reaction, 'likes_count' => $supportCount, 'like_count' => (int) ($counts['like'] ?? 0), 'heart_count' => (int) ($counts['heart'] ?? 0), 'reaction_counts' => $counts];
        });
    }

    public function removeReaction(int $messageId, int $userId): array
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        return DB::transaction(function () use ($messageId, $userId) {
            $message = CommunityMessage::where('is_approved', true)->lockForUpdate()->findOrFail($messageId);
            CommunityReaction::where('community_message_id', $messageId)->where('user_id', $userId)->delete();
            $supportCount = CommunityReaction::where('community_message_id', $messageId)->where('reaction', 'support')->count();
            $message->update(['likes_count' => $supportCount]);
            $counts = $this->reactionCounts($messageId);
            return ['reaction' => null, 'likes_count' => $supportCount, 'like_count' => (int) ($counts['like'] ?? 0), 'heart_count' => (int) ($counts['heart'] ?? 0), 'reaction_counts' => $counts];
        });
    }

    public function reactionCounts(int $messageId): array
    {
        return CommunityReaction::where('community_message_id', $messageId)->select('reaction', DB::raw('COUNT(*) as count'))
            ->groupBy('reaction')->pluck('count', 'reaction')->map(fn ($count) => (int) $count)->all();
    }

    public function addReport(int $reporterId, string $type, int $targetId, string $reason): CommunityReport
    {
        $this->requireCommunityAccess();
        $this->requireActor($reporterId);
        if (!in_array($type, ['post', 'comment', 'user'], true) || !in_array($reason, self::REPORT_REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'The report details are invalid.']);
        }
        $targetExists = match ($type) {
            'post' => CommunityMessage::whereKey($targetId)->exists(),
            'comment' => PostComment::whereKey($targetId)->exists(),
            'user' => app(CommunityVerificationService::class)->isEligibleParticipant(User::find($targetId)),
        };
        abort_unless($targetExists, 404);

        try {
            return CommunityReport::firstOrCreate(
                ['reporter_id' => $reporterId, 'reportable_type' => $type, 'reportable_id' => $targetId],
                ['reason' => $reason, 'status' => 'pending']
            );
        } catch (QueryException $exception) {
            if (!in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                throw $exception;
            }
            // A concurrent duplicate is safe: return the already-created report.
            return CommunityReport::where('reporter_id', $reporterId)
                ->where('reportable_type', $type)
                ->where('reportable_id', $targetId)
                ->firstOrFail();
        }
    }

    public function blockUser(int $blockerId, int $blockedId): void
    {
        $this->requireCommunityAccess();
        $this->requireActor($blockerId);
        abort_if($blockerId === $blockedId, 422, 'You cannot block yourself.');
        abort_unless(app(CommunityVerificationService::class)->isEligibleParticipant(User::find($blockedId)), 404);
        CommunityBlock::firstOrCreate(['blocker_id' => $blockerId, 'blocked_id' => $blockedId]);
    }

    public function unblockUser(int $blockerId, int $blockedId): void
    {
        $this->requireCommunityAccess();
        $this->requireActor($blockerId);
        CommunityBlock::where('blocker_id', $blockerId)->where('blocked_id', $blockedId)->delete();
    }

    public function isBlocked(int $firstUserId, int $secondUserId): bool
    {
        return CommunityBlock::where(function ($query) use ($firstUserId, $secondUserId) {
            $query->where('blocker_id', $firstUserId)->where('blocked_id', $secondUserId);
        })->orWhere(function ($query) use ($firstUserId, $secondUserId) {
            $query->where('blocker_id', $secondUserId)->where('blocked_id', $firstUserId);
        })->exists();
    }

    public function addComment(int $postId, int $userId, string $content, ?int $parentId = null): PostComment
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        return DB::transaction(function () use ($postId, $userId, $content, $parentId) {
            $post = CommunityMessage::where('is_approved', true)->lockForUpdate()->findOrFail($postId);
            $this->ensureNoBlockedInteraction($userId, (int) $post->user_id);
            $parent = null;
            if ($parentId !== null) {
                $parent = PostComment::where('post_id', $postId)->findOrFail($parentId);
                abort_if($parent->parent_id !== null, 422, 'Replies can only be one level deep.');
                $this->ensureNoBlockedInteraction($userId, (int) $parent->user_id);
            }
            $comment = PostComment::create(['post_id' => $postId, 'user_id' => $userId, 'parent_id' => $parentId, 'content' => trim($content)]);
            $post->increment('replies_count');
            $post->update(['last_activity_at' => now()]);
            if ((int) $post->user_id !== $userId) {
                $this->notifyOnce((int) $post->user_id, $userId, 'community_comment', 'Someone commented on your community post.', '/student/community');
            }
            if ($parent && (int) $parent->user_id !== $userId && (int) $parent->user_id !== (int) $post->user_id) {
                $this->notifyOnce((int) $parent->user_id, $userId, 'community_reply', 'Someone replied to your community comment.', '/student/community');
            }
            return $comment;
        });
    }

    public function getPostComments(int $postId)
    {
        $this->requireCommunityAccess();
        abort_unless(CommunityMessage::whereKey($postId)->where('is_approved', true)->exists(), 404);
        $post = CommunityMessage::whereKey($postId)->where('is_approved', true)->firstOrFail();
        $comments = PostComment::where('post_id', $postId)->whereNull('parent_id')
            ->with(['user:id,name,role', 'replies' => fn ($query) => $query->with('user:id,name,role')->orderBy('created_at')->limit(20)])
            ->orderBy('created_at')->paginate(10);
        $comments->setCollection($comments->getCollection()->map(function (PostComment $comment) use ($post) {
            $public = function (PostComment $item) use ($post): array {
                $hideOwner = $post->is_anonymous && (int) $item->user_id === (int) $post->user_id;
                $roleLabel = match ($item->user?->role) {
                    'counselor' => 'Authorized Counselor',
                    'admin' => 'Guidance Admin',
                    default => null,
                };

                return [
                    'id' => $item->id,
                    'content' => $item->content,
                    'created_at' => optional($item->created_at)->toIso8601String(),
                    'author' => ['name' => $hideOwner ? 'Anonymous student' : (optional($item->user)->name ?? 'Community member'), 'role_label' => $hideOwner ? null : $roleLabel],
                    'can_edit' => (int) $item->user_id === (int) Auth::id(),
                    'can_delete' => (int) $item->user_id === (int) Auth::id() || Auth::user()?->role === 'admin',
                ];
            };
            return $public($comment) + ['replies' => $comment->replies->map($public)->values()->all()];
        }));
        return $comments;
    }

    public function updateComment(int $commentId, int $actorId, string $content): PostComment
    {
        $this->requireCommunityAccess();
        $this->requireActor($actorId);
        $comment = PostComment::lockForUpdate()->findOrFail($commentId);
        abort_unless((int) $comment->user_id === $actorId, 403);
        abort_unless($comment->post()->where('is_approved', true)->exists(), 404);
        $comment->update(['content' => trim($content)]);
        $comment->post()->update(['last_activity_at' => now()]);
        return $comment->fresh();
    }

    public function deleteComment(int $commentId, int $actorId): void
    {
        $this->requireCommunityAccess();
        $this->requireActor($actorId);
        DB::transaction(function () use ($commentId, $actorId) {
            $comment = PostComment::lockForUpdate()->findOrFail($commentId);
            abort_unless((int) $comment->user_id === $actorId || Auth::user()?->role === 'admin', 403);
            $post = CommunityMessage::lockForUpdate()->find($comment->post_id);
            $removedCount = 1 + $comment->replies()->count();
            $comment->delete();
            if ($post) {
                $post->decrement('replies_count', $removedCount);
                $post->update(['last_activity_at' => now()]);
            }
        });
    }
    public function updateMessage(int $messageId, int $userId, array $data): CommunityMessage
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        return DB::transaction(function () use ($messageId, $userId, $data) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            abort_unless((int) $message->user_id === $userId, 403);
            $fromStatus = $message->moderation_status;
            $wasPinned = (bool) $message->is_pinned;
            $this->validateCategoryAndLabel($data['category'], $data['support_label'] ?? null);
            $message->update([
                'title' => $data['title'] ?? null,
                'message' => trim((string) $data['message']),
                'category' => $data['category'],
                'support_label' => $data['support_label'] ?? null,
                'is_approved' => false,
                'moderation_status' => 'pending',
                'approved_at' => null,
                'moderation_notes' => null,
                'last_activity_at' => now(),
            ]);
            $this->recordModerationAudit($message, $userId, null, 'author_edit', $fromStatus, 'pending', $wasPinned, $wasPinned, null);
            return $message->fresh();
        });
    }

    public function deleteMessage(int $messageId, int $userId): void
    {
        $this->requireCommunityAccess();
        $this->requireActor($userId);
        $mediaPath = null;
        $visualMediaPath = null;
        DB::transaction(function () use ($messageId, $userId, &$mediaPath, &$visualMediaPath) {
            $message = CommunityMessage::lockForUpdate()->findOrFail($messageId);
            abort_unless((int) $message->user_id === $userId || Auth::user()?->role === 'admin', 403);
            $fromStatus = $message->moderation_status;
            $wasPinned = (bool) $message->is_pinned;
            $isAdminRemoval = (int) $message->user_id !== $userId;
            $mediaPath = $message->image_path;
            $visualMediaPath = $message->visual_media_path;
            $message->update([
                'is_approved' => false,
                'moderation_status' => 'rejected',
                'moderation_notes' => 'Removed by author',
                'image_path' => null,
                'image_hash' => null,
                'image_width' => null,
                'image_height' => null,
                'last_activity_at' => now(),
            ]);
            $this->recordModerationAudit($message, $userId, $isAdminRemoval ? $userId : null, $isAdminRemoval ? 'admin_remove' : 'author_remove', $fromStatus, 'rejected', $wasPinned, false, $isAdminRemoval ? 'Removed by administrator.' : 'Removed by author.');
        });
        $this->deleteCommunityMedia($mediaPath);
        $this->deleteCommunityMedia($visualMediaPath);
    }

    public function getStats()
    {
        return [
            'total_messages' => $this->publishedMessagesQuery()->count(),
            'pending_moderation' => CommunityMessage::where(function ($q) {
                $q->whereIn('moderation_status', ['pending', 'flagged'])
                    ->orWhere(function ($legacy) {
                        $legacy->where('is_approved', false)->whereNull('moderation_status');
                    });
            })->count(),
            'by_category' => $this->publishedMessagesQuery()->selectRaw('category, COUNT(*) as count')->groupBy('category')->pluck('count', 'category')->toArray(),
            'most_liked' => $this->publishedMessagesQuery()->orderByDesc('likes_count')->take(5)->get(),
        ];
    }

    public function getCategoryStats()
    {
        return $this->publishedMessagesQuery()->selectRaw('category, COUNT(*) as count, SUM(likes_count) as total_likes')->groupBy('category')->get()->map(function ($stat) {
            return ['category' => $stat->category, 'count' => $stat->count, 'total_likes' => $stat->total_likes, 'avg_likes' => $stat->count ? round($stat->total_likes / $stat->count, 2) : 0];
        });
    }

    public function getGuidanceAnnouncements(int $limit = 3)
    {
        return CommunityMessage::query()
            ->where('is_approved', true)
            ->whereHas('user', function ($query) {
                $this->applyEligibleAuthorConstraints($query);
                $query->whereIn('role', ['counselor', 'admin']);
            })
            ->with('user:id,name,role')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 5)))
            ->get();
    }

    /**
     * Tiptap documents are accepted as data, not HTML. This strict normalizer
     * keeps the storage contract small, predictable and safe to render in React.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function normalizeRichContent(array $document, int $minimumLength = 5): array
    {
        $nodeCount = 0;
        $normalized = $this->normalizeRichNode($document, $nodeCount, 0, true);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 16000) {
            throw ValidationException::withMessages(['rich_content' => 'The formatted post is too large.']);
        }
        $plain = trim(preg_replace("/\n{3,}/", "\n\n", $this->richNodeText($normalized)) ?? '');
        if (mb_strlen($plain) < $minimumLength || mb_strlen($plain) > 2000) {
            throw ValidationException::withMessages(['rich_content' => 'Write between ' . $minimumLength . ' and 2,000 readable characters.']);
        }
        return [$normalized, $plain];
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private function normalizeRichNode(array $node, int &$nodeCount, int $depth, bool $isRoot = false): array
    {
        if ($depth > 12 || ++$nodeCount > 300) {
            throw ValidationException::withMessages(['rich_content' => 'The formatted post is too complex.']);
        }
        $type = (string) ($node['type'] ?? '');
        if (!in_array($type, self::RICH_NODE_TYPES, true) || ($isRoot && $type !== 'doc') || (!$isRoot && $type === 'doc')) {
            throw ValidationException::withMessages(['rich_content' => 'This post contains an unsupported formatting element.']);
        }
        if ($type === 'text') {
            $text = (string) ($node['text'] ?? '');
            $text = str_replace("\0", '', $text);
            if ($text === '' || mb_strlen($text) > 1000) {
                throw ValidationException::withMessages(['rich_content' => 'The formatted text is invalid.']);
            }
            $marks = [];
            $providedMarks = $node['marks'] ?? [];
            if (!is_array($providedMarks)) {
                throw ValidationException::withMessages(['rich_content' => 'The formatted text marks are invalid.']);
            }
            foreach ($providedMarks as $mark) {
                $markType = is_array($mark) ? (string) ($mark['type'] ?? '') : '';
                if (!in_array($markType, self::RICH_MARK_TYPES, true)) {
                    throw ValidationException::withMessages(['rich_content' => 'This text style is not allowed.']);
                }
                if ($markType === 'textStyle') {
                    $attrs = $mark['attrs'] ?? [];
                    if (!is_array($attrs)) {
                        throw ValidationException::withMessages(['rich_content' => 'The text style attributes are invalid.']);
                    }
                    $safe = [];
                    foreach ($attrs as $key => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        if (!is_string($value)) {
                            throw ValidationException::withMessages(['rich_content' => 'Text style values must use approved text options.']);
                        }
                        if ($key === 'fontFamily' && in_array($value, self::RICH_FONT_FAMILIES, true)) {
                            $safe['fontFamily'] = $value;
                        } elseif ($key === 'fontSize' && in_array($value, self::RICH_FONT_SIZES, true)) {
                            $safe['fontSize'] = $value;
                        } elseif ($key === 'color' && in_array(strtolower((string) $value), self::RICH_TEXT_COLORS, true)) {
                            $safe['color'] = strtolower((string) $value);
                        } else {
                            throw ValidationException::withMessages(['rich_content' => 'This text style is not approved.']);
                        }
                    }
                    if ($safe !== []) {
                        $marks[] = ['type' => 'textStyle', 'attrs' => $safe];
                    }
                } elseif (!in_array(['type' => $markType], $marks, true)) {
                    $attrs = $mark['attrs'] ?? [];
                    if (is_array($attrs) && collect($attrs)->filter(static fn ($value) => $value !== null && $value !== '')->isNotEmpty()) {
                        throw ValidationException::withMessages(['rich_content' => 'Formatting attributes are not allowed for this text style.']);
                    }
                    $marks[] = ['type' => $markType];
                }
            }
            return array_filter(['type' => 'text', 'text' => $text, 'marks' => $marks], static fn ($value) => $value !== []);
        }
        if ($type === 'hardBreak') {
            return ['type' => 'hardBreak'];
        }
        $attrs = $node['attrs'] ?? [];
        if (!is_array($attrs)) {
            throw ValidationException::withMessages(['rich_content' => 'The formatted post attributes are invalid.']);
        }
        $children = $node['content'] ?? [];
        if (!is_array($children) || ($type !== 'paragraph' && $type !== 'heading' && $type !== 'listItem' && $type !== 'doc' && $children === [])) {
            throw ValidationException::withMessages(['rich_content' => 'The formatted post structure is invalid.']);
        }
        $normalizedChildren = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                throw ValidationException::withMessages(['rich_content' => 'The formatted post structure is invalid.']);
            }
            $normalizedChild = $this->normalizeRichNode($child, $nodeCount, $depth + 1);
            $allowedChildren = match ($type) {
                'doc' => ['paragraph', 'heading', 'bulletList', 'orderedList'],
                'paragraph', 'heading' => ['text', 'hardBreak'],
                'bulletList', 'orderedList' => ['listItem'],
                'listItem' => ['paragraph', 'bulletList', 'orderedList'],
                default => [],
            };
            if (!in_array($normalizedChild['type'] ?? '', $allowedChildren, true)) {
                throw ValidationException::withMessages(['rich_content' => 'The formatted post structure is invalid.']);
            }
            $normalizedChildren[] = $normalizedChild;
        }
        $normalized = ['type' => $type, 'content' => $normalizedChildren];
        if (in_array($type, ['paragraph', 'heading'], true)) {
            $safeAttrs = [];
            foreach ($attrs as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if ($key === 'textAlign' && in_array($value, self::RICH_TEXT_ALIGNMENTS, true)) {
                    $safeAttrs['textAlign'] = $value;
                } elseif ($type === 'heading' && $key === 'level' && in_array((int) $value, [2, 3], true)) {
                    $safeAttrs['level'] = (int) $value;
                } else {
                    throw ValidationException::withMessages(['rich_content' => 'This block style is not approved.']);
                }
            }
            if ($type === 'heading' && !isset($safeAttrs['level'])) {
                $safeAttrs['level'] = 2;
            }
            if ($safeAttrs !== []) {
                $normalized['attrs'] = $safeAttrs;
            }
        } elseif ($type === 'orderedList') {
            $safeAttrs = [];
            foreach ($attrs as $key => $value) {
                if ($value === null || $value === '' || ($key === 'start' && (int) $value === 1)) {
                    continue;
                }
                if ($key === 'start' && filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= 1 && (int) $value <= 1000) {
                    $safeAttrs['start'] = (int) $value;
                } elseif ($key === 'type' && in_array($value, ['1', 'a', 'A', 'i', 'I'], true)) {
                    $safeAttrs['type'] = $value;
                } else {
                    throw ValidationException::withMessages(['rich_content' => 'This list style is not approved.']);
                }
            }
            if ($safeAttrs !== []) {
                $normalized['attrs'] = $safeAttrs;
            }
        } elseif (collect($attrs)->filter(static fn ($value) => $value !== null && $value !== '')->isNotEmpty()) {
            throw ValidationException::withMessages(['rich_content' => 'Attributes are not allowed on this element.']);
        }
        return $normalized;
    }

    /** @param array<string, mixed> $node */
    private function richNodeText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? null) === 'hardBreak') {
            return "\n";
        }
        $content = '';
        foreach (($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $content .= $this->richNodeText($child);
            }
        }
        return in_array(($node['type'] ?? ''), ['paragraph', 'heading', 'listItem'], true)
            ? $content . "\n"
            : $content;
    }

    /** @param array<string, mixed> $style @return array<string, string> */
    private function normalizeRichStyle(array $style): array
    {
        $background = (string) ($style['background'] ?? 'canvas');
        $font = (string) ($style['font'] ?? 'jakarta');
        $size = (string) ($style['size'] ?? 'standard');
        if (!in_array($background, self::RICH_BACKGROUNDS, true) || !in_array($font, self::RICH_FONTS, true) || !in_array($size, self::RICH_SIZES, true)) {
            throw ValidationException::withMessages(['post_style' => 'Choose one of the approved Community themes and type styles.']);
        }
        return compact('background', 'font', 'size');
    }

    /**
     * Decode and re-encode a community image before it enters private storage.
     * The generated WebP contains no source metadata or client filename.
     */
    private function prepareCommunityImage(UploadedFile $image): array
    {
        if (!$image->isValid() || ($image->getSize() ?? 0) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['image' => 'Choose a valid image up to 5 MB.']);
        }

        $realPath = $image->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            throw ValidationException::withMessages(['image' => 'The selected image could not be read.']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($realPath);
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMime, true)) {
            throw ValidationException::withMessages(['image' => 'Only JPG, PNG, and WebP images are supported.']);
        }

        $dimensions = @getimagesize($realPath);
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $type = (int) ($dimensions[2] ?? 0);
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || ($width * $height) > 20000000 || !in_array($type, $allowedTypes, true)) {
            throw ValidationException::withMessages(['image' => 'That image is too large or could not be safely decoded.']);
        }

        $loader = match ($type) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            default => null,
        };
        if (!$loader || !function_exists($loader) || !function_exists('imagewebp')) {
            throw ValidationException::withMessages(['image' => 'Secure image processing is unavailable right now.']);
        }

        $source = @$loader($realPath);
        if (!$source) {
            throw ValidationException::withMessages(['image' => 'The image appears to be corrupted.']);
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        $encoded = imagewebp($canvas, null, 82);
        $binary = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (!$encoded || !$binary) {
            throw ValidationException::withMessages(['image' => 'The image could not be securely processed.']);
        }

        $path = 'community_media/' . bin2hex(random_bytes(20)) . '.webp';
        if (!Storage::disk('local')->put($path, $binary)) {
            throw ValidationException::withMessages(['image' => 'The image could not be saved. Please try again.']);
        }

        return [
            'path' => $path,
            'hash' => hash_file('sha256', $realPath),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function normalizeVisualLayout(array $layout, ?int $sourceWidth = null, ?int $sourceHeight = null): array
    {
        if (array_key_exists('version', $layout)) {
            if ($layout['version'] !== 2) {
                throw ValidationException::withMessages(['visual_layout' => 'This visual composition version is not supported.']);
            }

            return $this->normalizeCanvasComposition($layout, $sourceWidth, $sourceHeight);
        }

        $backgrounds = ['mint', 'ivory', 'forest', 'night', 'sunrise'];
        $background = in_array((string) ($layout['background'] ?? ''), $backgrounds, true)
            ? (string) $layout['background']
            : 'mint';
        $clamp = static fn ($value, float $min, float $max): float => max($min, min($max, is_numeric($value) ? (float) $value : $min));
        $color = strtolower((string) ($layout['color'] ?? '#ffffff'));
        if (!preg_match('/\A#[0-9a-f]{6}\z/i', $color)) {
            $color = in_array($background, ['ivory', 'mint', 'sunrise'], true) ? '#153f32' : '#ffffff';
        }

        return [
            'background' => $background,
            'x' => round($clamp($layout['x'] ?? 50, 8, 92), 2),
            'y' => round($clamp($layout['y'] ?? 50, 12, 88), 2),
            'font_size' => (int) round($clamp($layout['font_size'] ?? 48, 24, 88)),
            'color' => $color,
            'align' => in_array(($layout['align'] ?? 'center'), ['left', 'center', 'right'], true) ? $layout['align'] : 'center',
        ];
    }

    /**
     * Normalize the data-only Konva scene. The client can choose from a small
     * palette and geometry range, but can never submit arbitrary URLs, HTML,
     * CSS, filesystem paths, or renderer instructions.
     */
    private function normalizeCanvasComposition(array $layout, ?int $sourceWidth, ?int $sourceHeight): array
    {
        $backgrounds = ['mint', 'ivory', 'forest', 'night', 'sunrise'];
        $fonts = ['jakarta', 'lora', 'manrope', 'dm-sans', 'fraunces', 'caveat', 'playfair-display'];
        $colors = ['#173e32', '#2f6b4f', '#805000', '#633f7a', '#4b5563', '#111827', '#ffffff'];
        $number = static function (array $item, string $key, float $min, float $max): float {
            $value = $item[$key] ?? null;
            if (!is_numeric($value) || !is_finite((float) $value)) {
                throw ValidationException::withMessages(['visual_layout' => 'The visual composition contains invalid dimensions.']);
            }
            $value = (float) $value;
            if ($value < $min || $value > $max) {
                throw ValidationException::withMessages(['visual_layout' => 'Keep every visual layer inside the supported canvas limits.']);
            }
            return round($value, 2);
        };

        if (($layout['width'] ?? null) !== 1080 || ($layout['height'] ?? null) !== 1350) {
            throw ValidationException::withMessages(['visual_layout' => 'The visual canvas dimensions are not supported.']);
        }
        $background = (string) ($layout['background'] ?? '');
        if (!in_array($background, $backgrounds, true)) {
            throw ValidationException::withMessages(['visual_layout' => 'Choose an approved background.']);
        }
        if (!is_array($layout['layers'] ?? null) || count($layout['layers']) < 1 || count($layout['layers']) > 12) {
            throw ValidationException::withMessages(['visual_layout' => 'A composition must contain between 1 and 12 layers.']);
        }

        $layers = [];
        $ids = [];
        $imageLayers = 0;
        $textCharacters = 0;
        foreach ($layout['layers'] as $layer) {
            if (!is_array($layer)) {
                throw ValidationException::withMessages(['visual_layout' => 'A visual layer could not be read.']);
            }
            $id = (string) ($layer['id'] ?? '');
            $type = (string) ($layer['type'] ?? '');
            if (!preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D', $id) || isset($ids[$id])) {
                throw ValidationException::withMessages(['visual_layout' => 'Visual layer identifiers must be unique and valid.']);
            }
            $ids[$id] = true;
            if (!in_array($type, ['image', 'text', 'sticker'], true)) {
                throw ValidationException::withMessages(['visual_layout' => 'This visual layer type is not supported.']);
            }

            $base = [
                'id' => $id,
                'type' => $type,
                'x' => $number($layer, 'x', -1080, 2160),
                'y' => $number($layer, 'y', -1350, 2700),
                'width' => $number($layer, 'width', 32, 2160),
                'height' => $number($layer, 'height', 32, 2700),
                'rotation' => $number($layer, 'rotation', -180, 180),
                'opacity' => $number($layer, 'opacity', 0.1, 1),
            ];

            if ($type === 'image') {
                if (++$imageLayers > 1) {
                    throw ValidationException::withMessages(['visual_layout' => 'A post can contain one private photo layer.']);
                }
                if (!$sourceWidth || !$sourceHeight || $sourceWidth < 1 || $sourceHeight < 1) {
                    throw ValidationException::withMessages(['image' => 'Add or retain the private photo used by this composition.']);
                }
                $layers[] = $base;
                continue;
            }

            if ($type === 'text') {
                $text = trim(str_replace("\0", '', (string) ($layer['text'] ?? '')));
                $font = (string) ($layer['font'] ?? '');
                $color = strtolower((string) ($layer['color'] ?? ''));
                $align = (string) ($layer['align'] ?? 'left');
                $fontSize = filter_var($layer['font_size'] ?? null, FILTER_VALIDATE_INT);
                if ($text === '' || mb_strlen($text) > 250 || preg_match('/<\/?[a-z][^>]*>/i', $text) === 1 || !in_array($font, $fonts, true) || !in_array($color, $colors, true) || !in_array($align, ['left', 'center', 'right'], true) || $fontSize === false || $fontSize < 18 || $fontSize > 128) {
                    throw ValidationException::withMessages(['visual_layout' => 'A text layer contains unsupported text or styling.']);
                }
                $textCharacters += mb_strlen($text);
                $backgroundColor = $layer['background_color'] ?? null;
                if ($backgroundColor !== null && !in_array(strtolower((string) $backgroundColor), ['#103f32', '#ffffff', '#e8bb45', '#172d39'], true)) {
                    throw ValidationException::withMessages(['visual_layout' => 'Choose an approved text background.']);
                }
                $layers[] = $base + [
                    'text' => $text,
                    'font' => $font,
                    'font_size' => $fontSize,
                    'color' => $color,
                    'bold' => (bool) ($layer['bold'] ?? false),
                    'italic' => (bool) ($layer['italic'] ?? false),
                    'align' => $align,
                    'background_color' => $backgroundColor ? strtolower((string) $backgroundColor) : null,
                ];
                continue;
            }

            $emoji = (string) ($layer['emoji'] ?? '');
            $emojiSequence = preg_match('/\A[\p{So}\p{Sk}\p{Mn}\p{Cf}\p{Nd}\x{FE0F}\x{200D}\x{20E3}]+\z/u', $emoji) === 1;
            $containsEmoji = preg_match('/[\p{So}\p{Sk}]/u', $emoji) === 1 || str_contains($emoji, "\u{20E3}");
            if (!$emojiSequence || !$containsEmoji || mb_strlen($emoji) > 16) {
                throw ValidationException::withMessages(['visual_layout' => 'Choose a sticker from the approved collection.']);
            }
            $stickerPath = $this->twemojiPngPath($emoji);
            if (!$stickerPath || !is_file($stickerPath) || @getimagesize($stickerPath) === false) {
                throw ValidationException::withMessages(['visual_layout' => 'This sticker is not available in the local approved artwork set. Choose another emoji.']);
            }
            if ($base['width'] > 512 || $base['height'] > 512) {
                throw ValidationException::withMessages(['visual_layout' => 'Keep sticker layers within the supported size.']);
            }
            if (abs($base['width'] - $base['height']) > max($base['width'], $base['height']) * 0.02) {
                throw ValidationException::withMessages(['visual_layout' => 'Sticker layers must keep their original proportions.']);
            }
            $layers[] = $base + ['emoji' => $emoji];
        }

        if ($textCharacters > 1500) {
            throw ValidationException::withMessages(['visual_layout' => 'The combined visual text is too long.']);
        }
        if (($imageLayers === 1) !== ($sourceWidth !== null && $sourceHeight !== null)) {
            throw ValidationException::withMessages(['visual_layout' => 'The photo layer and private photo upload must match.']);
        }

        return ['version' => 2, 'width' => 1080, 'height' => 1350, 'background' => $background, 'layers' => $layers];
    }

    private function compositionText(array $layout, string $fallback): string
    {
        if (($layout['version'] ?? null) !== 2) {
            return trim($fallback);
        }
        $parts = [];
        foreach ($layout['layers'] as $layer) {
            if (($layer['type'] ?? null) === 'text' && trim((string) ($layer['text'] ?? '')) !== '') {
                $parts[] = trim((string) $layer['text']);
            } elseif (($layer['type'] ?? null) === 'sticker') {
                $parts[] = (string) $layer['emoji'];
            }
        }
        return trim(implode("\n", $parts));
    }

    /** Resolve Unicode to a bundled Twemoji filename; no client URL or path is accepted. */
    private function twemojiPngPath(string $emoji): ?string
    {
        if (!function_exists('mb_ord')) {
            return null;
        }
        $characters = preg_split('//u', $emoji, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || $characters === [] || count($characters) > 16) {
            return null;
        }
        $points = [];
        foreach ($characters as $character) {
            $point = mb_ord($character, 'UTF-8');
            if (!is_int($point) || $point < 1) {
                return null;
            }
            $points[] = dechex($point);
        }
        $filename = implode('-', $points);
        if (!preg_match('/\A[a-f0-9]+(?:-[a-f0-9]+)*\z/D', $filename)) {
            return null;
        }
        return public_path('assets/emoji/twemoji/64/'.$filename.'.png');
    }

    private function renderVisualComposition(?string $sourcePath, array $layout, string $text): string
    {
        if (($layout['version'] ?? null) === 2) {
            return $this->renderLayeredVisualComposition($sourcePath, $layout);
        }

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp') || !function_exists('imagettftext')) {
            throw ValidationException::withMessages(['visual_text' => 'Visual composition is unavailable right now.']);
        }

        $width = 1080;
        $height = 1080;
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $backgrounds = [
            'mint' => '#e9f3eb',
            'ivory' => '#f7f0df',
            'forest' => '#153f32',
            'night' => '#0f1720',
            'sunrise' => '#d9a96b',
        ];

        $source = null;
        if ($sourcePath !== null && $sourcePath !== '') {
            if (!preg_match('/\Acommunity_media\/[a-f0-9]{40}\.webp\z/D', $sourcePath) || !Storage::disk('local')->exists($sourcePath)) {
                throw ValidationException::withMessages(['image' => 'The source image is not available for editing.']);
            }
            $source = @imagecreatefromstring(Storage::disk('local')->get($sourcePath));
        }

        if ($source) {
            $sourceWidth = max(1, imagesx($source));
            $sourceHeight = max(1, imagesy($source));
            $scale = max($width / $sourceWidth, $height / $sourceHeight);
            $drawWidth = (int) ceil($sourceWidth * $scale);
            $drawHeight = (int) ceil($sourceHeight * $scale);
            $drawX = (int) floor(($width - $drawWidth) / 2);
            $drawY = (int) floor(($height - $drawHeight) / 2);
            imagecopyresampled($canvas, $source, $drawX, $drawY, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);
            imagedestroy($source);
        } else {
            [$red, $green, $blue] = $this->visualHexColor($backgrounds[$layout['background']]);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $red, $green, $blue));
        }

        $font = $this->visualFontPath();
        $fontSize = max(10, min(88, (int) $layout['font_size']));
        // Keep a small reserve for the drop shadow as well as the actual glyph bearings.
        $safePadding = 72;
        $safeLeft = $safePadding;
        $safeTop = $safePadding;
        $safeRight = $width - $safePadding - 4;
        $safeBottom = $height - $safePadding - 4;
        $maxTextWidth = $safeRight - $safeLeft;
        $maxTextHeight = $safeBottom - $safeTop;

        // Fit by actual glyph bounds, not only an approximate line-height calculation.
        do {
            $lines = $this->wrapVisualText($text, $font, $fontSize, $maxTextWidth);
            $lineHeight = max(12, (int) round($fontSize * 1.24));
            $metrics = $this->visualTextMetrics($font, $fontSize, $lines, $lineHeight, $layout['align']);
            $blockHeight = $metrics['height'];
            if ($blockHeight <= $maxTextHeight || $fontSize <= 10) {
                break;
            }
            $fontSize = max(10, $fontSize - 2);
        } while (true);

        // A pathological payload still gets a bounded, readable final line.
        if ($blockHeight > $maxTextHeight) {
            $maxLines = count($lines);
            do {
                $candidate = array_slice($lines, 0, max(1, $maxLines));
                $lastIndex = count($candidate) - 1;
                $last = rtrim((string) ($candidate[$lastIndex] ?? ''), '. ');
                while ($last !== '' && $this->visualTextWidth($font, $fontSize, $last . '...') > $maxTextWidth) {
                    $last = (string) preg_replace('/.$/u', '', $last);
                }
                $candidate[$lastIndex] = ($last !== '' ? $last : '') . '...';
                $candidateMetrics = $this->visualTextMetrics($font, $fontSize, $candidate, $lineHeight, $layout['align']);
                if ($candidateMetrics['height'] <= $maxTextHeight || $maxLines === 1) {
                    $lines = $candidate;
                    $metrics = $candidateMetrics;
                    break;
                }
                $maxLines--;
            } while ($maxLines > 0);
        }

        $blockWidth = $metrics['width'];
        $blockHeight = $metrics['height'];
        $centerX = (float) $width * ((float) $layout['x'] / 100);
        $centerY = (float) $height * ((float) $layout['y'] / 100);
        $left = max((float) $safeLeft, min((float) ($safeRight - $blockWidth), $centerX - ($blockWidth / 2)));
        $top = max((float) $safeTop, min((float) ($safeBottom - $blockHeight), $centerY - ($blockHeight / 2)));
        [$red, $green, $blue] = $this->visualHexColor($layout['color']);
        $ink = imagecolorallocatealpha($canvas, $red, $green, $blue, 0);
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 68);

        foreach ($lines as $index => $line) {
            $x = $left + ($metrics['origins'][$index] ?? 0);
            $baseline = $top - $metrics['min_y'] + ($metrics['offsets'][$index] ?? 0);
            imagettftext($canvas, $fontSize, 0, (int) round($x + 3), (int) round($baseline + 3), $shadow, $font, $line);
            imagettftext($canvas, $fontSize, 0, (int) round($x), (int) round($baseline), $ink, $font, $line);
        }

        ob_start();
        $encoded = imagewebp($canvas, null, 88);
        $binary = ob_get_clean();
        imagedestroy($canvas);
        if (!$encoded || !$binary) {
            throw ValidationException::withMessages(['visual_text' => 'The visual composition could not be rendered.']);
        }
        return $binary;
    }

    /** Render a bounded server-side feed fallback from the validated scene JSON. */
    private function renderLayeredVisualComposition(?string $sourcePath, array $layout): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp') || !function_exists('imagettftext')) {
            throw ValidationException::withMessages(['visual_layout' => 'Visual composition is unavailable right now.']);
        }
        $width = 1080;
        $height = 1350;
        $backgrounds = [
            'mint' => '#e9f3eb', 'ivory' => '#f7f0df', 'forest' => '#153f32',
            'night' => '#172d39', 'sunrise' => '#f4d7a1',
        ];
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        [$red, $green, $blue] = $this->visualHexColor($backgrounds[$layout['background']]);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $red, $green, $blue));

        foreach ($layout['layers'] as $layer) {
            if ($layer['type'] === 'image') {
                if (!$sourcePath || !preg_match('/\Acommunity_media\/[a-f0-9]{40}\.webp\z/D', $sourcePath) || !Storage::disk('local')->exists($sourcePath)) {
                    imagedestroy($canvas);
                    throw ValidationException::withMessages(['image' => 'The private source photo is unavailable.']);
                }
                $source = @imagecreatefromstring(Storage::disk('local')->get($sourcePath));
                if (!$source) {
                    imagedestroy($canvas);
                    throw ValidationException::withMessages(['image' => 'The private source photo could not be decoded.']);
                }
                $layerWidth = (int) round($layer['width']);
                $layerHeight = (int) round($layer['height']);
                $scaled = imagecreatetruecolor($layerWidth, $layerHeight);
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
                imagefill($scaled, 0, 0, $transparent);
                imagecopyresampled($scaled, $source, 0, 0, 0, 0, $layerWidth, $layerHeight, imagesx($source), imagesy($source));
                imagedestroy($source);

                $drawn = $scaled;
                if (abs($layer['rotation']) > 0.01 && function_exists('imagerotate')) {
                    $rotated = imagerotate($scaled, -$layer['rotation'], $transparent);
                    if ($rotated) {
                        $drawn = $rotated;
                        imagedestroy($scaled);
                    }
                }
                $drawX = (int) round($layer['x'] + ($layerWidth - imagesx($drawn)) / 2);
                $drawY = (int) round($layer['y'] + ($layerHeight - imagesy($drawn)) / 2);
                imagecopy($canvas, $drawn, $drawX, $drawY, 0, 0, imagesx($drawn), imagesy($drawn));
                imagedestroy($drawn);
                continue;
            }

            if ($layer['type'] === 'sticker') {
                $stickerPath = $this->twemojiPngPath((string) $layer['emoji']);
                $sourceSticker = $stickerPath && is_file($stickerPath) ? @imagecreatefrompng($stickerPath) : false;
                if (!$sourceSticker) {
                    // Historical data from before local artwork was enforced is
                    // represented honestly instead of silently painting a blank.
                    $font = $this->visualLayerFontPath('manrope', true, false);
                    $label = 'Sticker unavailable';
                    $size = 20;
                    $color = imagecolorallocate($canvas, 83, 107, 93);
                    imagettftext($canvas, $size, 0, (int) round($layer['x']), (int) round($layer['y'] + $size + 8), $color, $font, $label);
                    continue;
                }
                $layerWidth = (int) round($layer['width']);
                $layerHeight = (int) round($layer['height']);
                $sticker = imagecreatetruecolor($layerWidth, $layerHeight);
                imagealphablending($sticker, false);
                imagesavealpha($sticker, true);
                $transparent = imagecolorallocatealpha($sticker, 0, 0, 0, 127);
                imagefill($sticker, 0, 0, $transparent);
                imagecopyresampled($sticker, $sourceSticker, 0, 0, 0, 0, $layerWidth, $layerHeight, imagesx($sourceSticker), imagesy($sourceSticker));
                imagedestroy($sourceSticker);

                if ($layer['opacity'] < 0.999) {
                    for ($pixelY = 0; $pixelY < $layerHeight; $pixelY++) {
                        for ($pixelX = 0; $pixelX < $layerWidth; $pixelX++) {
                            $rgba = imagecolorsforindex($sticker, imagecolorat($sticker, $pixelX, $pixelY));
                            $alpha = (int) round(127 - ((127 - $rgba['alpha']) * $layer['opacity']));
                            $color = imagecolorallocatealpha($sticker, $rgba['red'], $rgba['green'], $rgba['blue'], max(0, min(127, $alpha)));
                            imagesetpixel($sticker, $pixelX, $pixelY, $color);
                        }
                    }
                }
                $drawn = $sticker;
                if (abs($layer['rotation']) > 0.01 && function_exists('imagerotate')) {
                    $rotated = imagerotate($sticker, -$layer['rotation'], $transparent);
                    if ($rotated) {
                        imagesavealpha($rotated, true);
                        $drawn = $rotated;
                        imagedestroy($sticker);
                    }
                }
                imagealphablending($canvas, true);
                imagecopy($canvas, $drawn, (int) round($layer['x'] + ($layerWidth - imagesx($drawn)) / 2), (int) round($layer['y'] + ($layerHeight - imagesy($drawn)) / 2), 0, 0, imagesx($drawn), imagesy($drawn));
                imagedestroy($drawn);
                continue;
            }

            $font = $this->visualLayerFontPath($layer['font'], $layer['bold'], $layer['italic']);
            $fontSize = (int) $layer['font_size'];
            $textLines = $this->wrapVisualText($layer['text'], $font, $fontSize, (int) $layer['width']);
            $lineHeight = max(18, (int) round($fontSize * 1.25));
            $x = (int) round($layer['x']);
            $y = (int) round($layer['y']);
            if ($layer['background_color']) {
                [$br, $bg, $bb] = $this->visualHexColor($layer['background_color']);
                $padding = 18;
                imagefilledrectangle($canvas, $x - $padding, $y - $padding, $x + (int) $layer['width'] + $padding, $y + count($textLines) * $lineHeight + $padding, imagecolorallocatealpha($canvas, $br, $bg, $bb, 18));
            }
            [$tr, $tg, $tb] = $this->visualHexColor($layer['color']);
            $ink = imagecolorallocatealpha($canvas, $tr, $tg, $tb, (int) round((1 - $layer['opacity']) * 127));
            foreach ($textLines as $index => $line) {
                $lineWidth = $this->visualTextWidth($font, $fontSize, $line);
                $offset = match ($layer['align']) {
                    'center' => ((int) $layer['width'] - $lineWidth) / 2,
                    'right' => (int) $layer['width'] - $lineWidth,
                    default => 0,
                };
                imagettftext($canvas, $fontSize, $layer['rotation'], (int) round($x + $offset), $y + $fontSize + ($index * $lineHeight), $ink, $font, $line);
            }
        }

        ob_start();
        $encoded = imagewebp($canvas, null, 88);
        $binary = ob_get_clean();
        imagedestroy($canvas);
        if (!$encoded || !$binary) {
            throw ValidationException::withMessages(['visual_layout' => 'The visual composition could not be rendered.']);
        }
        return $binary;
    }

    private function visualLayerFontPath(string $font, bool $bold, bool $italic): string
    {
        $candidates = [];
        if (in_array($font, ['lora', 'fraunces', 'playfair-display'], true)) {
            $candidates[] = $bold ? 'C:\\Windows\\Fonts\\georgiab.ttf' : 'C:\\Windows\\Fonts\\georgia.ttf';
        } elseif ($font === 'caveat') {
            $candidates[] = 'C:\\Windows\\Fonts\\comic.ttf';
        } else {
            $candidates[] = $bold ? 'C:\\Windows\\Fonts\\arialbd.ttf' : 'C:\\Windows\\Fonts\\arial.ttf';
        }
        $candidates[] = $this->visualFontPath();
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return $this->visualFontPath();
    }

    private function visualEmojiFontPath(): string
    {
        foreach (['C:\\Windows\\Fonts\\seguiemj.ttf', 'C:\\Windows\\Fonts\\segoeui.ttf'] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }
        return $this->visualFontPath();
    }

    /**
     * Measure the actual glyph bounds for every wrapped line. Bearings and
     * ascent/descent are included so alignment and clamping match the canvas.
     */
    private function visualTextMetrics(string $font, int $fontSize, array $lines, int $lineHeight, string $align): array
    {
        $boxes = [];
        $offsets = [];
        $minY = PHP_INT_MAX;
        $maxY = PHP_INT_MIN;
        $blockWidth = 1;

        foreach ($lines ?: [''] as $index => $line) {
            $box = imagettfbbox($fontSize, 0, $font, (string) $line);
            $lineMinX = min($box[0], $box[2], $box[4], $box[6]);
            $lineMaxX = max($box[0], $box[2], $box[4], $box[6]);
            $lineMinY = min($box[1], $box[3], $box[5], $box[7]);
            $lineMaxY = max($box[1], $box[3], $box[5], $box[7]);
            $offset = ($index + 1) * $lineHeight;
            $offsets[] = $offset;
            $minY = min($minY, $offset + $lineMinY);
            $maxY = max($maxY, $offset + $lineMaxY);
            $lineWidth = max(1, $lineMaxX - $lineMinX);
            $blockWidth = max($blockWidth, $lineWidth);
            $boxes[] = ['min_x' => $lineMinX, 'width' => $lineWidth];
        }

        $origins = [];
        foreach ($boxes as $box) {
            $desiredLeft = match ($align) {
                'left' => 0,
                'right' => $blockWidth - $box['width'],
                default => ($blockWidth - $box['width']) / 2,
            };
            $origins[] = $desiredLeft - $box['min_x'];
        }

        return [
            'width' => $blockWidth,
            'height' => max(1, $maxY - $minY),
            'min_y' => $minY === PHP_INT_MAX ? 0 : $minY,
            'origins' => $origins,
            'offsets' => $offsets,
        ];
    }

    private function wrapVisualText(string $text, string $font, int $fontSize, int $maxWidth): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paragraphs = explode("\n", $normalized);
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $line = '';
            $words = preg_split('/\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($words as $word) {
                foreach ($this->splitVisualToken($word, $font, $fontSize, $maxWidth) as $chunk) {
                    $candidate = $line === '' ? $chunk : $line . ' ' . $chunk;
                    if ($line !== '' && $this->visualTextWidth($font, $fontSize, $candidate) > $maxWidth) {
                        $lines[] = $line;
                        $line = $chunk;
                    } else {
                        $line = $candidate;
                    }
                }
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines ?: [''];
    }

    private function splitVisualToken(string $token, string $font, int $fontSize, int $maxWidth): array
    {
        if ($this->visualTextWidth($font, $fontSize, $token) <= $maxWidth) {
            return [$token];
        }

        $chunks = [];
        $chunk = '';
        foreach (preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $candidate = $chunk . $character;
            if ($chunk !== '' && $this->visualTextWidth($font, $fontSize, $candidate) > $maxWidth) {
                $chunks[] = $chunk;
                $chunk = $character;
            } else {
                $chunk = $candidate;
            }
        }
        if ($chunk !== '') {
            $chunks[] = $chunk;
        }
        return $chunks ?: [''];
    }

    private function visualTextWidth(string $font, int $fontSize, string $text): int
    {
        if ($text === '') {
            return 0;
        }
        $box = imagettfbbox($fontSize, 0, $font, $text);
        return max(0, (int) ceil(abs((int) ($box[2] - $box[0]))));
    }
    private function visualFontPath(): string
    {
        foreach ([
            resource_path('fonts/Inter-Regular.ttf'),
            'C:\\Windows\\Fonts\\segoeui.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }
        throw ValidationException::withMessages(['visual_text' => 'A compatible visual font is unavailable.']);
    }

    private function visualHexColor(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    private function storeCommunityMedia(string $binary, string $directory): string
    {
        $path = trim($directory, '/') . '/' . bin2hex(random_bytes(20)) . '.webp';
        if (!Storage::disk('local')->put($path, $binary)) {
            throw ValidationException::withMessages(['image' => 'The visual composition could not be saved.']);
        }
        return $path;
    }
    private function deleteCommunityMedia(?string $path): void
    {
        if ($path && preg_match('/\\Acommunity_(?:media|visuals)\\/[a-f0-9]{40}\\.webp\\z/D', $path)) {
            Storage::disk('local')->delete($path);
        }
    }
    /**
     * Normal student posts are published immediately. Explicit severe/critical
     * safety signals are held for moderator review; ordinary sadness, stress,
     * and support-seeking language remain publishable.
     */
    private function moderationDecision(string $content, bool $visualMedia = false): array
    {
        if ($content === '') {
            return $visualMedia
                ? ['approved' => false, 'status' => 'pending', 'notes' => 'Visual media requires moderator review.']
                : ['approved' => true, 'status' => 'approved', 'notes' => null];
        }

        $screen = app(RiskPhraseScreen::class)->screen($content);
        if (in_array($screen['level'] ?? 'none', ['severe', 'critical'], true)) {
            $rules = implode(', ', array_slice($screen['rules'] ?? [], 0, 5));
            return [
                'approved' => false,
                'status' => 'flagged',
                'notes' => 'Automatic safety hold' . ($rules !== '' ? ': ' . $rules : '.'),
            ];
        }

        if ($visualMedia) {
            return ['approved' => false, 'status' => 'pending', 'notes' => 'Visual media requires moderator review.'];
        }

        return ['approved' => true, 'status' => 'approved', 'notes' => null];
    }
    private function validateCategoryAndLabel(string $category, ?string $supportLabel): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => 'Choose a valid community category.']);
        }
        if ($supportLabel !== null && !in_array($supportLabel, self::SUPPORT_LABELS, true)) {
            throw ValidationException::withMessages(['support_label' => 'Choose a valid support request label.']);
        }
    }

    private function ensureNoBlockedInteraction(int $firstUserId, int $secondUserId): void
    {
        if ($firstUserId !== $secondUserId && $this->isBlocked($firstUserId, $secondUserId)) {
            abort(403, 'This interaction is unavailable because one participant is blocked.');
        }
    }

    private function requireCommunityAccess(): void
    {
        $user = Auth::user();
        app(CommunityVerificationService::class)->requireEligibleParticipant($user);
        if ($user->isStudent() && !app(PrivacyService::class)->canUseCommunity($user->id)) {
            throw new AuthorizationException('Community participation requires current, purpose-specific consent.');
        }
    }

    private function studentRequestedAnonymity(bool $requested): bool
    {
        $user = Auth::user();
        return $user?->role === 'student' && $requested;
    }

    private function requireActor(int $userId): void
    {
        abort_unless((int) Auth::id() === $userId, 403, 'The community action must belong to the authenticated account.');
    }

    private function publishedMessagesQuery()
    {
        return CommunityMessage::query()
            ->where('is_approved', true)
            ->whereHas('user', fn ($query) => $this->applyEligibleAuthorConstraints($query));
    }

    private function applyEligibleAuthorConstraints($query): void
    {
        $query->whereIn('role', ['student', 'counselor', 'admin'])
            ->where('is_active', true)
            ->where(function ($status) {
                $status->where('approval_status', 'approved')->orWhereNull('approval_status');
            })
            ->where(function ($onboarding) {
                $onboarding->where('owner_onboarding_required', false)->orWhereNull('owner_onboarding_required');
            });
    }

    private function requireCommunityModerator(?int $moderatorId): void
    {
        $moderator = Auth::user();
        abort_unless(
            $moderator?->role === 'admin'
                && (int) Auth::id() === (int) $moderatorId
                && app(CommunityVerificationService::class)->isEligibleParticipant($moderator),
            403,
            'An active, approved administrator is required to moderate Community posts.'
        );
    }

    private function recordModerationAudit(CommunityMessage $message, ?int $actorId, ?int $moderatorId, string $action, ?string $fromStatus, ?string $toStatus, ?bool $fromPinned, ?bool $toPinned, ?string $notes): void
    {
        CommunityModerationAudit::create([
            'community_message_id' => $message->id,
            'actor_user_id' => $actorId,
            'moderator_id' => $moderatorId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_pinned' => $fromPinned,
            'to_pinned' => $toPinned,
            'notes' => $notes,
        ]);
    }

    private function notifyOnce(int $userId, int $fromUserId, string $type, string $message, string $link): void
    {
        $recipient = User::find($userId);
        if (!$recipient || !app(CommunityVerificationService::class)->isEligibleParticipant($recipient)
            || ($recipient->isStudent() && !app(PrivacyService::class)->canUseCommunity($userId))) {
            return;
        }
        $duplicate = Notification::where('user_id', $userId)->where('type', $type)->where('link', $link)->where('message', $message)->where('created_at', '>=', now()->subMinutes(2))->exists();
        if (!$duplicate) {
            Notification::create(['user_id' => $userId, 'from_user_id' => $fromUserId, 'type' => $type, 'title' => 'Community activity', 'message' => $message, 'link' => $link, 'is_read' => false, 'priority' => 'normal']);
        }
    }
}
