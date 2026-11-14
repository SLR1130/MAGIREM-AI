# Guidance Office reviewer access and controlled evaluation

## Reviewer authorization procedure

**Current local configuration:** the owner-confirmed existing Guidance reviewer is User ID 229. The `.env` allowlist contains only `229`, reviewer authorization is enabled, and public registration remains disabled. No account or credential was changed to configure this. Privacy notice and retention-policy approvals remain pending.

After the owner/institution designates a reviewer:

1. Confirm the intended person's existing account through the authorized project owner and Guidance Office process. Do not create an account or change its role as part of this procedure.
2. Confirm the account ID belongs to the intended person, has the existing `admin` role, is active, and is approved. Never select by display name alone.
3. Obtain explicit project-owner confirmation of that exact account ID and that person's reviewer designation.
4. For a future reviewer change, set `ACCOUNT_VERIFICATION_REVIEWER_IDS` to only the newly confirmed ID(s) and set `ACCOUNT_VERIFICATION_REVIEWER_AUTHORIZATION_APPROVED=true` in the approved local secret/configuration channel. Do not commit local `.env` values.
5. Rebuild Laravel's config cache / restart workers in the controlled environment. Verify the reviewer can open the verification queue and an unlisted administrator receives 403. If the designated account changes, remove its ID and repeat authorization.

An admin role alone is not reviewer permission. Registration stays blocked unless the approval flag is true and at least one configured ID resolves to an active, approved admin account.

The currently approved reviewer settings are separate from the still-pending privacy settings. Do not change the reviewer settings during policy review. The following values must remain pending/off until the institution approves them:

```dotenv
ACCOUNT_VERIFICATION_PRIVACY_NOTICE_APPROVED=false
ACCOUNT_VERIFICATION_PRIVACY_NOTICE_VERSION=
ACCOUNT_VERIFICATION_PRIVACY_CONTACT=
ACCOUNT_VERIFICATION_RETENTION_POLICY_APPROVED=false
ACCOUNT_VERIFICATION_RETENTION_POLICY_VERSION=
ACCOUNT_VERIFICATION_RETENTION_SCHEDULE=
ACCOUNT_VERIFICATION_ID_RETENTION_DAYS_AFTER_REVIEW=
ACCOUNT_VERIFICATION_ID_RETENTION_STATUSES=
ACCOUNT_VERIFICATION_ID_RETENTION_ENFORCEMENT_ENABLED=false
```

After approval, add the exact approved notice body using `ACCOUNT_VERIFICATION_PRIVACY_NOTICE_TEXT` (quote/escape multiline `.env` content as supported by the installed dotenv parser, or use an approved protected configuration source). The notice version is limited to 40 characters to fit the consent field. Keep `NATIVE_REGISTRATION_ENABLED=false` in the existing environment.

After all prerequisites are approved, the owner may configure a designated ID and institution-supplied policy values in the approved local secret/configuration channel. Keep registration false until the controlled test has been authorized and completed. Enabling a flag without the corresponding approval is not an acceptable shortcut.

## Privacy and retention prerequisites

Use `REGISTRATION_PRIVACY_AND_RETENTION_REVIEW_TEMPLATE.md` to obtain actual institutional decisions. Do not replace pending placeholders with guessed policy. Before even a controlled live registration, configure an approved notice version and exact notice text, privacy contact, approved retention-policy version/schedule, positive days-after-review value, approved terminal statuses, and an explicit enforcement flag. The current `.env.example` leaves these unset/off.

The scheduled `account-verification:purge-expired-documents` command is safe to schedule while unconfigured: it exits without touching data. It requires approved policy/version/schedule, a positive number of days after review, a nonempty subset of terminal statuses (`approved`, `rejected`, `suspended`), and explicit enforcement. Before enabling it, verify the policy and backup/backup-expiry treatment, then dry-run against an isolated staging database and review the count. The command never selects `pending` or `needs_information` and never deletes user or audit rows. Its scheduled execution is daily at 02:15 in the application timezone; a host scheduler must run Laravel's scheduler for it to execute.

## Staging configuration after approvals

The existing local `.env` is not a staging setup. Prepare an access-restricted staging host with an isolated database and private storage, synthetic applicants, a non-delivering mail fake/sink, and no copied real student records or ID images. Confirm backups and restore procedure before any evaluation. Only after the institution has approved the notice and retention policy and the owner separately authorizes a controlled registration evaluation should the staging environment receive the approved settings below. Do not alter the current local reviewer grant or set its registration flag as part of this preparation.

```dotenv
# Only inside access-restricted staging, after written approvals and explicit authorization:
NATIVE_REGISTRATION_ENABLED=true
ACCOUNT_VERIFICATION_REVIEWER_IDS=229
ACCOUNT_VERIFICATION_REVIEWER_AUTHORIZATION_APPROVED=true
ACCOUNT_VERIFICATION_PRIVACY_NOTICE_APPROVED=true
ACCOUNT_VERIFICATION_PRIVACY_NOTICE_VERSION=[APPROVED_VERSION]
ACCOUNT_VERIFICATION_PRIVACY_NOTICE_TEXT=[APPROVED_NOTICE_TEXT]
ACCOUNT_VERIFICATION_PRIVACY_CONTACT=[APPROVED_CONTACT]
ACCOUNT_VERIFICATION_RETENTION_POLICY_APPROVED=true
ACCOUNT_VERIFICATION_RETENTION_POLICY_VERSION=[APPROVED_POLICY_VERSION]
ACCOUNT_VERIFICATION_RETENTION_SCHEDULE=[APPROVED_SCHEDULE]
ACCOUNT_VERIFICATION_ID_RETENTION_DAYS_AFTER_REVIEW=[APPROVED_POSITIVE_INTEGER]
ACCOUNT_VERIFICATION_ID_RETENTION_STATUSES=[APPROVED_TERMINAL_STATUS_SET]
ACCOUNT_VERIFICATION_ID_RETENTION_ENFORCEMENT_ENABLED=false
```

Keep automated deletion off through initial staging review. Enable it only after policy approval, isolated dry-run review, backup/restore checks, and explicit operator authorization. Keep public/prod `NATIVE_REGISTRATION_ENABLED=false`. Apply approved config via protected settings, then refresh/cache config and restart long-lived processes as appropriate; never paste secrets into logs or chat.

## Browser-based controlled evaluation checklist

Run only on the access-restricted staging origin with an isolated database and private storage. First use `Mail::fake()` or a non-delivering sink; do not process old jobs or send to applicants. Browser camera access requires HTTPS or localhost and explicit browser permission.

- [ ] Confirm prod/local registration stays `false`; staging is not publicly reachable.
- [ ] Student registration: exact case-insensitive `@minsu.edu.ph`, invalid lookalike domain rejection, strong password, separate personal Gmail, student role fields.
- [ ] Counselor registration: same identity/login controls, counselor role fields, and required counselor authorization review.
- [ ] Upload JPG/PNG/WebP, verify client preview, test invalid/oversized image rejection, and confirm server-private storage / metadata re-encoding.
- [ ] Camera capture: test permission granted, denied, unavailable, retake/remove, and upload fallback on desktop and mobile; verify stream stops when the dialog closes.
- [ ] OTP: only staging test Gmail/sink receives it; verify correct code, wrong/expired/reused code, resend cooldown, and rate limits.
- [ ] Applicant sees pending status; pending student/counselor cannot open protected dashboards or features.
- [ ] Reviewer ID 229 signs in; queue/status filters load; open a synthetic ID preview and confirm private/no-store response and access audit.
- [ ] Verify an unlisted admin, student, counselor, and pending account cannot open applicant pages, approve/reject, or fetch ID images.
- [ ] Reviewer approves one synthetic student and one synthetic counselor only after required attestations; reject another synthetic application; verify status, requested role, reviewer ID, timestamp, and audit.
- [ ] Use mail fake/sink to verify OTP and approval/rejection notices go only to the synthetic verified Gmail and contain no ID image, wellness data, crisis details, or internal review note.
- [ ] Approved synthetic account logs in with institutional email/password to the correct dashboard; pending/rejected accounts remain restricted.
- [ ] Password recovery goes only to the verified synthetic Gmail; reset cannot change role or bypass approval.
- [ ] Simulate mail/queue failure and verify status reports queued/failed accurately; do not start a worker until pending/failed jobs have been inspected.
- [ ] Run retention dry-run in isolated staging; verify counts. Exercise actual deletion only with synthetic files/rows and `Storage::fake('local')`; confirm pending/needs-information remain and successful deletion leaves an audit.
- [ ] No real student/counselor identity, production record, real ID image, live SMTP recipient, or production queue job is used.

Do not create persistent test identities or send a real message without separate explicit authorization specifying the exact test identity and recipient. A live registration evaluation requires a further explicit activation decision; this document does not authorize opening public registration.
