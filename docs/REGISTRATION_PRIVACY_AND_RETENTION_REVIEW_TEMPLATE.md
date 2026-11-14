# MAGIREM AI account-verification privacy and retention review

**Status: DRAFT TEMPLATE — NOT APPROVED BY MINDSU. Do not enable registration using this document.**

This editable template is for review by the institution's designated privacy and Guidance Office authorities. Every policy value below is intentionally pending. The application does not infer or invent official MinSU policy.

## Institutional decisions required

| Item | Institution-approved value | Approval evidence / office / date |
|---|---|---|
| Privacy notice text, including purposes, lawful basis, recipients, rights, contact, and complaint route | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Privacy contact and official channel | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Data controller / responsible office wording | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| ID image use and authorized viewer roles | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Retention trigger (the application currently supports elapsed days after recorded review) | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Retention period in days after review | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Terminal application statuses whose images may be purged (`approved`, `rejected`, `suspended` only) | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Whether purge applies to backups, exports, and disaster-recovery copies, and how those copies expire | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Exception, legal hold, appeal, incident, and access-request handling | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |
| Notice version and effective date | **[PENDING MINDSU APPROVAL]** | **[PENDING]** |

## System behavior to review

- Applicants provide an institutional `@minsu.edu.ph` login address, a separate personal Gmail for OTP/status delivery, and an ID image or accessible alternative-review request.
- Domain ownership, an ID image, and Gmail OTP are not treated as proof of institutional identity. An explicitly authorized reviewer records independent institutional identity and eligibility attestations.
- Accepted ID image formats are JPEG, PNG, and WebP, up to 5 MB. The server checks actual image content, decodes/re-encodes to private WebP storage, uses a random path, and does not publish the file or send it to the AI/emotion service.
- Private preview is restricted to an active administrator whose ID is explicitly allowlisted and whose reviewer authorization has been confirmed in deployment configuration. Document access is audited.
- Automated ID purge is off by default. When explicitly enabled, it considers only applications with a recorded review, a configured approved terminal status, and a document older than the approved number of days after review. Pending and needs-information applications are never eligible through this command.
- The purge command removes only managed random WebP paths, clears the database pointer after successful file deletion, and records a system audit event. The command does not delete user/application/audit rows.
- Database and filesystem transactions are not one atomic storage transaction. Keep verified backups and test recovery procedures before enabling automated purge; define backup expiration separately.

## Approval record

- Reviewing office: **[PENDING]**
- Authorized approver / role: **[PENDING]**
- Approval reference: **[PENDING]**
- Approval date: **[PENDING]**
- Approved notice version: **[PENDING]**
- Approved retention-policy version: **[PENDING]**

This template is not legal advice and is not an approved privacy notice or retention schedule.

## Proposed student-facing notice wording (editable, not approved)

**DRAFT FOR MINDSU REVIEW — NOT A LIVE NOTICE. Every bracketed item requires institutional approval. Do not publish or configure this wording until approved.**

> **About your MAGIREM AI account application**
>
> To apply, you provide your name, MinSU institutional email, a password, whether you are applying as a student or counselor, campus/office, and role-related details. Students provide student number, program, and year level. Counselor applicants provide staff position and may provide specialization. You also provide a separate personal Gmail address for a one-time verification code, account-status messages, and (after that Gmail is verified) password recovery. Your password is stored as a hash; staff cannot read it.
>
> **Why we ask for a school or staff ID**
>
> An ID image, or an accessible alternative-review request, helps an authorized reviewer compare your application with an approved official source to assess identity and current enrollment or staff authorization. The `@minsu.edu.ph` domain, ID image, and Gmail verification by themselves do not prove that you are eligible. A reviewer must make the required checks. The ID is not used for facial recognition, AI emotion analysis, or automated identity matching.
>
> **Who can see your ID and how it is handled**
>
> Only active administrators explicitly designated as Guidance Office reviewers can open an uploaded ID through the application. Other applicants, students, counselors, and administrators who are not on the reviewer allowlist cannot open it there. Reviewer access is recorded in an audit log. The server checks the image contents, converts supported images to WebP, removes embedded image metadata, stores the result in a private (not public-web) location under a random name, and serves the preview only through the authorized review route. **[MINDSU/IT APPROVAL REQUIRED: confirm hosting, HTTPS, storage-at-rest, backup, and incident safeguards; do not imply encryption unless verified.]**
>
> **Your choices and privacy requests**
>
> You may contact **[PENDING: institution-approved privacy contact and official channel]** to ask about the information you provided, request correction, withdraw an application, ask how a deletion request would be handled, or raise a privacy concern. The institution must insert its approved request process, applicable rights wording, response channel, and any recordkeeping exceptions here: **[PENDING MINDSU PRIVACY REVIEW]**.
>
> **How long an ID image is kept**
>
> The institution has not yet supplied an approved ID-image retention period. The approved policy must state the retention trigger, the number of days after review, which reviewed application outcomes it covers, how exceptions or appeals are handled, and how backups expire. Until those values are approved and configured, automated ID deletion is disabled. The final approved schedule will be shown here: **[PENDING MINDSU APPROVAL — PERIOD, TRIGGER, ELIGIBLE OUTCOMES, EXCEPTIONS, AND BACKUP TREATMENT]**. Deleting an ID image does not automatically delete the account or its review/audit history.
>
> **Questions:** [PENDING: official privacy contact]  
> **Notice version/effective date:** [PENDING MINDSU APPROVAL]

The draft describes current application behavior where stated, but its contact, rights/request process, hosting safeguards, retention period, exceptions, and final language remain unapproved. An institutional reviewer must reconcile the draft with the institution's actual policy before it can be configured.
