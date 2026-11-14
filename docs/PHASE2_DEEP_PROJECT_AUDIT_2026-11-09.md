# Phase 2 — Deep Project Audit and Optimization

**Date:** 2026-11-09  
**Scope:** Read-only project inventory, except the explicitly requested stale Community acceptance assertion correction.  
**Safety:** No database rows, migrations, models, datasets, environment files, ACLs, or existing reports were changed or deleted. Standalone diagnostic scripts were inspected but not executed.

## Executive summary

- The Community implementation matches the latest shared-feed behavior: ordinary text posts publish immediately; only specific safety/media cases are held. Admin moderation controls are served from the shared feed. The failing assertion expected obsolete copy, so the test was updated to assert the current behavior.
- Community tests: **52 passed, 608 assertions**. Full Laravel suite: **340 passed, 8 skipped, 0 failed, 2,608 assertions**. The previous **339 passed / 8 skipped / 1 failed** became a clean pass after the assertion correction.
- A fresh walk found **79,943 readable files and 18,339,162,084 bytes (17.08 GiB)** excluding `.git` and reparse points. Ten storage directories could not be enumerated, so the size is a lower bound. The earlier 71,542 count cannot be reconciled from the available audit snapshot; enumeration scope may differ.
- Root Markdown: **155 files**, with **zero byte-identical duplicates**. The main issue is overlapping status/index/report material, not exact duplicate files.
- Root standalone code candidates: **31** (30 PHP/Python/PowerShell files plus one standalone JavaScript diagnostic; `vite.config.js` is build configuration and excluded). Three fail syntax parsing. None were run because several can mutate databases/files, invoke real local services, or train/overwrite model artifacts.
- Ten `storage/app` locations deny both directory enumeration and ACL inspection. No permission changes were attempted.
- The dominant storage is valuable research/runtime material: `storage` 7.20 GiB, `ml_service` 4.99 GiB, `.venv` 4.60 GiB. No safe immediate deletion was identified.
- Composer, npm, Python environment, TypeScript, Vite, MySQL connectivity, and local six-class inference were checked. OCR-specific implementation/tests were not found.

## 1. Community test failure

`tests/Feature/CommunityFrontendAcceptanceTest.php` expected the old sentence “Students and authorized MinSU staff, sharing one moderated community space.” The current `resources/views/student/community.blade.php` states that students and authorized MinSU staff share one space, ordinary text posts appear immediately, and only cases needing specific safety/media review are held.

This matches the latest requirements: one shared feed across student/counselor/admin roles; no blanket approval queue; Admin controls remain moderation/monitoring controls within the feed. Other passing tests confirm staff access to the same feed, admin controls served from that feed, and immediate publishing for ordinary text posts.

**Correction:** updated only the acceptance test’s wording assertions to verify the shared-feed and immediate-publishing copy. No implementation change or assertion weakening was needed.

**Verification:** `php artisan test tests/Feature/CommunityFrontendAcceptanceTest.php tests/Feature/CommunityInstantPublishingTest.php tests/Feature/CommunityPhotoPostingTest.php tests/Feature/CommunityStaffModerationTest.php tests/Feature/CommunityStage1AcceptanceTest.php tests/Feature/CommunityStage1Test.php tests/Feature/CommunityVisualPostStudioTest.php` → **52 passed, 608 assertions**.

## 2. Root Markdown audit

All 155 root `*.md` files were enumerated, their headings/opening metadata reviewed, and exact SHA-256 duplicates checked. The following primary classification is exhaustive (**15 + 13 + 12 + 25 + 90 = 155**). “Current operational candidate” means useful to retain as a possible runbook, not that every step has been re-certified against the current app.

### Important architecture/decision records — 15

`ARCHITECTURE_COMPLETE.md`, `ARCHITECTURE_OVERVIEW.md`, `ARCHITECTURE_SUMMARY.md`, `COMPLETE_SYSTEM_DIAGRAM.md`, `CRISIS_CONFIRMATION_FLOW_DOCUMENTATION.md`, `MINDCARE_ARCHITECTURE_AUDIT_2026-10-18.md`, `MINDCARE_CRISIS_PRIVACY_REMEDIATION_FINAL_2026-10-18.md`, `MINDCARE_PHASE3B_CLOSURE_GATE4_READINESS_2026-10-18.md`, `MINDCARE_PHASE4_PREPARATION_IMPLEMENTATION_PLAN_2026-10-18.md`, `MINDCARE_PHASE4_SOURCE_CONTROL_MANIFEST_2026-10-18.md`, `MINDCARE_POST_REFACTOR_RELEASE_READINESS_2026-10-18.md`, `MINDCARE_TARGETED_CRISIS_PRIVACY_REMEDIATION_PROPOSAL_2026-10-18.md`, `SYSTEM_REQUIREMENTS_SPECIFICATION.md`, `TASK_9_EMOTION_FUSION_ARCHITECTURE_DESIGN.md`, `TASK_9_EXECUTIVE_SUMMARY.md`.

Keep as dated decisions/evidence. Do not treat proposals, readiness gates, or historical diagrams as current implementation truth without checking source and tests.

### Current operational-document candidates — 13

`MINDCARE_DEVELOPMENT_HANDOFF_SOURCE_CONTROL.md`, `MINDCARE_LOCAL_DEVELOPMENT_CONFIGURATION.md`, `QUICK_START_TESTING_GUIDE.md`, `RUNTIME_TEST_GUIDE.md`, `START_HERE_REALTIME_TESTING.md`, `TWO_BROWSER_TEST_PROTOCOL.md`, `REALTIME_TESTING_GUIDE.md`, `README_MESSENGER_DELETE.md`, `MESSENGER_FEATURE_INDEX.md`, `TASK_3_DOCUMENTATION_INDEX.md`, `TASK_3_REALTIME_STATUS.md`, `VIDEO_CALL_FINAL_VALIDATION_REPORT.md`, `VIDEO_CALL_TIMELINE_IMPLEMENTATION_REPORT.md`.

These are the best candidates for a small maintained `docs/runbooks/` or feature documentation area. The Oct 16 browser procedures still need a current manual run before being labeled verified.

### Obsolete troubleshooting / diagnostic reports — 12

`LANGUAGE_ROUTING_DIAGNOSTIC.md`, `METADATA_FIX_REPORT.md`, `METADATA_FIX_STATUS.md`, `METADATA_FIX_VERIFICATION.md`, `NORMAL_CHAT_RESPONSE_LENGTH_INVESTIGATION.md`, `PARITY_INVESTIGATION_REPORT.md`, `QUICK_FIX_SUMMARY.md`, `RISK_PHRASE_SCREEN_BUGS.md`, `TASK_9_DIAGNOSIS_SUMMARY.md`, `TASK_9_ROOT_CAUSE_DIAGNOSIS.md`, `TASK9_EMOTION_FIX_ANALYSIS.md`, `TEST_AI_UNAVAILABLE.md`.

These are useful historical root-cause evidence but should not be presented as current troubleshooting instructions without revalidation.

### Superseded/legacy index, status, or overview snapshots — 25

`DEPLOYMENT_READY_SUMMARY.md`, `DOCUMENTATION_INDEX.md`, `DOCUMENTATION_INDEX_CURRENT.md`, `DOCUMENTATION_MANIFEST.md`, `DOCUMENTATION_TASKS_6_7_8.md`, `EMOTION_DETECTION_QUICK_REFERENCE.md`, `EMOTION_ML_AUDIT_REPORT.md`, `ENHANCEMENT_INDEX.md`, `EXECUTIVE_SUMMARY_CURRENT_STATE.md`, `FEATURE_CHECKLIST.md`, `FINAL_AUDIT_REPORT.md`, `FINAL_REPORT.md`, `FINAL_STATUS.md`, `GITHUB_DEPLOYMENT_READY.md`, `IMPLEMENTATION_ROADMAP.md`, `MASTER_README.md`, `QUICK_REFERENCE_GUIDE.md`, `README.md`, `RUN_SYSTEM.md`, `SETUP_INSTRUCTIONS.md`, `SYSTEM_STATUS_OCTOBER_15_2026.md`, `TASK_5_FINAL_DATASET_VALIDATION_AUDIT.md`, `TASK_5_MULTILINGUAL_DATASET_REPORT.md`, `TASK10_ACTIVATION_STATUS.md`, `TASK11_FINAL_REPORT.md`.

Several assert “complete,” “production-ready,” or data/model states that are dated and have since evolved. Retain them as history until an owner approves archival; do not use them as the current operational source of truth.

### Historical implementation / test evidence reports — 90

`CONTINUATION_SESSION_STATUS_REPORT.md`, `CRITICAL_CODE_FLOW_DIFF.md`, `FINAL_CORRECTIONS_SUMMARY.md`, `FINAL_REALTIME_CHECKLIST.md`, `FRONTEND_DELETE_UNSEND_IMPLEMENTATION.md`, `FRONTEND_REDESIGN_PHASE1.md`, `FRONTEND_REDESIGN_PHASE2_SUMMARY.md`, `GEMMA_FIRST_CRISIS_FLOW_TEST_GUIDE.md`, `IMPLEMENTATION_CHANGES_REFERENCE.md`, `IMPLEMENTATION_CHECKLIST.md`, `IMPLEMENTATION_COMPLETE.md`, `LANGUAGE_ROUTING_INVESTIGATION_COMPLETE.md`, `MESSAGE_DELETE_UNSEND_IMPLEMENTATION.md`, `MESSAGE_DELETE_UNSEND_TEST_REPORT.md`, `MESSENGER_DELETE_UNSEND_COMPLETE.md`, `METADATA_FIX_COMPLETE.md`, `NORMAL_CHAT_RESPONSE_LENGTH_FIX_SUMMARY.md`, `NORMAL_CHAT_TASK_CHECKLIST.md`, `NORMAL_CHAT_TASK_COMPLETE.md`, `PHASE4_COMPLETION_REPORT.md`, `PHASE4_CSS_CHANGES_GUIDE.md`, `PHASE4_VERIFICATION.md`, `PHASE8_IMPLEMENTATION_SUMMARY.md`, `PIPELINE_VERIFICATION_REPORT.md`, `POLLING_FIX_DIAGRAM.md`, `QUICK_REFERENCE_SESSION_4.md`, `README_TASK_6.md`, `REALTIME_IMPLEMENTATION_SUMMARY.md`, `REALTIME_MESSAGING_ANALYSIS.md`, `REALTIME_MESSAGING_FINAL_REPORT.md`, `RESPONSE_LENGTH_FIX_CODE_REFERENCE.md`, `SESSION_16B_QUICK_SUMMARY.md`, `SESSION_17_SUMMARY.md`, `SESSION_COMPLETION_SUMMARY.md`, `SESSION_CONTINUATION_INDEX.md`, `SESSION_CONTINUATION_VALIDATION.md`, `TASK_1_COMPLETION_REPORT.md`, `TASK_1_EMOTION_FIX_REPORT.md`, `TASK_10_EXECUTION_SUMMARY.md`, `TASK_2_ROUTE_FIX_REPORT.md`, `TASK_2_VERIFICATION_RESULTS.md`, `TASK_3_ARCHITECTURE_DIAGRAM.md`, `TASK_3_FINAL_REPORT.md`, `TASK_3_MESSAGING_WORKSPACE_UNIFICATION.md`, `TASK_4_EVIDENCE_DOCUMENTATION.md`, `TASK_4_FINAL_DATA_QUALITY_REPORT.md`, `TASK_4_FINAL_SUMMARY.md`, `TASK_4_VIDEO_CALL_INTEGRATION_REPORT.md`, `TASK_6_COMPLETE.md`, `TASK_6_CRITICAL_FIXES.md`, `TASK_6_DELIVERABLES.md`, `TASK_6_DOCUMENTATION_INDEX.md`, `TASK_6_EXECUTIVE_SUMMARY.md`, `TASK_6_FINAL_DATASET_CORRECTIONS_REPORT.md`, `TASK_6_FINAL_EVIDENCE_REPORT.md`, `TASK_6_FINAL_STATUS.md`, `TASK_6_FINAL_TEST_VERIFICATION.md`, `TASK_6_GEMMA_FIRST_REDESIGN_COMPLETE.md`, `TASK_6_IMPLEMENTATION_SUMMARY.md`, `TASK_6_MASTER_SUMMARY.md`, `TASK_6_START_HERE.md`, `TASK_6_VERIFICATION.md`, `TASK_6_VERIFICATION_RESULTS.md`, `TASK_7_COMPLETION_REPORT.md`, `TASK_7_DELIVERABLES_INDEX.md`, `TASK_7_FINAL_EVIDENCE_REPORT.md`, `TASK_7_READING_GUIDE.md`, `TASK_7_RESOLUTION_SUMMARY.md`, `TASK_7_SUMMARY.md`, `TASK_8_COMPLETION_VERIFIED.md`, `TASK_8_FINAL_REPORT.md`, `TASK_8_PATH_HARDENING_FINAL_REPORT.md`, `TASK_8_SHADOW_MODE_INTEGRATION_COMPLETE.md`, `TASK_9_1_FUSION_SHADOW_INTEGRATION_REPORT.md`, `TASK_9_DELIVERABLES_CHECKLIST.md`, `TASK_9_FINAL_STATUS.md`, `TASK_9_IMPLEMENTATION_REPORT.md`, `TASK_9_PHASE_3_FINAL_REPORT.md`, `TASK_9_PHASE2_COMPLETION_SUMMARY.md`, `TASK_9_PHASE3_CODE_CHANGES.md`, `TASK_9_PHASE3_FINAL_REPORT.md`, `TASK_9_QUICK_REFERENCE.md`, `TASK_9_V2_VALIDATION_RESULTS.md`, `TASK8_FINAL_REPORT.md`, `TASK9_CHANGES_SUMMARY.md`, `TASK9_COMPLETION_STATUS.md`, `TASK9_FINAL_REPORT.md`, `TASK9_QUICK_REFERENCE.md`, `TASKS_6_7_8_COMPLETION_SUMMARY.md`, `VALIDATION_CHECKLIST.md`.

### Cross-cutting semantic duplication

There are **no byte-identical root Markdown files**. Repeated coverage does exist in thematic clusters: architecture summaries/diagrams; documentation indexes/manifests; crisis/Task 6 completion reports; Emotion35/Task 4–5–6–9 audit and status documents; metadata fix reports; language-routing reports; normal-chat response-length reports; real-time/messenger guides; and video-call reports. Preserve the dated evidence, but recommend one maintained overview/runbook per feature plus linked dated history. No merge or deletion was performed.

### Recommended documentation structure

Keep one concise root `README.md`; organize maintained materials under `docs/architecture/`, `docs/runbooks/`, `docs/features/{community,messaging,video-calls,emotion}/`, and `docs/decisions/`. Keep immutable audits and dated implementation evidence under `docs/history/YYYY-MM/`. Move/archive only after references, Git history, and owner approval are checked; this audit did not move any file.

## 3. Root standalone diagnostic-script audit

The prior “24” count did not match a fresh root scan: **30 `.php`/`.py`/`.ps1` candidates plus `COUNSELOR_CHAT_DIAGNOSTIC.js` = 31**. `vite.config.js` is build configuration, not a diagnostic. Syntax was checked without executing program code: **28 parse cleanly; 3 fail**. Runtime success remains unverified unless covered by existing automated tests.

| Path | Purpose / dependencies | Status, overlap, risk |
|---|---|---|
| `check_flask.php` | cURL POST to local Flask `/chat` | Syntax OK; unrun; overlaps Flask probes; external-to-process service call. |
| `check_ollama_models.py` | `requests` GET of local Ollama model tags | Syntax OK; unrun; health discovery only; contacts local model service. |
| `COUNSELOR_CHAT_DIAGNOSTIC.js` | Browser/counselor-chat interaction diagnostic | Syntax OK; unrun; manual UI diagnostic, not a runtime module. |
| `FEATURE_VALIDATION.php` | Laravel/vendor-backed static/schema/method/route checks for delete/unsend | Syntax OK; unrun; database/framework environment dependent. |
| `final_diagnostic.py` | Local `ml_service` message-routing diagnostic | Syntax OK; unrun; overlaps current language-routing tests. |
| `health_check.ps1` | Retry local Flask `/health` | Syntax OK; unrun; overlaps `check_flask.php` and service health checks. |
| `measure_classifier_latency.py` | Loads Torch/Transformers checkpoint from a hardcoded local path and measures inference | Syntax OK; unrun; useful manual ML benchmark, path-specific. |
| `original_conversation_validation.py` | Sends synthetic Filipino/Taglish prompts to Flask | Syntax OK; unrun; overlaps Python/PHP language tests; service call. |
| `test_api_simple.php` | Laravel bootstrap and API test; `User::firstOrCreate` | Syntax OK; unrun; can write user data to whichever DB Laravel loads. |
| `test_complete_flow_with_metadata.php` | Local pipeline/metadata HTTP test | **Syntax error line 132** (`{$elapsedMs:.0f}` invalid PHP interpolation); overlaps metadata tests; do not run. |
| `test_confidence_routing.py` | Sends 8 messages to Flask `/chat` | Syntax OK; unrun; duplicates automated routing coverage and calls local AI. |
| `test_flask_direct_debug.py` | Direct Flask crisis/debug prompts | Syntax OK; unrun; duplicates crisis tests and calls local AI. |
| `test_fusion_t2.py` | Earlier T2 fusion experiment | **Syntax error line 59** (literal placeholder `{...dict...}`); superseded by maintained fusion tests; do not run. |
| `test_language_layers.php` | Laravel → Flask/Ollama layer-by-layer test | Syntax OK; unrun; boots app and contacts services. |
| `test_language_routing.php` | Language-routing/response-length test | Syntax OK; unrun; manual service test; overlaps Laravel/Python tests. |
| `test_messaging_fix.php` | Counselor image-upload simulation using existing users/appointment; writes a temporary image and exercises storage | Syntax OK; unrun; can touch DB/storage; use isolated fixtures only. |
| `test_metadata_fix_verification.php` | Metadata flow verification over HTTP | Syntax OK; unrun; overlaps active tests; service calls. |
| `test_metadata_frontend_simulation.php` | Simulates frontend metadata response over HTTP | Syntax OK; unrun; overlaps active tests; service calls. |
| `test_metadata_trace.php` | Direct Flask and Laravel-wrapper metadata trace | Syntax OK; unrun; overlaps active tests; service calls. |
| `test_negation_patterns.py` | In-memory regex checks | Syntax OK; low-risk pure diagnostic; overlaps crisis/language unit tests. |
| `test_single_message.php` | Laravel bootstrap; creates a factory user if none exists | Syntax OK; unrun; can write to active DB. |
| `test_task6_isolated.php` | Laravel/Gemma-first crisis-flow diagnostic | Syntax OK; unrun; app/service/database context uncertain; do not treat “isolated” in filename as isolation proof. |
| `test_timeline_logic.php` | Inserts and later deletes video-call rows | Syntax OK; unrun; live DB mutation risk. |
| `test_video_call_api.ps1` | HTTP video-call API exercise with hardcoded local IDs | **PowerShell parse error line 54** (invalid pipeline script block); also could mutate live app state. |
| `test_video_call_http.php` | Database-backed video-call HTTP checks | Syntax OK; unrun; app/DB dependent; possible endpoint mutation. |
| `test_video_call_runtime.php` | Database-backed runtime/route checks | Syntax OK; unrun; app/DB dependent. |
| `test_video_call_setup.php` | Checks appointments and creates a test appointment if missing | Syntax OK; unrun; DB mutation risk. |
| `test_video_call_timeline.php` | Inserts/deletes video-call timeline rows | Syntax OK; unrun; DB mutation risk. |
| `tests_direct_api.php` | Laravel API test; explicitly calls `artisan migrate` and creates a user | Syntax OK; **high risk**; never run against the main DB. |
| `tests_real_end_to_end.php` | `User::firstOrCreate` plus HTTP login/chat against local server | Syntax OK; DB writes and service calls possible; use isolated staging only. |
| `TRAIN_AI_NOW.ps1` | Launches `ml_service/train_context_model.py` | Syntax OK; training launcher, not diagnostic; can write/replace artifacts. Do not execute without a bounded isolated training plan. |

None is required by Laravel/Flask runtime imports. `TRAIN_AI_NOW.ps1` and `measure_classifier_latency.py` are manual ML maintenance tools; the remainder are legacy/manual diagnostics that overlap `tests/` or require local services. No OCR-specific script was found. Three syntax failures are confirmed; other scripts were not run, so syntax pass is not a runtime guarantee.

## 4. Inaccessible storage paths

The read-only recursive walk reproduced ten `UnauthorizedAccessException` failures. `Get-Acl` was also denied for all ten. The observed symptom is access-control denial; because ACL details are inaccessible, the precise ACL rule/sandbox cause cannot be identified, and a concurrent lock cannot be ruled out. No ACL, ownership, contents, or files were changed.

1. `storage/app/audit-recovery/emotion_model_35_multilabel_feasibility_20261025`
2. `storage/app/audit-recovery/emotion35-direct-probe-1pgb3xmz`
3. `storage/app/audit-recovery/emotion35-et26p2_j`
4. `storage/app/audit-recovery/mindcare-phase4b-jtmlc69s`
5. `storage/app/audit-recovery/mindcare-phase4b-zmr7xkau`
6. `storage/app/audit-recovery/tmp_dj_18ae`
7. `storage/app/audit-recovery/tmphw55zb7g`
8. `storage/app/audit-recovery/tmplmol9do0`
9. `storage/app/mindcare-phase4b-zmxonjor`
10. `storage/app/phase3b-runtime/tmp6qt3vy5w`

Names suggest research, consent/memory, or temporary runtime artifacts, but this is not proof of their contents. Treat them as potentially valuable/sensitive. Owner/admin review is required before any cleanup or ACL action.

## 5. Large files, directory classes, and space opportunities

### Readable directory sizes

| Path | Bytes | Class / action |
|---|---:|---|
| `storage/` | 7,734,869,672 | Mixed logs, backups, browser profiles, research; preserve pending privacy/provenance review. |
| `storage/app/` | 7,011,526,376 | User/runtime/research artifacts; preserve. |
| `storage/app/audit-recovery/` | 6,406,496,608 | Emotion35 checkpoints, datasets, logs, backups, temporary probes; research evidence, preserve. |
| `ml_service/` | 5,359,154,625 | Runtime code/models plus training checkpoints; preserve. |
| `.venv/` | 4,937,787,836 | Python 3.13 environment with CUDA PyTorch; required ML runtime, preserve. |
| `ml_service/emotion_model_multilingual_candidate/` | 4,928,015,886 | Five full checkpoints including optimizer states; resume/reproducibility artifacts, preserve. |
| `node_modules/` | 198,125,061 | Build dependencies; rebuildable but required for local frontend work, preserve. |
| `vendor/` | 56,215,952 | Composer runtime packages; required, preserve. |

Directory sizes exclude ten unreadable subtrees and `.git`; reparse points were not followed.

### Largest files inspected by metadata only

- `.venv/Lib/site-packages/torch/lib/torch_cuda.dll` — **1,060,440,064 bytes**, required CUDA runtime.
- `storage/logs/magi-recommendations-20261107-194056-403-out.log` — **703,584,517 bytes**, generated log; content not opened because it may contain private prompt/response data.
- Five `ml_service/emotion_model_multilingual_candidate/checkpoint-*/optimizer.pt` files — **657,072,587 bytes each**, training-resume state; not duplicates.
- `storage/app/audit-recovery/optionb-default-flask.stdout.log` — **562,844,679 bytes**; content not opened.
- CUDA/cuDNN libraries under `.venv/Lib/site-packages/torch/lib/` and model `.safetensors` files (roughly 328.5 MB each) are model/runtime dependencies; preserve.
- `storage/app/browser-acceptance-profile-20261018/` — **515,976,820 bytes**; browser state may contain authenticated profile data.
- `storage/app/audit-recovery/optionb-correction-flask.stdout.log` — **386,887,707 bytes**; content not opened.
- Research dataset `train.jsonl` files in `storage/app/audit-recovery/emotion35_*` are source/evaluation evidence; preserve.

Eight readable `.log` files are at least 10 MiB, totaling **2,178,283,124 bytes (~2.03 GiB)**. They are potential space candidates only after privacy/retention and audit-evidence review. No log contents were read.

### Duplicate and generated artifacts

- Root Markdown exact hashes: **0 duplicate pairs**.
- Same-sized model/optimizer/scheduler candidates were hashed: one identical pair exists between `ml_service/emotion_model_multilingual_deployable/model.safetensors` and `ml_service/emotion_model_multilingual_candidate/checkpoint-130/model.safetensors` (**328,504,568 bytes / ~313 MiB**). The checkpoint copy may be needed for a self-contained resume/evaluation snapshot; do not remove it without provenance/reference approval.
- Forty-seven `.csv/.jsonl/.json/.tsv/.parquet/.xlsx/.zip` data/model metadata files >=1 MiB in `ml_service` and readable audit-recovery folders were hashed. Six identical-content groups represent about **63,402,132 bytes (~60.5 MiB)** of duplicate copies, mostly shared train/validation/test splits and tokenizer JSON copied into self-contained checkpoints. These duplicates appear intentional for reproducibility; do not consolidate automatically.
- `storage/app/audit-recovery/chrome-ui-profile-mobile2/` — **218,342,524 bytes**; together with the acceptance browser profile, browser data is about **0.68 GiB**, but may include cookies/session artifacts.
- Root contains **43 `.db` files**, including `memory_store.db` and many `phase4c-test-*` databases. They were not opened or modified; preserve because the memory store may be runtime/personal data and test DBs may be fixtures.
- `public/build/` is ignored generated output and required for built frontend delivery. The verification build refreshed it; source assets were not changed by the build.
- Three root `train.jsonl`, `validation.jsonl`, and `test.jsonl` files are 3 bytes each and referenced by ML scripts; preserve until references are removed by an approved code change.

**Theoretical review pool:** up to ~3.08 GiB across large logs, browser profiles, the duplicate checkpoint weight, and exact duplicate research data. This is not a safe-to-delete estimate: all categories have privacy, reproducibility, or runtime caveats. **Safely reclaimable now: 0 bytes.** No files were removed in this phase.

## 6. Dependency, environment, and build audit

- `composer.json` / `composer.lock`: valid; 76 production and 35 development packages locked. PHP CLI 8.3.30 satisfies the declared PHP constraint; `composer check-platform-reqs --no-dev` passed.
- `package.json` / `package-lock.json`: lockfile v3, 317 package entries; declared dependency/dev-dependency versions match the lockfile. `npm ls --depth=0` showed no missing/invalid direct packages.
- Direct React references exist for Radix Dialog/Popover, Framer Motion, Uppy, Konva/React-Konva, Tiptap, Emoji Mart, Lucide, Axios, and the listed Fontsource families. No safe npm removal candidate was identified.
- `laravel/sail` is a **possible optional dev dependency**: no Dockerfile/Compose/Sail workflow reference was found, but retain it until the team confirms Docker/Sail is not part of anyone’s local workflow. `laravel/pail` is referenced by the Composer dev script; Faker/PHPUnit/Mockery are test tooling.
- `ml_service/requirements.txt` has 11 broad lower-bound constraints and no Python lockfile. `pip check` passed in `.venv` (Python 3.13.15). `sentencepiece`/`protobuf` may be transitive tokenizer/runtime requirements; do not remove based on direct-import search alone. Recommend a reviewed, GPU-aware lock/constraints file for reproducible reinstalls.
- CUDA check: Torch **2.14.1+cu126**, CUDA build 12.6, CUDA available, one device. The existing production `trained_emotion_classifier.py` explicitly forces CPU; measured sample inference below used CPU. This is a profiling/architecture decision, not a confirmed defect: GPU use could compete with Gemma and should be benchmarked before any change.
- `npm run typecheck`: passed.
- `npm run build`: passed after the restricted sandbox blocked esbuild’s child process (`spawn EPERM`) and the approved normal build was run. Warnings: public SVG/WebP URLs remain runtime-resolved (both files exist under `public/`); dependency `"use client"` directives are ignored by Vite; the `community-react` chunk is about **1.43 MB** minified (>500 kB warning). Consider lazy-loading the visual studio/emoji features in a separate approved optimization task.
- `composer run setup` includes `php artisan migrate --force`; it was not run. Do not use it against the main DB as a generic setup shortcut.

## 7. Regression and connectivity results

| Check | Result |
|---|---|
| Community focused tests | **52 passed, 608 assertions** |
| Full Laravel suite | **340 passed, 8 skipped, 0 failed, 2,608 assertions**; 348 total outcomes |
| Previous vs current | Previous **339 passed / 8 skipped / 1 failed**; corrected stale expectation yields **340 / 8 / 0** |
| Skip 1–5 | Five `MicrosoftOidcServiceTest` cryptographic tests skipped because PHP OpenSSL key generation was unavailable; fixture configuration was not changed in this task. |
| Skip 6–7 | Two `CrisisAppointmentVerificationTest` cases skipped by the test itself: one requires a real DB, one is manual verification. |
| Skip 8 | Optional local synthetic MAGI inference test is disabled unless explicitly enabled. |
| TypeScript | `npm run typecheck` passed. |
| Frontend production build | `npm run build` passed; warnings recorded above. |
| Authentication/authorization | Full suite includes registration, reviewer ID 229, inactive-account, privacy consent, password recovery, and role authorization tests; passed in isolated SQLite suite. |
| Community/Admin moderation | Shared roles, immediate text publishing, safety holds, privacy, reports, pin/moderation audit, and same-feed Admin controls tests passed. |
| ML inference | `ml_service/test_direct_classifier.py` ran with synthetic cases after using Python UTF-8 mode; six-class checkpoint/tokenizer loaded locally and returned eight predictions. One clear likely mismatch: positive Filipino “masaya/pasado” sample predicted anxiety (~44.9%). This is a small synthetic smoke test, not an accuracy evaluation. It ran CPU-only by explicit classifier configuration; crisis safety is covered separately by passing Laravel tests. |
| OCR | No OCR engine, OCR-specific service, or OCR test suite was found. Image validation/re-encoding exists, but OCR capability cannot be claimed or verified. |
| Test database isolation | `phpunit.xml` forces `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, array cache/session, sync queue; `.env.testing` also says SQLite `:memory:`. The full suite did not use MySQL. |
| MySQL connectivity | Read-only `php artisan db:show --database=mysql --json` and `migrate:status` succeeded. Configured target is `emotional_aware_db`; its schema had **65 table metadata entries**. No row counts or student records were queried. The DB inspection output exposed metadata across 98 schemas visible to the account; this does not establish row access, but warrants owner review of least-privilege grants. No database writes/migrations were run. |

### Regression coverage limitations

The suite’s eight skips are not caused by this assertion fix, but five skipped OpenSSL crypto tests mean those cryptographic checks did not execute in this run. The two crisis-appointment skips explicitly require real-database/manual verification, and the optional local MAGI test was not enabled. No authenticated browser session or OCR runtime was tested in this phase.

## 8. Recommended actions and approval boundary

1. Keep all database/model/dataset/runtime files and all inaccessible storage paths.
2. Reconcile the authoritative README/current runbook and archive structure only after owner review; the 155 root reports were not moved or merged.
3. Ask the data/privacy owner to review log retention/redaction before rotating or purging the ~2.03 GiB of large logs.
4. Preserve checkpoint directories as self-contained research evidence. Review duplicate dataset splits only if provenance confirms that shared holdouts are intentionally immutable and checkpoint loaders do not depend on local copies.
5. Review browser profiles for session ownership and retention before cleanup; do not inspect cookies or clear them automatically.
6. Review MySQL grants for the app credential; do not change grants in this audit.
7. Repair or retire the three syntax-invalid scripts only after confirming whether their manual workflows are still needed. Do not run the scripts that bootstrap, mutate, migrate, upload, call AI services, or train.
8. Consider code-splitting the 1.43 MB Community React chunk and adding Python dependency constraints after a dedicated performance/reproducibility change request.

**Requires explicit approval before cleanup:** any root Markdown report; any script; any `.db`/SQL backup; `storage/app/**`, logs, backups, browser profiles, user uploads, inaccessible directories/ACLs; `ml_service/**` models/training data; `.venv`, `node_modules`, `vendor`, or `public/build`; `.env`/secrets; database objects or grants.

## Changes made in this phase

- `tests/Feature/CommunityFrontendAcceptanceTest.php`: corrected the stale copy assertions.
- `docs/PHASE2_DEEP_PROJECT_AUDIT_2026-11-09.md`: added this audit report.
- Vite refreshed ignored generated `public/build/` assets as part of the requested build verification.

