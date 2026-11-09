<?php

namespace App\Services;

use App\Models\User;

/**
 * Defines the server-side eligibility boundary for the shared Community.
 * Registration approval is the current authorization record for students and
 * counselors; administrator access follows the existing approved account.
 */
class CommunityVerificationService
{
    private const PARTICIPANT_ROLES = ['student', 'counselor', 'admin'];

    public function isEligibleStudent(?User $user): bool
    {
        return $user?->role === 'student' && $this->isEligibleParticipant($user);
    }

    public function requireEligibleStudent(?User $user): void
    {
        abort_unless($this->isEligibleStudent($user), 403,
            'Community participation is available to active student accounts only.');
    }

    public function isEligibleParticipant(?User $user): bool
    {
        return $user !== null
            && in_array($user->role, self::PARTICIPANT_ROLES, true)
            && $user->is_active === true
            && $user->isApproved()
            && !$user->requiresOwnerOnboarding();
    }

    public function requireEligibleParticipant(?User $user): void
    {
        abort_unless($this->isEligibleParticipant($user), 403,
            'Community Circle is available to active, approved MinSU community accounts.');
    }
}
