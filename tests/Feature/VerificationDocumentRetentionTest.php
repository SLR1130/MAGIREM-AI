<?php

namespace Tests\Feature;

use App\Models\AccountVerificationAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationDocumentRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'services.account_verification.retention_policy_approved' => false,
            'services.account_verification.retention_policy_version' => null,
            'services.account_verification.retention_schedule' => null,
            'services.account_verification.id_retention_days_after_review' => null,
            'services.account_verification.id_retention_statuses' => [],
            'services.account_verification.id_retention_enforcement_enabled' => false,
        ]);
    }

    public function test_unconfigured_retention_policy_is_a_no_op_even_for_old_reviewed_records(): void
    {
        $user = $this->applicant('approved', now()->subDays(90));
        Storage::disk('local')->put($user->verification_document_path, 'synthetic-private-image');

        $this->assertSame(0, Artisan::call('account-verification:purge-expired-documents'));

        Storage::disk('local')->assertExists($user->verification_document_path);
        $this->assertNotNull($user->fresh()->verification_document_path);
        $this->assertSame(0, AccountVerificationAudit::query()->count());
    }

    public function test_approved_retention_purges_only_configured_reviewed_terminal_statuses_and_audits(): void
    {
        config([
            'services.account_verification.retention_policy_approved' => true,
            'services.account_verification.retention_policy_version' => 'synthetic-policy-v1',
            'services.account_verification.retention_schedule' => 'Synthetic test policy; not institutional.',
            'services.account_verification.id_retention_days_after_review' => 30,
            'services.account_verification.id_retention_statuses' => ['approved', 'rejected'],
            'services.account_verification.id_retention_enforcement_enabled' => true,
        ]);

        $approved = $this->applicant('approved', now()->subDays(40));
        $rejected = $this->applicant('rejected', now()->subDays(40));
        $pending = $this->applicant('pending', now()->subDays(90));
        $needsInformation = $this->applicant('needs_information', now()->subDays(90));
        $recent = $this->applicant('approved', now()->subDays(10));
        $users = [$approved, $rejected, $pending, $needsInformation, $recent];
        foreach ($users as $user) {
            Storage::disk('local')->put($user->verification_document_path, 'synthetic-private-image');
        }

        $this->assertSame(0, Artisan::call('account-verification:purge-expired-documents'));

        foreach ([$approved, $rejected] as $purged) {
            Storage::disk('local')->assertMissing($purged->verification_document_path);
            $this->assertNull($purged->fresh()->verification_document_path);
            $this->assertDatabaseHas('account_verification_audits', [
                'application_user_id' => $purged->id,
                'actor_user_id' => null,
                'action' => 'verification_document_retention_deleted',
                'from_status' => $purged->approval_status,
                'to_status' => $purged->approval_status,
            ]);
        }
        foreach ([$pending, $needsInformation, $recent] as $retained) {
            Storage::disk('local')->assertExists($retained->verification_document_path);
            $this->assertNotNull($retained->fresh()->verification_document_path);
        }
        $this->assertSame(2, AccountVerificationAudit::query()->where('action', 'verification_document_retention_deleted')->count());
    }

    public function test_dry_run_reports_eligible_documents_without_changing_files_or_rows(): void
    {
        $this->enableSyntheticPolicy();
        $user = $this->applicant('approved', now()->subDays(40));
        Storage::disk('local')->put($user->verification_document_path, 'synthetic-private-image');

        $this->assertSame(0, Artisan::call('account-verification:purge-expired-documents', ['--dry-run' => true]));

        Storage::disk('local')->assertExists($user->verification_document_path);
        $this->assertNotNull($user->fresh()->verification_document_path);
        $this->assertSame(0, AccountVerificationAudit::query()->count());
    }

    public function test_unmanaged_document_path_is_not_deleted_or_cleared(): void
    {
        $this->enableSyntheticPolicy();
        $user = $this->applicant('approved', now()->subDays(40), '../public/id.webp');

        $this->assertSame(1, Artisan::call('account-verification:purge-expired-documents'));

        $this->assertSame('../public/id.webp', $user->fresh()->verification_document_path);
        $this->assertSame(0, AccountVerificationAudit::query()->count());
    }

    private function enableSyntheticPolicy(): void
    {
        config([
            'services.account_verification.retention_policy_approved' => true,
            'services.account_verification.retention_policy_version' => 'synthetic-policy-v1',
            'services.account_verification.retention_schedule' => 'Synthetic test policy; not institutional.',
            'services.account_verification.id_retention_days_after_review' => 30,
            'services.account_verification.id_retention_statuses' => ['approved'],
            'services.account_verification.id_retention_enforcement_enabled' => true,
        ]);
    }

    private function applicant(string $status, $reviewedAt, ?string $path = null): User
    {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'approval_status' => $status,
            'requested_role' => 'student',
            'approval_reviewed_at' => $reviewedAt,
            'verification_document_path' => $path ?? ('verification-documents/' . bin2hex(random_bytes(24)) . '.webp'),
            'verification_document_submitted_at' => now()->subDays(100),
        ]);

        return $user;
    }
}
