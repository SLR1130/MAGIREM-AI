# MAGIREM AI native institutional registration

Student and counselor applications use native Laravel email/password credentials. The login identifier is the applicant's institutional address ending exactly in `@minsu.edu.ph` (case-insensitive); notifications and one-time email codes go to a separate personal Gmail address. Microsoft Entra/OIDC is not part of the registration or login flow.

New applications remain pending. A matching email domain, an uploaded ID image, or a personal Gmail OTP is not institutional proof. An authorized Guidance Office reviewer must confirm institutional identity and current enrollment or staff authorization using an approved official source. The reviewer decision, timestamp, and audit note are retained. Applicants cannot access student/counselor features until approved.

Account-verification access requires the existing active, approved `role:admin` account to be explicitly listed in `ACCOUNT_VERIFICATION_REVIEWER_IDS` and the separately approved `ACCOUNT_VERIFICATION_REVIEWER_AUTHORIZATION_APPROVED=true` setting. The list and approval default to empty/false, so no administrator can view applications or IDs until specifically authorized. This does not change any administrator role, password, or general dashboard access. Only add the exact designated account ID after owner/institution authorization, and remove the grant when review duties end. Apply the allowlist through protected environment/secret configuration and rebuild Laravel's config cache/restart the app; never use placeholder IDs or expose the list publicly.

Public registration is deliberately fail-closed. `NATIVE_REGISTRATION_ENABLED` defaults to `false`. Before it could be enabled for an approved controlled evaluation, the institution must approve and provide the privacy notice text/version/contact and the ID retention policy/version/schedule/days-after-review/status scope. The retention enforcement flag must be explicitly enabled, and at least one owner-authorized reviewer ID must resolve to an active, approved administrator. Do not use placeholder policy text or values to enable real collection of student or staff identification records.

## Application data and review

- `users.email` and `users.institutional_email` both store the canonical lowercase MinSU institutional login address.
- Passwords are hashed by Laravel; student and counselor registration requires at least 12 characters with mixed case, a number, and a symbol.
- `users.notification_email` is a separate personal Gmail address. OTP and neutral status notices are sent only there; the school address is not used for email verification or registration notices.
- Applicants can upload a supported school/staff ID image or request the accessible alternative-review method. Uploaded images are decoded/re-encoded to WebP, assigned random names, stored on the private local disk, and available only through the administrator-authorized, audited document route.
- Account approval requires an authorized reviewer to attest to independent institutional identity and eligibility checks. Counselor applicants also require a separate counselor/staff authorization confirmation.
- No facial recognition, AI identity matching, or wellness-content processing is performed on ID images.

The scheduled `account-verification:purge-expired-documents` command is fail-closed. It exits without changes unless the retention policy is approved, a version/schedule/positive retention period/status scope is present, and explicit enforcement is enabled. It can only select reviewed applications in configured terminal statuses (`approved`, `rejected`, `suspended`); it never selects `pending` or `needs_information`, and it never deletes user or audit rows. Use `php artisan account-verification:purge-expired-documents --dry-run` in an isolated copy before enablement. Database/file storage is not a single atomic transaction, so backup recovery and backup-expiry handling must be institutionally reviewed too.

## Password recovery readiness

Password recovery uses the registered institutional login address to locate an account but delivers a reset link only to its successfully verified personal Gmail address. Responses are non-enumerating, reset tokens expire and are single-use, requests are throttled, and a successful reset does not change role, approval, or account status. Recovery does not grant access to a pending applicant.

## Mail delivery

Personal Gmail verification codes are short-lived, stored as keyed hashes, attempt-limited, and replay-protected. Notifications contain neutral account-status text only; they exclude the ID image, password, journals, conversations, and internal reviewer notes. The default `log` mailer is treated as unconfigured for sensitive messages. Configure authenticated Gmail SMTP, the database queue, and a worker before expecting delivery.

Verification mail jobs are dispatched after commit, retry up to three times with 20/90-second backoff, and use a 25-second job timeout. The database queue retry window must remain greater than the job timeout (the current queue config uses 90 seconds). Before starting a worker, an authorized operator must inspect pending and failed jobs so old development notifications are not released to real recipients. Once deployment is explicitly authorized, run a supervised worker such as `php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=25` and monitor delivery states and `failed_jobs`. This task did not start a worker or process live jobs; worker behavior is covered by an isolated database-queue test with `Mail::fake()`.

## Local testing

Registration feature tests use the isolated PHPUnit database and synthetic data. They do not create records in the local MySQL database. Test images are stored on a fake disk. Keep `NATIVE_REGISTRATION_ENABLED=false` in normal local/deployment environments until privacy and review prerequisites are approved.

The former Entra/OIDC integration checklist is historical and is not required for this native registration workflow.
