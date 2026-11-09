# Phase 5 — Experimental Interpreter Freeze

**Status:** FROZEN — evaluation-only (not enabled in live analytics)

**Freeze date:** 2026-10-24 (Asia/Manila)

This document records the exact experimental Gemma interpreter used for Phase 4 and
the starting point for independent professional review. The six-class trained model
and deterministic crisis/safety paths remain the production behavior.

## Frozen configuration

- Model: `gemma3:4b` through the existing Ollama/Flask evaluation path.
- Temperature: `0`.
- Maximum generated tokens: `180` (`num_predict`).
- Output: JSON validated by `emotion_interpreter_schema.py`.
- Taxonomy: `config/emotion_taxonomy.json`, version `1.0.0` (35 detailed labels,
  8 reporting categories).
- Category mapping: derived by the shared taxonomy loader only; an LLM-supplied
  category is never authoritative.
- Numeric confidence: not requested, accepted, or synthesized.
- Runtime: evaluation-only; no production persistence or Admin analytics activation.
- Safety: deterministic crisis screening and counselor escalation are independent
  of this interpreter.

## Content hashes (SHA-256)

These hashes are a reproducibility record. Do not edit the frozen files before the
final holdout has been reviewed and adjudicated.

| Artifact | SHA-256 |
| --- | --- |
| `ml_service/emotion_interpreter_schema.py` | `E5A059616FDC80FCA766B6AC6A3F805B92F36EC531682D23C37538F7D1217AAD` |
| `ml_service/phase2_emotion_feasibility_eval.py` | `7543CB7C62B35615501B0AC29E906740899F4C15803456550AF25B10C537F88D` |
| `config/emotion_taxonomy.json` | `6D648565B3ABFA0FEEC44D4FB373B0F6DEB5A3DA1F0A78D1E9DB329001AD7DA3` |
| `storage/app/audit-recovery/emotion-taxonomy-20261024/phase4-frozen-baseline.json` | `997E31776DA3E219BCDA8D1D4DA6A4BD924DB49C8631575A820BA87E5C9C3E57` |
| `storage/app/audit-recovery/emotion-taxonomy-20261024/phase4-holdout-gemma-results.json` | `74B9E2B46CD430154719DF82EA6F81B32E3292539BC29E5EAB39BDE122873FB2` |

## Historical evaluation records (not final validation)

- Frozen Phase 3 development result: 68 synthetic cases; primary-label denominator
  55, exact agreement 43/55 (78.18%), category agreement 44/55 (80%).
- Phase 4 unseen-at-the-time holdout: 39 synthetic cases; schema-valid 37/39
  (94.87%), primary-label exact 22/29 (75.86%), category 25/29 (86.21%).
- These datasets and scores are retained as development/evaluation history. They
  must not be described as an untouched professional validation set.

## Freeze rule

The prompt, schema, taxonomy, and evaluator configuration are frozen until the
review package in `PHASE5_COUNSELOR_VALIDATION_PACKAGE.json` has been reviewed,
disagreements adjudicated, and the final unseen dataset in
`phase5_final_unseen_cases.json` has its draft labels replaced with adjudicated
labels. Any change after that point requires a new version and a new holdout.
