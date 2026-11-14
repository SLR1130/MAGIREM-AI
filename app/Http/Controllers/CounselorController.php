<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmotionalEntry;
use App\Models\CounselorMessage;
use App\Models\CrisisAlert;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Services\PrivacyService;
use App\Services\AppointmentLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CounselorController extends Controller
{
    public function dashboard()
    {
        $counselorId = (int) Auth::id();
        $assignedStudentIds = $this->assignedStudentIds($counselorId);
        $totalStudents = $assignedStudentIds->count();

        // Crisis alerts are visible only to the counselor explicitly assigned
        // to the alert. InstitutionalCrisisEscalationGate remains responsible
        // for creating new alerts; this query only reads an existing assignment.
        $activeCrisisAlerts = CrisisAlert::where('counselor_id', $counselorId)
            ->where('is_resolved', false)
            ->whereHas('student', fn ($query) => $query->where('role', 'student'))
            ->with('student')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // Journal-derived data requires both a confirmed assignment and the
        // student's current counselor-sharing consent.
        $crisisStudentIds = $activeCrisisAlerts->pluck('student_id')->unique();
        $crisisEntries = $this->assignedConsentedEntries($counselorId)
            ->with('user')
            ->whereIn('user_id', $crisisStudentIds)
            ->whereIn('crisis_level', ['critical', 'severe'])
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $needsAttention = $this->assignedConsentedEntries($counselorId)
            ->with('user')
            ->where('needs_counselor', true)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        // Count only unread student messages associated with this counselor's
        // confirmed appointments, preventing orphaned/private messages from
        // entering the dashboard summary.
        $unreadMessages = CounselorMessage::where('counselor_id', $counselorId)
            ->whereHas('sender', fn ($query) => $query->where('role', 'student'))
            ->whereHas('student.appointments', fn ($query) => $query
                ->where('counselor_id', $counselorId)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome'))
            ->where('is_read', false)
            ->count();

        // ── Chart Data ──────────────────────────────────────────────

        // 1. Emotion Distribution (Pie Chart)
        $emotionDistribution = $this->assignedConsentedEntries($counselorId)
            ->selectRaw('detected_emotion, COUNT(*) as count')
            ->whereNotNull('detected_emotion')
            ->groupBy('detected_emotion')
            ->orderBy('count', 'desc')
            ->pluck('count', 'detected_emotion')
            ->toArray();

        // 2. Daily Emotion Trend – last 7 days (Line Chart)
        $last7DaysLabels = [];
        $emotionTrends = [];
        $trendEmotions = ['happy', 'sad', 'anxious', 'angry', 'neutral', 'depressed'];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $last7DaysLabels[] = now()->subDays($i)->format('M d');

            $dayData = $this->assignedConsentedEntries($counselorId)
                ->whereDate('created_at', $date)
                ->selectRaw('detected_emotion, COUNT(*) as count')
                ->groupBy('detected_emotion')
                ->pluck('count', 'detected_emotion')
                ->toArray();

            foreach ($trendEmotions as $emotion) {
                $emotionTrends[$emotion][] = $dayData[$emotion] ?? 0;
            }
        }

        // 3. Crisis Level Distribution (Bar Chart)
        $crisisDistribution = $this->assignedConsentedEntries($counselorId)
            ->selectRaw('crisis_level, COUNT(*) as count')
            ->whereNotNull('crisis_level')
            ->where('crisis_level', '!=', 'none')
            ->groupBy('crisis_level')
            ->pluck('count', 'crisis_level')
            ->toArray();

        // 4. Monthly Entries Trend – last 6 months (Bar Chart)
        $monthlyLabels = [];
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyLabels[] = $month->format('M Y');
            $monthlyData[] = $this->assignedConsentedEntries($counselorId)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        }

        // 5. Students needing attention vs total (Doughnut)
        $studentsNeedingHelp = $this->assignedConsentedEntries($counselorId)
            ->where('needs_counselor', true)
            ->distinct('user_id')->count('user_id');
        $studentsOk = max(0, $totalStudents - $studentsNeedingHelp);

        return view('counselor.dashboard', compact(
            'totalStudents', 'unreadMessages',
            'crisisEntries', 'needsAttention', 'activeCrisisAlerts',
            // chart data
            'emotionDistribution', 'last7DaysLabels', 'emotionTrends',
            'crisisDistribution', 'monthlyLabels', 'monthlyData',
            'studentsNeedingHelp', 'studentsOk'
        ));
    }

    public function students()
    {
        $counselorId = (int) Auth::id();
        $students = User::where('role', 'student')
            ->whereHas('appointments', fn ($query) => $query
                ->where('counselor_id', $counselorId)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome'))
            ->paginate(15);
        return view('counselor.students', compact('students'));
    }

    // ── Appointments ──────────────────────────────────────────────

    public function appointments()
    {
        $counselorId = (int) Auth::id();
        $lifecycle = app(AppointmentLifecycleService::class);
        $base = fn () => Appointment::with('student')
            ->where('counselor_id', $counselorId);

        $pending = Appointment::with('student')
            ->where('status', 'pending')
            ->where(function ($query) use ($counselorId) {
                $query->whereNull('counselor_id')->orWhere('counselor_id', $counselorId);
            })
            ->orderBy('preferred_date')->orderBy('id')->get();

        $confirmed = $base()
            ->where('status', 'confirmed')->whereNull('session_outcome')
            ->orderBy('appointment_date')->orderBy('id')->get();

        $needsReview = $confirmed->filter(fn (Appointment $appointment) => $lifecycle->isOverdueWithoutOutcome($appointment))->values();
        $confirmed = $confirmed->reject(fn (Appointment $appointment) => $lifecycle->isOverdueWithoutOutcome($appointment))->values();

        $failed = $base()
            ->where('status', 'confirmed')
            ->whereIn('session_outcome', AppointmentLifecycleService::FAILURE_OUTCOMES)
            ->orderByDesc('outcome_verified_at')->orderByDesc('id')->get();

        $completed = $base()->where('status', 'completed')
            ->orderByDesc('appointment_date')->orderByDesc('id')->take(20)->get();
        $cancelled = $base()->where('status', 'cancelled')
            ->orderByDesc('updated_at')->orderByDesc('id')->take(10)->get();

        $todayCount = $base()->where('status', 'confirmed')->whereNull('session_outcome')
            ->whereDate('appointment_date', today())->count();
        $upcomingCount = $base()->where('status', 'confirmed')->whereNull('session_outcome')
            ->where('appointment_date', '>', now())->count();

        return view('counselor.appointments', compact(
            'pending', 'confirmed', 'needsReview', 'failed', 'completed', 'cancelled',
            'todayCount', 'upcomingCount'
        ));
    }
    public function confirmAppointment(Request $request, $id)
    {
        $data = $request->validate([
            'appointment_date' => 'required|date|after:now',
            'counselor_notes' => 'nullable|string|max:500',
        ]);

        $appointment = DB::transaction(function () use ($data, $id) {
            $appointment = Appointment::where(function ($query) {
                    $query->whereNull('counselor_id')
                        ->orWhere('counselor_id', Auth::id());
                })
                ->where('status', 'pending')
                ->lockForUpdate()
                ->findOrFail($id);

            $appointment->update([
                'counselor_id' => Auth::id(),
                'status' => 'confirmed',
                'appointment_date' => $data['appointment_date'],
                'counselor_notes' => $data['counselor_notes'] ?? null,
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'from_status' => 'pending',
                'to_status' => 'confirmed',
                'changed_by' => Auth::id(),
                'reason' => $data['counselor_notes'] ?? null,
            ]);

            return $appointment->fresh(['student', 'counselor']);
        });

        \App\Services\NotificationService::appointmentConfirmed($appointment);

        return back()->with('success', 'Appointment confirmed successfully.');
    }

    public function completeAppointment(Request $request, $id)
    {
        $data = $request->validate([
            'attendance_evidence' => 'required|in:online_call,manual_verification,external_verification',
            'counselor_notes' => 'nullable|string|max:2000',
        ]);

        $appointment = Appointment::where('counselor_id', Auth::id())->findOrFail($id);
        $updated = app(AppointmentLifecycleService::class)->recordOutcome(
            $appointment,
            'completed',
            $data['counselor_notes'] ?? null,
            $data['attendance_evidence'],
            (int) Auth::id()
        );

        \App\Services\NotificationService::appointmentCompleted($updated);

        return back()->with('success', 'Appointment marked as completed with verified attendance evidence.');
    }

    public function recordAppointmentOutcome(Request $request, $id)
    {
        $data = $request->validate([
            'outcome' => 'required|in:student_no_show,counselor_no_show,technical_problem,other,needs_review',
            'reason' => 'required|string|max:2000',
            'attendance_evidence' => 'nullable|string|max:40',
        ]);

        $appointment = Appointment::where('counselor_id', Auth::id())->findOrFail($id);
        $updated = app(AppointmentLifecycleService::class)->recordOutcome(
            $appointment,
            $data['outcome'],
            $data['reason'],
            $data['attendance_evidence'] ?? null,
            (int) Auth::id()
        );

        if ($data['outcome'] !== 'needs_review') {
            \App\Services\NotificationService::appointmentFailed($updated);
        }

        return back()->with('success', 'Appointment outcome recorded for review history.');
    }

    public function cancelAppointment($id)
    {
        $reason = request()->input('reason');

        $appointment = DB::transaction(function () use ($id, $reason) {
            $appointment = Appointment::where('counselor_id', Auth::id())
                ->lockForUpdate()
                ->findOrFail($id);

            abort_unless(
                in_array($appointment->status, ['pending', 'confirmed'], true)
                && $appointment->session_outcome === null,
                422,
                'This appointment is no longer cancellable.'
            );

            $oldStatus = $appointment->status;
            $appointment->update(['status' => 'cancelled']);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'from_status' => $oldStatus,
                'to_status' => 'cancelled',
                'changed_by' => Auth::id(),
                'reason' => $reason,
            ]);

            return $appointment->fresh(['student', 'counselor']);
        });

        \App\Services\NotificationService::appointmentCancelled($appointment);

        return back()->with('success', 'Appointment cancelled.');
    }

    public function studentProfile($id)
    {
        $student = User::where('role', 'student')->findOrFail($id);

        abort_unless($this->hasJournalAccess((int) $id, (int) Auth::id()), 403,
            'A confirmed appointment and current counselor-sharing consent are required.');

        $entries = EmotionalEntry::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Emotion breakdown summary
        $emotionSummary = EmotionalEntry::where('user_id', $id)
            ->selectRaw('detected_emotion, count(*) as cnt')
            ->groupBy('detected_emotion')
            ->orderByDesc('cnt')
            ->get();

        return view('counselor.student-profile', compact('student', 'entries', 'emotionSummary'));
    }

    /**
     * Unified messaging workspace
     * Loads student list and optionally loads a selected student's conversation
     *
     * @param int|null $studentId - If provided, loads conversation; otherwise shows empty state
     * @return \Illuminate\View\View
     */
    private function messagingWorkspace($studentId = null)
    {
        $counselorId = Auth::id();

        // Get students with confirmed appointments (always show this list)
        $students = User::where('role', 'student')
            ->whereHas('appointments', function($query) use ($counselorId) {
                $query->where('counselor_id', $counselorId)
                      ->where(function ($appointmentQuery) {
                          $appointmentQuery->whereIn('status', ['confirmed', 'completed', 'cancelled'])
                              ->orWhereNotNull('session_outcome');
                      });
            })
            ->orderBy('student_id')
            ->get();

        // Initialize defaults (no student selected)
        $student = null;
        $messages = collect();
        $hasAccess = false;

        // If a student ID is provided, load that conversation
        if ($studentId) {
            // Verify student exists
            $student = User::where('role', 'student')->findOrFail($studentId);

            // CHECK: Does this counselor have a CONFIRMED appointment with this student?
            $hasAppointment = $this->hasAppointmentHistory((int) $studentId, (int) $counselorId);
            $hasAccess = $this->hasMessagingAppointmentAccess((int) $studentId, (int) $counselorId);

            // DEBUG: Log access attempt
            \Log::info('Messaging workspace access attempt', [
                'counselor_id' => $counselorId,
                'student_id' => $studentId,
                'has_confirmed_appointment' => $hasAccess,
                'has_appointment_history' => $hasAppointment,
            ]);

            // If no confirmed appointment, deny access
            if (!$hasAppointment) {
                return back()->withErrors(['error' => 'You must have a confirmed appointment with this student before messaging.']);
            }

            // Get messages ONLY from after first confirmed appointment
            $firstAppointment = Appointment::where('student_id', $studentId)
                ->where('counselor_id', $counselorId)
                ->where(function ($query) {
                    $query->whereIn('status', ['confirmed', 'completed', 'cancelled'])
                        ->orWhereNotNull('session_outcome');
                })
                ->orderBy('created_at', 'asc')
                ->first();

            if ($firstAppointment) {
                $messages = CounselorMessage::where('student_id', $studentId)
                    ->where(function ($query) use ($counselorId) {
                        $query->where('counselor_id', $counselorId)->orWhere('sender_id', $counselorId);
                    })
                    ->where('created_at', '>=', $firstAppointment->created_at)
                    ->with(['sender'])
                    ->orderBy('created_at', 'asc')
                    ->get();
            }

            // Mark unread messages from this student as read
            CounselorMessage::where('student_id', $studentId)
                ->where('counselor_id', $counselorId)
                ->where('sender_id', $studentId)
                ->where('is_read', false)
                ->update(['is_read' => true, 'counselor_id' => $counselorId]);
        }

        return view('counselor.chat', compact('students', 'student', 'messages'))->with('canSend', $hasAccess);
    }

    /**
     * Messages route - Shows unified messaging workspace with no student selected
     */
    public function messages()
    {
        return $this->messagingWorkspace(null);
    }

    /**
     * Chat route - Shows unified messaging workspace with a selected student
     */
    public function chat($studentId)
    {
        return $this->messagingWorkspace($studentId);
    }

    public function sendMessage(Request $request, $studentId)
    {
        try {
            $counselorId = Auth::id();

            // CHECK: Does this counselor have a CONFIRMED appointment with this student?
            $hasConfirmedAppointment = User::whereKey($studentId)->where('role', 'student')->exists()
                && $this->hasMessagingAppointmentAccess((int) $studentId, (int) $counselorId);

            if (!$hasConfirmedAppointment) {
                if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                    return response()->json([
                        'success' => false,
                        'message' => 'You must have a confirmed appointment with this student before messaging.'
                    ], 403);
                }
                return back()->withErrors(['error' => 'You must have a confirmed appointment with this student before messaging.']);
            }

            // DIAGNOSTIC: Log upload details
            \Log::info('[CHAT_UPLOAD_DEBUG]', [
                'message_length' => strlen((string) $request->input('message', '')),
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

            // Validate: message is nullable, attachment is nullable, but BOTH cannot be null
            $request->validate([
                'message' => 'nullable|string|max:5000',
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
                'student_id' => $studentId,
                'counselor_id' => Auth::id(),
                'sender_id' => Auth::id(),
                'message' => $request->message ?? '',
            ];

            // Handle file upload securely on private disk
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('chat_attachments', 'local');

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
                \Log::info('[MESSAGE_ONLY]', ['student_id' => $studentId]);
            }

            $message = CounselorMessage::create($data);

            // Send notification to student
            $messagePreview = $request->message ?? '[File attachment]';
            \App\Services\NotificationService::newMessage(
                Auth::id(),
                $studentId,
                Auth::user()->name,
                $messagePreview
            );

            // Return JSON for AJAX requests with attachment_url included
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                $attachmentUrl = null;
                if ($message->attachment_path) {
                    $attachmentUrl = route('counselor-chat.attachment', ['message' => $message->id]);
                }

                return response()->json([
                    'success' => true,
                    'senderInitial' => substr(Auth::user()->name, 0, 1),
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
            }

            return back()->with('success', 'Message sent successfully.');
        } catch (\Exception $e) {
            \Log::error('[SEND_MESSAGE_ERROR]', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_id' => $studentId,
                'counselor_id' => Auth::id()
            ]);
            if ($request->expectsJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Create appointment from crisis alert
     */
    public function createAppointmentFromCrisis($alertId)
    {
        $alert = CrisisAlert::findOrFail($alertId);

        // Verify this counselor is assigned to this alert
        if ((int) $alert->counselor_id !== (int) Auth::id()) {
            return back()->withErrors(['error' => 'Unauthorized']);
        }

        // Check if appointment already exists for this student
        $existingAppointment = Appointment::where('student_id', $alert->student_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existingAppointment) {
            return back()->withErrors(['error' => 'Appointment already exists for this student.']);
        }

        // Create appointment automatically
        Appointment::create([
            'student_id' => $alert->student_id,
            'counselor_id' => Auth::id(),
            'status' => 'pending',
            'reason' => 'Crisis Alert: ' . substr($alert->description, 0, 100),
            'preferred_date' => now()->addDay(), // Tomorrow
            'appointment_date' => now()->addDay(), // Set appointment_date same as preferred_date
            'created_from_crisis' => true,
        ]);

        // Mark alert as resolved
        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => Auth::id()
        ]);

        return back()->with('success', 'Appointment created successfully. Please confirm it in the Appointments page.');
    }

    /**
     * API: Get all students for counselor
     */
    public function apiGetStudents()
    {
        $counselorId = (int) Auth::id();
        $students = User::where('role', 'student')
            ->whereHas('appointments', fn ($query) => $query
                ->where('counselor_id', $counselorId)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome'))
            ->select('id', 'name', 'student_id', 'email', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json([
            'students' => $students
        ]);
    }

    /**
     * API: Get messages for a specific student
     */
    public function apiGetStudentMessages($studentId)
    {
        $counselorId = Auth::id();

        abort_unless(User::whereKey($studentId)->where('role', 'student')->exists() && $this->hasAppointmentHistory((int) $studentId, (int) $counselorId), 403,
            'An authorized appointment is required to access this conversation.');

        // Load messages
        $messages = CounselorMessage::where('student_id', $studentId)
            ->where(function ($query) use ($counselorId) {
                $query->where('counselor_id', $counselorId)->orWhere('sender_id', $counselorId);
            })
            ->with(['sender'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                $attachmentUrl = null;
                if ($msg->attachment_path && !$msg->isUnsent()) {
                    $attachmentUrl = route('counselor-chat.attachment', ['message' => $msg->id]);
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

        // Load video calls involving this counselor and student
        $calls = DB::table('video_calls')
            ->where(function($q) use ($counselorId, $studentId) {
                $q->where('caller_id', $counselorId)->where('callee_id', $studentId)
                  ->orWhere('caller_id', $studentId)->where('callee_id', $counselorId);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($call) use ($counselorId) {
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
                    'is_caller' => (int) $call->caller_id === (int) $counselorId,
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

    /**
     * Batch retrain ML model from crisis conversations
     */
    public function batchRetrainML()
    {
        abort(403,
            'Raw conversation export is disabled until separate model-training authorization is approved.');
    }

    /**
     * Appointment-Based Chat System (ANONYMOUS)
     * Students can only chat if they have a CONFIRMED appointment with this counselor
     */
    public function chatAdvanced($studentId = null)
    {
        $counselorId = Auth::id();

        // Get only students who have CONFIRMED appointments with this counselor
        $students = User::where('role', 'student')
            ->whereHas('appointments', function($query) use ($counselorId) {
                $query->where('counselor_id', $counselorId)
                      ->where(function ($appointmentQuery) {
                          $appointmentQuery->whereIn('status', ['confirmed', 'completed', 'cancelled'])
                              ->orWhereNotNull('session_outcome');
                      });
            })
            ->with(['appointments' => function($query) use ($counselorId) {
                $query->where('counselor_id', $counselorId)
                      ->where('status', 'confirmed')
                      ->whereNull('session_outcome')
                      ->orderBy('appointment_date', 'desc');
            }])
            ->orderBy('student_id')
            ->get()
            ->map(function($student) use ($counselorId) {
                $canViewJournal = app(PrivacyService::class)->canShareWithCounselor($student->id);
                $latestEntry = $canViewJournal
                    ? EmotionalEntry::where('user_id', $student->id)->latest()->first()
                    : null;
                $assignedCrisisLevel = CrisisAlert::where('student_id', $student->id)
                    ->where('counselor_id', $counselorId)
                    ->where('is_resolved', false)
                    ->latest()
                    ->value('crisis_level');
                $crisisLevel = $latestEntry?->crisis_level ?? $assignedCrisisLevel ?? 'none';

                // Get unread message count
                $unreadCount = CounselorMessage::where('student_id', $student->id)
                    ->where('sender_id', $student->id)
                    ->where('is_read', false)
                    ->count();

                // Get latest appointment
                $latestAppointment = $student->appointments->first();

                return [
                    'id' => $student->id,
                    'display_name' => 'Student #' . $student->student_id, // ANONYMOUS
                    'student_id' => $student->student_id,
                    'avatar' => 'S' . substr($student->student_id, -1),
                    'joined' => $student->created_at->format('M Y'),
                    'crisis_level' => $crisisLevel,
                    'unread_count' => $unreadCount,
                    'latest_appointment' => $latestAppointment ? $latestAppointment->appointment_date->format('M d, Y g:i A') : null,
                    // NO real name, NO email for privacy
                ];
            });

        $selectedStudent = null;
        $messages = collect();
        $upcomingAppointment = null;
        $appointmentHistory = collect();
        $sessionNotes = collect();
        $studentStats = null;
        $hasAccess = false;

        if ($studentId) {
            $selectedStudent = User::where('role', 'student')->find($studentId);

            if ($selectedStudent) {
                // CHECK: Does this student have a CONFIRMED appointment with this counselor?
                $hasAccess = $this->hasMessagingAppointmentAccess((int) $studentId, (int) $counselorId);

                if ($hasAccess) {
                    // Get messages ONLY from appointments (filter by appointment date)
                    // Only show messages sent AFTER the first confirmed appointment
                    $firstAppointment = Appointment::where('student_id', $studentId)
                        ->where('counselor_id', $counselorId)
                        ->where('status', 'confirmed')
                        ->whereNull('session_outcome')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($firstAppointment) {
                        $messages = CounselorMessage::where('student_id', $studentId)
                            ->where('created_at', '>=', $firstAppointment->created_at)
                            ->with(['sender'])
                            ->orderBy('created_at', 'asc')
                            ->get();
                    } else {
                        $messages = collect();
                    }

                    // Mark as read
                    CounselorMessage::where('student_id', $studentId)
                        ->where('sender_id', $studentId)
                        ->where('is_read', false)
                        ->update(['is_read' => true, 'counselor_id' => $counselorId]);

                    // Get upcoming appointment
                    $upcomingAppointment = Appointment::where('student_id', $studentId)
                        ->where('counselor_id', $counselorId)
                        ->where('appointment_date', '>=', now())
                        ->where('status', 'confirmed')
                        ->whereNull('session_outcome')
                        ->orderBy('appointment_date')
                        ->first();

                    // Get appointment history
                    $appointmentHistory = Appointment::where('student_id', $studentId)
                        ->where('counselor_id', $counselorId)
                        ->where('status', 'completed')
                        ->orderBy('appointment_date', 'desc')
                        ->limit(5)
                        ->get();

                    // Get session notes
                    $sessionNotes = \App\Models\CounselorSessionNote::where('student_id', $studentId)
                        ->where('counselor_id', $counselorId)
                        ->orderBy('session_date', 'desc')
                        ->limit(10)
                        ->get();

                    // Get student stats
                    $canViewJournal = app(PrivacyService::class)->canShareWithCounselor($studentId);
                    $studentStats = [
                        'total_messages' => CounselorMessage::where('student_id', $studentId)->count(),
                        'total_sessions' => $appointmentHistory->count(),
                        'crisis_count' => $canViewJournal
                            ? EmotionalEntry::where('user_id', $studentId)
                                ->whereIn('crisis_level', ['critical', 'severe', 'high'])
                                ->count()
                            : CrisisAlert::where('student_id', $studentId)
                                ->where('counselor_id', $counselorId)
                                ->count(),
                        'last_contact' => CounselorMessage::where('student_id', $studentId)
                            ->orderBy('created_at', 'desc')
                            ->first()
                            ?->created_at
                            ?->diffForHumans() ?? 'Never',
                    ];
                }
            }
        }

        return view('counselor.chat-advanced', compact(
            'students',
            'selectedStudent',
            'messages',
            'upcomingAppointment',
            'appointmentHistory',
            'sessionNotes',
            'studentStats',
            'studentId',
            'hasAccess'
        ));
    }

    /**
     * Save Session Note
     */
    public function saveSessionNote(Request $request, $studentId)
    {
        abort_unless(User::whereKey($studentId)->where('role', 'student')->exists() && $this->hasConfirmedAppointment((int) $studentId, (int) Auth::id()), 403,
            'A confirmed appointment is required to create a session note.');

        $request->validate([
            'note' => 'required|string|max:5000',
            'session_type' => 'required|in:chat,appointment,crisis',
            'mood_assessment' => 'nullable|in:improving,stable,declining'
        ]);

        \App\Models\CounselorSessionNote::create([
            'counselor_id' => Auth::id(),
            'student_id' => $studentId,
            'note' => $request->note,
            'session_type' => $request->session_type,
            'mood_assessment' => $request->mood_assessment,
            'session_date' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session note saved successfully'
        ]);
    }

    /**
     * Get notifications for counselor (AJAX)
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

            $service = new \App\Services\MessageDeletionService();
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

            $service = new \App\Services\MessageDeletionService();
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

    private function assignedStudentIds(int $counselorId)
    {
        return Appointment::where('counselor_id', $counselorId)
            ->where('status', 'confirmed')
            ->whereNull('session_outcome')
            ->distinct()
            ->pluck('student_id');
    }

    private function assignedConsentedEntries(int $counselorId)
    {
        return EmotionalEntry::query()
            ->whereHas('user.privacySetting', fn ($query) =>
                $query->withCurrentConsent('share_with_counselor'))
            ->whereHas('user.appointments', fn ($query) => $query
                ->where('counselor_id', $counselorId)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome'));
    }

    private function hasAppointmentHistory(int $studentId, int $counselorId): bool
    {
        return Appointment::where('student_id', $studentId)
            ->where('counselor_id', $counselorId)
            ->where(function ($query) {
                $query->whereIn('status', ['confirmed', 'completed', 'cancelled'])
                    ->orWhereNotNull('session_outcome');
            })
            ->exists();
    }
    private function hasConfirmedAppointment(int $studentId, int $counselorId): bool
    {
        return Appointment::where('student_id', $studentId)
            ->where('counselor_id', $counselorId)
            ->where('status', 'confirmed')
            ->whereNull('session_outcome')
            ->exists();
    }

    private function hasMessagingAppointmentAccess(int $studentId, int $counselorId): bool
    {
        return app(AppointmentLifecycleService::class)
            ->hasMessagingAppointment($counselorId, $studentId);
    }

    private function hasJournalAccess(int $studentId, int $counselorId): bool
    {
        return $this->hasConfirmedAppointment($studentId, $counselorId)
            && app(PrivacyService::class)->canShareWithCounselor($studentId);
    }

    /**
     * Safely stream confidential counseling chat attachments to authorized participants.
     */
    public function viewAttachment(int $messageId)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $message = CounselorMessage::findOrFail($messageId);
        abort_if($message->isUnsent(), 404, 'Attachment is no longer available.');

        $isAuthorized = (int) $message->student_id === (int) $user->id
            || (int) $message->counselor_id === (int) $user->id
            || (int) $message->sender_id === (int) $user->id
            || $user->isAdmin();

        abort_unless($isAuthorized, 403, 'Unauthorized to access this attachment.');

        $path = $message->attachment_path;
        abort_unless($path, 404, 'Attachment path not recorded.');

        // First check the secure local disk (new uploads)
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->response(
                $path,
                $message->attachment_name ?: basename($path),
                [
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        // Backward compatibility fallback for legacy attachments stored on public disk
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->response(
                $path,
                $message->attachment_name ?: basename($path),
                [
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        abort(404, 'Attachment file not found on disk.');
    }
}
