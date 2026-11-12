<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use App\Models\EmotionalEntry;
use App\Models\CounselorMessage;
use App\Models\MoodCheckin;
use App\Models\CopingStrategy;
use App\Models\ReflectionPrompt;
use App\Services\MessageDeletionService;
use App\Services\AppointmentLifecycleService;
use App\Models\WellnessReminder;
use App\Models\PrivacySetting;
use App\Models\CommunityMessage;
use App\Models\Resource;
use App\Models\EmergencyHotline;
use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PostComment;
use App\Models\ForumThread;
use App\Models\ForumActivityLog;
use App\Services\EmotionDetectionService;
use App\Services\EmotionTaxonomy;
use App\Services\CrisisDetectionService;
use App\Services\MoodCheckInService;
use App\Services\WellbeingService;
use App\Services\CopingToolkitService;
use App\Services\ReflectionService;
use App\Services\WellnessReminderService;
use App\Services\PrivacyService;
use App\Services\CommunityService;
use App\Services\WellnessResourceSearchService;
use App\Services\SocialPlatformService;
use App\Services\DiscussionForumService;
use App\Services\InternalMemoryAuthClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    protected $emotionService;

    public function __construct(EmotionDetectionService $emotionService)
    {
        $this->emotionService = $emotionService;
    }

    /**
     * Legacy dashboard() method
     * Kept for backward compatibility - redirects to insights
     */
    public function dashboard()
    {
        return redirect()->route('student.insights');
    }
    
    public function journal()
    {
        $user = Auth::user();
        
        // Get all entries for statistics
        $allEntries = EmotionalEntry::where('user_id', $user->id)->get();
        
        // Total entries
        $totalEntries = $allEntries->count();
        
        // Entries this week
        $thisWeek = EmotionalEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
        
        // Most common emotion
        $emotionCounts = $allEntries->groupBy('detected_emotion')
            ->map->count()
            ->sortDesc();
        $mostCommonEmotion = $emotionCounts->keys()->first() ?? 'None';
        $mostCommonEmotion = ucfirst($mostCommonEmotion);
        
        // Calculate streak (consecutive days with entries)
        $streak = 0;
        $currentDate = now()->startOfDay();
        while (true) {
            $hasEntry = EmotionalEntry::where('user_id', $user->id)
                ->whereDate('created_at', $currentDate)
                ->exists();
            
            if ($hasEntry) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
            
            if ($streak > 365) break; // Safety limit
        }
        
        // Emotion distribution
        $emotionCounts = [
            'happy' => $allEntries->where('detected_emotion', 'happy')->count(),
            'sad' => $allEntries->where('detected_emotion', 'sad')->count(),
            'anxious' => $allEntries->where('detected_emotion', 'anxious')->count(),
            'angry' => $allEntries->where('detected_emotion', 'angry')->count(),
            'neutral' => $allEntries->where('detected_emotion', 'neutral')->count(),
        ];
        
        // Recent entries
        $recentEntries = EmotionalEntry::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        return view('student.journal', compact(
            'totalEntries',
            'thisWeek',
            'mostCommonEmotion',
            'streak',
            'emotionCounts',
            'recentEntries'
        ));
    }

    public function createEntry()
    {
        return view('student.create-entry');
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'entry_text' => 'required|string|min:1|max:5000',
            'entry_title' => 'nullable|string|max:120',
            'mood_hint' => 'nullable|in:happy,sad,anxious,angry,neutral',
            'is_anonymous' => 'boolean',
            'session_id' => 'nullable|exists:ai_conversation_sessions,id',
            'conversation_history' => 'nullable|array',
        ], [
            'entry_text.required' => 'Please enter your message before sending.',
            'entry_text.max' => 'Message is too long. Please keep it under 5000 characters.',
        ]);

        try {
            // Get or create session
            $sessionId = $request->session_id;
            if (!$sessionId) {
                $session = \App\Models\AIConversationSession::where('user_id', Auth::id())
                    ->latest('last_message_at')
                    ->first();
                    
                if (!$session) {
                    $session = \App\Models\AIConversationSession::create([
                        'user_id' => Auth::id(),
                        'title' => 'New Conversation',
                        'last_message_at' => now(),
                    ]);
                }
                $sessionId = $session->id;
            } else {
                $session = \App\Models\AIConversationSession::whereKey($sessionId)
                    ->where('user_id', Auth::id())
                    ->firstOrFail();
            }
            
            // Use conversation history from frontend if provided, otherwise fetch from database
            $conversationHistory = $request->input('conversation_history', []);
            
            if (empty($conversationHistory)) {
                // Fallback: Get recent conversation history (last 5 entries from this session)
                $conversationHistory = EmotionalEntry::where('user_id', Auth::id())
                    ->where('session_id', $sessionId)
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get()
                    ->reverse()
                    ->map(function($entry) {
                        return [
                            'user' => $entry->entry_text,
                            'ai' => strip_tags($entry->ai_response),
                            'emotion' => $entry->detected_emotion,
                        ];
                    })
                    ->toArray();
            }

            // Use ML-based comprehensive analysis
            $analysis = $this->emotionService->detectEmotion($request->entry_text);
            
            // Get user's AI preference mode
            $privacySetting = \App\Models\PrivacySetting::where('user_id', Auth::id())->first();
            $aiPreferenceMode = $privacySetting ? $privacySetting->ai_preference_mode : 'companion';
            
            // Try to get ML-generated response from unified_ml_api (unified AI system)
            $aiResponse = $this->getMLResponse($request->entry_text, Auth::id(), $conversationHistory, $aiPreferenceMode);
            
            // If ML API fails, fall back to template-based responses
            if (!$aiResponse) {
                $aiResponse = $this->emotionService->generateResponseWithContext(
                    $request->entry_text,
                    $analysis['emotion'],
                    $analysis['confidence'],
                    $conversationHistory
                );
            }

            $entryTitle = trim((string) $request->input('entry_title', ''));
            $moodHint = $request->input('mood_hint');
            $entryContext = array_filter([
                'title' => $entryTitle !== '' ? $entryTitle : null,
                'mood_hint' => $moodHint ?: null,
            ]);

            $taxonomyMetadata = app(EmotionTaxonomy::class)->metadata($analysis);

            $entry = EmotionalEntry::create(array_merge([
                'user_id' => Auth::id(),
                'session_id' => $sessionId,
                'entry_text' => $request->entry_text,
                'detected_emotion' => $analysis['emotion'],
                'confidence_score' => ($analysis['confidence'] ?? 0.5) * 100,
                'crisis_level' => $analysis['crisis_level'] ?? 'none',
                'needs_counselor' => $analysis['needs_counselor'] ?? false,
                'detected_contexts' => json_encode($analysis['contexts'] ?? []),
                'conversation_context' => $entryContext ?: null,
                'ai_response' => $aiResponse,
                'is_anonymous' => $request->boolean('is_anonymous', true),
            ], $taxonomyMetadata));
            
            // Update session
            $session->update(['last_message_at' => now()]);
            
            // Generate title if this is the first message
            if ($session->entries()->count() === 1) {
                $session->generateTitle();
            }

            // Store ML prediction for training
            $trainingService = app(\App\Services\MLTrainingService::class);
            $trainingService->storePrediction($entry, $analysis);

            // Persist the consolidated emotional score on the entry itself.
            $emotionScorerService = app(\App\Services\EmotionScorerService::class);
            $emotionScorerService->scoreJournalEntry($entry->id, $request->entry_text, Auth::id(), $analysis);

            // Optional wellbeing analytics must not invalidate an already-persisted entry.
            try {
                $calculator = app(\App\Services\WellbeingScoreCalculator::class);
                $metrics = $calculator->calculateWellbeing(Auth::id(), $analysis, $analysis);
                $progress = $calculator->saveMetrics(Auth::id(), $metrics);
                $progress->increment('entries_count');
            } catch (\Throwable $analyticsError) {
                \Log::warning('Journal wellbeing analytics failed', [
                    'user_id' => Auth::id(),
                    'entry_id' => $entry->id,
                    'error' => $analyticsError->getMessage(),
                ]);
            }

            // Handle crisis detection and counselor assignment
            if ($analysis['needs_counselor'] ?? false) {
                $crisisService = app(CrisisDetectionService::class);
                $alert = $crisisService->handleCrisisDetection(
                    Auth::id(),
                    $analysis,
                    $request->entry_text,
                    $aiResponse
                );
            }

            // Check if this is an AJAX request
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully!',
                    'data' => [
                        'entry' => [
                            'id' => $entry->id,
                            'entry_text' => $entry->entry_text,
                            'detected_emotion' => $entry->detected_emotion,
                            'confidence_score' => $entry->confidence_score,
                            'ai_response' => $entry->ai_response,
                            'created_at' => $entry->created_at->toISOString(),
                        ],
                        'analysis' => $analysis,
                        'session_id' => $sessionId
                    ]
                ]);
            }

            if ($request->boolean('journal_origin')) {
                return redirect()->route('student.journal')->with('success', 'Reflection saved.');
            }

            return redirect()->route('student.insights')->with('success', 'Message sent successfully!');
            
        } catch (\Exception $e) {
            \Log::error('Error storing entry: ' . $e->getMessage());
            
            // Return JSON error for AJAX requests
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong. Please try again.',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Something went wrong. Please try again.']);
        }
    }

    public function updateEntry(Request $request, EmotionalEntry $entry)
    {
        abort_unless((int) $entry->user_id === (int) Auth::id(), 404);

        $validated = $request->validate([
            'entry_text' => 'required|string|min:1|max:5000',
            'entry_title' => 'nullable|string|max:120',
            'mood_hint' => 'nullable|in:happy,sad,anxious,angry,neutral',
        ]);

        $existingContext = is_array($entry->conversation_context) ? $entry->conversation_context : [];
        $entryTitle = trim((string) ($validated['entry_title'] ?? ''));
        $moodHint = array_key_exists('mood_hint', $validated) ? $validated['mood_hint'] : ($existingContext['mood_hint'] ?? null);
        $existingContext['title'] = $entryTitle !== '' ? $entryTitle : null;
        $existingContext['mood_hint'] = $moodHint ?: null;
        $entryContext = array_filter($existingContext, static fn ($value) => $value !== null && $value !== '');

        // Editing changes only the student's authorized journal content and metadata.
        // It intentionally does not trigger a new AI analysis or counselor share.
        $entry->update([
            'entry_text' => $validated['entry_text'],
            'conversation_context' => $entryContext ?: null,
        ]);

        return redirect()->route('student.journal')->with('success', 'Reflection updated.');
    }
    public function myEntries()
    {
        $entries = EmotionalEntry::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('student.entries', compact('entries'));
    }

    // AI Companion Chat
    public function aiChat($sessionId = null)
    {
        $user = Auth::user();
        
        // Get or create current session
        if ($sessionId) {
            $currentSession = \App\Models\AIConversationSession::where('id', $sessionId)
                ->where('user_id', Auth::id())
                ->firstOrFail();
        } else {
            // Get the most recent session or create new one
            $currentSession = \App\Models\AIConversationSession::where('user_id', Auth::id())
                ->latest('last_message_at')
                ->first();
                
            if (!$currentSession) {
                $currentSession = \App\Models\AIConversationSession::create([
                    'user_id' => Auth::id(),
                    'title' => 'New Conversation',
                    'last_message_at' => now(),
                ]);
            }
        }
        
        // Get messages for current session
        $recentEntries = EmotionalEntry::where('user_id', Auth::id())
            ->where('session_id', $currentSession->id)
            ->orderBy('created_at', 'asc')
            ->get();
        
        // Get recent conversation sessions for sidebar (only sessions with messages, exclude hidden)
        $recentSessions = \App\Models\AIConversationSession::where('user_id', Auth::id())
            ->where('is_hidden', false)
            ->whereHas('entries', function($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderBy('last_message_at', 'desc')
            ->take(20)
            ->get();

        // Get companion stats
        $totalConversations = EmotionalEntry::where('user_id', Auth::id())->count();
        $emotionCounts = EmotionalEntry::where('user_id', Auth::id())
            ->selectRaw('detected_emotion, COUNT(*) as count')
            ->groupBy('detected_emotion')
            ->pluck('count', 'detected_emotion');
        
        $primaryEmotion = $emotionCounts->keys()->first() ?? 'neutral';
        
        // Calculate streak (consecutive days with entries)
        $streak = 0;
        $currentDate = now()->startOfDay();
        while (EmotionalEntry::where('user_id', Auth::id())
            ->whereDate('created_at', $currentDate)
            ->exists()) {
            $streak++;
            $currentDate->subDay();
        }

        return view('student.chat', compact('recentEntries', 'currentSession', 'recentSessions', 'totalConversations', 'primaryEmotion', 'streak', 'emotionCounts'));
    }
    
    public function newConversation()
    {
        $session = \App\Models\AIConversationSession::create([
            'user_id' => Auth::id(),
            'title' => 'New Conversation - ' . now()->format('M j, g:i A'),
            'last_message_at' => now(),
        ]);
        
        // Redirect with cache-busting to ensure fresh page load with updated sidebar
        return redirect()->route('student.ai-chat', ['session' => $session->id])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
    
    /**
     * End current conversation and create a new one
     */
    public function endConversation(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');
            
            if ($sessionId) {
                // Mark current session as ended (optional - you can add is_active field)
                $session = \App\Models\AIConversationSession::where('id', $sessionId)
                    ->where('user_id', Auth::id())
                    ->first();
                    
                if ($session) {
                    // Generate title from first message if not already set
                    if ($session->title === 'New Conversation') {
                        $session->generateTitle();
                    }
                }
            }
            
            // Create new session
            $newSession = \App\Models\AIConversationSession::create([
                'user_id' => Auth::id(),
                'title' => 'New Conversation',
                'last_message_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'session_id' => $newSession->id,
                'message' => 'Conversation ended successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error ending conversation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to end conversation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hide/Archive conversation from sidebar (soft delete - keeps in history/journal)
     */
    public function hideConversation($sessionId)
    {
        try {
            \Log::info("hideConversation called for session $sessionId by user " . Auth::id());
            
            // Verify the session belongs to the user
            $session = \App\Models\AIConversationSession::where('id', $sessionId)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            
            \Log::info("Found session: {$session->title}, currently is_hidden={$session->is_hidden}");
            
            // Mark as hidden (soft delete from sidebar, but remains in database for history)
            $session->update(['is_hidden' => true]);
            
            \Log::info("Updated session {$sessionId} to is_hidden=true");
            
            return response()->json([
                'success' => true,
                'message' => 'Conversation removed from sidebar (still visible in history)'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error hiding conversation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to hide conversation: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // AI Companion Features Overview
    public function aiCompanion()
    {
        $user = Auth::user();

        // Get companion stats
        $totalConversations = EmotionalEntry::where('user_id', Auth::id())->count();
        $emotionCounts = EmotionalEntry::where('user_id', Auth::id())
            ->selectRaw('detected_emotion, COUNT(*) as count')
            ->groupBy('detected_emotion')
            ->pluck('count', 'detected_emotion');

        $primaryEmotion = $emotionCounts->keys()->first() ?? 'neutral';

        // Calculate streak (consecutive days with entries)
        $streak = 0;
        $currentDate = now()->startOfDay();
        while (EmotionalEntry::where('user_id', Auth::id())
            ->whereDate('created_at', $currentDate)
            ->exists()) {
            $streak++;
            $currentDate->subDay();
        }

        return view('student.ai-companion', compact('totalConversations', 'primaryEmotion', 'streak', 'emotionCounts'));
    }



    public function counselorChat()
    {
        // Get ONLY counselors with CONFIRMED appointments with this student
        $counselors = User::where('role', 'counselor')
            ->whereHas('counselorAppointments', function($query) {
                $query->where('student_id', Auth::id())
                      ->where('status', 'confirmed');
            })
            ->get();
        
        // Get conversations with counselors (group by counselor)
        $conversations = collect();
        try {
            $conversations = CounselorMessage::where('student_id', Auth::id())
                ->with(['sender', 'counselor'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('counselor_id');
        } catch (\Exception $e) {
            // CounselorMessage table may not exist - use empty collection
            \Log::warning("CounselorMessage query failed: " . $e->getMessage());
            $conversations = collect([]);
        }

        return view('student.counselor-chat', compact('counselors', 'conversations'));
    }

    public function getCounselorMessages($counselorId)
    {
        $studentId = Auth::id();

        $authorized = User::whereKey($counselorId)->where('role', 'counselor')->exists() && Appointment::where('student_id', $studentId)
            ->where('counselor_id', $counselorId)
            ->where(function ($query) {
                $query->whereIn('status', ['confirmed', 'completed', 'cancelled'])
                    ->orWhereNotNull('session_outcome');
            })->exists();
        abort_unless($authorized, 403, 'A counseling appointment is required to access this conversation.');

        try {
            // Load messages
            $messages = CounselorMessage::where('student_id', $studentId)
                ->where(function($query) use ($counselorId) {
                    $query->where('counselor_id', $counselorId)
                          ->orWhere('sender_id', $counselorId);
                })
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($msg) use ($studentId) {
                    $attachmentUrl = null;
                    if ($msg->attachment_path && !$msg->isUnsent()) {
                        $attachmentUrl = \Storage::url($msg->attachment_path);
                    }
                    
                    return [
                        'type' => 'message',
                        'id' => $msg->id,
                        'sender_id' => $msg->sender_id,
                        'message' => $msg->isUnsent() ? null : $msg->message,
                        'attachment_path' => $msg->isUnsent() ? null : $msg->attachment_path,
                        'attachment_url' => $attachmentUrl,
                        'attachment_name' => $msg->isUnsent() ? null : ($msg->attachment_name ?? null),
                        'attachment_type' => $msg->isUnsent() ? null : ($msg->attachment_type ?? null),
                        'created_at' => $msg->created_at,
                        'is_read' => $msg->is_read ?? false,
                        'is_unsent' => $msg->isUnsent(),
                    ];
                });
        } catch (\Exception $e) {
            \Log::warning("CounselorMessage query failed in getCounselorMessages: " . $e->getMessage());
            $messages = collect([]);
        }

        // Load video calls involving this student and counselor
        $calls = DB::table('video_calls')
            ->where(function($q) use ($studentId, $counselorId) {
                $q->where('caller_id', $studentId)->where('callee_id', $counselorId)
                  ->orWhere('caller_id', $counselorId)->where('callee_id', $studentId);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($call) use ($studentId) {
                $duration = null;
                if ($call->accepted_at && $call->ended_at) {
                    $duration = (int) (strtotime($call->ended_at) - strtotime($call->accepted_at));
                }

                return [
                    'type' => 'video_call',
                    'id' => $call->id,
                    'caller_id' => (int) $call->caller_id,
                    'callee_id' => (int) $call->callee_id,
                    'status' => $call->status,
                    'accepted_at' => $call->accepted_at,
                    'ended_at' => $call->ended_at,
                    'created_at' => $call->created_at,
                    'duration_seconds' => $duration,
                    'is_caller' => (int) $call->caller_id === (int) $studentId,
                ];
            });

        // Merge and sort by created_at
        $timeline = collect(array_merge($messages->toArray(), $calls->toArray()))
            ->sortBy('created_at')
            ->values()
            ->toArray();

        return response()->json([
            'messages' => $messages->toArray(),  // Keep for backward compatibility
            'calls' => $calls->toArray(),        // New: separate calls
            'timeline' => $timeline              // New: merged timeline
        ]);
    }

    public function sendMessage(Request $request)
    {
        $counselorId = (int) $request->input('counselor_id');
        $canSend = User::whereKey($counselorId)->where('role', 'counselor')->exists()
            && app(AppointmentLifecycleService::class)->hasMessagingAppointment($counselorId, (int) Auth::id());
        abort_unless($canSend, 403, 'An unresolved confirmed appointment is required before messaging.');

        // DIAGNOSTIC: Log upload details
        \Log::info('[CHAT_UPLOAD_DEBUG]', [
            'message' => $request->input('message'),
            'has_attachment' => $request->hasFile('attachment'),
            'all_files' => array_keys($request->allFiles()),
            'content_type' => $request->header('Content-Type'),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            \Log::info('[CHAT_UPLOAD_FILE]', [
                'name' => $file?->getClientOriginalName(),
                'mime' => $file?->getMimeType(),
                'size' => $file?->getSize(),
                'error' => $file?->getError(),
                'valid' => $file?->isValid(),
            ]);
        }

        $request->validate([
            'message' => 'nullable|string|max:5000',
            'counselor_id' => 'required|exists:users,id',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,txt',
        ]);

        // Explicitly check: at least message or attachment is required
        $hasMessage = filled(trim((string) $request->input('message', '')));
        $hasAttachment = $request->hasFile('attachment') && $request->file('attachment')->isValid();
        
        \Log::info('[VALIDATION_CHECK]', [
            'hasMessage' => $hasMessage,
            'hasAttachment' => $hasAttachment,
        ]);
        
        if (!$hasMessage && !$hasAttachment) {
            return response()->json([
                'success' => false,
                'message' => 'Message or attachment is required.'
            ], 422);
        }

        $data = [
            'student_id' => Auth::id(),
            'counselor_id' => $request->counselor_id,
            'sender_id' => Auth::id(),
            'message' => $request->message ?? '',
        ];

        // Handle file upload
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('chat_attachments', $filename, 'public');
            
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_type'] = $file->getClientMimeType();
            
            \Log::info('[FILE_STORED]', [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ]);
        } else {
            \Log::info('[MESSAGE_ONLY]', ['counselor_id' => $request->counselor_id]);
        }

        try {
            $message = CounselorMessage::create($data);

            // Score the chat message for emotional monitoring (only if message has text)
            if (!empty($request->message)) {
                $emotionScorerService = app(\App\Services\EmotionScorerService::class);
                $emotionScorerService->scoreChatMessage($message->id, $request->message, Auth::id());
            }

            // Send notification to counselor
            $counselor = User::find($request->counselor_id);
            if ($counselor) {
                $messagePreview = $request->message ?? '[File attachment]';
                \App\Services\NotificationService::newMessage(
                    Auth::id(),
                    $request->counselor_id,
                    Auth::user()->name,
                    $messagePreview
                );
            }

            // Include attachment_url in response
            $attachmentUrl = null;
            if ($message->attachment_path) {
                $attachmentUrl = \Storage::url($message->attachment_path);
            }

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'student_id' => $message->student_id,
                    'counselor_id' => $message->counselor_id,
                    'sender_id' => $message->sender_id,
                    'message' => $message->message,
                    'attachment_path' => $message->attachment_path,
                    'attachment_url' => $attachmentUrl,
                    'attachment_name' => $message->attachment_name,
                    'attachment_type' => $message->attachment_type,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error('[SEND_MESSAGE_ERROR]', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'counselor_id' => $request->counselor_id,
                'student_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message'
            ], 500);
        }
    }

    // Mood Check-In
    public function storeMoodCheckin(Request $request)
    {
        $validated = $request->validate([
            'mood' => 'required|in:happy,sad,anxious,stressed,neutral',
            'intensity' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        $service = app(MoodCheckInService::class);
        $service->recordCheckin(
            Auth::id(),
            $validated['mood'],
            $validated['intensity'] ?? 5,
            $validated['notes'] ?? null
        );
        
        return back()->with('success', 'Mood recorded successfully!');
    }

    // Wellbeing Tracker
    public function wellbeing()
    {
        $service = app(WellbeingService::class);
        $progress = $service->getWellbeingProgress(Auth::id());
        
        $moodHistory = [];
        try {
            $moodHistory = MoodCheckin::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->take(30)
                ->get();
        } catch (\Exception $e) {
            // MoodCheckin table may not exist - use empty array
            \Log::warning("MoodCheckin query failed: " . $e->getMessage());
            $moodHistory = collect([]);
        }
        
        return view('student.wellbeing', compact('progress', 'moodHistory'));
    }

    // Coping Toolkit
    public function copingToolkit(WellnessResourceSearchService $resourceSearch)
    {
        $service = app(CopingToolkitService::class);
        
        // Get the user's most recent emotion for recommendations
        // Check if mood_checkins table exists first
        if (Schema::hasTable('mood_checkins')) {
            $recentMood = \App\Models\MoodCheckin::where('user_id', Auth::id())
                ->orderBy('checked_in_at', 'desc')
                ->first();
        } else {
            $recentMood = null;
        }
        
        $strategies = $recentMood ? $service->getRecommendedStrategies($recentMood->mood) : collect();
        $allStrategies = $service->getAllStrategies();
        $copingSearchQuery = trim((string) request()->query('q', ''));
        $includePublicSources = $copingSearchQuery !== '' && request()->boolean('public_sources');
        $copingCategory = trim((string) request()->query('category', ''));
        $searchData = $copingSearchQuery !== ''
            ? $resourceSearch->search($copingSearchQuery, 1, $includePublicSources)
            : null;
        $fallbackActivities = [
            [
                'name' => 'Box Breathing',
                'description' => 'Equal-count breathing that can help you pause and notice your body.',
                'instructions' => 'Inhale for 4 counts, hold for 4, exhale for 4, and hold for 4. Repeat at a comfortable pace.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'category' => 'breathing',
                'search_keywords' => 'breath breathing breathwork paced breathing square breathing focus reset',
            ],
            [
                'name' => 'Progressive Muscle Relaxation',
                'description' => 'Tense and release muscle groups to notice physical tension and relaxation.',
                'instructions' => 'Tense one muscle group briefly, release, and move gradually from your toes toward your head.',
                'duration_minutes' => 10,
                'difficulty' => 'medium',
                'category' => 'stress_management',
                'search_keywords' => 'relaxation relaxing muscle tension pmr release stress unwind body',
            ],
            [
                'name' => 'Cognitive Reframing',
                'description' => 'Examine a stressful thought and look for a more balanced alternative.',
                'instructions' => 'Write the thought, note the evidence you have, and try a realistic wording that leaves room for options.',
                'duration_minutes' => 10,
                'difficulty' => 'medium',
                'category' => 'mindfulness',
                'search_keywords' => 'thoughts reflection perspective worry reframing focus academic stress',
            ],
            [
                'name' => 'Grounding Technique (5-4-3-2-1)',
                'description' => 'Use your senses to reconnect with the present moment.',
                'instructions' => 'Name 5 things you see, 4 you feel, 3 you hear, 2 you smell, and 1 you taste.',
                'duration_minutes' => 5,
                'difficulty' => 'easy',
                'category' => 'grounding',
                'search_keywords' => 'senses sensory present moment grounding overwhelm focus anxiety',
            ],
            [
                'name' => 'Expressive Journaling',
                'description' => 'Write freely to organize thoughts and make space for reflection.',
                'instructions' => 'Write for 10 to 15 minutes without editing. Stop if the exercise feels overwhelming and choose another option.',
                'duration_minutes' => 15,
                'difficulty' => 'easy',
                'category' => 'stress_management',
                'search_keywords' => 'journal journaling writing reflection thoughts feelings stress',
            ],
            [
                'name' => 'Physical Activity',
                'description' => 'Choose comfortable movement that fits your energy and circumstances.',
                'instructions' => 'Walk, stretch, dance, or choose another safe movement for a few minutes. Adjust or stop if needed.',
                'duration_minutes' => 30,
                'difficulty' => 'easy',
                'category' => 'stress_management',
                'search_keywords' => 'movement walk stretching exercise energy focus stress',
            ],
        ];
        $canonicalActivities = collect($fallbackActivities)->map(function ($fallback) use ($allStrategies) {
            $record = $allStrategies->first(function ($strategy) use ($fallback) {
                return strcasecmp((string) $strategy->name, $fallback['name']) === 0;
            });
            return $record ?: $fallback;
        });

        $activityCatalog = $canonicalActivities
            ->concat($allStrategies)
            ->unique(fn ($activity) => mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8'))
            ->values();
        $activityCategories = $activityCatalog
            ->map(fn ($activity) => trim((string) data_get($activity, 'category', '')))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        if (!$activityCategories->contains($copingCategory)) {
            $copingCategory = '';
        }
        $hasCopingSearch = $copingSearchQuery !== '' || $copingCategory !== '';
        $searchedStrategies = $hasCopingSearch
            ? $service->searchStrategies($copingSearchQuery, $activityCatalog, $copingCategory)
            : collect();
        $searchedNames = $searchedStrategies->map(fn ($activity) => mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8'));
        $relatedStrategies = $strategies
            ->reject(fn ($activity) => $searchedNames->contains(mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8')))
            ->unique(fn ($activity) => mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8'))
            ->values();
        $displayedNames = $searchedNames->merge($relatedStrategies->map(fn ($activity) => mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8')));
        $otherActivities = $activityCatalog
            ->reject(fn ($activity) => $displayedNames->contains(mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8')))
            ->values();
        
        return view('student.coping-toolkit', compact('strategies', 'allStrategies', 'canonicalActivities', 'copingSearchQuery', 'includePublicSources', 'copingCategory', 'activityCategories', 'hasCopingSearch', 'searchedStrategies', 'relatedStrategies', 'otherActivities', 'searchData'));
    }

    // Reflection Prompts
    public function reflection()
    {
        $service = app(ReflectionService::class);
        $prompts = $service->getReflectionPrompts(Auth::id());
        
        return view('student.reflection', compact('prompts'));
    }

    // Wellness Reminders
    public function reminders()
    {
        $service = app(WellnessReminderService::class);
        $reminders = $service->getUserReminders(Auth::id());
        
        return view('student.reminders', compact('reminders'));
    }

    // Privacy Control
    public function privacy()
    {
        $service = app(PrivacyService::class);
        $settings = $service->getSettings(Auth::id());
        
        return view('student.privacy', compact('settings'));
    }

    public function updatePrivacy(Request $request)
    {
        $validated = $request->validate([
            'share_with_counselor' => 'sometimes|boolean',
            'share_analytics' => 'sometimes|boolean',
            'allow_anonymous_community' => 'sometimes|boolean',
            'allow_ai_recommendations' => 'sometimes|boolean',
            'allow_crisis_alerts' => 'sometimes|boolean',
            'allow_reminders' => 'sometimes|boolean',
            'share_emotion_data' => 'sometimes|boolean',
            'auto_journal_entry' => 'sometimes|boolean',
            'wellness_reminders_enabled' => 'sometimes|boolean',
            'data_retention_days' => 'required|integer|min:30|max:730',
            'consent_policy_version' => [
                'required',
                'string',
                Rule::in([(string) config('privacy.consent_policy_version')]),
            ],
        ]);

        $booleanFields = [
            'share_with_counselor',
            'share_analytics',
            'allow_anonymous_community',
            'allow_ai_recommendations',
            'share_emotion_data',
            'auto_journal_entry',
            'allow_reminders',
            'wellness_reminders_enabled',
        ];
        foreach ($booleanFields as $field) {
            $validated[$field] = $request->boolean($field);
        }
        // Safety alerts are mandatory and cannot be disabled by a general privacy toggle.
        $validated['allow_crisis_alerts'] = true;

        $service = app(PrivacyService::class);
        $service->updateSettings(Auth::id(), $validated);
        
        return back()->with('success', 'Privacy settings updated!');
    }

    // Community Support
    public function community()
    {
        $service = app(CommunityService::class);
        $category = request('category', 'all');
        $sortBy = request('sort', 'recent');
        $search = trim((string) request('q', ''));
        $availableCampuses = ['Bongabong Campus', 'Calapan Campus', 'Victoria Campus'];
        $campusFilter = in_array(request('campus'), $availableCampuses, true) ? request('campus') : null;
        $communityView = request('view') === 'mine' ? 'mine' : 'feed';
        $myPostStatus = in_array(request('status'), ['all', 'published', 'pending', 'flagged'], true)
            ? request('status')
            : 'all';
        $moderationView = in_array(request('moderation'), ['all', 'needs_review', 'hidden', 'published'], true)
            ? request('moderation', 'all')
            : 'all';

        $messages = Auth::user()?->role === 'admin'
            ? $service->getModeratorFeed($moderationView, $category, $sortBy, (int) Auth::id(), $search, $campusFilter)
            : $service->getApprovedMessages($category, $sortBy, null, $search, $campusFilter);
        $myMessages = $service->getMyMessages((int) Auth::id(), $myPostStatus);
        $stats = $service->getStats();
        $guidanceAnnouncements = $service->getGuidanceAnnouncements();
        $categories = array_keys(config('community.categories', []));
        $shareableResources = in_array(Auth::user()->role, ['counselor', 'admin'], true)
            ? Resource::where('is_active', true)->orderBy('title')->get(['id', 'title', 'description', 'category'])
            : collect();
        $communityEligible = app(\App\Services\CommunityVerificationService::class)->isEligibleParticipant(Auth::user());
        $communityIsStudent = Auth::user()->isStudent();
        $communityRoleLabel = match (Auth::user()->role) {
            'counselor' => 'Authorized Counselor',
            'admin' => 'Guidance Admin',
            default => 'Student',
        };
        $communityCanInteract = $communityEligible && (!$communityIsStudent || app(PrivacyService::class)->canUseCommunity(Auth::id()));

        return view('student.community', compact(
            'messages', 'myMessages', 'stats', 'categories', 'category', 'sortBy', 'search',
            'communityView', 'myPostStatus', 'moderationView',
            'communityEligible', 'communityCanInteract', 'communityIsStudent', 'communityRoleLabel',
            'campusFilter', 'availableCampuses', 'guidanceAnnouncements', 'shareableResources'
        ));
    }

    /**
     * React Community Circle data boundary.
     *
     * This intentionally emits a public projection instead of serializing Eloquent
     * models: anonymous student author IDs and every private account attribute stay
     * on the server. Laravel continues to enforce every mutation authorization.
     */
    public function communityData(Request $request)
    {
        $service = app(CommunityService::class);
        $category = $request->string('category', 'all')->toString();
        $sortBy = $request->string('sort', 'recent')->toString();
        $search = trim($request->string('q')->toString());
        $availableCampuses = ['Bongabong Campus', 'Calapan Campus', 'Victoria Campus'];
        $campusFilter = in_array($request->query('campus'), $availableCampuses, true) ? $request->query('campus') : null;
        $communityView = $request->query('view') === 'mine' ? 'mine' : 'feed';
        $myPostStatus = in_array($request->query('status'), ['all', 'published', 'pending', 'flagged'], true)
            ? $request->query('status')
            : 'all';
        $moderationView = in_array($request->query('moderation'), ['all', 'needs_review', 'hidden', 'published'], true)
            ? $request->query('moderation', 'all')
            : 'all';
        $user = $request->user();
        $messages = $user->role === 'admin'
            ? $service->getModeratorFeed($moderationView, $category, $sortBy, (int) $user->id, $search, $campusFilter)
            : $service->getApprovedMessages($category, $sortBy, (int) $user->id, $search, $campusFilter);
        $myMessages = $service->getMyMessages((int) $user->id, $myPostStatus);
        $communityIsStudent = $user->isStudent();
        $communityEligible = app(\App\Services\CommunityVerificationService::class)->isEligibleParticipant($user);
        $communityCanInteract = $communityEligible && (!$communityIsStudent || app(PrivacyService::class)->canUseCommunity($user->id));

        return response()->json([
            'viewer' => [
                'name' => $user->name,
                'role' => $user->role,
                'role_label' => $this->communityRoleLabel($user->role),
                'is_student' => $communityIsStudent,
                'can_interact' => $communityCanInteract,
                'is_admin' => $user->role === 'admin',
                'can_moderate' => $user->role === 'admin' && $communityEligible,
            ],
            'filters' => [
                'category' => $category,
                'sort' => $sortBy,
                'q' => $search,
                'campus' => $campusFilter,
                'view' => $communityView,
                'status' => $myPostStatus,
                'moderation' => $moderationView,
            ],
            'categories' => collect(config('community.categories', []))->map(fn ($definition, $key) => [
                'key' => $key,
                'label' => $definition['label'] ?? ucfirst(str_replace('-', ' ', $key)),
                'icon' => $definition['icon'] ?? '',
            ])->values(),
            'campuses' => $availableCampuses,
            'posts' => $messages->getCollection()->map(fn (CommunityMessage $message) => $this->communityPostPayload($message, (int) $user->id, $user->role === 'admin'))->values(),
            'my_posts' => $myMessages->getCollection()->map(fn (CommunityMessage $message) => $this->communityPostPayload($message, (int) $user->id, true))->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
                'has_more' => $messages->hasMorePages(),
            ],
            'my_pagination' => [
                'current_page' => $myMessages->currentPage(),
                'last_page' => $myMessages->lastPage(),
                'total' => $myMessages->total(),
                'has_more' => $myMessages->hasMorePages(),
            ],
            'stats' => $service->getStats(),
            'announcements' => $service->getGuidanceAnnouncements()->map(fn (CommunityMessage $message) => $this->communityPostPayload($message, (int) $user->id))->values(),
            'shareable_resources' => in_array($user->role, ['counselor', 'admin'], true)
                ? Resource::where('is_active', true)->orderBy('title')->get(['id', 'title', 'description', 'category'])->map(fn (Resource $resource) => [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'category' => $resource->category,
                ])->values()
                : [],
            'urls' => [
                'post' => route('student.community.post'),
                'report' => route('student.community.report'),
                'resource_base' => url('/student/community/resources'),
                'image_base' => url('/student/community'),
                'reaction_base' => url('/student/community'),
                'comments_base' => url('/student/post'),
                'comment_base' => url('/student/comment'),
                'feed' => route('student.community.data'),
                'moderation_approve' => url('/admin/community-moderation') . '/{postId}/approve',
                'moderation_reject' => url('/admin/community-moderation') . '/{postId}/reject',
                'moderation_pin' => url('/admin/community-moderation') . '/{postId}/pin',
                'moderation_hide' => url('/admin/community-moderation') . '/{postId}/hide',
                'moderation_restore' => url('/admin/community-moderation') . '/{postId}/restore',
            ],
        ]);
    }

    public function postCommunity(Request $request)
    {
        $validated = $request->validate([
            // Legacy fields remain accepted for existing clients and integrations.
            'title' => 'nullable|string|max:140',
            'message' => 'nullable|string|max:2000',
            'category' => ['nullable', Rule::in(array_keys(config('community.categories', [])))],
            'support_label' => ['nullable', Rule::in(array_keys(config('community.support_labels', [])))],
            'image' => 'nullable|file|max:5120',
            'resource_id' => 'nullable|integer|exists:resources,id',
            'is_anonymous' => 'sometimes|boolean',
            'post_format' => ['nullable', Rule::in(['text', 'rich', 'visual'])],
            'visual_text' => 'nullable|string|max:2000',
            'visual_layout' => 'nullable|string|max:16000',
            'visual_caption' => 'nullable|string|max:16000',
            'rich_content' => 'nullable|string|max:16000',
            'post_style' => 'nullable|string|max:500',
        ]);

        if (($validated['post_format'] ?? 'text') === 'visual') {
            $layout = json_decode((string) ($validated['visual_layout'] ?? '{}'), true);
            if (!is_array($layout)) {
                throw ValidationException::withMessages(['visual_layout' => 'The visual layout could not be read.']);
            }
            $caption = null;
            if (!empty($validated['visual_caption'])) {
                $caption = json_decode($validated['visual_caption'], true);
                if (!is_array($caption)) {
                    throw ValidationException::withMessages(['visual_caption' => 'The post caption could not be read.']);
                }
            }
            $post = app(CommunityService::class)->postVisualMessage(
                (string) ($validated['visual_text'] ?? ''),
                $layout,
                Auth::user()->isStudent() && $request->boolean('is_anonymous', false),
                $request->file('image'),
                $caption,
                $validated['category'] ?? 'general',
                $validated['support_label'] ?? null,
                isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
            );
        } elseif (($validated['post_format'] ?? 'text') === 'rich') {
            $document = json_decode((string) ($validated['rich_content'] ?? ''), true);
            $style = json_decode((string) ($validated['post_style'] ?? ''), true);
            if (!is_array($document) || !is_array($style)) {
                throw ValidationException::withMessages(['rich_content' => 'The formatted post could not be read.']);
            }
            $post = app(CommunityService::class)->postRichMessage(
                $document,
                $style,
                $validated['category'] ?? 'general',
                Auth::user()->isStudent() && $request->boolean('is_anonymous', false),
                $validated['title'] ?? null,
                $validated['support_label'] ?? null,
                $request->file('image'),
                isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
            );
        } else {
            $body = trim((string) ($validated['message'] ?? ''));
            $image = $request->file('image');
            if ($body === '' && !$image) {
                throw ValidationException::withMessages([
                    'message' => 'Write something or attach a photo before posting.',
                ]);
            }

            $post = app(CommunityService::class)->postMessage(
                $body,
                $validated['category'] ?? 'general',
                Auth::user()->isStudent() && $request->boolean('is_anonymous', false),
                $validated['title'] ?? null,
                $validated['support_label'] ?? null,
                $image,
                isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
            );
        }

        $feedback = $post->is_approved
            ? 'Your post has been shared with the community.'
            : 'Your post is under review. You can find it in My Posts.';

        $anchor = $post->is_approved ? 'community-post-' : 'my-post-';
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'post_id' => $post->id,
                'moderation_status' => $post->moderation_status,
                'is_approved' => (bool) $post->is_approved,
                'message' => $feedback,
            ], 201);
        }
        return redirect()
            ->to(route('student.community') . '#' . $anchor . $post->id)
            ->with('success', $feedback);
    }

    public function communityImage($messageId)
    {
        $message = CommunityMessage::findOrFail((int) $messageId);
        $user = Auth::user();
        $eligible = app(\App\Services\CommunityVerificationService::class)->isEligibleParticipant($user);
        abort_unless($eligible, 403);

        if (!$message->is_approved) {
            abort_unless((int) $message->user_id === (int) Auth::id(), 403);
        } elseif (app(CommunityService::class)->isBlocked((int) Auth::id(), (int) $message->user_id)) {
            abort(404);
        }

        $layout = is_array($message->visual_layout) ? $message->visual_layout : [];
        $compositionSource = request()->boolean('composition')
            && ($layout['version'] ?? null) === 2
            && (int) $message->user_id !== 0;
        $sourcePreview = (request()->boolean('source') && (int) $message->user_id === (int) Auth::id()) || $compositionSource;
        $path = (string) ($sourcePreview ? $message->image_path : ($message->visual_media_path ?: $message->image_path));
        abort_unless((bool) preg_match('/\\Acommunity_(?:media|visuals)\\/[a-f0-9]{40}\\.webp\\z/D', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $response = response()->file($disk->path($path), [
            'Content-Type' => 'image/webp',
        ]);
        // Symfony's file response may append a public cache directive; force
        // private, non-cacheable delivery for student media.
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }

    public function updateCommunityMessage(Request $request, $messageId)
    {
        if ($request->input('post_format') === 'visual') {
            $validated = $request->validate([
                'post_format' => ['required', Rule::in(['visual'])],
                'visual_text' => 'nullable|string|max:2000',
                'visual_layout' => 'required|string|max:16000',
                'visual_caption' => 'nullable|string|max:16000',
                'category' => ['nullable', Rule::in(array_keys(config('community.categories', [])))],
                'support_label' => ['nullable', Rule::in(array_keys(config('community.support_labels', [])))],
                'resource_id' => 'nullable|integer|exists:resources,id',
                'image' => 'nullable|file|max:5120',
                'is_anonymous' => 'sometimes|boolean',
            ]);
            $layout = json_decode($validated['visual_layout'], true);
            if (!is_array($layout)) {
                throw ValidationException::withMessages(['visual_layout' => 'The visual layout could not be read.']);
            }
            $caption = null;
            if (!empty($validated['visual_caption'])) {
                $caption = json_decode($validated['visual_caption'], true);
                if (!is_array($caption)) {
                    throw ValidationException::withMessages(['visual_caption' => 'The post caption could not be read.']);
                }
            }
            $post = app(CommunityService::class)->updateVisualMessage(
                (int) $messageId,
                (int) Auth::id(),
                (string) ($validated['visual_text'] ?? ''),
                $layout,
                $request->file('image'),
                $caption,
                $validated['category'] ?? null,
                $validated['support_label'] ?? null,
                isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
            );
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'post_id' => $post->id, 'moderation_status' => $post->moderation_status]);
            }
            return redirect()
                ->to(route('student.community', ['view' => 'mine', 'status' => 'pending']) . '#my-post-' . $post->id)
                ->with('success', 'Your visual post was updated and returned to moderation.');
        }

        if ($request->input('post_format') === 'rich') {
            $validated = $request->validate([
                'title' => 'nullable|string|max:140',
                'category' => ['required', Rule::in(array_keys(config('community.categories', [])))],
                'support_label' => ['nullable', Rule::in(array_keys(config('community.support_labels', [])))],
                'post_format' => ['required', Rule::in(['rich'])],
                'rich_content' => 'required|string|max:16000',
                'post_style' => 'required|string|max:500',
                'image' => 'nullable|file|max:5120',
            ]);
            $document = json_decode($validated['rich_content'], true);
            $style = json_decode($validated['post_style'], true);
            if (!is_array($document) || !is_array($style)) {
                throw ValidationException::withMessages(['rich_content' => 'The formatted post could not be read.']);
            }
            $post = app(CommunityService::class)->updateRichMessage(
                (int) $messageId,
                (int) Auth::id(),
                $document,
                $style,
                $validated,
                $request->file('image'),
            );
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'post_id' => $post->id, 'moderation_status' => $post->moderation_status]);
            }
            return back()->with('success', 'Your formatted post was updated and returned to moderation.');
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:140',
            'message' => 'required|string|min:5|max:2000',
            'category' => ['required', Rule::in(array_keys(config('community.categories', [])))],
            'support_label' => ['nullable', Rule::in(array_keys(config('community.support_labels', [])))],
        ]);
        $post = app(CommunityService::class)->updateMessage((int) $messageId, (int) Auth::id(), $validated);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'post_id' => $post->id, 'moderation_status' => $post->moderation_status]);
        }
        return back()->with('success', 'Your post was updated and returned to moderation.');
    }

    public function deleteCommunityMessage(Request $request, $messageId)
    {
        app(CommunityService::class)->deleteMessage((int) $messageId, (int) Auth::id());
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Your community post was removed.');
    }

    /**
     * Like a community message (AJAX)
     */
    public function likeCommunityMessage($messageId)
    {
        $service = app(CommunityService::class);
        $result = $service->reactMessage((int) $messageId, (int) Auth::id(), 'support');
        
        return response()->json([
            'success' => true,
            'likes_count' => $result['likes_count'],
            'reaction_counts' => $result['reaction_counts']
        ]);
    }

    /**
     * Unlike a community message (AJAX)
     */
    public function unlikeCommunityMessage($messageId)
    {
        $service = app(CommunityService::class);
        $result = $service->removeReaction((int) $messageId, (int) Auth::id());
        
        return response()->json([
            'success' => true,
            'likes_count' => $result['likes_count'],
            'reaction_counts' => $result['reaction_counts']
        ]);
    }

    public function reactCommunityMessage(Request $request, $messageId)
    {
        $request->validate(['reaction' => ['required', Rule::in(array_keys(config('community.reaction_types', [])))] ]);
        return response()->json(app(CommunityService::class)->reactMessage((int) $messageId, (int) Auth::id(), $request->string('reaction')->toString()));
    }

    public function removeCommunityReaction($messageId)
    {
        return response()->json(app(CommunityService::class)->removeReaction((int) $messageId, (int) Auth::id()));
    }

    public function reportCommunity(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:post,comment,user',
            'target_id' => 'required|integer|min:1',
            'reason' => ['required', Rule::in(array_keys(config('community.report_reasons', [])))],
        ]);
        app(CommunityService::class)->addReport((int) Auth::id(), $validated['type'], (int) $validated['target_id'], $validated['reason']);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Thank you. Your report was sent to authorized moderators.']);
        }
        return back()->with('success', 'Thank you. Your report was sent to authorized moderators.');
    }

    public function blockCommunityUser($userId)
    {
        app(CommunityService::class)->blockUser((int) Auth::id(), (int) $userId);
        return back()->with('success', 'This account is now blocked from interacting with you.');
    }

    public function unblockCommunityUser($userId)
    {
        app(CommunityService::class)->unblockUser((int) Auth::id(), (int) $userId);
        return back()->with('success', 'The account is no longer blocked.');
    }
    // Resources Library
    public function resources(WellnessResourceSearchService $resourceSearch)
    {
        $category = trim((string) request()->query('category', '')) ?: null;
        $searchQuery = trim((string) request()->query('q', ''));
        // Public providers are opt-in. A typed local catalog query never leaves
        // the application unless the student explicitly enables this setting.
        $includePublicSources = request()->boolean('public_sources');
        $includeVideos = request()->boolean('videos');
        $includeResearch = $includePublicSources && request()->boolean('research');
        $page = max(1, (int) request()->query('resource_page', request()->query('page', 1)));
        $searchData = $resourceSearch->search($searchQuery, $page, $includePublicSources, $category, $includeResearch, $includeVideos);
        $resources = $searchData['local'];
        $remoteResults = $searchData['remote'];
        $videoResults = $searchData['videos'];
        $searchErrors = $searchData['errors'];
        $providerErrors = $searchData['provider_errors'];
        $providerStatus = $searchData['providers'];
        $mappingNote = $searchData['mapping_note'];
        $relatedTopics = $searchData['related_topics'];

        $resourceCategories = Resource::where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        // These are non-urgent directory entries from the legacy Psychosocial page.
        $supportOrganizations = [
            [
                'name' => 'Natasha Goulbourn Foundation',
                'type' => 'Mental-health advocacy and suicide-prevention information',
                'description' => 'Existing directory entry for an external organization. Verify current services and contact details on its website.',
                'url' => 'https://www.ngf-hope.org',
            ],
            [
                'name' => 'Philippine Mental Health Association',
                'type' => 'Mental-health programs',
                'description' => 'Existing directory entry for an external organization offering mental-wellness information and programs. Review current services directly.',
                'url' => 'https://pmha.org.ph',
            ],
            [
                'name' => 'MindNation',
                'type' => 'Online mental-wellness platform',
                'description' => 'Existing directory entry for an external platform. Review its current services, eligibility, and fees directly.',
                'url' => 'https://mindnation.com',
            ],
            [
                'name' => 'Silakbo PH',
                'type' => 'Youth mental-health and peer-support information',
                'description' => 'Existing directory entry for an external youth organization. Verify current channels before using them.',
                'url' => 'https://www.facebook.com/SilakboPH',
            ],
        ];

        return view('student.resources', compact(
            'resources',
            'resourceCategories',
            'category',
            'searchQuery',
            'includePublicSources',
            'includeVideos',
            'includeResearch',
            'remoteResults',
            'videoResults',
            'searchErrors',
            'providerErrors',
            'providerStatus',
            'mappingNote',
            'relatedTopics',
            'supportOrganizations'
        ));
    }

    /**
     * Local-first resource search for the student Resources page.
     * The authenticated student controls whether a public provider is queried.
     */
    public function searchResources(Request $request, WellnessResourceSearchService $resourceSearch)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'public_sources' => ['nullable', 'boolean'],
            'videos' => ['nullable', 'boolean'],
            'research' => ['nullable', 'boolean'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $category = trim((string) ($validated['category'] ?? '')) ?: null;
        $includePublicSources = $request->boolean('public_sources');
        $includeVideos = $request->boolean('videos');
        $includeResearch = $includePublicSources && $request->boolean('research');
        $searchData = $resourceSearch->search(
            $query,
            max(1, (int) ($validated['page'] ?? 1)),
            $includePublicSources,
            $category,
            $includeResearch,
            $includeVideos,
        );
        $paginator = $searchData['local'];
        $publicCards = $searchData['remote']->map(fn (array $result) => $this->publicResourceSearchCard($result))->values();
        $videoCards = $searchData['videos']->map(fn (array $result) => $this->publicResourceSearchCard($result))->values();
        $libraryCount = (int) $paginator->total();
        $sourceCounts = [
            'library' => $libraryCount,
            'web' => $publicCards->count(),
            'videos' => $videoCards->count(),
        ];
        $sourceCounts['all'] = array_sum($sourceCounts);

        return response()->json([
            'query' => $searchData['query'],
            'category' => $category,
            'public_requested' => $searchData['public_requested'],
            'videos_requested' => $searchData['videos_requested'],
            'providers' => $searchData['providers'],
            'mapping_note' => $searchData['mapping_note'],
            'errors' => $searchData['errors'],
            'provider_errors' => $searchData['provider_errors'],
            'counts' => $sourceCounts,
            'related_topics' => $searchData['related_topics'],
            'local' => [
                'items' => collect($paginator->items())->map(fn (Resource $resource) => $this->resourceSearchCard($resource))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'public' => $publicCards,
            'videos' => $videoCards,
        ]);
    }

    private function resourceSearchCard(Resource $resource): array
    {
        $sourceUrl = (string) ($resource->url ?? '');
        if (!str_starts_with(strtolower($sourceUrl), 'https://')) {
            $sourceUrl = null;
        }
        $videoId = $sourceUrl ? $this->youtubeVideoId($sourceUrl) : null;

        return [
            'id' => $resource->id,
            'title' => (string) $resource->title,
            'description' => (string) ($resource->description ?? ''),
            'category' => $this->wellnessCategoryLabel((string) ($resource->category ?? 'general')),
            'image' => $videoId ? 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/hqdefault.jpg' : null,
            'video_id' => $videoId,
            'embeddable' => false,
            'type' => $videoId ? 'video' : 'library',
            'detail_url' => route('student.resources.show', $resource->id),
            'source_url' => $sourceUrl,
            'updated_at' => optional($resource->updated_at)->toDateString(),
        ];
    }

    private function publicResourceSearchCard(array $result): array
    {
        $thumbnail = $this->safeWellnessThumbnail($result['thumbnail'] ?? null);
        return [
            'title' => (string) ($result['title'] ?? ''),
            'description' => (string) ($result['summary'] ?? ''),
            'category' => (string) ($result['category'] ?? 'Public health resource'),
            'provider' => (string) ($result['provider'] ?? 'Approved public provider'),
            'organization' => (string) ($result['organization'] ?? ''),
            'author' => (string) ($result['author'] ?? ''),
            'attribution' => (string) ($result['attribution'] ?? ''),
            'date' => (string) ($result['date'] ?? ''),
            'reader_content' => (string) ($result['reader_content'] ?? ''),
            'reader_content_kind' => (string) ($result['reader_content_kind'] ?? 'Source metadata'),
            'reader_note' => (string) ($result['reader_note'] ?? 'Open the official source to review the available article content and access terms.'),
            'source_url' => (string) ($result['url'] ?? ''),
            'image' => $thumbnail,
            'thumbnail_is_permitted' => $thumbnail !== null,
            'video_id' => $this->youtubeVideoId((string) ($result['url'] ?? '')),
            'published_at' => (string) ($result['published_at'] ?? ''),
            'duration' => (string) ($result['duration'] ?? ''),
            'channel' => (string) ($result['channel'] ?? ''),
            'embeddable' => (bool) ($result['embeddable'] ?? false),
            'type' => (string) ($result['type'] ?? 'article'),
        ];
    }

    private function safeWellnessThumbnail(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $parts = parse_url($url);
        return ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'i.ytimg.com'
            ? $url
            : null;
    }

    private function youtubeVideoId(string $url): ?string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim((string) ($parts['path'] ?? ''), '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $path = trim((string) ($parts['path'] ?? ''), '/');
            if (($query['v'] ?? '') !== '') {
                $id = (string) $query['v'];
            } elseif (preg_match('#^(?:embed|shorts)/([A-Za-z0-9_-]{11})$#', $path, $matches)) {
                $id = $matches[1];
            }
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    private function wellnessCategoryLabel(string $category): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $category ?: 'general'));
    }

    public function resourceDetail($resourceId)
    {
        $resource = Resource::where('is_active', true)->findOrFail($resourceId);

        return view('student.resource-detail', compact('resource'));
    }

    // Emergency Resources
    public function emergencyResources()
    {
        $hotlines = collect();
        if (Schema::hasTable('emergency_hotlines')) {
            $query = EmergencyHotline::where('is_active', true);

            // The project has two historical hotline schemas. Select an
            // ordering column only when it exists so the safety page does not
            // fail during a compatibility deployment.
            if (Schema::hasColumn('emergency_hotlines', 'priority')) {
                $query->orderByDesc('priority');
            } elseif (Schema::hasColumn('emergency_hotlines', 'type')) {
                $query->orderBy('type');
            }
            if (Schema::hasColumn('emergency_hotlines', 'name')) {
                $query->orderBy('name');
            }
            $hotlines = $query->get();
        }

        // Keep a reviewed, local fallback available when the configured table
        // has no Philippine entries. These claims intentionally avoid
        // unsupported availability or confidentiality promises.
        $hasPhilippineHotlines = $hotlines->contains(function ($hotline) {
            return strcasecmp((string) ($hotline->country ?? ''), 'Philippines') === 0;
        });
        $fallbackHotlines = [
            [
                'name' => 'National Center for Mental Health (NCMH)',
                'phone' => '1553',
                'description' => 'Crisis support contact listed in the existing student safety directory.',
                'availability' => null,
            ],
            [
                'name' => 'National Center for Mental Health (NCMH)',
                'phone' => '0917-899-8727',
                'description' => 'Additional NCMH contact listed in the existing student safety directory.',
                'availability' => null,
            ],
            [
                'name' => 'Hopeline Philippines',
                'phone' => '2919',
                'description' => 'Toll-free calling option for Globe/TM subscribers.',
                'availability' => null,
            ],
        ];

        return view('student.emergency-resources', compact(
            'hotlines',
            'fallbackHotlines',
            'hasPhilippineHotlines'
        ));
    }
    // Counselor Directory
    public function counselorDirectory()
    {
        $counselors = app(\App\Services\CounselorDirectoryService::class)->eligibleCounselors();
        
        return view('student.counselor-directory', compact('counselors'));
    }

    // Store Counseling Request
    public function storeCounselingRequest(Request $request)
    {
        $validated = $request->validate([
            'counselor_id' => 'required|integer',
            'category' => 'required|in:academic,anxiety,personal,emotional,other',
            'description' => 'required|string|min:10|max:1000',
            'preferred_date' => 'nullable|date|after:today',
        ]);

        if (!app(\App\Services\CounselorDirectoryService::class)->isEligibleCounselor((int) $validated['counselor_id'])) {
            return back()->withErrors(['counselor_id' => 'That counselor is not currently available for requests.']);
        }

        DB::transaction(function () use ($request) {
            $appointment = \App\Models\Appointment::create([
                'student_id' => Auth::id(),
                'counselor_id' => $request->counselor_id,
                'category' => $request->category,
                'description' => $request->description,
                'preferred_date' => $request->preferred_date,
                'appointment_date' => $request->preferred_date ?? now()->addDays(3),
                'status' => 'pending',
            ]);
            \App\Services\NotificationService::appointmentRequested($appointment);
        });

        return back()->with('success', 'Counseling request submitted successfully!');
    }

    // View Counseling Requests
    public function counselingRequests()
    {
        $lifecycle = app(AppointmentLifecycleService::class);
        $now = now();
        $allRequests = Appointment::where('student_id', Auth::id())
            ->with('counselor')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Appointment $appointment) use ($lifecycle, $now) {
                $displayStatus = $lifecycle->displayStatus($appointment, $now);
                $scheduledAt = $appointment->appointment_date ?: $appointment->preferred_date;

                $appointment->display_status = $displayStatus;
                $appointment->display_status_label = $lifecycle->displayStatusLabel($appointment, $now);
                $appointment->can_message = $lifecycle->studentCanMessage($appointment, $now);
                $appointment->can_call = $lifecycle->studentCanCall($appointment, $now);
                $appointment->scheduled_at = $scheduledAt;
                $appointment->status_note = match ($displayStatus) {
                    'pending' => 'Your request is waiting for counselor approval.',
                    'confirmed' => 'Your session is confirmed. Messaging and calls follow the approved session window.',
                    'needs_review' => 'The scheduled window has passed. The counselor must verify the outcome before access can continue.',
                    'completed' => 'This session has a verified completed outcome. Conversation history remains available where authorized.',
                    'failed' => 'This session was verified as not held. You can request another appointment if you need support.',
                    'cancelled' => 'This appointment was cancelled and cannot be used for calls or messaging.',
                    default => 'Review the appointment details below.',
                };

                return $appointment;
            });

        $allowedFilters = ['all', 'pending', 'confirmed', 'needs_review', 'completed', 'failed', 'cancelled'];
        $activeFilter = strtolower((string) request()->query('status', 'all'));
        if (!in_array($activeFilter, $allowedFilters, true)) {
            $activeFilter = 'all';
        }

        $statusCounts = $allRequests->groupBy('display_status')->map->count();
        $requests = $activeFilter === 'all'
            ? $allRequests
            : $allRequests->where('display_status', $activeFilter)->values();

        return view('student.counseling-requests', compact(
            'requests', 'statusCounts', 'activeFilter', 'allowedFilters'
        ));
    }

    // View Appointments
    public function appointments()
    {
        $today = now()->startOfDay();

        $appointments = Appointment::where('student_id', Auth::id())
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', '>=', $today)
            ->with('counselor')
            ->orderBy('appointment_date', 'asc')
            ->paginate(10);

        $historyAppointments = Appointment::where('student_id', Auth::id())
            ->where(function ($query) use ($today) {
                $query->whereIn('status', ['pending', 'needs_review', 'completed', 'failed', 'cancelled'])
                    ->orWhere(function ($pastConfirmed) use ($today) {
                        $pastConfirmed->where('status', 'confirmed')
                            ->whereDate('appointment_date', '<', $today);
                    });
            })
            ->with('counselor')
            ->orderByDesc('appointment_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('student.appointments', compact('appointments', 'historyAppointments'));
    }
    public function peerSupport()
    {
        return redirect()->route('student.community');
    }

    public function psychosocial()
    {
        return redirect()->route('student.resources', ['section' => 'support']);
    }

    // Schedule Appointment
    public function scheduleAppointment(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'appointment_date' => 'required|date|after:now',
            'time_slot' => 'required|string',
        ]);

        $appointment = \App\Models\Appointment::findOrFail($request->appointment_id);
        
        if ($appointment->student_id !== Auth::id() || !in_array($appointment->status, ['pending', 'confirmed'], true) || $appointment->session_outcome !== null) {
            return back()->with('error', 'This appointment is not available for scheduling.');
        }

        $oldStatus = $appointment->status;
        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'time_slot' => $request->time_slot,
            'status' => 'confirmed',
        ]);

        AppointmentStatusHistory::create([
            'appointment_id' => $appointment->id,
            'from_status' => $oldStatus,
            'to_status' => 'confirmed',
            'changed_by' => Auth::id(),
            'reason' => 'Student scheduled appointment.',
        ]);

        return back()->with('success', 'Appointment scheduled successfully!');
    }

    /**
     * Update student profile (bio and picture)
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'student_id' => 'nullable|string|max:100',
            'year_level' => 'nullable|string|max:100',
            'program' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:500',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = Auth::user();
        
        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            // Delete old picture if exists
            if ($user->profile_picture && file_exists(public_path($user->profile_picture))) {
                unlink(public_path($user->profile_picture));
            }
            
            // Ensure upload directory exists
            $uploadDir = public_path('uploads/profiles');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Store new picture
            $file = $request->file('profile_picture');
            $filename = 'profile_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $user->profile_picture = 'uploads/profiles/' . $filename;
        }
        
        // Update user data
        $user->name = $request->name;
        $user->email = $request->email;
        $user->student_id = $request->student_id;
        $user->year_level = $request->year_level;
        $user->program = $request->program;
        $user->bio = $request->bio;
        $user->save();
        
        return redirect()->back()->with('success', 'Profile updated successfully!');
    }

    /**
     * Get notifications for AJAX
     */
    public function getNotifications()
    {
        $notifications = \App\Models\Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take(20)
            ->get(['id', 'user_id', 'type', 'title', 'message', 'icon', 'link', 'is_read', 'priority', 'created_at', 'updated_at']);
        
        $unreadCount = \App\Models\Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();
        
        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead($id)
    {
        $notification = \App\Models\Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        $notification->markAsRead();
        
        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsRead()
    {
        \App\Models\Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        return response()->json(['success' => true]);
    }

    /**
     * Delete notification
     */
    public function deleteNotification($id)
    {
        $notification = \App\Models\Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        $notification->delete();
        
        return response()->json(['success' => true]);
    }

    /**
     * Show mental status dashboard
     */
    public function mentalStatus()
    {
        return view('student.mental-status');
    }
    
    /**
     * Show appointment request form
     */
    public function showRequestAppointmentForm()
    {
        return view('student.request-appointment');
    }

    /**
     * Submit appointment request
     */
    public function submitAppointmentRequest(Request $request)
    {
        $request->validate([
            'category' => 'required|in:academic,anxiety,personal,emotional,crisis,other',
            'reason' => 'required|string|min:10|max:2000',
            'preferred_date' => 'nullable|date|after:today',
            'time_slot' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $appointment = \App\Models\Appointment::create([
                'student_id' => Auth::id(),
                'counselor_id' => null, // Will be assigned when counselor approves
                'category' => $request->category,
                'reason' => $request->reason,
                'notes' => $request->reason, // Store reason in notes field too
                'preferred_date' => $request->preferred_date,
                'time_slot' => $request->time_slot,
                'status' => 'pending',
                'appointment_date' => $request->preferred_date ?? now()->addDays(3), // Default 3 days from now
            ]);
            \App\Services\NotificationService::appointmentRequested($appointment);
        });

        return redirect()->route('student.request-appointment')
            ->with('success', 'Appointment request submitted successfully! A counselor will review and confirm your appointment soon.');
    }

    /**
     * Get mental status data via API
     */
    public function getMentalStatusData(Request $request)
    {
        $mentalStatusService = new \App\Services\MentalStatusService();
        $userId = auth()->id();
        
        $dailyStatus = $mentalStatusService->getDailyStatus($userId);
        $weeklyTrend = $mentalStatusService->getWeeklyTrend($userId);
        
        return response()->json([
            'success' => true,
            'daily' => $dailyStatus,
            'weekly' => $weeklyTrend
        ]);
    }

    /**
     * Get the student's current non-clinical wellbeing indicator.
     */
    public function getWellnessScore(Request $request)
    {
        $progress = \App\Models\WellbeingProgress::getOrCreateToday(auth()->id());
        $score = (int) $progress->wellbeing_score;

        if ($score >= 80) {
            [$level, $barColor, $barLabel, $description] = ['high', '#10b981', 'High wellbeing', 'Your current non-clinical wellbeing indicator is high.'];
        } elseif ($score >= 60) {
            [$level, $barColor, $barLabel, $description] = ['positive', '#22c55e', 'Generally positive', 'Your current non-clinical wellbeing indicator is generally positive.'];
        } elseif ($score >= 40) {
            [$level, $barColor, $barLabel, $description] = ['monitor', '#f59e0b', 'Monitor', 'Your current non-clinical wellbeing indicator suggests checking in with yourself.'];
        } else {
            [$level, $barColor, $barLabel, $description] = ['support', '#ef4444', 'Support suggested', 'Your current non-clinical wellbeing indicator suggests that additional support may help.'];
        }

        $appointment = \App\Models\Appointment::where('student_id', auth()->id())
            ->where('status', 'confirmed')
            ->where('appointment_date', '>=', now())
            ->with('counselor')
            ->orderBy('appointment_date', 'asc')
            ->first();

        $appointmentData = [
            'exists' => false,
            'appointment_status' => null,
            'counselor_name' => null,
            'counselor_status' => null,
            'message' => null,
        ];

        if ($appointment) {
            $counselor = $appointment->counselor;
            $counselorStatus = $counselor->status ?? 'offline';
            $counselorAvailable = in_array($counselorStatus, ['online', 'available_later']);
            $appointmentTime = optional($appointment->appointment_date)->format('M j, Y \a\t g:i A');
            $availabilityText = $counselorAvailable
                ? 'Your counselor is currently available.'
                : 'Your counselor is currently ' . $counselorStatus . '.';

            $appointmentData = [
                'exists' => true,
                'appointment_status' => ucfirst($appointment->status),
                'counselor_name' => $counselor->name ?? 'your counselor',
                'counselor_status' => $counselorStatus,
                'message' => "You have a confirmed appointment with " . ($counselor->name ?? 'your counselor') . " on {$appointmentTime}. {$availabilityText}",
            ];
        }

        return response()->json([
            'success' => true,
            'score'       => $score,
            'level'       => $level,
            'bar_color'   => $barColor,
            'bar_label'   => $barLabel,
            'description' => $description,
            'entry_count' => $progress->entries_count,
            'score_type' => 'non_clinical_wellbeing_indicator',
            'appointment' => $appointmentData,
        ]);
    }
    
    /**
     * Show unified Insights dashboard (combines Dashboard + Journal + Mental Status)
     */
    public function resourceRecommendations(Request $request, \App\Services\MagiResourceRecommendationService $recommendations)
    {
        $data = $request->validate([
            'refresh' => ['sometimes', 'boolean'],
            // Explicit one-time authorization for cross-session excerpts.
            'consent_to_history_use' => ['required', 'accepted'],
        ]);

        return response()->json($recommendations->get($request->user(), (bool) ($data['refresh'] ?? false)))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function insights()
    {
        $user = Auth::user();
        
        // === OPTIMIZED: Single query with aggregation instead of 365+ loop queries ===
        
        // Mood weight mapping
        $moodWeights = [
            // These are descriptive display weights only; they are not a
            // diagnostic score and unknown labels are intentionally excluded.
            'happy' => 100,
            'joy' => 100,
            'excited' => 85,
            'calm' => 75,
            'neutral' => 50,
            'sad' => 20,
            'sadness' => 20,
            'grief' => 15,
            'anxious' => 30,
            'anxiety' => 30,
            'fear' => 25,
            'stressed' => 25,
            'stress' => 25,
            'overwhelm' => 20,
            'angry' => 15,
            'anger' => 15,
        ];
        
        // Get all emotion counts with single query
        $emotionCounts = EmotionalEntry::where('user_id', $user->id)
            ->groupBy('detected_emotion')
            ->selectRaw('detected_emotion, COUNT(*) as count')
            ->pluck('count', 'detected_emotion')
            ->toArray();
        
        // Fill missing emotions with 0
        foreach (['happy', 'sad', 'anxious', 'angry', 'neutral'] as $emotion) {
            if (!isset($emotionCounts[$emotion])) {
                $emotionCounts[$emotion] = 0;
            }
        }
        
        // EmotionalEntry rows are individual messages/reflections.  A
        // conversation is counted from the conversation-session table so the
        // dashboard never labels messages as conversations.
        $totalEntries = EmotionalEntry::where('user_id', $user->id)->count();
        $totalConversations = \App\Models\AIConversationSession::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->count();
        $thisWeek = \App\Models\AIConversationSession::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->whereHas('entries', function ($query) {
                $query->where('created_at', '>=', now()->startOfWeek());
            })
            ->count();
        $thisWeekMessages = EmotionalEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
        
        // Today's messages
        $todayMessages = EmotionalEntry::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();
        
        // === OPTIMIZED: Streak calculation - 1 query instead of 365 queries ===
        // Get all distinct dates with entries, ordered by date
        $datesWithEntries = EmotionalEntry::where('user_id', $user->id)
            ->selectRaw('DATE(created_at) as entry_date')
            ->distinct()
            ->orderBy('entry_date', 'desc')
            ->limit(400)  // Safety limit
            ->pluck('entry_date')
            ->map(fn($date) => \Carbon\Carbon::parse($date)->startOfDay());
        
        // Calculate streak
        $streak = 0;
        $currentDate = now()->startOfDay();
        foreach ($datesWithEntries as $entryDate) {
            if ($entryDate->format('Y-m-d') === $currentDate->format('Y-m-d')) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
        }
        
        // Dominant emotion is unavailable until at least one signal exists.
        $dominantEmotion = $totalEntries > 0
            ? collect($emotionCounts)->sortDesc()->keys()->first()
            : null;

        // Only mapped emotion labels contribute to this non-clinical indicator.
        // Unknown labels are excluded rather than silently treated as neutral.
        $knownMoodCount = 0;
        $totalScore = 0;
        $moodSamples = [];
        foreach ($emotionCounts as $emotion => $count) {
            if (!array_key_exists($emotion, $moodWeights)) {
                continue;
            }
            $knownMoodCount += (int) $count;
            $totalScore += $moodWeights[$emotion] * (int) $count;
            for ($i = 0; $i < (int) $count; $i++) {
                $moodSamples[] = $moodWeights[$emotion];
            }
        }

        $moodScore = $knownMoodCount > 0 ? (int) round($totalScore / $knownMoodCount) : null;
        $avgMood = $moodScore === null ? null : round($moodScore / 10, 1);
        
        // Today's emotion distribution (single query)
        $todayEmotionCounts = EmotionalEntry::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->groupBy('detected_emotion')
            ->selectRaw('detected_emotion, COUNT(*) as count')
            ->pluck('count', 'detected_emotion')
            ->toArray();
        
        // Fill missing emotions with 0
        foreach (['happy', 'sad', 'anxious', 'angry', 'neutral'] as $emotion) {
            if (!isset($todayEmotionCounts[$emotion])) {
                $todayEmotionCounts[$emotion] = 0;
            }
        }
        
        // === OPTIMIZED: Weekly activity and mood scores - 1 query instead of 14 queries ===
        $weeklyData = EmotionalEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as entry_date, detected_emotion, COUNT(*) as count')
            ->groupBy('entry_date', 'detected_emotion')
            ->orderBy('entry_date')
            ->get();
        
        // Process weekly data
        $weeklyActivity = [];
        $weeklyMoodScores = [];
        $weeklyDataByDate = $weeklyData->groupBy('entry_date');
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $dateStr = $date->format('Y-m-d');
            
            // Get entries for this day
            $dayData = $weeklyDataByDate->get($dateStr, collect());
            $dayCount = $dayData->sum('count');
            
            $weeklyActivity[] = [
                'date' => $date->format('D'),
                'count' => $dayCount
            ];
            
            // Calculate a score only when this day has mapped emotions.
            $dayKnownCount = 0;
            $dayTotalScore = 0;
            foreach ($dayData as $entry) {
                if (!array_key_exists($entry->detected_emotion, $moodWeights)) {
                    continue;
                }
                $dayKnownCount += (int) $entry->count;
                $dayTotalScore += $moodWeights[$entry->detected_emotion] * (int) $entry->count;
            }
            $dayMoodScore = $dayKnownCount > 0 ? round($dayTotalScore / $dayKnownCount) : null;
            $weeklyMoodScores[] = [
                'date' => $date->format('M d'),
                'score' => $dayMoodScore
            ];
        }
        $hasMoodTrendData = collect($weeklyMoodScores)
            ->contains(fn (array $day) => $day['score'] !== null);
        
        // Recent entries
        $recentEntries = EmotionalEntry::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Only this student's visible sessions may appear in the history.
        $insightSessions = \App\Models\AIConversationSession::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->whereHas('entries', fn ($query) => $query->where('user_id', $user->id))
            ->with(['entries' => fn ($query) => $query->where('user_id', $user->id)
                ->oldest()->select('id', 'user_id', 'session_id', 'entry_text', 'created_at')])
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        // Counts reflect recorded labels, not inferred mood scores. Unknown
        // labels are omitted and no missing dates are fabricated as emotions.
        $trendObservations = EmotionalEntry::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMonths(12)->startOfDay())
            ->whereNotNull('detected_emotion')
            ->where('detected_emotion', '!=', '')
            ->orderBy('created_at')
            ->get(['detected_emotion', 'created_at']);

        $insightRecommendationState = app(\App\Services\StudentRecommendationAvailability::class)
            ->forStudent($user);
        
        // Data-derived indicators.  Empty histories remain unavailable rather
        // than receiving a baseline or an inverse "stress" score.
        $activeDaysLastSeven = $weeklyData->pluck('entry_date')->unique()->count();
        $consistencyRate = $totalEntries > 0
            ? (int) round(($activeDaysLastSeven / 7) * 100)
            : null;
        $stability = null;
        if (count($moodSamples) >= 3) {
            $sampleMean = array_sum($moodSamples) / count($moodSamples);
            $variance = array_sum(array_map(
                fn ($value) => ($value - $sampleMean) ** 2,
                $moodSamples
            )) / count($moodSamples);
            // A descriptive consistency indicator, not a clinical measure.
            $stability = (int) round(max(0, min(100, 100 - sqrt($variance))));
        }

        $progress = [
            'stability' => $stability,
            'stress' => null,
            'consistency' => $consistencyRate
        ];
        
        $moodHistory = [];
        try {
            $moodHistory = \App\Models\MoodCheckin::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();
        } catch (\Exception $e) {
            // MoodCheckin table may not exist
            \Log::warning("MoodCheckin query failed in userProfile: " . $e->getMessage());
            $moodHistory = collect([]);
        }
        
        // Mental health monitoring data
        $mentalHealthService = app(\App\Services\MentalHealthMonitoringService::class);
        $mentalStatus = $mentalHealthService->getMentalStatus($user->id);
        $hasWellbeingEvidence = \App\Models\WellbeingProgress::where('user_id', $user->id)->exists()
            || \App\Models\UsagePattern::where('student_id', $user->id)->exists();
        if (!$hasWellbeingEvidence) {
            $mentalStatus = null;
        }
        $usagePatterns = $mentalHealthService->getUsagePatterns($user->id, 7);

        $upcomingAppointment = \App\Models\Appointment::where('student_id', $user->id)
            ->where('status', 'confirmed')
            ->where('appointment_date', '>=', now())
            ->with('counselor')
            ->orderBy('appointment_date', 'asc')
            ->first();
        
        return view('student.insights', compact(
            'totalEntries',
            'totalConversations',
            'thisWeek',
            'thisWeekMessages',
            'todayMessages',
            'streak',
            'emotionCounts',
            'todayEmotionCounts',
            'dominantEmotion',
            'moodScore',
            'avgMood',
            'weeklyActivity',
            'weeklyMoodScores',
            'hasMoodTrendData',
            'recentEntries',
            'insightSessions',
            'trendObservations',
            'insightRecommendationState',
            'progress',
            'activeDaysLastSeven',
            'moodHistory',
            'mentalStatus',
            'usagePatterns',
            'upcomingAppointment'
        ));
    }

    // ============ SOCIAL PLATFORM METHODS ============

    /**
     * Show unified social feed (peer-support + community)
     */
    public function socialFeed()
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $userId = Auth::id();
        
        $feed = $service->getActivityFeed($userId, 20);
        $trending = $service->getTrendingPosts(10);
        $suggested = $service->getSuggestedUsers($userId, 5);
        $stats = $service->getSocialStats();
        
        return view('student.social-feed', compact('feed', 'trending', 'suggested', 'stats'));
    }

    /**
     * Show user profile
     */
    public function userProfile($userId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $currentUserId = Auth::id();
        
        $profileData = $service->getUserProfile($userId);
        $userPosts = $service->getUserPosts($userId);
        $isFollowing = $service->isFollowing($currentUserId, $userId);
        $mutualConnections = $service->getMutualConnections($currentUserId, $userId);
        
        return view('student.user-profile', compact('profileData', 'userPosts', 'isFollowing', 'mutualConnections'));
    }

    /**
     * Update user profile
     */
    public function updateUserProfile(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string|max:500',
            'interests' => 'nullable|array',
            'location' => 'nullable|string|max:100',
            'is_public' => 'nullable|boolean',
        ]);

        $service = app(\App\Services\SocialPlatformService::class);
        $service->createOrUpdateProfile(Auth::id(), $request->all());
        
        return back()->with('success', 'Profile updated successfully!');
    }

    /**
     * Follow a user
     */
    public function followUser($userId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $service->followUser(Auth::id(), $userId);
        
        // Create notification
        $service->createNotification(
            $userId,
            Auth::id(),
            'follow',
            Auth::user()->name . ' started following you',
            "/student/user-profile/{$userId}"
        );
        
        return back()->with('success', 'You are now following this user!');
    }

    /**
     * Unfollow a user
     */
    public function unfollowUser($userId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $service->unfollowUser(Auth::id(), $userId);
        
        return back()->with('success', 'You have unfollowed this user.');
    }

    /**
     * Get followers list
     */
    public function followers($userId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $followers = $service->getFollowers($userId);
        $user = User::findOrFail($userId);
        
        return view('student.followers', compact('followers', 'user'));
    }

    /**
     * Get following list
     */
    public function following($userId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $following = $service->getFollowing($userId);
        $user = User::findOrFail($userId);
        
        return view('student.following', compact('following', 'user'));
    }

    /**
     * Add comment to post
     */
    public function addComment(Request $request, $postId)
    {
        $request->validate([
            'content' => 'required|string|min:1|max:1000',
            'parent_id' => 'nullable|integer|min:1',
        ]);

        $comment = app(CommunityService::class)->addComment((int) $postId, (int) Auth::id(), $request->content, $request->integer('parent_id') ?: null);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'comment_id' => $comment->id], 201);
        }
        
        return back()->with('success', 'Comment added successfully!');
    }

    /**
     * Get post comments (AJAX)
     */
    public function getPostComments($postId)
    {
        $comments = app(CommunityService::class)->getPostComments((int) $postId);
        
        return response()->json([
            'success' => true,
            'comments' => $comments
        ]);
    }

    public function updateComment(Request $request, $commentId)
    {
        $validated = $request->validate(['content' => 'required|string|min:1|max:1000']);
        app(CommunityService::class)->updateComment((int) $commentId, (int) Auth::id(), $validated['content']);
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Comment updated.');
    }

    /**
     * Delete comment
     */
    public function deleteComment(Request $request, $commentId)
    {
        app(CommunityService::class)->deleteComment((int) $commentId, (int) Auth::id());
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        
        return back()->with('success', 'Comment deleted successfully!');
    }

    /**
     * Share a post
     */
    public function sharePost($postId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $service->sharePost($postId, Auth::id());
        
        return back()->with('success', 'Post shared successfully!');
    }

    /**
     * Get social notifications
     */
    public function getSocialNotifications()
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $notifications = $service->getNotifications(Auth::id());
        
        return view('student.notifications', compact('notifications'));
    }

    /**
     * Mark social notification as read
     */
    public function markSocialNotificationRead($notificationId)
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $service->markNotificationRead($notificationId);
        
        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Search posts
     */
    public function searchPosts(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'category' => 'nullable|in:academic,mental-health,relationships,general,all',
        ]);

        $service = app(\App\Services\SocialPlatformService::class);
        $results = $service->searchPosts($request->q, $request->category);
        
        return view('student.search-results', compact('results'));
    }

    /**
     * Get trending posts
     */
    public function trendingPosts()
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $posts = $service->getTrendingPosts(30);
        
        return view('student.trending-posts', compact('posts'));
    }

    /**
     * Get recommended posts
     */
    public function recommendedPosts()
    {
        $service = app(\App\Services\SocialPlatformService::class);
        $posts = $service->getRecommendedPosts(Auth::id(), 30);
        
        return view('student.recommended-posts', compact('posts'));
    }

    // ============ DISCUSSION FORUM METHODS ============

    /**
     * Show discussion forums
     */
    public function discussionForums()
    {
        $service = app(DiscussionForumService::class);
        $forums = $service->getAllForums();
        $stats = $service->getActivityStats();
        
        return view('student.discussion-forums', compact('forums', 'stats'));
    }

    /**
     * Show forum with threads
     */
    public function viewForum($forumId)
    {
        $service = app(DiscussionForumService::class);
        $data = $service->getForumWithThreads($forumId);
        $stats = $service->getActivityStats($forumId);
        
        return view('student.forum-threads', array_merge($data, compact('stats')));
    }

    /**
     * Create new thread
     */
    public function createThread(Request $request, $forumId)
    {
        $request->validate([
            'title' => 'required|string|min:5|max:200',
            'content' => 'required|string|min:10|max:5000',
        ]);

        $service = app(DiscussionForumService::class);
        $thread = $service->createThread($forumId, $request->title, $request->content);
        
        return redirect()->route('student.forum.thread', $thread->id)
            ->with('success', 'Thread created successfully!');
    }

    /**
     * Show thread with replies
     */
    public function viewThread($threadId)
    {
        $service = app(DiscussionForumService::class);
        $data = $service->getThreadWithReplies($threadId);
        $helpfulReplies = $service->getMostHelpfulReplies($threadId);
        
        return view('student.forum-thread-detail', array_merge($data, compact('helpfulReplies')));
    }

    /**
     * Reply to thread
     */
    public function replyToThread(Request $request, $threadId)
    {
        $request->validate([
            'content' => 'required|string|min:5|max:2000',
        ]);

        $service = app(DiscussionForumService::class);
        $reply = $service->replyToThread($threadId, $request->content);
        
        return back()->with('success', 'Reply posted successfully!');
    }

    /**
     * Like a reply
     */
    public function likeReply($replyId)
    {
        $service = app(DiscussionForumService::class);
        $reply = $service->likeReply($replyId);
        
        return response()->json([
            'success' => true,
            'likes_count' => $reply->likes_count
        ]);
    }

    /**
     * Mark reply as helpful
     */
    public function markReplyHelpful($replyId)
    {
        $service = app(DiscussionForumService::class);
        $reply = $service->markReplyAsHelpful($replyId);
        
        return response()->json([
            'success' => true,
            'is_helpful' => $reply->is_helpful
        ]);
    }

    /**
     * Get user forum activity
     */
    public function forumActivity()
    {
        $service = app(DiscussionForumService::class);
        $activity = $service->getUserActivity(Auth::id());
        
        return view('student.forum-activity', compact('activity'));
    }

    /**
     * Search forum threads
     */
    public function searchForumThreads(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $service = app(DiscussionForumService::class);
        $results = $service->searchThreads($request->q);
        
        return view('student.forum-search-results', compact('results'));
    }

    /**
     * Get trending forum threads
     */
    public function trendingForumThreads()
    {
        $service = app(DiscussionForumService::class);
        $threads = $service->getTrendingThreads();
        
        return view('student.trending-forum-threads', compact('threads'));
    }

    /**
     * Get ML-generated response from unified ML API
     * Falls back gracefully if API is unavailable
     */
    private function getMLResponse($message, $userId, $conversationHistory = [], $aiPreferenceMode = 'companion')
    {
        try {
            $url = rtrim((string) config('services.mindcare_ml.url'), '/') . '/chat';
            
            // Format history for AI
            $formattedHistory = [];
            foreach ($conversationHistory as $msg) {
                $formattedHistory[] = [
                    'user' => $msg['user'] ?? '',
                    'ai' => $msg['ai'] ?? '',
                    'emotion' => $msg['emotion'] ?? 'neutral',
                    'timestamp' => $msg['timestamp'] ?? ''
                ];
            }
            
            $data = [
                'message' => $message,
                'user_id' => $userId,
                'conversation_history' => $formattedHistory,
                'ai_preference_mode' => $aiPreferenceMode,
                'profile' => [
                    'name' => Auth::user()->name ?? 'User',
                    'email' => Auth::user()->email ?? '',
                    'student_id' => Auth::user()->student_id ?? '',
                    'program' => Auth::user()->program ?? ''
                ]
            ];

            // Use the same authenticated Laravel-to-Flask boundary as the
            // primary AI chat controller. Unsigned calls fail when Phase 4A
            // HMAC authentication is enabled.
            $authClient = app(InternalMemoryAuthClient::class);
            $result = $authClient->post(
                'memory.chat',
                Auth::id(),
                $data,
                $authClient->memoryAuthorizedFor(Auth::id())
            );

            if (!empty($result['response'])) {
                return $result['response'];
            }
        } catch (\Exception $e) {
            \Log::warning('ML API unavailable: ' . $e->getMessage());
        }

        // Return null to trigger fallback
        return null;
    }

    /**
     * Delete message for current user only (private deletion)
     */
    public function deleteMessageForMe($messageId)
    {
        try {
            $message = CounselorMessage::findOrFail($messageId);
            $userId = Auth::id();

            // Verify user is a participant
            if ($message->student_id != $userId && $message->counselor_id != $userId && $message->sender_id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $service = new MessageDeletionService();
            $success = $service->deleteForMe($message, $userId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'action' => 'delete_for_me',
                    'message_id' => $message->id
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete message'
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('deleteMessageForMe error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Message not found'
            ], 404);
        }
    }

    private function communityPostPayload(CommunityMessage $message, int $viewerId, bool $isPrivateView = false): array
    {
        $message->loadMissing([
            'user:id,name,role',
            'resource:id,title,description,category,is_active',
        ]);

        $anonymous = (bool) $message->is_anonymous && $message->user?->role === 'student';
        $authorName = $anonymous ? 'Anonymous student' : ($message->user?->name ?? 'Community member');
        $resource = $message->resource && $message->resource->is_active ? [
            'id' => (int) $message->resource->id,
            'title' => $message->resource->title,
            'description' => $message->resource->description,
            'category' => $message->resource->category,
            'url' => route('student.community.resource.show', $message->resource->id),
        ] : null;
        $visualLayout = is_array($message->visual_layout) ? $message->visual_layout : null;
        $hasCompositionPhoto = $message->post_format === 'visual'
            && ($visualLayout['version'] ?? null) === 2
            && !empty($message->image_path);

        return [
            'id' => $message->id,
            'title' => $message->title,
            'message' => $message->post_format === 'visual'
                ? ($message->rich_content ? '' : (string) ($message->visual_text ?: $message->message))
                : (string) $message->message,
            'visual_text' => $message->post_format === 'visual' ? (string) $message->visual_text : null,
            'category' => $message->category,
            'category_label' => config('community.categories.' . $message->category . '.label', ucfirst(str_replace('-', ' ', (string) $message->category))),
            'category_icon' => config('community.categories.' . $message->category . '.icon', ''),
            'support_label' => $message->support_label ? config('community.support_labels.' . $message->support_label, $message->support_label) : null,
            'post_format' => $message->post_format ?: 'text',
            'rich_content' => in_array($message->post_format, ['rich', 'visual'], true) ? $message->rich_content : null,
            'post_style' => $message->post_format === 'rich' ? $message->post_style : null,
            'visual_layout' => $message->post_format === 'visual' ? $visualLayout : null,
            'image_url' => ($message->visual_media_path || $message->image_path)
                ? (($isPrivateView && (int) Auth::user()?->id === $viewerId && Auth::user()?->role === 'admin' && !$message->is_approved)
                    ? route('admin.community-moderation.image', $message->id)
                    : route('student.community.image', $message->id))
                : null,
            'composition_image_url' => $hasCompositionPhoto
                ? route('student.community.image', ['message' => $message->id, 'composition' => 1])
                : null,
            'source_image_url' => (int) $message->user_id === $viewerId && $message->image_path
                ? route('student.community.image', ['message' => $message->id, 'source' => 1])
                : null,
            'resource' => $resource,
            'author' => [
                'name' => $authorName,
                'initial' => $anonymous ? 'A' : mb_strtoupper(mb_substr($authorName, 0, 1)),
                'anonymous' => $anonymous,
                'role_label' => $anonymous ? null : $this->communityRoleLabel($message->user?->role),
            ],
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'is_pinned' => (bool) $message->is_pinned,
            'replies_count' => (int) $message->replies_count,
            'reaction_counts' => [
                'like' => (int) ($message->like_reactions_count ?? 0),
                'heart' => (int) ($message->heart_reactions_count ?? 0),
                'support' => (int) ($message->support_reactions_count ?? $message->likes_count ?? 0),
                'heard' => (int) ($message->heard_reactions_count ?? 0),
                'helpful' => (int) ($message->helpful_reactions_count ?? 0),
                'encouragement' => (int) ($message->encouragement_reactions_count ?? 0),
            ],
            'viewer_reaction' => $message->relationLoaded('reactions') ? optional($message->reactions->first())->reaction : null,
            'is_owner' => (int) $message->user_id === $viewerId,
            'moderation_status' => $isPrivateView ? ($message->moderation_status ?? 'pending') : null,
            'is_approved' => $isPrivateView ? (bool) $message->is_approved : null,
            'can_moderate' => $isPrivateView && Auth::user()?->role === 'admin',
            'can_restore' => $isPrivateView && Auth::user()?->role === 'admin' && !$message->is_approved
                && \App\Models\CommunityModerationAudit::where('community_message_id', $message->id)->orderByDesc('id')->value('action') === 'hide',
        ];
    }

    private function communityRoleLabel(?string $role): ?string
    {
        return match ($role) {
            'counselor' => 'Authorized Counselor',
            'admin' => 'Guidance Admin',
            default => null,
        };
    }

    /**
     * Unsend message for everyone (global deletion)
     */
    public function unsendMessage($messageId)
    {
        try {
            $message = CounselorMessage::findOrFail($messageId);
            $userId = Auth::id();

            // Only sender can unsend
            if ($message->sender_id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $service = new MessageDeletionService();
            $success = $service->unsendForEveryone($message, $userId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'action' => 'unsend',
                    'message_id' => $message->id
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unsend message'
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('unsendMessage error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Message not found'
            ], 404);
        }
    }
}
