# Phase 6 - 35-Emotion Integration Status

## Engineering decision: opt-in local pilot ready; production analytics remain off

The owner has confirmed that the 35-emotion taxonomy and reporting categories
were professionally evaluated outside this repository. That external review is
not reproduced or fabricated here, and no confidential school records are
requested. The repository still contains the synthetic Phase 5 development set,
not a finalized adjudicated holdout, so its historical scores are not presented
as independent production accuracy.

The implementation is ready for a narrowly controlled, consent-gated local
shadow pilot. The pilot is deliberately non-persisting: the existing six-class
classifier remains authoritative, detailed labels are not written to student
records, and Admin statistics do not consume shadow output.

## What is implemented

- The centralized 35-emotion/8-category taxonomy remains authoritative.
- The existing six-class model remains the production classifier.
- The frozen Gemma structured interpreter supports clear, mixed, uncertain,
  no-emotion, and attributed-other states without numeric confidence scores.
- Flask exposes `/internal/detailed-emotion-shadow` only behind both
  `MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED` and
  `MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED`.
- The route requires loopback access, HMAC authentication, a request ID,
  replay protection, purpose/revision metadata, and explicit consent fields.
- Laravel obtains consent from the server-side `PrivacySetting` record and
  requires current AI and analytics consent; a browser body claim cannot opt in.
- Shadow generation uses the shared cross-process GPU queue with one active
  generation, bounded wait, and a finite maximum timeout of 15 seconds.
- Laravel keeps the pilot result internal to the server-side pipeline and never
  returns it to the student API/UI. It never persists the raw message, evidence,
  hash, or detailed label unless the separate persistence gate is enabled.
- A shadow failure is logged as a bounded warning and never changes the normal
  response, crisis decision, counselor escalation, or safety fallback.

## Pilot activation and rollback

Both detailed-shadow flags are false by default. A local operator may enable
them only for a synthetic, consented test account, then restore them to false
and restart Flask. `MINDCARE_PHASE4A_AUTH_ENABLED` remains false and is
independent of this feature.

The pilot must be rolled back if latency, queue saturation, schema failures,
privacy-scope failures, or unexpected output exposure is observed. The normal
six-class path remains available if Flask, Ollama, or the shadow interpreter is
unavailable.

## Validation boundary

The repository's 50-case file is still a draft development artifact. The final
one-pass holdout evaluator remains intentionally unavailable until a genuinely
final dataset is supplied; this is an evaluation limitation, not a blocker to
the local non-persisting engineering pilot. No broad production activation or
Admin 35-label reporting is claimed.

## Remaining limitations

- The small synthetic runtime probes showed valid schemas and correct
  third-person/no-emotion routing, but Gemma missed at least one explicit
  mixed-emotion example and treated an ambiguous statement as no-emotion.
- These observations are engineering evidence, not clinical accuracy claims.
- Professional review scope is owner-confirmed externally and is not copied
  into repository files.
- Authorized Admin screenshots and a final independent holdout score are still
  required before any decision to persist detailed labels or publish them in
  analytics.
