# MAGIREM AI controlled registration evaluation checklist

**Status: preparation only. Public registration remains disabled.** This checklist is for an isolated staging environment. Do not use real student/staff identities, real school IDs, or external email delivery until the institution and system owner explicitly authorize that evaluation.

## Current readiness boundary

- Native Laravel login uses a MinSU `@minsu.edu.ph` address and password. A separate, verified personal Gmail address is used for OTP and account-status messages.
- Applicants remain pending until an explicitly authorized Guidance Office reviewer records independent institutional-identity and eligibility checks. The institutional domain, Gmail OTP, or ID image alone is not proof.
- The designated reviewer configuration is retained for User ID 229. Do not change account credentials, role, or reviewer authorization as part of this checklist.
- `NATIVE_REGISTRATION_ENABLED=false` must remain in the normal local/deployment environment until all institutional prerequisites below are approved. The registration readiness guard also fails closed unless approved privacy notice, retention settings, and an active authorized reviewer are configured.
- No approved privacy contact, notice/version, ID retention period/schedule, eligible terminal outcomes, exception process, or backup-expiry policy is supplied by this document. Obtain each from the institution; do not fill these with guessed values.
- The ID purge command is a no-op until the retention approval, version, schedule, positive number of days, allowed terminal statuses, and enforcement flag are all explicitly configured. No cleanup is authorized by this checklist.

## Privacy notice draft for institutional review

The editable notice in `REGISTRATION_PRIVACY_AND_RETENTION_REVIEW_TEMPLATE.md` is the source draft. The wording below is a plain-language outline, not a live notice or an assertion of MinSU policy. The institution must approve or replace it and complete every bracketed item before real collection.

> **About your account application**  
> We ask for your name, MinSU institutional email, password, applicant role, and role-related school or work details. We also ask for a separate personal email address so we can send an application verification code and account-status messages. Your password is stored in protected hashed form and is not readable by reviewers.
>
> **Why an ID may be requested**  
> An ID image, or an approved alternative-review request, helps an authorized Guidance Office reviewer check the application against an appropriate official source. An institutional email address, ID image, or personal-email code by itself does not establish eligibility. A reviewer must independently verify institutional identity and current enrollment or work authorization. The ID is not used for facial recognition, automated identity matching, or emotion analysis.
>
> **Who can access the ID**  
> Only active administrators explicitly authorized as Guidance Office reviewers can open an uploaded ID through the application. Access is restricted to the private review route and recorded for audit. **[Institution/IT must confirm hosting, transport security, storage-at-rest, backups, access procedures, and incident response; do not claim encryption unless verified.]**
>
> **Your privacy choices and requests**  
> For questions or requests about access, correction, withdrawal, deletion, or a privacy concern, contact **[institution-approved privacy office/contact/channel]**. The institution must supply the applicable rights wording, request process, response timelines, lawful basis/purpose, recipients, and any recordkeeping exceptions.
>
> **ID retention**  
> The institution-approved schedule is **[retention trigger]**, for **[approved duration]**, covering **[approved reviewed outcomes]**. **[Approved appeal, legal-hold, incident, and exception handling.]** The policy must also explain when backup and disaster-recovery copies expire. Until those values are approved and configured, automated ID deletion remains disabled. Removing an image does not automatically remove an account or its audit history.
>
> **Privacy contact:** [pending institutional approval]  
> **Notice version/effective date:** [pending institutional approval]

The application currently collects a MinSU login address, password, requested role, role-specific details, separate personal notification email, and (for self-registration) an ID image or alternative-review request. Confirm that this list and the stated purpose match the final data flow before approval.

## Institutional configuration required before any controlled live evaluation

The institution/owner must provide and approve each value; this checklist intentionally supplies none:

| Configuration / decision | Required approval or value |
|---|---|
| `ACCOUNT_VERIFICATION_PRIVACY_NOTICE_APPROVED` | Explicit approval of final notice; otherwise false |
| `ACCOUNT_VERIFICATION_PRIVACY_NOTICE_VERSION` | Approved version identifier |
| `ACCOUNT_VERIFICATION_PRIVACY_NOTICE_TEXT` | Approved student-facing text, including purpose and rights/request process |
| `ACCOUNT_VERIFICATION_PRIVACY_CONTACT` | Approved office and official contact channel |
| `ACCOUNT_VERIFICATION_RETENTION_POLICY_APPROVED` | Explicit approval; otherwise false |
| `ACCOUNT_VERIFICATION_RETENTION_POLICY_VERSION` | Approved policy version |
| `ACCOUNT_VERIFICATION_RETENTION_SCHEDULE` | Approved trigger, duration, eligible outcomes, exceptions, and backup treatment |
| `ACCOUNT_VERIFICATION_ID_RETENTION_DAYS_AFTER_REVIEW` | Approved positive number of days after review |
| `ACCOUNT_VERIFICATION_ID_RETENTION_STATUSES` | Only institution-approved terminal outcomes supported by the application (`approved`, `rejected`, `suspended`) |
| `ACCOUNT_VERIFICATION_ID_RETENTION_ENFORCEMENT_ENABLED` | Remains false until policy approval and dry-run/recovery checks |
| `ACCOUNT_VERIFICATION_REVIEWER_IDS` and `ACCOUNT_VERIFICATION_REVIEWER_AUTHORIZATION_APPROVED` | Preserve the confirmed authorization for ID 229; confirm reviewer appointment remains current before evaluation |
| `NATIVE_REGISTRATION_ENABLED` | Remains false outside the explicitly isolated staging test |

Set approved production/staging secrets through the protected deployment environment/secret store, not Git. Rebuild the Laravel config cache and restart the relevant app/worker only after approval. Never put credentials or real applicant data in this checklist.

## Isolated staging setup

1. Start from a fresh, restore-verified copy of the database and private storage. Keep the real local MySQL database and production/deployment environment untouched.
2. Use an isolated `APP_ENV`/configuration, isolated database name, isolated private-storage root, and synthetic-only accounts. Verify the active database/storage paths before testing.
3. Use clearly synthetic addresses under reserved test domains and generated non-identifying image fixtures. Do not use real school IDs, personal Gmail addresses, or student records.
4. Keep mail inside a local SMTP capture service bound to localhost, or use `Mail::fake()` for automated tests. Do not configure Gmail/Internet SMTP for this test and do not use the Laravel `log` mailer for OTP capture if that would write codes to shared logs.
5. If browser testing requires the registration route to render, use temporary test-only configuration in the isolated staging process with conspicuously synthetic notice/retention values and a synthetic reviewer account. Do not describe these fixture values as MinSU policy. Do not copy these values into `.env`, shared staging, or production.
6. Confirm the synthetic reviewer can review only the staging fixture accounts and private fixture images. Confirm an ordinary admin, student, counselor, pending applicant, and unauthenticated visitor are denied.
7. Destroy the isolated database/storage copy after retaining only approved, non-identifying test evidence. Do not run the ID purge against actual stored documents.

## Browser evaluation checklist

Run the checklist with synthetic identities only. Record pass/fail and the test environment/build, not real applicant information.

| Check | Expected result |
|---|---|
| Guest visits registration while normal `NATIVE_REGISTRATION_ENABLED=false` | Registration is unavailable; no applicant can submit or create an account |
| Isolated staging registration page | Clearly displays the test notice and pending-review explanation; required acknowledgement is enforced |
| Student registration | Accepts only case-insensitive `@minsu.edu.ph`; collects separate synthetic personal email, student fields, password confirmation, and ID/alternative request; creates inactive/pending account |
| Counselor registration | Same institutional-email and personal-email separation; counselor-specific fields and staff authorization are enforced |
| Invalid domain / duplicate institutional address | Rejected server-side, including case variants and collisions with `institutional_email` |
| Personal-email OTP | Delivered only to the submitted synthetic personal mailbox; expires, is single-use, rate-limited, and cannot approve the account |
| ID upload | Genuine supported image accepted; invalid MIME/content, oversized, or malformed files rejected; file remains outside public web storage |
| Camera capture | Browser permission/capture works only in secure context; verify cancel/retry and mobile camera behavior without real IDs |
| Pending status | Applicant sees accurate pending/rejected/needs-information state; cannot access protected student/counselor features |
| Reviewer queue and private preview | Only authorized reviewer can see the application and ID; access is audited; no public/static URL works |
| Approval | Reviewer records independent identity and eligibility basis; counselor approval additionally requires staff authorization; status changes and neutral notification are recorded |
| Rejection / needs information | Decision and reason are audited; no private wellness data or ID is included in email; admin-created account workflow remains distinct |
| Approved login | Approved active account logs in with institutional email/password and reaches the correct role dashboard |
| Pending login/password recovery | Reset can use only verified personal email but never changes approval or grants protected access |
| Notification failure/queue | Failure is visible and recoverable; no unrestricted worker processes stale jobs; no duplicate applicant/account is created on retry |
| Retention dry-run | With only a synthetic test policy, reports eligible synthetic fixture files and changes nothing |
| Unauthorized access | Non-reviewer admins, students, counselors, pending applicants, and guests cannot access reviewer actions or private IDs |

## Controlled live evaluation sequence (only after approvals)

1. Obtain written institutional approval for the final privacy notice, privacy contact, ID purpose/access controls, retention schedule and exceptions, backup lifecycle, and controlled evaluation scope.
2. Confirm the designated reviewer (currently configured as User ID 229) is still authorized. Do not change their account or grant other administrators access without explicit approval.
3. Make and restore-verify a fresh database and private-file backup. Verify isolated restoration and preserve the existing administrator account.
4. Complete the synthetic staging checklist above, including private ID access, notification failure handling, authorization-denial tests, retention dry-run, and recovery procedure.
5. Agree on the exact test participants, recipient mailboxes, test window, and number of synthetic/authorized test accounts. Obtain explicit owner approval before any external email or persistent account creation.
6. Configure only institution-approved values in protected deployment settings; review the rendered notice and the effective readiness state. Keep registration disabled if any required approval, reviewer, privacy value, or retention safeguard is missing.
7. For a limited evaluation, enable registration only in the intended isolated evaluation environment and window. Use authorized participants and collect no data beyond the approved scope. Monitor queues, audit logs, access denials, and storage without logging passwords, OTPs, ID content, or wellness content.
8. Close registration after the agreed window, revoke temporary test access, review and securely dispose of test data under the approved schedule, and verify account/audit/backup outcomes. Do not run cleanup until retention approval explicitly covers those records.

## Verification performed for this handoff

- The privacy/retention review template and fail-closed registration/retention implementation were inspected.
- Automated retention tests verify that missing policy settings are a no-op, only old reviewed records in configured terminal states are eligible, dry-run is non-mutating, and unmanaged file paths are rejected.
- The full isolated Laravel suite passes; see the task handoff for the run count and intentional skips.
- No real registration was enabled, no live account or database row was created/modified, no ID was deleted, and no email was sent as part of this preparation.
- Browser-based camera capture and reviewer UI still require manual verification in the isolated staging environment. No interactive browser session was run for this handoff.

