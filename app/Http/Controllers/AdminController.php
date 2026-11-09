<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmotionalEntry;
use App\Models\PrivacySetting;
use App\Models\CommunityReport;
use App\Models\CommunityMessage;
use App\Models\CommunityReportAudit;
use App\Models\CommunityModerationAudit;
use App\Models\AccountVerificationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\EmotionTaxonomy;
use App\Services\AccountInvitationService;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalStudents = User::where('role', 'student')->count();
        $totalCounselors = User::where('role', 'counselor')->count();
        $totalEntries = $this->analyticsEntries()->count();
        $recentEntries = $this->analyticsEntries()->with('user')->orderBy('created_at', 'desc')->take(10)->get();

        return view('admin.dashboard', compact('totalStudents', 'totalCounselors', 'totalEntries', 'recentEntries'));
    }

    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(15);
        return view('admin.users', [
            'users' => $users,
            'canReviewAccounts' => auth()->user()?->canReviewAccountVerification() ?? false,
        ]);
    }

    public function createUser()
    {
        return view('admin.create-user', [
            'canDirectlyApprove' => auth()->user()?->canReviewAccountVerification() ?? false,
        ]);
    }

    public function storeUser(Request $request)
    {
        $role = (string) $request->input('role', '');
        $isApplicantRole = in_array($role, ['student', 'counselor'], true);
        $isReviewer = $request->user()?->canReviewAccountVerification() ?? false;
        $requestedApproval = $request->boolean('approve_verified');

        if ($requestedApproval && !$isReviewer) {
            abort(403, 'Only an explicitly authorized Guidance Office reviewer may approve a student or counselor account.');
        }

        $email = $request->input('email');
        if ($isApplicantRole && is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => array_values(array_filter([
                'required', 'string', 'email:rfc', 'max:255', 'unique:users,email',
                $isApplicantRole ? 'regex:/^[^@\\s]+@minsu\\.edu\\.ph$/i' : null,
            ])),
            'password' => $isApplicantRole ? ['prohibited'] : ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => $isApplicantRole ? ['prohibited'] : ['required'],
            'role' => 'required|in:student,counselor,admin',
            'student_id' => [$role === 'student' ? 'required' : 'nullable', 'string', 'max:80', Rule::unique('users', 'student_id')],
            'year_level' => [$role === 'student' ? 'required' : 'nullable', 'nullable', 'string', 'max:40'],
            'program' => [$role === 'student' ? 'required' : 'nullable', 'nullable', 'string', 'max:160'],
            'specialization' => ['nullable', 'string', 'max:160'],
            'years_of_experience' => [$role === 'counselor' ? 'required' : 'nullable', 'nullable', 'integer', 'min:0', 'max:60'],
            'availability_schedule' => [$role === 'counselor' ? 'required' : 'nullable', 'nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:online,offline,busy,available_later'],
            'approve_verified' => ['sometimes', 'accepted'],
            'verification_basis' => [$requestedApproval ? 'required' : 'nullable', 'string', $requestedApproval ? 'min:8' : 'nullable', 'max:2000'],
            'notification_email' => [!$isApplicantRole || !$isReviewer ? 'prohibited' : 'nullable', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i'],
            'notification_email_contact_confirmed' => !$isApplicantRole || !$isReviewer
                ? ['prohibited']
                : ['sometimes', 'required_with:notification_email', 'accepted'],
            'invitation_method' => !$isApplicantRole || !$isReviewer
                ? ['prohibited']
                : ($requestedApproval ? ['required', Rule::in(['email', 'in_person'])] : ['prohibited']),
        ]);

        if ($requestedApproval && $isApplicantRole && $validated['invitation_method'] === 'email'
            && blank($validated['notification_email'] ?? null)) {
            return back()->withErrors(['notification_email' => 'A reviewer-confirmed personal Gmail is required for an email invitation.'])->withInput();
        }

        if ($requestedApproval) {
            $verificationRules = [
                'institutional_identity_confirmed' => ['required', 'accepted'],
                'eligibility_confirmed' => ['required', 'accepted'],
            ];
            if ($role === 'counselor') {
                $verificationRules['counselor_authorized'] = ['required', 'accepted'];
            }
            $request->validate($verificationRules);
        }

        if ($isApplicantRole && User::query()->where(function ($query) use ($validated): void {
            $query->whereRaw('LOWER(email) = ?', [$validated['email']])
                ->orWhereRaw('LOWER(institutional_email) = ?', [$validated['email']]);
        })->exists()) {
            return back()->withErrors(['email' => 'This MinSU institutional email already has an application or account.'])->withInput();
        }

        $userData = [
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            // Applicant-role accounts receive an inaccessible random password.
            // The owner establishes their own password through a one-time invitation.
            'password' => Hash::make($isApplicantRole ? Str::random(96) : $validated['password']),
            'role' => $validated['role'],
        ];

        // Add role-specific fields
        if ($validated['role'] === 'student') {
            $userData['student_id'] = $validated['student_id'];
            $userData['year_level'] = $validated['year_level'];
            $userData['program'] = $validated['program'];
        } elseif ($validated['role'] === 'counselor') {
            $userData['specialization'] = $validated['specialization'] ?? null;
            $userData['years_of_experience'] = $validated['years_of_experience'];
            $userData['availability_schedule'] = $validated['availability_schedule'];
            $userData['status'] = $validated['status'] ?? 'offline';
        }

        if ($isApplicantRole) {
            $approved = $requestedApproval && $isReviewer;
            $basis = $approved ? trim($validated['verification_basis']) : null;
            $userData['is_active'] = $approved;

            $user = DB::transaction(function () use ($userData, $validated, $approved, $basis, $request): User {
                $user = User::create($userData);
                $user->forceFill([
                    'is_active' => $approved,
                    'institutional_email' => $user->email,
                    'requested_role' => $user->role,
                    'approval_status' => $approved ? 'approved' : 'pending',
                    'approval_submitted_at' => null,
                    'email_verified_at' => null,
                    'notification_email' => filled($validated['notification_email'] ?? null) ? mb_strtolower(trim($validated['notification_email'])) : null,
                    'notification_email_verified_at' => null,
                    'notification_email_enabled' => filled($validated['notification_email'] ?? null),
                    'notification_email_confirmed_at' => filled($validated['notification_email'] ?? null) ? now() : null,
                    'notification_email_confirmed_by' => filled($validated['notification_email'] ?? null) ? $request->user()->id : null,
                    'password_setup_completed_at' => null,
                    'owner_onboarding_required' => true,
                    'institutional_verified_at' => $approved ? now() : null,
                    'approval_reviewer_id' => $approved ? $request->user()->id : null,
                    'approval_reviewed_at' => $approved ? now() : null,
                    'internal_review_reason' => $basis,
                    'approval_notification_status' => 'not_requested',
                    'verification_email_delivery_status' => 'not_requested',
                ])->save();

                PrivacySetting::getOrCreateForUser($user->id);
                AccountVerificationAudit::query()->create([
                    'application_user_id' => $user->id,
                    'actor_user_id' => $request->user()->id,
                    'action' => $approved ? 'admin_created_approved' : 'admin_created_pending',
                    'from_status' => null,
                    'to_status' => $approved ? 'approved' : 'pending',
                    'internal_note' => $approved
                        ? 'Admin-created account. Institutional identity and eligibility were attested by the authorized reviewer. Owner must establish a password and verify personal Gmail. Basis: ' . $basis
                        : 'Admin-created account; identity verification and approval are incomplete. Account is inactive and is not a self-registration submission.',
                    'created_at' => now(),
                ]);
                if (filled($validated['notification_email'] ?? null)) {
                    AccountVerificationAudit::query()->create([
                        'application_user_id' => $user->id,
                        'actor_user_id' => $request->user()->id,
                        'action' => 'owner_invitation_contact_confirmed',
                        'from_status' => $approved ? 'approved' : 'pending',
                        'to_status' => $approved ? 'approved' : 'pending',
                        'internal_note' => 'Authorized reviewer confirmed that the recorded personal Gmail contact belongs to the intended account holder. Gmail ownership remains unverified until owner OTP.',
                        'created_at' => now(),
                    ]);
                }

                return $user;
            });

            if ($approved) {
                $invitation = app(AccountInvitationService::class)->issue(
                    $user->fresh(),
                    (int) $request->user()->id,
                    $validated['invitation_method'],
                );
                if ($validated['invitation_method'] === 'in_person') {
                    return response()->view('admin.account-verification.manual-invitation', [
                        'applicant' => $user->fresh(),
                        'handoffToken' => $invitation['handoff_token'],
                        'activationUrl' => $invitation['activation_url'],
                        'expiresAt' => $invitation['invitation']->expires_at,
                    ])->header('Cache-Control', 'private, no-store, max-age=0')
                        ->header('Pragma', 'no-cache')
                        ->header('Referrer-Policy', 'no-referrer')
                        ->header('X-Robots-Tag', 'noindex, nofollow');
                }
                $message = $invitation['delivery_status'] === 'queued'
                    ? 'Account approved. A one-time password-setup invitation was queued for the reviewer-confirmed personal Gmail. The owner must still set a password and verify Gmail before normal access.'
                    : 'Account approved but remains access-restricted. No invitation was sent; use the reviewer page to retry or arrange a secure in-person handoff.';
                return redirect()->route('admin.users')->with($invitation['delivery_status'] === 'queued' ? 'success' : 'warning', $message);
            }

            $message = 'Account created in restricted pending status. An authorized reviewer must complete verification before an owner invitation can be issued.';

            return redirect()->route('admin.users')->with('success', $message);
        }

        $user = User::create($userData);
        PrivacySetting::getOrCreateForUser($user->id);

        return redirect()->route('admin.users')->with('success', 'User created successfully!');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return back()->with('success', 'User deleted successfully!');
    }

    public function reports()
    {
        $taxonomy = app(EmotionTaxonomy::class);

        // Overall emotion distribution
        $emotionStats = $this->analyticsEntries()->selectRaw('detected_emotion, COUNT(*) as count')
            ->groupBy('detected_emotion')
            ->orderBy('count', 'desc')
            ->get();

        // Last 7 days emotion trends
        $last7Days = [];
        $emotionTrends = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $last7Days[] = now()->subDays($i)->format('M d');
            
            $dayEmotions = $this->analyticsEntries()->whereDate('created_at', $date)
                ->selectRaw('detected_emotion, COUNT(*) as count')
                ->groupBy('detected_emotion')
                ->pluck('count', 'detected_emotion')
                ->toArray();
            
            foreach (['happy', 'sad', 'anxious', 'angry', 'neutral'] as $emotion) {
                if (!isset($emotionTrends[$emotion])) {
                    $emotionTrends[$emotion] = [];
                }
                $emotionTrends[$emotion][] = $dayEmotions[$emotion] ?? 0;
            }
        }

        // Weekly comparison (this week vs last week)
        $thisWeekStart = now()->startOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        
        $thisWeekCount = $this->analyticsEntries()->where('created_at', '>=', $thisWeekStart)->count();
        $lastWeekCount = $this->analyticsEntries()->whereBetween('created_at', [
            $lastWeekStart, 
            $lastWeekStart->copy()->endOfWeek()
        ])->count();
        
        $weeklyChange = $lastWeekCount > 0
            ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1)
            : null;

        // Crisis level distribution
        $crisisStats = $this->analyticsEntries()->selectRaw('crisis_level, COUNT(*) as count')
            ->whereNotNull('crisis_level')
            ->groupBy('crisis_level')
            ->get();

        // Most active students
        $activeStudents = $this->analyticsEntries()->with('user')
            ->selectRaw('user_id, COUNT(*) as entry_count')
            ->groupBy('user_id')
            ->orderBy('entry_count', 'desc')
            ->take(5)
            ->get();

        // Monthly trend (last 6 months)
        $monthlyData = [];
        $monthlyLabels = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyLabels[] = $month->format('M Y');
            
            $count = $this->analyticsEntries()->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            
            $monthlyData[] = $count;
        }

        // Category analytics only use entries with current analytics consent.
        // Older records without taxonomy metadata remain explicitly unclassified.
        $categoryPeriod = (string) request()->query('period', 'all');
        if (! in_array($categoryPeriod, ['all', '7', '30', '90'], true)) {
            $categoryPeriod = 'all';
        }
        $categoryPeriodStart = $categoryPeriod === 'all'
            ? null
            : now()->subDays((int) $categoryPeriod - 1)->startOfDay();
        $categoryEntries = function () use ($categoryPeriodStart) {
            $query = $this->analyticsEntries();
            if ($categoryPeriodStart !== null) {
                $query->where('created_at', '>=', $categoryPeriodStart);
            }
            return $query;
        };

        $analyticsTotal = (int) $categoryEntries()->count();
        $classifiedTotal = (int) $categoryEntries()
            ->whereNotNull('emotion_category_key')
            ->count();
        $unclassifiedCount = max(0, $analyticsTotal - $classifiedTotal);

        $categoryCounts = $categoryEntries()
            ->whereNotNull('emotion_category_key')
            ->selectRaw('emotion_category_key, COUNT(*) as count, COUNT(DISTINCT user_id) as student_count')
            ->groupBy('emotion_category_key')
            ->get()
            ->keyBy('emotion_category_key');

        $minGroupSize = (int) config('privacy.analytics_min_group_size', 5);
        $categoryVisibility = [];

        $categoryStats = collect($taxonomy->categories())->map(function (array $category) use ($categoryCounts, $classifiedTotal, $minGroupSize, &$categoryVisibility): array {
            $aggregate = $categoryCounts->get($category['key']);
            $studentCount = (int) ($aggregate->student_count ?? 0);
            $visible = $studentCount >= $minGroupSize;
            $categoryVisibility[$category['key']] = $visible;
            $count = $visible ? (int) ($aggregate->count ?? 0) : null;

            return array_merge($category, [
                'count' => $count,
                'percentage' => $visible && $classifiedTotal > 0 ? round(($count / $classifiedTotal) * 100, 1) : null,
                'student_count' => $studentCount,
                'suppressed' => ! $visible && $studentCount > 0,
            ]);
        });

        $categoryTrends = [];
        foreach ($taxonomy->categories() as $category) {
            $categoryTrends[$category['key']] = [];
        }
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayCategories = $categoryEntries()
                ->whereDate('created_at', $date)
                ->whereNotNull('emotion_category_key')
                ->selectRaw('emotion_category_key, COUNT(*) as count')
                ->groupBy('emotion_category_key')
                ->pluck('count', 'emotion_category_key');

            foreach ($categoryTrends as $key => $values) {
                $categoryTrends[$key][] = ($categoryVisibility[$key] ?? false)
                    ? (int) ($dayCategories[$key] ?? 0)
                    : null;
            }
        }

        $detailedCounts = $categoryEntries()
            ->whereNotNull('detailed_emotion_key')
            ->selectRaw('detailed_emotion_key, COUNT(*) as count')
            ->groupBy('detailed_emotion_key')
            ->pluck('count', 'detailed_emotion_key');

        $categoryDrilldown = collect($taxonomy->categories())->mapWithKeys(function (array $category) use ($taxonomy, $detailedCounts, $categoryVisibility): array {
            $emotions = collect($taxonomy->emotions())
                ->where('category_key', $category['key'])
                ->map(function (array $emotion) use ($detailedCounts): array {
                    return [
                        'key' => $emotion['key'],
                        'label' => $emotion['label'],
                        'count' => (int) ($detailedCounts[$emotion['key']] ?? 0),
                    ];
                })
                ->values()
                ->all();

            if (! ($categoryVisibility[$category['key']] ?? false)) {
                $emotions = array_map(static fn (array $emotion): array => array_merge($emotion, ['count' => null]), $emotions);
            }

            return [$category['key'] => [
                'key' => $category['key'],
                'label' => $category['label'],
                'suppressed' => ! ($categoryVisibility[$category['key']] ?? false),
                'emotions' => $emotions,
            ]];
        });

        $mostFrequentCategory = $categoryStats->filter(fn (array $category) => $category['count'] !== null)->sortByDesc('count')->first();
        $categoryDenominatorLabel = 'consenting classified messages';
        $categoryPrivacyNote = "Categories with fewer than {$minGroupSize} consenting students are suppressed.";
        $categoryPeriodLabel = match ($categoryPeriod) {
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            default => 'All available time',
        };

        return view('admin.reports', compact(
            'emotionStats',
            'last7Days',
            'emotionTrends',
            'thisWeekCount',
            'lastWeekCount',
            'weeklyChange',
            'crisisStats',
            'activeStudents',
            'monthlyData',
            'monthlyLabels',
            'categoryStats',
            'categoryTrends',
            'categoryDrilldown',
            'taxonomy',
            'analyticsTotal',
            'classifiedTotal',
            'unclassifiedCount',
            'mostFrequentCategory',
            'categoryDenominatorLabel',
            'categoryPrivacyNote',
            'categoryPeriod',
            'categoryPeriodLabel'
        ));
    }

    /**
     * Get notifications for admin (AJAX)
     */
    public function getNotifications()
    {
        $notifications = \App\Models\Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
        
        $unreadCount = \App\Models\Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();
        
        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount
        ]);
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

    /** Keep the legacy moderation URL on the single shared Community Circle feed. */
    public function communityModeration()
    {
        return redirect()->route('student.community');
    }

    /**
     * Approve a community post
     */
    public function communityImage($messageId)
    {
        $message = CommunityMessage::findOrFail((int) $messageId);
        $path = (string) ($message->visual_media_path ?: $message->image_path);
        abort_unless((bool) preg_match('/\\Acommunity_(?:media|visuals)\\/[a-f0-9]{40}\\.webp\\z/D', $path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $response = response()->file($disk->path($path), ['Content-Type' => 'image/webp']);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
    public function approveCommunityPost($postId)
    {
        $communityService = new \App\Services\CommunityService();
        $message = $communityService->approveMessage($postId, 'Approved by admin', (int) Auth::id());

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Post is now visible in the shared feed.', 'post_id' => $message->id]);
        }

        return back()->with('success', 'Post approved successfully!');
    }

    /**
     * Reject a community post
     */
    public function rejectCommunityPost(Request $request, $postId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $communityService = new \App\Services\CommunityService();
        $message = $communityService->rejectMessage($postId, $request->reason, (int) Auth::id());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Post remains out of the shared feed and the action was recorded.', 'post_id' => $message->id]);
        }

        return back()->with('success', 'Post rejected successfully!');
    }

    public function hideCommunityPost(Request $request, $postId)
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        app(\App\Services\CommunityService::class)->hidePublishedMessage((int) $postId, (int) Auth::id(), trim($validated['reason']));
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'Post hidden from the shared feed. Its content and audit history are preserved.']);
        return back()->with('success', 'Post removed from the feed. Its content and audit history are preserved.');
    }

    public function restoreCommunityPost($postId)
    {
        app(\App\Services\CommunityService::class)->restoreHiddenMessage((int) $postId, (int) Auth::id());
        if (request()->expectsJson()) return response()->json(['success' => true, 'message' => 'Post restored to the shared feed.']);
        return back()->with('success', 'Post restored to the feed.');
    }

    public function pinCommunityPost(Request $request, $postId)
    {
        $validated = $request->validate(['pinned' => 'required|boolean']);
        app(\App\Services\CommunityService::class)->pinMessage((int) $postId, (int) Auth::id(), (bool) $validated['pinned']);
        if ($request->expectsJson()) return response()->json(['success' => true, 'message' => $validated['pinned'] ? 'Post pinned in the shared feed.' : 'Post unpinned.']);
        return back()->with('success', $validated['pinned'] ? 'Community post pinned.' : 'Community post unpinned.');
    }

    public function communityReports()
    {
        $reports = CommunityReport::with(['reporter:id,name'])->whereIn('status', ['pending', 'reviewed', 'actioned', 'dismissed'])->orderByDesc('created_at')->paginate(25);
        return view('admin.community-reports', compact('reports'));
    }

    public function reviewCommunityReport(Request $request, $reportId)
    {
        $validated = $request->validate([
            'status' => 'required|in:reviewed,actioned,dismissed',
            'moderator_notes' => 'nullable|string|max:1000',
        ]);
        $report = CommunityReport::findOrFail($reportId);
        CommunityReportAudit::create([
            'report_id' => $report->id,
            'moderator_id' => Auth::id(),
            'from_status' => $report->status,
            'to_status' => $validated['status'],
            'notes' => $validated['moderator_notes'] ?? null,
        ]);
        $report->update([
            'status' => $validated['status'],
            'moderator_notes' => $validated['moderator_notes'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);
        return back()->with('success', 'Community report updated.');
    }

    public function verifyCommunityStudent($userId)
    {
        $student = User::whereKey($userId)->where('role', 'student')->firstOrFail();
        $student->update(['community_verified_at' => now(), 'community_verified_by' => Auth::id()]);
        return back()->with('success', 'Community enrollment verification recorded.');
    }

    public function unverifyCommunityStudent($userId)
    {
        $student = User::whereKey($userId)->where('role', 'student')->firstOrFail();
        $student->update(['community_verified_at' => null, 'community_verified_by' => null]);
        return back()->with('success', 'Community enrollment verification removed.');
    }
    private function analyticsEntries()
    {
        $query = EmotionalEntry::query()
            ->whereHas('user.privacySetting', fn ($query) => $query
                ->withCurrentConsent('share_analytics')
                ->where('share_emotion_data', true));

        // Gemma shadow labels are excluded from official Admin statistics until
        // the separate, explicit analytics gate is enabled. This keeps the
        // six-class production classifier authoritative by default.
        if (! config('services.mindcare_ml.detailed_emotion_shadow.admin_enabled', false)) {
            $query->where(function ($query): void {
                $query->whereNull('emotion_classification_source')
                    ->orWhere('emotion_classification_source', '!=', 'gemma_shadow');
            });
        }

        return $query;
    }
}
