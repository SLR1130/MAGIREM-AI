<?php

namespace App\Http\Controllers;

use App\Models\AccountVerificationAudit;
use App\Models\AccountInvitation;
use App\Models\User;
use App\Services\AccountVerificationEmailService;
use App\Services\AccountInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AccountVerificationController extends Controller
{
    public function __construct(
        private readonly AccountVerificationEmailService $emails,
        private readonly AccountInvitationService $invitations,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $role = $request->query('role');
        $source = $request->query('source', 'self_registration');
        $search = trim((string) $request->query('q', ''));

        if (!in_array($source, ['self_registration', 'admin_created', 'all'], true)) {
            $source = 'self_registration';
        }

        $base = User::query()->whereNotNull('requested_role');
        $adminCreatedActions = ['admin_created_pending', 'admin_created_approved'];
        $selfRegistered = static fn ($query) => $query->whereDoesntHave(
            'verificationAudits', static fn ($audits) => $audits->whereIn('action', $adminCreatedActions)
        );
        $adminCreated = static fn ($query) => $query->whereNull('approval_submitted_at')
            ->whereHas('verificationAudits', static fn ($audits) => $audits->whereIn('action', $adminCreatedActions));
        $sourceCounts = [
            'self_registration' => (clone $base)->where($selfRegistered)->count(),
            'admin_created' => (clone $base)->where($adminCreated)->count(),
        ];

        $query = clone $base;
        if ($source === 'self_registration') {
            $query->where($selfRegistered);
        } elseif ($source === 'admin_created') {
            $query->where($adminCreated);
        } else {
            $query->where(function ($sources) use ($selfRegistered, $adminCreated): void {
                $sources->where($selfRegistered)->orWhere($adminCreated);
            });
        }
        if (in_array($status, ['pending', 'approved', 'rejected', 'needs_information', 'suspended'], true)) {
            $query->where('approval_status', $status);
        } else {
            $status = 'all';
        }
        if (in_array($role, ['student', 'counselor'], true)) {
            $query->where('requested_role', $role);
        } else {
            $role = 'all';
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('institutional_email', 'like', '%' . $search . '%')
                    ->orWhere('student_id', 'like', '%' . $search . '%')
                    ->orWhere('campus', 'like', '%' . $search . '%');
            });
        }

        $countsQuery = clone $base;
        if ($source === 'self_registration') {
            $countsQuery->where($selfRegistered);
        } elseif ($source === 'admin_created') {
            $countsQuery->where($adminCreated);
        } else {
            $countsQuery->where(function ($sources) use ($selfRegistered, $adminCreated): void {
                $sources->where($selfRegistered)->orWhere($adminCreated);
            });
        }
        $counts = $countsQuery
            ->selectRaw("approval_status, requested_role, COUNT(*) as aggregate")
            ->groupBy('approval_status', 'requested_role')->get();

        return view('admin.account-verification.index', [
            'applications' => $query
                ->withExists(['verificationAudits as admin_created_account' => static fn ($audits) => $audits->whereIn('action', $adminCreatedActions)])
                ->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'status' => $status,
            'role' => $role,
            'source' => $source,
            'sourceCounts' => $sourceCounts,
            'search' => $search,
            'counts' => $counts,
        ]);
    }

    public function show(User $user): View
    {
        $adminCreated = $this->isAdminCreated($user);
        abort_unless($user->requested_role && ($user->approval_submitted_at || $adminCreated), 404);

        return view('admin.account-verification.show', [
            'applicant' => $user,
            'adminCreated' => $adminCreated,
            'audits' => $user->verificationAudits()->with('actor')->latest('created_at')->get(),
            'deliveries' => $user->verificationEmailDeliveries()->latest()->get(),
            'invitations' => $user->accountInvitations()->with('creator')->latest()->get(),
        ]);
    }

    public function document(User $user): BinaryFileResponse
    {
        abort_unless(
            $user->requested_role
                && is_string($user->verification_document_path)
                && preg_match('/^verification-documents\/[a-f0-9]{48}\.webp$/', $user->verification_document_path),
            404
        );
        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->verification_document_path), 404);

        AccountVerificationAudit::query()->create([
            'application_user_id' => $user->id,
            'actor_user_id' => auth()->id(),
            'action' => 'document_accessed',
            'from_status' => $user->approval_status,
            'to_status' => $user->approval_status,
            'internal_note' => null,
            'created_at' => now(),
        ]);

        $response = response()->file($disk->path($user->verification_document_path), [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="verification-document.webp"',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        return $response;
    }

    public function approve(Request $request, User $user): Response|RedirectResponse
    {
        $adminCreated = $this->isAdminCreated($user);
        $ownerSetup = $adminCreated && $user->requiresOwnerOnboarding() && $user->password_setup_completed_at === null;
        abort_unless($user->requested_role && in_array($user->approval_status, ['pending', 'needs_information'], true), 409);
        abort_if((int) $user->id === (int) $request->user()->id, 403, 'Administrators cannot approve their own account.');
        $rules = [
            'institutional_identity_confirmed' => ['required', 'accepted'],
            'eligibility_confirmed' => ['required', 'accepted'],
            'internal_review_reason' => ['required', 'string', 'min:8', 'max:2000'],
        ];
        if ($adminCreated) {
            $rules['verification_basis'] = ['required', 'string', 'min:8', 'max:2000'];
        } else {
            $rules['identification_reviewed'] = ['required', 'accepted'];
        }
        if ($ownerSetup) {
            $rules['invitation_method'] = ['required', Rule::in(['email', 'in_person'])];
            $rules['notification_email'] = [
                $request->input('invitation_method') === 'email' ? 'required' : 'nullable',
                'email:rfc', 'max:255', 'regex:/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i',
            ];
            $rules['notification_email_contact_confirmed'] = [
                'sometimes',
                $request->input('invitation_method') === 'email' ? 'required' : 'nullable',
                'accepted',
            ];
        }
        if ($user->requested_role === 'counselor') {
            $rules['counselor_authorized'] = ['required', 'accepted'];
        }
        if (!$adminCreated && !$user->verification_document_path) {
            $rules['alternative_review_confirmed'] = ['required', 'accepted'];
        }
        $validated = $request->validate($rules);
        if (!$adminCreated && !$user->notification_email_verified_at) {
            return back()->withErrors(['approval' => 'Verify the personal notification email before approval.']);
        }
        if (!$adminCreated && !$user->verification_document_path && !$user->alternative_review_requested_at) {
            return back()->withErrors(['approval' => 'No school ID document or accessible alternative-review request is recorded.']);
        }

        DB::transaction(function () use ($request, $user, $validated, $adminCreated, $ownerSetup): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $previous = $locked->approval_status;
            abort_unless(in_array($previous, ['pending', 'needs_information'], true), 409);
            abort_if((int) $locked->id === (int) $request->user()->id, 403);
            abort_unless(in_array($locked->requested_role, ['student', 'counselor'], true), 409);

            $locked->role = $locked->requested_role;
            $locked->approval_status = 'approved';
            $locked->is_active = true;
            $locked->institutional_verified_at = now();
            $locked->approval_reviewer_id = $request->user()->id;
            $locked->approval_reviewed_at = now();
            $reviewNote = $adminCreated
                ? 'Admin-created account; institutional identity and eligibility independently confirmed by an authorized reviewer. Verification basis: ' . trim($validated['verification_basis']) . '. Review note: ' . trim($validated['internal_review_reason'])
                : 'Institutional identity, identification review, and eligibility independently confirmed through approved official procedures. ' . trim($validated['internal_review_reason']);
            if (!$adminCreated && !$locked->verification_document_path) {
                $reviewNote = 'Accessible alternative verification was reviewed and confirmed. ' . $reviewNote;
            }
            if ($locked->requested_role === 'counselor') {
                $reviewNote = 'Counselor role authorization confirmed through the official institutional channel. ' . $reviewNote;
            }
            $locked->internal_review_reason = $reviewNote;
            $locked->information_request = null;
            if ($ownerSetup && ($validated['invitation_method'] ?? null) === 'email') {
                $locked->notification_email = mb_strtolower(trim($validated['notification_email']));
                $locked->notification_email_enabled = true;
                $locked->notification_email_confirmed_at = now();
                $locked->notification_email_confirmed_by = $request->user()->id;
                $locked->notification_email_verified_at = null;
                $locked->verification_email_delivery_status = 'not_requested';
            }
            $locked->save();
            $this->audit($locked, $request->user()->id, $adminCreated ? 'admin_created_approved' : 'approved', $previous, 'approved', $reviewNote);
            if ($ownerSetup) {
                $this->audit($locked, $request->user()->id, ($validated['invitation_method'] ?? null) === 'email' ? 'owner_invitation_contact_confirmed' : 'owner_invitation_method_selected', 'pending', 'approved',
                    ($validated['invitation_method'] ?? null) === 'email'
                        ? "Reviewer confirmed the owner's personal Gmail association. Gmail ownership remains unverified until OTP."
                        : 'Reviewer selected the one-time in-person owner setup handoff.');
            }
        });

        $fresh = $user->fresh();
        if ($ownerSetup) {
            try {
                $invitation = $this->invitations->issue($fresh, (int) $request->user()->id, $validated['invitation_method']);
            } catch (\InvalidArgumentException) {
                return redirect()->route('admin.account-verification.show', $fresh)
                    ->with('warning', 'The account is approved but remains restricted because a secure owner invitation could not be created. No password was set or sent.');
            }
            if ($validated['invitation_method'] === 'in_person') {
                return response()->view('admin.account-verification.manual-invitation', [
                    'applicant' => $fresh,
                    'handoffToken' => $invitation['handoff_token'],
                    'activationUrl' => $invitation['activation_url'],
                    'expiresAt' => $invitation['invitation']->expires_at,
                ])->header('Cache-Control', 'private, no-store, max-age=0')
                    ->header('Pragma', 'no-cache')
                    ->header('Referrer-Policy', 'no-referrer')
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }
            $message = $invitation['delivery_status'] === 'queued'
                ? 'Admin-created account approved. The one-time setup invitation is queued, not sent yet; a queue worker must process it. Normal access remains blocked until the owner sets a password and verifies Gmail.'
                : 'Admin-created account approved but remains restricted. The invitation was not queued; use the reviewer page to retry or arrange a secure in-person handoff.';
            return redirect()->route('admin.account-verification.show', $fresh)
                ->with($invitation['delivery_status'] === 'queued' ? 'success' : 'warning', $message);
        }
        $notice = $fresh->notification_email_verified_at ? $this->safeQueueNotice($fresh, 'approved') : 'not_verified';
        $fresh->forceFill(['approval_notification_status' => $notice])->save();
        return redirect()->route('admin.account-verification.show', $fresh)->with('success', ($adminCreated ? 'Admin-created account approved. ' : 'Application approved. ') . 'The account is active; the email notice status is ' . str_replace('_', ' ', $notice) . '.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $adminCreated = $this->isAdminCreated($user);
        abort_unless($user->requested_role && in_array($user->approval_status, ['pending', 'needs_information'], true), 409);
        abort_if((int) $user->id === (int) $request->user()->id, 403, 'Administrators cannot review their own application.');
        $validated = $request->validate(['internal_review_reason' => ['required', 'string', 'min:8', 'max:2000']]);

        DB::transaction(function () use ($request, $user, $validated, $adminCreated): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless(in_array($locked->approval_status, ['pending', 'needs_information'], true), 409);
            abort_if((int) $locked->id === (int) $request->user()->id, 403);
            $previous = $locked->approval_status;
            $locked->approval_status = 'rejected';
            $locked->is_active = !$adminCreated; // Self-applicants may read status; admin-created records stay login-restricted.
            $locked->approval_reviewer_id = $request->user()->id;
            $locked->approval_reviewed_at = now();
            $locked->internal_review_reason = trim($validated['internal_review_reason']);
            $locked->information_request = null;
            $locked->save();
            $this->audit($locked, $request->user()->id, 'rejected', $previous, 'rejected', $validated['internal_review_reason']);
        });

        $fresh = $user->fresh();
        $notice = $fresh->notification_email_verified_at ? $this->safeQueueNotice($fresh, 'rejected') : 'not_verified';
        $fresh->forceFill(['approval_notification_status' => $notice])->save();
        return redirect()->route('admin.account-verification.show', $fresh)->with('success', 'Application rejected. The internal reason is not included in email.');
    }

    public function requestInformation(Request $request, User $user): RedirectResponse
    {
        abort_if($this->isAdminCreated($user), 409, 'Admin-created restricted accounts are not self-registration applicants.');
        abort_unless($user->requested_role && in_array($user->approval_status, ['pending', 'needs_information'], true), 409);
        abort_if((int) $user->id === (int) $request->user()->id, 403, 'Administrators cannot review their own application.');
        $validated = $request->validate([
            'information_request' => ['required', 'string', 'min:8', 'max:2000'],
            'internal_review_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $user, $validated): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless(in_array($locked->approval_status, ['pending', 'needs_information'], true), 409);
            abort_if((int) $locked->id === (int) $request->user()->id, 403);
            $previous = $locked->approval_status;
            $locked->approval_status = 'needs_information';
            $locked->information_request = trim($validated['information_request']);
            $locked->approval_reviewer_id = $request->user()->id;
            $locked->approval_reviewed_at = now();
            $locked->internal_review_reason = filled($validated['internal_review_reason'] ?? null) ? trim($validated['internal_review_reason']) : null;
            $locked->save();
            $this->audit($locked, $request->user()->id, 'information_requested', $previous, 'needs_information', $validated['internal_review_reason'] ?? null);
        });

        $fresh = $user->fresh();
        $notice = $fresh->notification_email_verified_at ? $this->safeQueueNotice($fresh, 'needs_information') : 'not_verified';
        $fresh->forceFill(['approval_notification_status' => $notice])->save();
        return redirect()->route('admin.account-verification.show', $fresh)->with('success', 'More information requested. The applicant can sign in to view the request.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->requested_role && $user->approval_status === 'approved', 409);
        abort_if((int) $user->id === (int) $request->user()->id, 403);
        $validated = $request->validate(['internal_review_reason' => ['required', 'string', 'min:8', 'max:2000']]);

        DB::transaction(function () use ($request, $user, $validated): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->approval_status === 'approved', 409);
            abort_if((int) $locked->id === (int) $request->user()->id, 403);
            $locked->approval_status = 'suspended';
            $locked->is_active = false;
            $locked->approval_reviewer_id = $request->user()->id;
            $locked->approval_reviewed_at = now();
            $locked->internal_review_reason = trim($validated['internal_review_reason']);
            $locked->save();
            AccountInvitation::query()->where('user_id', $locked->id)->whereNull('accepted_at')->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit($locked, $request->user()->id, 'suspended', 'approved', 'suspended', $validated['internal_review_reason']);
        });

        $fresh = $user->fresh();
        $notice = $fresh->notification_email_verified_at ? $this->safeQueueNotice($fresh, 'suspended') : 'not_verified';
        $fresh->forceFill(['approval_notification_status' => $notice])->save();
        return redirect()->route('admin.account-verification.show', $fresh)->with('success', 'Account suspended. Existing sessions will be denied on their next request.');
    }

    private function safeQueueNotice(User $user, string $kind): string
    {
        try {
            return $this->emails->queueStatusNotice($user, $kind);
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function audit(User $applicant, int $actorId, string $action, ?string $from, ?string $to, ?string $note): void
    {
        AccountVerificationAudit::query()->create([
            'application_user_id' => $applicant->id,
            'actor_user_id' => $actorId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'internal_note' => filled($note) ? trim($note) : null,
            'created_at' => now(),
        ]);
    }

    private function isAdminCreated(User $user): bool
    {
        return $user->approval_submitted_at === null
            && $user->verificationAudits()
                ->whereIn('action', ['admin_created_pending', 'admin_created_approved'])
                ->exists();
    }
}
