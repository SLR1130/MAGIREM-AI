# Community module Stage 0 audit

Date: 2026-10-22

## Existing structure

- `routes/web.php` exposes the student Community page, post, like/unlike, comment, notification, and existing social/forum routes inside authenticated `role:student` routes.
- `StudentController::community` uses `CommunityService` and server-side pagination. The existing Blade feed is `resources/views/student/community.blade.php`.
- `CommunityMessage` is the persistent post model. Existing migrations also contain moderation fields, a parent post field, post comments, an older empty `community_likes` table, user profiles/follows, and notifications.
- `EnsureCommunityConsent` and `PrivacyService::canUseCommunity` provide purpose-specific privacy consent. Existing posts are held for admin moderation.
- `AdminController` and `resources/views/admin/community-moderation.blade.php` provide the existing post approval queue.
- There was no authoritative enrollment-verification field, per-student reaction constraint, confidential report model, or block model. Existing follow/social code is a separate legacy layer and is not used to infer interests or private data.

## Stage 1 implementation plan and boundary

The Stage 1 patch adds only additive community safeguards and interactions:

1. Explicit administrator-set `community_verified_at` status; email, campus text, and student ID are not treated as enrollment proof.
2. Bounded category and support-label configuration, optional title, and server-side limits.
3. Transactional one-reaction-per-student records with change/remove behavior.
4. One-level comment replies with server ownership checks and privacy-safe JSON serialization.
5. Confidential report records, moderator review status/notes, and reciprocal blocking.
6. Author-only edit/remove, duplicate-submission collapse, rate limiting, and deduplicated community activity notifications using the existing notification table.
7. A confidential moderation report audit trail (community_report_audits).

Community activity notifications are emitted only when the recipient still has current Community consent, and repeated equivalent notifications are coalesced.

AI Companion, Flask ML, counseling records, emotion data, journals, crisis routing, and persistent AI memory remain outside this module and are not read or copied by Community code.

Student connections, profile discovery, cross-campus fields, and messaging remain deferred until Stage 1 verification is complete.
