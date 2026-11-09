<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/** Read-only, student-safe view of registered counselors. */
class CounselorDirectoryService
{
    private const PUBLIC_COLUMNS = ['id', 'name', 'role', 'specialization', 'years_of_experience', 'availability_schedule', 'status', 'bio', 'profile_picture', 'is_active'];

    public function eligibleCounselors(): Collection
    {
        return User::query()->select(self::PUBLIC_COLUMNS)->where('role', 'counselor')->where('is_active', true)->orderBy('name')->get();
    }

    public function isEligibleCounselor(int $counselorId): bool
    {
        return User::query()->whereKey($counselorId)->where('role', 'counselor')->where('is_active', true)->exists();
    }

    /** Trusted, non-identifying facts suitable for the internal Flask prompt. */
    public function aiContext(): array
    {
        $counselors = $this->eligibleCounselors();
        $availableNow = $counselors->filter(fn (User $counselor) => strtolower((string) $counselor->status) === 'online');

        return [
            'lookup_outcome' => $counselors->isEmpty() ? 'no_eligible_counselors' : 'eligible_counselors_available',
            'eligible_counselor_count' => $counselors->count(),
            'available_now_count' => $availableNow->count(),
            'specializations' => $counselors->pluck('specialization')->filter()->unique()->values()->all(),
        ];
    }

    /** Server-generated, voluntary navigation only. */
    public function referral(bool $offer): array
    {
        $context = $this->aiContext();

        return [
            'offered' => $offer,
            'lookup_outcome' => $context['lookup_outcome'],
            'eligible_counselor_count' => $context['eligible_counselor_count'],
            'available_now_count' => $context['available_now_count'],
            'actions' => $offer ? [
                // Relative paths intentionally follow the active browser origin.
                ['label' => 'View counselor directory', 'url' => route('student.counselor-directory', [], false)],
                ['label' => 'Request an appointment', 'url' => route('student.request-appointment', [], false)],
            ] : [],
        ];
    }
}
