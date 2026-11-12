<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CounselorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AIChatController;
use App\Http\Controllers\MentalHealthMonitoringController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\AccountOnboardingController;

// Home page - accessible to everyone
Route::get('/', function () {
    $homeUpcomingAppointment = null;
    $insightRecommendationState = null;

    if (auth()->check() && !auth()->user()->isApproved()) {
        return redirect()->route('account.status');
    }

    if (auth()->check() && auth()->user()->isStudent()) {
        $insightRecommendationState = app(\App\Services\StudentRecommendationAvailability::class)
            ->forStudent(auth()->user());
        $homeUpcomingAppointment = \App\Models\Appointment::where('student_id', auth()->id())
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', '>=', now()->startOfDay())
            ->with('counselor')
            ->orderBy('appointment_date')
            ->first();
    }

    return view('home', compact('homeUpcomingAppointment', 'insightRecommendationState'));
})->name('home');

// Diagnostic health-check endpoint - protected by auth + secure.auth
Route::get('/debug/auth-check', function () {
    return response()->json([
        'auth_check' => Auth::check(),
        'auth_id' => Auth::id(),
        'guard' => config('auth.defaults.guard'),
        'session_active' => request()->session()->isStarted(),
    ]);
})->middleware('auth', 'secure.auth');

// Public routes - redirect to dashboard if already logged in
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'sendLink'])->middleware('throttle:5,10')->name('password.email');
    Route::get('/reset-password', [PasswordRecoveryController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:10,10')->name('password.update');
    Route::get('/account/activate', [AccountOnboardingController::class, 'activateForm'])->name('account.activate');
    Route::post('/account/activate', [AccountOnboardingController::class, 'acceptInvitation'])->middleware('throttle:5,10')->name('account.activate.accept');
    Route::get('/register', [\App\Http\Controllers\RegistrationController::class, 'show'])->name('register');
    Route::post('/register', [\App\Http\Controllers\RegistrationController::class, 'submit'])->middleware('throttle:5,10')->name('register.submit');
});

// Authenticated routes - with secure session validation
Route::middleware(['auth', 'secure.auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/status', [\App\Http\Controllers\RegistrationController::class, 'status'])->name('account.status');
    Route::post('/account/status/resubmit', [\App\Http\Controllers\RegistrationController::class, 'resubmit'])->middleware('throttle:5,10')->name('account.resubmit');
    Route::post('/account/email-verification/resend', [\App\Http\Controllers\RegistrationController::class, 'resendEmailVerification'])->middleware('throttle:3,10')->name('account.email.resend');
    Route::post('/account/email-verification/confirm', [\App\Http\Controllers\RegistrationController::class, 'confirmEmailVerification'])->middleware('throttle:5,10')->name('account.email.confirm');
    Route::post('/account/onboarding/personal-email', [AccountOnboardingController::class, 'setPersonalEmail'])->middleware('throttle:5,10')->name('account.onboarding.email');

    // A single shared feed for approved student, counselor, and admin accounts.
    // Keep the established student.* route names and /student URLs so existing
    // notifications, bookmarks, and integrations continue to resolve.
    Route::prefix('student')->name('student.')->middleware(['account.approved', 'community.participant'])->group(function () {
        Route::get('/community', [StudentController::class, 'community'])->name('community');
        Route::get('/community/data', [StudentController::class, 'communityData'])->name('community.data');
        Route::get('/community/resources/{resource}', [StudentController::class, 'resourceDetail'])->whereNumber('resource')->name('community.resource.show');
        Route::get('/community/{message}/image', [StudentController::class, 'communityImage'])->whereNumber('message')->name('community.image');
        Route::post('/community/post', [StudentController::class, 'postCommunity'])->middleware(['community.consent', 'throttle:30,1'])->name('community.post');
        Route::put('/community/{message}', [StudentController::class, 'updateCommunityMessage'])->middleware(['community.consent', 'throttle:30,1'])->name('community.update');
        Route::delete('/community/{message}', [StudentController::class, 'deleteCommunityMessage'])->middleware(['community.consent', 'throttle:30,1'])->name('community.delete');
        Route::post('/community/{message}/like', [StudentController::class, 'likeCommunityMessage'])->middleware(['community.consent', 'throttle:30,1'])->name('community.like');
        Route::post('/community/{message}/unlike', [StudentController::class, 'unlikeCommunityMessage'])->middleware(['community.consent', 'throttle:30,1'])->name('community.unlike');
        Route::post('/community/{message}/reaction', [StudentController::class, 'reactCommunityMessage'])->middleware(['community.consent', 'throttle:30,1'])->name('community.reaction');
        Route::delete('/community/{message}/reaction', [StudentController::class, 'removeCommunityReaction'])->middleware(['community.consent', 'throttle:30,1'])->name('community.reaction.remove');
        Route::post('/community/report', [StudentController::class, 'reportCommunity'])->middleware(['community.consent', 'throttle:30,1'])->name('community.report');
        Route::post('/community/block/{userId}', [StudentController::class, 'blockCommunityUser'])->middleware(['community.consent', 'throttle:30,1'])->name('community.block');
        Route::delete('/community/block/{userId}', [StudentController::class, 'unblockCommunityUser'])->middleware(['community.consent', 'throttle:30,1'])->name('community.unblock');
        Route::post('/post/{postId}/comment', [StudentController::class, 'addComment'])->middleware(['community.consent', 'throttle:30,1'])->name('post.comment');
        Route::get('/post/{postId}/comments', [StudentController::class, 'getPostComments'])->name('post.comments');
        Route::put('/comment/{commentId}', [StudentController::class, 'updateComment'])->middleware(['community.consent', 'throttle:30,1'])->name('comment.update');
        Route::delete('/comment/{commentId}', [StudentController::class, 'deleteComment'])->middleware(['community.consent', 'throttle:30,1'])->name('comment.delete');
        // Approved wellness resources are read-only and may be opened by any
        // approved Community participant, not only students.
        Route::get('/resources', [StudentController::class, 'resources'])->name('resources');
        Route::get('/resources/search', [StudentController::class, 'searchResources'])->middleware('throttle:30,1')->name('resources.search');
        Route::get('/resources/{resource}', [StudentController::class, 'resourceDetail'])->whereNumber('resource')->name('resources.show');
        Route::get('/emergency-resources', [StudentController::class, 'emergencyResources'])->name('emergency-resources');
    });

    // Student routes
    Route::prefix('student')->name('student.')->middleware(['role:student', 'account.approved'])->group(function () {
        Route::get('/dashboard', function() {
            return redirect()->route('home');
        })->name('dashboard');
        Route::get('/ai-companion', [StudentController::class, 'aiCompanion'])->name('ai-companion');
        Route::get('/journal', [StudentController::class, 'journal'])->name('journal');
        Route::put('/journal/{entry}', [StudentController::class, 'updateEntry'])->name('journal.update');
        Route::get('/insights', [StudentController::class, 'insights'])->name('insights');
        Route::post('/insights/recommendations', [StudentController::class, 'resourceRecommendations'])
            ->middleware('throttle:6,1')->name('insights.recommendations');
        Route::get('/create-entry', function() {
            return redirect()->route('student.ai-chat')->with('info', 'Use AI Chat to create entries!');
        })->name('create-entry');
        Route::post('/store-entry', [StudentController::class, 'storeEntry'])->name('store-entry');
        Route::get('/ai-chat/{session?}', [StudentController::class, 'aiChat'])->name('ai-chat');
        Route::get('/mental-status', [StudentController::class, 'mentalStatus'])->name('mental-status');
        Route::get('/api/mental-status', [StudentController::class, 'getMentalStatusData'])->name('api.mental-status');
        Route::get('/api/wellness-score', [StudentController::class, 'getWellnessScore'])->name('api.wellness-score');
        Route::get('/new-conversation', [StudentController::class, 'newConversation'])->name('new-conversation');
        Route::post('/end-conversation', [StudentController::class, 'endConversation'])->name('end-conversation');
        Route::delete('/conversation/{sessionId}/hide', [StudentController::class, 'hideConversation'])->name('conversation.hide');
        Route::get('/my-entries', [StudentController::class, 'myEntries'])->name('entries');
        Route::get('/counselor-chat', [StudentController::class, 'counselorChat'])->name('counselor-chat');
        Route::post('/send-message', [StudentController::class, 'sendMessage'])->name('send-message');
        
        // New feature routes
        Route::post('/mood-checkin', [StudentController::class, 'storeMoodCheckin'])->name('mood-checkin');
        Route::get('/wellbeing', [StudentController::class, 'wellbeing'])->name('wellbeing');
        Route::get('/coping-toolkit', [StudentController::class, 'copingToolkit'])->name('coping-toolkit');
        Route::get('/reflection', [StudentController::class, 'reflection'])->name('reflection');
        Route::get('/reminders', [StudentController::class, 'reminders'])->name('reminders');
        Route::get('/privacy', [StudentController::class, 'privacy'])->name('privacy');
        Route::get('/settings', [StudentController::class, 'privacy'])->name('settings');
        Route::post('/privacy/update', [StudentController::class, 'updatePrivacy'])->name('privacy.update');
        
        // Counselor features
        Route::get('/counselor-directory', [StudentController::class, 'counselorDirectory'])->name('counselor-directory');
        Route::post('/counseling-request', [StudentController::class, 'storeCounselingRequest'])->name('counseling-request');
        Route::get('/counseling-requests', [StudentController::class, 'counselingRequests'])->name('counseling-requests');
        Route::get('/appointments', [StudentController::class, 'appointments'])->name('appointments');
        Route::post('/appointment/schedule', [StudentController::class, 'scheduleAppointment'])->name('appointment.schedule');
        
        // Appointment Request System (CORE)
        Route::get('/request-appointment', [StudentController::class, 'showRequestAppointmentForm'])->name('request-appointment');
        Route::post('/request-appointment', [StudentController::class, 'submitAppointmentRequest'])->name('request-appointment.submit');
        
        // Peer Support & Community
        Route::get('/peer-support', [StudentController::class, 'peerSupport'])->name('peer-support');
        Route::get('/psychosocial', [StudentController::class, 'psychosocial'])->name('psychosocial');
        
        // Social Platform Routes
        Route::get('/social-feed', [StudentController::class, 'socialFeed'])->name('social-feed');
        Route::get('/user-profile/{userId}', [StudentController::class, 'userProfile'])->name('user-profile');
        Route::put('/user-profile/update', [StudentController::class, 'updateUserProfile'])->middleware('community.consent')->name('user-profile.update');
        Route::post('/user/{userId}/follow', [StudentController::class, 'followUser'])->middleware('community.consent')->name('user.follow');
        Route::post('/user/{userId}/unfollow', [StudentController::class, 'unfollowUser'])->middleware('community.consent')->name('user.unfollow');
        Route::get('/user/{userId}/followers', [StudentController::class, 'followers'])->name('user.followers');
        Route::get('/user/{userId}/following', [StudentController::class, 'following'])->name('user.following');
        Route::post('/post/{postId}/share', [StudentController::class, 'sharePost'])->middleware('community.consent')->name('post.share');
        Route::get('/social-notifications', [StudentController::class, 'getSocialNotifications'])->name('social-notifications');
        Route::post('/notification/{notificationId}/read', [StudentController::class, 'markSocialNotificationRead'])->name('notification.read');
        Route::get('/search-posts', [StudentController::class, 'searchPosts'])->name('search-posts');
        Route::get('/trending-posts', [StudentController::class, 'trendingPosts'])->name('trending-posts');
        Route::get('/recommended-posts', [StudentController::class, 'recommendedPosts'])->name('recommended-posts');
        
        // Discussion Forum Routes
        Route::get('/discussion-forums', [StudentController::class, 'discussionForums'])->name('discussion-forums');
        Route::get('/forum/{forumId}', [StudentController::class, 'viewForum'])->name('forum.view');
        Route::post('/forum/{forumId}/thread', [StudentController::class, 'createThread'])->middleware('community.consent')->name('forum.thread.create');
        Route::get('/forum/thread/{threadId}', [StudentController::class, 'viewThread'])->name('forum.thread');
        Route::post('/forum/thread/{threadId}/reply', [StudentController::class, 'replyToThread'])->middleware('community.consent')->name('forum.reply');
        Route::post('/forum/reply/{replyId}/like', [StudentController::class, 'likeReply'])->middleware('community.consent')->name('forum.reply.like');
        Route::post('/forum/reply/{replyId}/helpful', [StudentController::class, 'markReplyHelpful'])->middleware('community.consent')->name('forum.reply.helpful');
        Route::get('/forum-activity', [StudentController::class, 'forumActivity'])->name('forum.activity');
        Route::get('/forum-search', [StudentController::class, 'searchForumThreads'])->name('forum.search');
        Route::get('/trending-forum-threads', [StudentController::class, 'trendingForumThreads'])->name('forum.trending');
        
        // Profile Management
        Route::put('/profile/update', [StudentController::class, 'updateProfile'])->name('profile.update');
        
        // Notifications
        Route::get('/api/notifications', [StudentController::class, 'getNotifications'])->name('notifications.get');
        Route::post('/api/notifications/{id}/read', [StudentController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/api/notifications/read-all', [StudentController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
        Route::delete('/api/notifications/{id}', [StudentController::class, 'deleteNotification'])->name('notifications.delete');
        
        // Counselor messaging API
        Route::get('/api/counselor/{counselorId}/messages', [StudentController::class, 'getCounselorMessages'])->name('counselor.messages');
        
        // Message deletion API
        Route::delete('/api/messages/{messageId}/delete-for-me', [StudentController::class, 'deleteMessageForMe'])->name('message.delete-for-me');
        Route::post('/api/messages/{messageId}/unsend', [StudentController::class, 'unsendMessage'])->name('message.unsend');
        
        // Mental Health Monitoring
        Route::get('/mental-health-dashboard', [MentalHealthMonitoringController::class, 'studentDashboard'])->name('mental-health-dashboard');
        Route::get('/api/mental-health-status', [MentalHealthMonitoringController::class, 'getMentalStatus'])->name('api.mental-health-status');
    });

    // Counselor routes
    Route::prefix('counselor')->name('counselor.')->middleware(['role:counselor', 'account.approved'])->group(function () {
        Route::get('/dashboard', [CounselorController::class, 'dashboard'])->name('dashboard');
        Route::get('/students', [CounselorController::class, 'students'])->name('students');
        Route::get('/student/{id}', [CounselorController::class, 'studentProfile'])->name('student-profile');
        Route::get('/messages', [CounselorController::class, 'messages'])->name('messages');
        Route::get('/chat/{studentId}', [CounselorController::class, 'chat'])->name('chat');
        Route::post('/send-message/{studentId}', [CounselorController::class, 'sendMessage'])->name('send-message');
        
        // Advanced Chat with Anonymous Data & Appointments
        Route::get('/chat-advanced/{studentId?}', [CounselorController::class, 'chatAdvanced'])->name('chat.advanced');
        Route::post('/session-note/{studentId}', [CounselorController::class, 'saveSessionNote'])->name('session-note.save');
        Route::post('/crisis-alert/{alertId}/create-appointment', [CounselorController::class, 'createAppointmentFromCrisis'])->name('create-appointment-from-crisis');
        Route::post('/batch-retrain-ml', [CounselorController::class, 'batchRetrainML'])->name('batch-retrain-ml');
        
        // Mental Health Monitoring
        Route::get('/mental-health-monitoring', [MentalHealthMonitoringController::class, 'counselorDashboard'])->name('mental-health-monitoring');
        Route::post('/alerts/{alertId}/acknowledge', [MentalHealthMonitoringController::class, 'acknowledgeAlert'])->name('alerts.acknowledge');
        Route::post('/alerts/{alertId}/resolve', [MentalHealthMonitoringController::class, 'resolveAlert'])->name('alerts.resolve');
        
        // Appointments
        Route::get('/appointments', [CounselorController::class, 'appointments'])->name('appointments');
        Route::post('/appointments/{id}/confirm', [CounselorController::class, 'confirmAppointment'])->name('appointments.confirm');
        Route::post('/appointments/{id}/complete', [CounselorController::class, 'completeAppointment'])->name('appointments.complete');
        Route::post('/appointments/{id}/outcome', [CounselorController::class, 'recordAppointmentOutcome'])->name('appointments.outcome');
        Route::post('/appointments/{id}/cancel', [CounselorController::class, 'cancelAppointment'])->name('appointments.cancel');
        
        // API endpoints for chat interface
        Route::get('/api/students', [CounselorController::class, 'apiGetStudents'])->name('api.students');
        Route::get('/api/student/{studentId}/messages', [CounselorController::class, 'apiGetStudentMessages'])->name('api.student.messages');
        
        // Message deletion API
        Route::delete('/api/messages/{messageId}/delete-for-me', [CounselorController::class, 'deleteMessageForMe'])->name('message.delete-for-me');
        Route::post('/api/messages/{messageId}/unsend', [CounselorController::class, 'unsendMessage'])->name('message.unsend');
        
        // Notifications
        Route::get('/api/notifications', [CounselorController::class, 'getNotifications'])->name('notifications.get');
        Route::post('/api/notifications/{id}/read', [CounselorController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/api/notifications/read-all', [CounselorController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
        Route::delete('/api/notifications/{id}', [CounselorController::class, 'deleteNotification'])->name('notifications.delete');
    });

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/create-user', [AdminController::class, 'createUser'])->name('create-user');
        Route::post('/store-user', [AdminController::class, 'storeUser'])->name('store-user');
        Route::delete('/delete-user/{id}', [AdminController::class, 'deleteUser'])->name('delete-user');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');

        // General admins keep their dashboard; applicant records and decisions
        // require a separate explicit reviewer-ID grant.
        Route::middleware('account.verification.reviewer')->group(function (): void {
            Route::get('/account-verification', [\App\Http\Controllers\AccountVerificationController::class, 'index'])->name('account-verification.index');
            Route::get('/account-verification/{user}', [\App\Http\Controllers\AccountVerificationController::class, 'show'])->whereNumber('user')->name('account-verification.show');
            Route::get('/account-verification/{user}/document', [\App\Http\Controllers\AccountVerificationController::class, 'document'])->whereNumber('user')->name('account-verification.document');
            Route::post('/account-verification/{user}/approve', [\App\Http\Controllers\AccountVerificationController::class, 'approve'])->whereNumber('user')->name('account-verification.approve');
            Route::post('/account-verification/{user}/reject', [\App\Http\Controllers\AccountVerificationController::class, 'reject'])->whereNumber('user')->name('account-verification.reject');
            Route::post('/account-verification/{user}/request-information', [\App\Http\Controllers\AccountVerificationController::class, 'requestInformation'])->whereNumber('user')->name('account-verification.request-information');
            Route::post('/account-verification/{user}/suspend', [\App\Http\Controllers\AccountVerificationController::class, 'suspend'])->whereNumber('user')->name('account-verification.suspend');
            Route::post('/account-verification/{user}/owner-invitation/email', [AccountOnboardingController::class, 'confirmCounselorGmailAndSendInvitation'])->whereNumber('user')->middleware('throttle:3,10')->name('account-verification.owner-invitation');
            Route::post('/users/{user}/invitation/resend', [AccountOnboardingController::class, 'resendInvitation'])->whereNumber('user')->middleware('throttle:3,10')->name('users.invitation.resend');
            Route::post('/account-verification/{user}/invitation/in-person', [AccountOnboardingController::class, 'createInPersonInvitation'])->whereNumber('user')->middleware('throttle:3,10')->name('account-verification.invitation.in-person');
        });
        
        // Community Moderation
        Route::get('/community-moderation', [AdminController::class, 'communityModeration'])->middleware('community.participant')->name('community-moderation');
        Route::get('/community-moderation/{postId}/image', [AdminController::class, 'communityImage'])->whereNumber('postId')->middleware('community.participant')->name('community-moderation.image');
        Route::post('/community-moderation/{postId}/approve', [AdminController::class, 'approveCommunityPost'])->middleware('community.participant')->name('community-moderation.approve');
        Route::post('/community-moderation/{postId}/reject', [AdminController::class, 'rejectCommunityPost'])->middleware('community.participant')->name('community-moderation.reject');
        Route::post('/community-moderation/{postId}/hide', [AdminController::class, 'hideCommunityPost'])->whereNumber('postId')->middleware('community.participant')->name('community-moderation.hide');
        Route::post('/community-moderation/{postId}/restore', [AdminController::class, 'restoreCommunityPost'])->whereNumber('postId')->middleware('community.participant')->name('community-moderation.restore');
        Route::post('/community-moderation/{postId}/pin', [AdminController::class, 'pinCommunityPost'])->middleware('community.participant')->name('community-moderation.pin');
        Route::get('/community-reports', [AdminController::class, 'communityReports'])->middleware('community.participant')->name('community-reports');
        Route::post('/community-reports/{reportId}/review', [AdminController::class, 'reviewCommunityReport'])->middleware('community.participant')->name('community-reports.review');
        Route::post('/users/{userId}/community-verify', [AdminController::class, 'verifyCommunityStudent'])->name('users.community-verify');
        Route::delete('/users/{userId}/community-verify', [AdminController::class, 'unverifyCommunityStudent'])->name('users.community-unverify');
        
        // Notifications
        Route::get('/api/notifications', [AdminController::class, 'getNotifications'])->name('notifications.get');
        Route::post('/api/notifications/{id}/read', [AdminController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/api/notifications/read-all', [AdminController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
        Route::delete('/api/notifications/{id}', [AdminController::class, 'deleteNotification'])->name('notifications.delete');
    });
});

// API routes for AI Chat - Exclude from CSRF since they're protected by auth + secure.auth
Route::middleware(['auth', 'secure.auth', 'throttle:20,1'])->prefix('api/ai')->group(function () {
    Route::post('/chat', [AIChatController::class, 'sendMessage'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    Route::get('/history', [AIChatController::class, 'getHistory']);
    Route::get('/summary', [AIChatController::class, 'getSummary']);
    Route::post('/clear', [AIChatController::class, 'clearConversation'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    Route::post('/feedback', [AIChatController::class, 'submitFeedback'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    
    // AI Preference Settings
    Route::get('/preference', [AIChatController::class, 'getAIPreference']);
    Route::post('/preference', [AIChatController::class, 'updateAIPreference'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    
    // Chat initialization - NEW: AI greets first when creating new chat
    Route::post('/initialize-session', [AIChatController::class, 'initializeChatSession'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    
    // Session management
    Route::get('/sessions', [AIChatController::class, 'getSessions']);
    Route::post('/sessions', [AIChatController::class, 'createSession'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    Route::get('/sessions/{sessionId}/messages', [AIChatController::class, 'getSessionMessages']);
    Route::delete('/sessions/{sessionId}', [AIChatController::class, 'deleteSession'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
});

// Video call routes
require __DIR__.'/video_calls.php';
