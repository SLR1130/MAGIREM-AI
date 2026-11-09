<?php

namespace App\Http\Controllers;

use App\Models\AccountVerificationAudit;
use App\Models\User;
use App\Models\PrivacySetting;
use App\Services\AccountVerificationEmailService;
use App\Services\VerificationDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly VerificationDocumentStorage $documents,
        private readonly AccountVerificationEmailService $emails,
    ) {}

    public function show(Request $request): View
    {
        $localRegistrationPreview = $this->isIsolatedLocalRegistrationPreview($request);
        $registrationBlocker = $this->registrationBlocker();
        $localDevelopment = app()->environment('local');

        $viewData = [
            'registrationEnabled' => $registrationBlocker === null,
            'registrationBlocker' => $registrationBlocker,
            'localRegistrationPreview' => $localRegistrationPreview,
            'privacyNoticeVersion' => $localRegistrationPreview
                ? 'LOCAL-SYNTHETIC-TEST'
                : config('services.account_verification.privacy_notice_version'),
            'privacyNoticeText' => $localRegistrationPreview
                ? 'Local test notice only. This is not an institution-approved MinSU privacy notice. Use synthetic data only; do not submit real identification or personal information.'
                : config('services.account_verification.privacy_notice_text'),
            'privacyContact' => $localRegistrationPreview
                ? 'Not configured — institutional approval required'
                : config('services.account_verification.privacy_contact'),
            'retentionSchedule' => $localRegistrationPreview
                ? 'No approved schedule — isolated test database only; no real records'
                : config('services.account_verification.retention_schedule'),
        ];

        if ($localDevelopment && !$localRegistrationPreview && !filled($viewData['privacyNoticeVersion'])) {
            $viewData['privacyNoticeVersion'] = 'LOCAL-DEVELOPMENT-NOTICE';
            $viewData['privacyNoticeText'] = 'Local development notice: this environment is for authorized testing only. Use synthetic data only; do not submit real student information or identification. This notice is not an institution-approved production privacy policy.';
            $viewData['privacyContact'] = 'Project owner — local development configuration (not an official institutional privacy contact)';
            $viewData['retentionSchedule'] = 'Local development handling only; production retention policy is not configured and automated cleanup remains disabled.';
        }

        return view('auth.register', $viewData);
    }

    public function submit(Request $request): RedirectResponse
    {
        if (($blocker = $this->registrationBlocker()) !== null) {
            return redirect()->route('register')->with('registration_error', $blocker);
        }

        $institutionalEmail = $request->input('institutional_email');
        $notificationEmail = $request->input('notification_email');
        $request->merge([
            'institutional_email' => is_string($institutionalEmail) ? mb_strtolower(trim($institutionalEmail)) : $institutionalEmail,
            'notification_email' => is_string($notificationEmail) ? mb_strtolower(trim($notificationEmail)) : $notificationEmail,
        ]);

        $applicationRules = [
            'name' => ['required', 'string', 'max:255'],
            'institutional_email' => ['required', 'string', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@minsu\.edu\.ph$/i', Rule::unique('users', 'email'), Rule::unique('users', 'institutional_email')],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            // Public registration has no role selector and always creates a
            // student application. Reject attempts to supply another role.
            'role' => ['prohibited'],
            'requested_role' => ['prohibited'],
            'campus' => ['required', Rule::in(['Bongabong Campus', 'Calapan Campus', 'Victoria Campus'])],
            'student_id' => ['required', 'string', 'max:80', 'unique:users,student_id'],
            'program' => ['required', 'string', 'max:160'],
            'year_level' => ['required', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
            'notification_email' => ['required', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i'],
            'id_photo' => ['required_without:alternative_review', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=120,max_width=5000,max_height=5000'],
            'privacy_acknowledgement' => ['accepted'],
        ];
        if (!$request->hasFile('id_photo')) {
            $applicationRules['alternative_review'] = ['required', 'accepted'];
        }
        $validated = $request->validate($applicationRules, [
            'institutional_email.regex' => 'Use your MinSU institutional email ending exactly in @minsu.edu.ph.',
            'institutional_email.unique' => 'This MinSU institutional email already has an application or account.',
            'campus.in' => 'Select one of the three MinSU campuses.',
            'role.prohibited' => 'Public registration does not accept a role value. Staff and administrator accounts are provisioned internally.',
            'requested_role.prohibited' => 'Public registration is for student accounts only. Staff and administrator accounts are provisioned internally.',
            'password.min' => 'Use a password with at least 12 characters.',
            'password.mixed' => 'Use uppercase and lowercase letters in your password.',
            'password.numbers' => 'Include at least one number in your password.',
            'password.symbols' => 'Include at least one symbol in your password.',
            'password.confirmed' => 'The password confirmation does not match.',
            'notification_email.regex' => 'Use a personal Gmail address ending in gmail.com or googlemail.com.',
            'id_photo.required_without' => 'Upload a clear school ID image or request the accessible alternative review method.',
            'alternative_review.required_without' => 'Upload a clear school ID image or request the accessible alternative review method.',
            'privacy_acknowledgement.accepted' => 'Please acknowledge the registration privacy notice to continue.',
        ]);

        $canonicalEmail = $validated['institutional_email'];
        $caseInsensitiveDuplicate = User::query()->where(function ($query) use ($canonicalEmail): void {
            $query->whereRaw('LOWER(email) = ?', [$canonicalEmail])
                ->orWhereRaw('LOWER(institutional_email) = ?', [$canonicalEmail]);
        })->exists();
        if ($caseInsensitiveDuplicate) {
            return back()->withErrors(['institutional_email' => 'This MinSU institutional email already has an application or account.'])->withInput();
        }

        $documentPath = null;
        if ($request->file('id_photo')) {
            $documentPath = $this->documents->store($request->file('id_photo'));
        }

        try {
            $user = DB::transaction(function () use ($validated, $documentPath): User {
                $user = new User();
                $user->name = trim($validated['name']);
                $user->email = $validated['institutional_email'];
                $user->institutional_email = $validated['institutional_email'];
                // No school inbox confirmation is performed; authorized reviewers
                // record institutional verification only after independent checks.
                $user->email_verified_at = null;
                $user->password = Hash::make($validated['password']);
                $user->password_setup_completed_at = now();
                $user->owner_onboarding_required = false;
                $user->role = 'student';
                $user->requested_role = 'student';
                $user->is_active = true;
                $user->approval_status = 'pending';
                $user->approval_submitted_at = now();
                $user->microsoft_tenant_id = null;
                $user->microsoft_object_id = null;
                $user->institutional_verified_at = null;
                $user->campus = trim($validated['campus']);
                $user->notification_email = strtolower(trim($validated['notification_email']));
                $user->notification_email_enabled = true;
                $user->privacy_consent_version = app()->environment('local') && !filled(config('services.account_verification.privacy_notice_version'))
                    ? 'LOCAL-DEVELOPMENT-NOTICE'
                    : config('services.account_verification.privacy_notice_version');
                $user->privacy_consent_at = now();
                $user->verification_document_path = $documentPath;
                $user->verification_document_submitted_at = $documentPath ? now() : null;
                $user->alternative_review_requested_at = $documentPath ? null : now();

                $user->student_id = trim($validated['student_id']);
                $user->program = trim($validated['program']);
                $user->year_level = $validated['year_level'];
                $user->save();

                PrivacySetting::getOrCreateForUser($user->id);
                AccountVerificationAudit::query()->create([
                    'application_user_id' => $user->id,
                    'actor_user_id' => null,
                    'action' => 'submitted',
                    'from_status' => null,
                    'to_status' => 'pending',
                    'internal_note' => null,
                    'created_at' => now(),
                ]);
                return $user;
            });
        } catch (Throwable $exception) {
            $this->documents->delete($documentPath);
            throw $exception;
        }

        $request->session()->regenerate();
        Auth::login($user);
        $request->session()->regenerate();

        $mailQueued = $this->emails->issueCode($user);
        return redirect()->route('account.status')->with(
            $mailQueued ? 'success' : 'warning',
            $mailQueued
                ? 'Registration submitted. Check your personal Gmail for a verification code; administrator review is pending.'
                : 'Registration was submitted, but email delivery is not configured. Your Gmail is not verified and approval cannot proceed until delivery is configured or an authorized alternative is arranged.'
        );
    }

    public function status(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $adminCreated = $user->approval_submitted_at === null
            && $user->requested_role
            && $user->verificationAudits()->whereIn('action', ['admin_created_pending', 'admin_created_approved'])->exists();
        if ($user->isApproved() && !$user->requiresOwnerOnboarding()) {
            return redirect()->route($user->isAdmin() ? 'admin.dashboard' : ($user->isCounselor() ? 'counselor.dashboard' : 'home'));
        }

        return view('auth.account-status', [
            'user' => $user,
            'adminCreated' => (bool) $adminCreated,
            'ownerOnboarding' => $user->requiresOwnerOnboarding(),
            'privacyContact' => config('services.account_verification.privacy_contact'),
            'retentionSchedule' => config('services.account_verification.retention_schedule'),
            'mailConfigured' => $this->emails->transportConfigured(),
        ]);
    }

    public function resendEmailVerification(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->notification_email_verified_at) {
            return back()->with('info', 'Your personal notification email is already verified.');
        }
        if (!$user->notification_email) {
            return back()->withErrors(['notification_email' => 'No personal Gmail address is on this application.']);
        }

        $result = $this->emails->resendCode($user);
        return match ($result['status']) {
            'queued' => back()->with('success', 'A new verification code was queued. Check your Gmail.'),
            'cooldown' => back()->with('warning', 'Please wait ' . $result['retry_after'] . ' seconds before requesting another code.'),
            'not_configured' => back()->with('warning', 'The mail transport is not configured. No email was sent.'),
            default => back()->with('warning', 'The verification email could not be queued. No email was sent.'),
        };
    }

    public function confirmEmailVerification(Request $request): RedirectResponse
    {
        $completingOwnerOnboarding = $request->user()->requiresOwnerOnboarding()
            && $request->user()->password_setup_completed_at !== null
            && $request->user()->isApproved();
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        if (!$this->emails->confirmCode($request->user(), $validated['code'])) {
            return back()->withErrors(['code' => 'That code is invalid, expired, already used, or has reached its attempt limit.']);
        }

        AccountVerificationAudit::query()->create([
            'application_user_id' => $request->user()->id,
            'actor_user_id' => null,
            'action' => 'notification_email_verified',
            'from_status' => $request->user()->approval_status,
            'to_status' => $request->user()->approval_status,
            'internal_note' => null,
            'created_at' => now(),
        ]);
        if ($request->user()->requiresOwnerOnboarding() && $request->user()->password_setup_completed_at !== null) {
            $request->user()->forceFill(['owner_onboarding_required' => false])->save();
            AccountVerificationAudit::query()->create([
                'application_user_id' => $request->user()->id,
                'actor_user_id' => $request->user()->id,
                'action' => 'owner_onboarding_completed',
                'from_status' => $request->user()->approval_status,
                'to_status' => $request->user()->approval_status,
                'internal_note' => 'Owner completed first password setup and verified personal Gmail. Existing institutional approval was preserved.',
                'created_at' => now(),
            ]);
        }
        $message = $completingOwnerOnboarding
            ? 'Your personal Gmail is verified and first-use setup is complete. Your existing Guidance approval is unchanged.'
            : 'Your personal notification email is verified. Administrator review remains pending.';
        if ($completingOwnerOnboarding) {
            return redirect()->route($request->user()->isCounselor() ? 'counselor.dashboard' : 'home')->with('success', $message);
        }
        return back()->with('success', $message);
    }

    public function resubmit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->approval_submitted_at !== null && in_array($user->approval_status, ['pending', 'needs_information'], true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'campus' => ['required', Rule::in(['Bongabong Campus', 'Calapan Campus', 'Victoria Campus'])],
            'student_id' => [$user->requested_role === 'student' ? 'required' : 'nullable', 'string', 'max:80', Rule::unique('users', 'student_id')->ignore($user->id)],
            'program' => [$user->requested_role === 'student' ? 'required' : 'nullable', 'string', 'max:160'],
            'year_level' => [$user->requested_role === 'student' ? 'required' : 'nullable', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
            'staff_position' => [$user->requested_role === 'counselor' ? 'required' : 'nullable', 'string', 'max:160'],
            'specialization' => ['nullable', 'string', 'max:160'],
            'notification_email' => ['required', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i'],
            'id_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=120,max_width=5000,max_height=5000'],
            'alternative_review' => ['nullable', 'accepted'],
            'privacy_acknowledgement' => ['accepted'],
        ]);

        $newPath = $request->file('id_photo') ? $this->documents->store($request->file('id_photo')) : null;
        $oldPath = $user->verification_document_path;
        $emailChanged = strtolower((string) $user->notification_email) !== strtolower($validated['notification_email']);

        try {
            DB::transaction(function () use ($user, $validated, $newPath, $emailChanged): void {
                $previous = $user->approval_status;
                $user->name = trim($validated['name']);
                $user->campus = trim($validated['campus']);
                if ($user->requested_role === 'student') {
                    $user->student_id = trim($validated['student_id']);
                    $user->program = trim($validated['program']);
                    $user->year_level = $validated['year_level'];
                } else {
                    $user->staff_position = trim($validated['staff_position']);
                    $user->specialization = filled($validated['specialization'] ?? null) ? trim($validated['specialization']) : null;
                }
                if ($emailChanged) {
                    $user->notification_email = strtolower(trim($validated['notification_email']));
                    $user->notification_email_verified_at = null;
                    $user->verification_email_delivery_status = 'not_requested';
                }
                if ($newPath) {
                    $user->verification_document_path = $newPath;
                    $user->verification_document_submitted_at = now();
                    $user->alternative_review_requested_at = null;
                } elseif (!empty($validated['alternative_review'])) {
                    $user->verification_document_path = null;
                    $user->alternative_review_requested_at = now();
                }
                $user->privacy_consent_version = app()->environment('local') && !filled(config('services.account_verification.privacy_notice_version'))
                    ? 'LOCAL-DEVELOPMENT-NOTICE'
                    : config('services.account_verification.privacy_notice_version');
                $user->privacy_consent_at = now();
                $user->approval_status = 'pending';
                $user->approval_submitted_at = now();
                $user->information_request = null;
                $user->save();

                AccountVerificationAudit::query()->create([
                    'application_user_id' => $user->id,
                    'actor_user_id' => $user->id,
                    'action' => 'resubmitted',
                    'from_status' => $previous,
                    'to_status' => 'pending',
                    'internal_note' => null,
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->documents->delete($newPath);
            throw $exception;
        }

        if ($newPath || (!empty($validated['alternative_review']) && $oldPath)) {
            $this->documents->delete($oldPath);
        }
        if ($emailChanged) {
            // A reset link was addressed to the previously verified Gmail. Once
            // that address changes, invalidate any outstanding broker token.
            PasswordBroker::broker()->deleteToken($user->fresh());
            $this->emails->issueCode($user->fresh());
        }

        return redirect()->route('account.status')->with('success', 'Your updated application was submitted for administrator review.');
    }

    private function registrationBlocker(): ?string
    {
        $localPreview = $this->isIsolatedLocalRegistrationPreview(request());
        $localDevelopment = app()->environment('local');

        if (!$localPreview && !config('services.account_verification.native_registration_enabled', false)) {
            return 'Native registration is currently closed. An administrator must enable it after institutional privacy and review requirements are approved.';
        }
        if (!$localPreview && !$localDevelopment && (!config('services.account_verification.privacy_notice_approved', false)
            || !filled(config('services.account_verification.privacy_notice_version'))
            || mb_strlen((string) config('services.account_verification.privacy_notice_version')) > 40
            || !filled(config('services.account_verification.privacy_notice_text'))
            || !filled(config('services.account_verification.privacy_contact')))) {
            return 'Registration is temporarily unavailable until the institution-approved privacy notice and privacy contact are configured.';
        }

        $retentionStatuses = (array) config('services.account_verification.id_retention_statuses', []);
        $eligibleStatuses = ['approved', 'rejected', 'suspended'];
        $retentionDays = filter_var(config('services.account_verification.id_retention_days_after_review'), FILTER_VALIDATE_INT);
        if (!$localPreview && !$localDevelopment && (!config('services.account_verification.retention_policy_approved', false)
            || !filled(config('services.account_verification.retention_policy_version'))
            || !filled(config('services.account_verification.retention_schedule'))
            || $retentionDays === false
            || $retentionDays < 1
            || $retentionStatuses === []
            || array_diff($retentionStatuses, $eligibleStatuses) !== []
            || !config('services.account_verification.id_retention_enforcement_enabled', false))) {
            return 'Registration is temporarily unavailable until the institution-approved privacy contact and identification-record retention schedule are configured.';
        }

        $reviewerIds = array_values(array_unique(array_filter(array_map('intval', (array) config('services.account_verification.reviewer_user_ids', [])), static fn (int $id): bool => $id > 0)));
        if (!config('services.account_verification.reviewer_authorization_approved', false)
            || $reviewerIds === []
            || !User::query()->whereIn('id', $reviewerIds)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('approval_status')->orWhere('approval_status', 'approved');
                })->exists()
        ) {
            return 'Registration is temporarily unavailable until an existing active administrator is explicitly authorized as a Guidance Office reviewer.';
        }

        return null;
    }

    /**
     * Permit the local synthetic registration preview without changing public
     * registration or institutional approval flags. The preview is deliberately
     * constrained to loopback requests and a SQLite file beneath the private
     * audit-recovery directory; it can never open against MySQL or production.
     */
    private function isIsolatedLocalRegistrationPreview(Request $request): bool
    {
        if (!app()->environment('local')
            || !config('services.account_verification.local_registration_preview', false)
            || config('database.default') !== 'sqlite'
            || !in_array(strtolower($request->getHost()), ['127.0.0.1', 'localhost', '[::1]', '::1'], true)
            || !in_array($request->ip(), ['127.0.0.1', '::1'], true)
        ) {
            return false;
        }

        $configuredDatabase = (string) config('database.connections.sqlite.database');
        $databasePath = realpath($configuredDatabase);
        $privateRoot = realpath(storage_path('app/audit-recovery'));
        if ($databasePath === false || $privateRoot === false) {
            return false;
        }

        $prefix = rtrim($privateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with(strtolower($databasePath), strtolower($prefix))
            && is_file($databasePath);
    }
}
