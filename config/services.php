<?php

return [

    'microsoft' => [
        // Microsoft registration remains disabled until MinSU supplies an
        // approved single-tenant Entra application registration.
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI'),
        'institutional_domain' => strtolower(env('MICROSOFT_INSTITUTIONAL_DOMAIN', 'minsu.edu.ph')),
        'enabled' => env('MICROSOFT_REGISTRATION_ENABLED', false),
    ],

    'account_verification' => [
        // Explicitly scoped, localhost-only visual/functional test mode. This
        // never substitutes for institution-approved privacy configuration.
        'local_registration_preview' => filter_var(env('LOCAL_REGISTRATION_PREVIEW', false), FILTER_VALIDATE_BOOL),
        // Public registration remains closed unless explicitly enabled after
        // institution-approved privacy and verification procedures are ready.
        'native_registration_enabled' => env('NATIVE_REGISTRATION_ENABLED', false),
        // Comma-separated IDs of explicitly authorized Guidance Office admins.
        // An admin role alone does not grant access to applicant records.
        'reviewer_user_ids' => array_values(array_unique(array_filter(array_map(
            static fn (string $id): int => ctype_digit(trim($id)) ? (int) trim($id) : 0,
            explode(',', (string) env('ACCOUNT_VERIFICATION_REVIEWER_IDS', '')),
        ), static fn (int $id): bool => $id > 0))),
        'reviewer_authorization_approved' => filter_var(env('ACCOUNT_VERIFICATION_REVIEWER_AUTHORIZATION_APPROVED', false), FILTER_VALIDATE_BOOL),
        'privacy_notice_approved' => filter_var(env('ACCOUNT_VERIFICATION_PRIVACY_NOTICE_APPROVED', false), FILTER_VALIDATE_BOOL),
        'privacy_notice_version' => env('ACCOUNT_VERIFICATION_PRIVACY_NOTICE_VERSION'),
        'privacy_notice_text' => env('ACCOUNT_VERIFICATION_PRIVACY_NOTICE_TEXT'),
        'privacy_contact' => env('ACCOUNT_VERIFICATION_PRIVACY_CONTACT'),
        'resend_cooldown_seconds' => env('ACCOUNT_VERIFICATION_RESEND_COOLDOWN_SECONDS', 60),
        'invitation_ttl_hours' => max(1, (int) env('ACCOUNT_INVITATION_TTL_HOURS', 24)),
        'retention_policy_approved' => filter_var(env('ACCOUNT_VERIFICATION_RETENTION_POLICY_APPROVED', false), FILTER_VALIDATE_BOOL),
        'retention_policy_version' => env('ACCOUNT_VERIFICATION_RETENTION_POLICY_VERSION'),
        'retention_schedule' => env('ACCOUNT_VERIFICATION_RETENTION_SCHEDULE'),
        'id_retention_days_after_review' => env('ACCOUNT_VERIFICATION_ID_RETENTION_DAYS_AFTER_REVIEW'),
        'id_retention_statuses' => array_values(array_unique(array_filter(array_map(
            static fn (string $status): string => strtolower(trim($status)),
            explode(',', (string) env('ACCOUNT_VERIFICATION_ID_RETENTION_STATUSES', '')),
        )))),
        'id_retention_enforcement_enabled' => filter_var(env('ACCOUNT_VERIFICATION_ID_RETENTION_ENFORCEMENT_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wellness_resources' => [
        'medlineplus' => [
            'enabled' => env('WELLNESS_MEDLINEPLUS_ENABLED', true),
            'endpoint' => env('WELLNESS_MEDLINEPLUS_ENDPOINT', 'https://wsearch.nlm.nih.gov/ws/query'),
            'cache_ttl' => env('WELLNESS_MEDLINEPLUS_CACHE_TTL', 86400),
            'rate_limit' => env('WELLNESS_MEDLINEPLUS_RATE_LIMIT', 10),
        ],
        'europe_pmc' => [
            'enabled' => env('WELLNESS_EUROPE_PMC_ENABLED', false),
            'endpoint' => env('WELLNESS_EUROPE_PMC_ENDPOINT', 'https://www.ebi.ac.uk/europepmc/webservices/rest/search'),
            'cache_ttl' => env('WELLNESS_EUROPE_PMC_CACHE_TTL', 21600),
            'rate_limit' => env('WELLNESS_EUROPE_PMC_RATE_LIMIT', 10),
        ],
        'youtube' => [
            'enabled' => env('WELLNESS_YOUTUBE_ENABLED', true),
            'api_key' => env('YOUTUBE_DATA_API_KEY'),
            'endpoint' => env('WELLNESS_YOUTUBE_ENDPOINT', 'https://www.googleapis.com/youtube/v3'),
            'cache_ttl' => env('WELLNESS_YOUTUBE_CACHE_TTL', 21600),
            'rate_limit' => env('WELLNESS_YOUTUBE_RATE_LIMIT', 10),
        ],
        'timeout' => env('WELLNESS_SEARCH_TIMEOUT', 5),
        'connect_timeout' => env('WELLNESS_SEARCH_CONNECT_TIMEOUT', 3),
        'retries' => env('WELLNESS_SEARCH_RETRIES', 1),
        'max_query_length' => env('WELLNESS_SEARCH_MAX_QUERY_LENGTH', 120),
        'max_results' => env('WELLNESS_SEARCH_MAX_RESULTS', 8),
        'proxy' => env('WELLNESS_SEARCH_PROXY', ''),
    ],
    'mindcare_ml' => [
        'url' => env('MINDCARE_ML_API_URL', 'http://127.0.0.1:5000'),
        // Chat reliability is deliberately separate from short-lived HMAC claims.
        // The browser must never infer service availability from a timeout alone.
        'chat' => [
            'connect_timeout' => env('MINDCARE_CHAT_CONNECT_TIMEOUT', 5),
            // A CPU-bound local model may need more than a minute after a cold
            // start. These remain bounded so a failed dependency is surfaced.
            'request_timeout' => env('MINDCARE_CHAT_REQUEST_TIMEOUT', 180),
            'browser_timeout_ms' => env('MINDCARE_CHAT_BROWSER_TIMEOUT_MS', 210000),
            'retry_attempts' => env('MINDCARE_CHAT_RETRY_ATTEMPTS', 1),
            'retry_delay_ms' => env('MINDCARE_CHAT_RETRY_DELAY_MS', 350),
            'idempotency_ttl_seconds' => env('MINDCARE_CHAT_IDEMPOTENCY_TTL_SECONDS', 900),
            'recent_history_turns' => env('MINDCARE_CHAT_RECENT_HISTORY_TURNS', 12),
            'digest_source_turns' => env('MINDCARE_CHAT_DIGEST_SOURCE_TURNS', 36),
            'digest_max_chars' => env('MINDCARE_CHAT_DIGEST_MAX_CHARS', 1800),
            'lock_seconds' => env('MINDCARE_CHAT_LOCK_SECONDS', 150),
        ],
        'inference_bridge' => [
            'url' => env('MINDCARE_INFERENCE_BRIDGE_URL', 'http://127.0.0.1:5000'),
            'secret' => env('MINDCARE_INFERENCE_BRIDGE_SECRET'),
            'connect_timeout' => env('MINDCARE_INFERENCE_BRIDGE_CONNECT_TIMEOUT', 5),
            'request_timeout' => env('MINDCARE_INFERENCE_BRIDGE_TIMEOUT', 100),
        ],
        // Dormant until an explicitly approved local pilot is enabled. Shadow
        // results are non-persisting and never feed Admin automatically.
        'detailed_emotion_shadow' => [
            'enabled' => env('MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED', false),
            'approved' => env('MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED', false),
            'timeout' => env('MINDCARE_DETAILED_EMOTION_SHADOW_TIMEOUT', 15),
            'purpose' => 'emotion_shadow_evaluation',
            // Separate gates keep the local interpreter testable without making
            // Gemma labels part of student records or Admin statistics.
            'persist' => env('MINDCARE_DETAILED_EMOTION_SHADOW_PERSIST', false),
            'admin_enabled' => env('MINDCARE_DETAILED_EMOTION_SHADOW_ADMIN_ENABLED', false),
        ],
        // Local-only conversational pilot. It can pass a validated, student-
        // attributed shadow cue into the existing single normal response
        // generation for one explicitly allow-listed synthetic account.
        'detailed_emotion_response_pilot' => [
            'enabled' => env('MINDCARE_DETAILED_EMOTION_RESPONSE_PILOT_ENABLED', false),
            'approved' => env('MINDCARE_DETAILED_EMOTION_RESPONSE_PILOT_APPROVED', false),
            'account_id' => env('MINDCARE_DETAILED_EMOTION_RESPONSE_PILOT_ACCOUNT_ID', ''),
        ],
        'phase4a' => [
            'enabled' => env('MINDCARE_PHASE4A_AUTH_ENABLED', false),
            'secret' => env('MINDCARE_INTERNAL_HMAC_SECRET'),
            'key_id' => env('MINDCARE_INTERNAL_HMAC_KEY_ID', 'phase4a-dev'),
            'issuer' => env('MINDCARE_INTERNAL_HMAC_ISSUER', 'laravel-phase4a'),
            'audience' => env('MINDCARE_INTERNAL_HMAC_AUDIENCE', 'mindcare-flask-phase4a'),
            'ttl' => env('MINDCARE_INTERNAL_HMAC_TTL', 60),
            'connect_timeout' => env('MINDCARE_PHASE4A_CONNECT_TIMEOUT', 5),
            'timeout' => env('MINDCARE_PHASE4A_TIMEOUT', 15),
            'synthetic_mode' => env('MINDCARE_PHASE4A_SYNTHETIC_MODE', false),
            'synthetic_memory_subjects' => env('MINDCARE_PHASE4A_SYNTHETIC_MEMORY_SUBJECTS', ''),
        ],
        'phase4b' => [
            'consent_db' => env('MINDCARE_PHASE4B_CONSENT_DB', storage_path('app/phase4b-consent.db')),
            'policy_version' => env('MINDCARE_PHASE4B_POLICY_VERSION', 'synthetic-policy-v1'),
            'purpose' => 'long_term_memory',
        ],
    ],

];
