<?php

namespace App\Console\Commands;

use App\Models\AccountVerificationAudit;
use App\Models\User;
use App\Services\VerificationDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeExpiredVerificationDocuments extends Command
{
    protected $signature = 'account-verification:purge-expired-documents {--dry-run : Report eligible documents without deleting them}';

    protected $description = 'Remove only expired private verification images under an explicitly approved retention policy';

    public function handle(VerificationDocumentStorage $documents): int
    {
        $policy = $this->approvedPolicy();
        if ($policy === null) {
            $this->components->info('No approved, enabled ID-retention policy is configured; no records or files were changed.');
            return self::SUCCESS;
        }

        [$days, $statuses, $version] = $policy;
        $cutoff = Carbon::now()->subDays($days);
        $query = User::query()
            ->whereNotNull('requested_role')
            ->whereIn('approval_status', $statuses)
            ->whereNotNull('approval_reviewed_at')
            ->where('approval_reviewed_at', '<=', $cutoff)
            ->whereNotNull('verification_document_path')
            ->orderBy('id');

        $eligible = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->components->info("Dry run: {$eligible} document(s) match the configured approved retention policy. No files or records were changed.");
            return self::SUCCESS;
        }

        $removed = 0;
        $failed = 0;
        $query->chunkById(50, function ($users) use ($documents, $cutoff, $days, $version, &$removed, &$failed): void {
            foreach ($users as $candidate) {
                try {
                    $deleted = DB::transaction(function () use ($candidate, $documents, $cutoff, $days, $version): bool {
                        /** @var User|null $user */
                        $user = User::query()->whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$user || !$user->requested_role
                            || !in_array($user->approval_status, (array) config('services.account_verification.id_retention_statuses', []), true)
                            || !$user->approval_reviewed_at
                            || $user->approval_reviewed_at->gt($cutoff)
                            || !is_string($user->verification_document_path)
                        ) {
                            return false;
                        }

                        $path = $user->verification_document_path;
                        // Missing, malformed, or unmanaged paths are not silently
                        // removed from the applicant record or treated as purged.
                        if (!preg_match('/^verification-documents\/[a-f0-9]{48}\.webp$/', $path)
                            || !Storage::disk('local')->exists($path)
                            || !$documents->delete($path)
                        ) {
                            throw new \RuntimeException('Private verification file could not be safely removed.');
                        }

                        $status = $user->approval_status;
                        $user->forceFill(['verification_document_path' => null])->save();
                        AccountVerificationAudit::query()->create([
                            'application_user_id' => $user->id,
                            'actor_user_id' => null,
                            'action' => 'verification_document_retention_deleted',
                            'from_status' => $status,
                            'to_status' => $status,
                            'internal_note' => 'Approved policy ' . $version . '; retention period ' . $days . ' days after review.',
                            'created_at' => now(),
                        ]);

                        return true;
                    });
                    if ($deleted) {
                        $removed++;
                    }
                } catch (Throwable) {
                    // Do not log applicant names, emails, document paths, or ID content.
                    $failed++;
                    $this->components->warn('A matching verification document could not be safely purged; inspect application/audit health without exposing document data.');
                }
            }
        });

        $this->components->info("Eligible: {$eligible}; successfully purged and audited: {$removed}; safely skipped/failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{int, list<string>, string}|null */
    private function approvedPolicy(): ?array
    {
        if (!config('services.account_verification.retention_policy_approved', false)
            || !config('services.account_verification.id_retention_enforcement_enabled', false)
            || !filled(config('services.account_verification.retention_policy_version'))
            || !filled(config('services.account_verification.retention_schedule'))
        ) {
            return null;
        }

        $days = filter_var(config('services.account_verification.id_retention_days_after_review'), FILTER_VALIDATE_INT);
        $statuses = array_values(array_unique(array_map('strval', (array) config('services.account_verification.id_retention_statuses', []))));
        $allowedStatuses = ['approved', 'rejected', 'suspended'];
        if ($days === false || $days < 1 || $statuses === [] || array_diff($statuses, $allowedStatuses) !== []) {
            return null;
        }

        return [$days, $statuses, (string) config('services.account_verification.retention_policy_version')];
    }
}
