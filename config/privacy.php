<?php

return [
    /* Increment only after the student-facing consent notice has been reviewed. */
    'consent_policy_version' => env('PRIVACY_CONSENT_POLICY_VERSION', '2026-10-18'),

    // Category aggregates with fewer consenting students are withheld by default.
    'analytics_min_group_size' => max(1, (int) env('PRIVACY_ANALYTICS_MIN_GROUP_SIZE', 5)),

    /*
     * No default is invented here. Crisis safeguards remain technically enabled,
     * but deployment stays blocked until an institutional policy version is set.
     */
    'crisis_safeguard_policy_version' => env('CRISIS_SAFEGUARD_POLICY_VERSION'),

    // Fail closed: a policy version requires explicitly authorized active recipients too.
    'institutional_escalation' => [
        'authorized_counselor_ids' => array_values(array_filter(array_map('intval', explode(',', env('CRISIS_AUTHORIZED_COUNSELOR_IDS', ''))))),
    ],
];
