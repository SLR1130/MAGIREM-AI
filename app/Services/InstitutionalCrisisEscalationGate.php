<?php

namespace App\Services;

use App\Models\User;

class InstitutionalCrisisEscalationGate
{
    /**
     * Institutional escalation is fail-closed. A policy version alone is not
     * authorization: an approved, explicitly configured active recipient is required.
     */
    public function authorize(?User $counselor): array
    {
        $policyVersion = config('privacy.crisis_safeguard_policy_version');
        if (!filled($policyVersion)) {
            return ['authorized' => false, 'reason' => 'missing_policy_version'];
        }

        $authorizedIds = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) config('privacy.institutional_escalation.authorized_counselor_ids', [])
        )));

        if (empty($authorizedIds)) {
            return ['authorized' => false, 'reason' => 'missing_authorized_recipients'];
        }

        if (!$counselor || !$counselor->is_active) {
            return ['authorized' => false, 'reason' => 'no_active_authorized_recipient'];
        }

        if ($counselor->role !== 'counselor' || !in_array((int) $counselor->id, $authorizedIds, true)) {
            return ['authorized' => false, 'reason' => 'recipient_not_authorized'];
        }

        return [
            'authorized' => true,
            'policy_version' => (string) $policyVersion,
            'recipient_id' => (int) $counselor->id,
        ];
    }
}
