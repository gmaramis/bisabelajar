# M6-01 — NEXUS Evaluation Framework

**Status:** LOCKED — Evaluation foundation for M6.

## Purpose
Define an evaluation layer that tests NEXUS without changing production behavior.

## Core principle
Independent Scenario → Expected Outcome → NEXUS → Actual Outcome → Comparison → PASS / FAIL / REVIEW.

Expected outcomes must be independently defined, not generated from the production rule being tested.

## Evaluation dimensions
- Correctness
- Consistency
- Traceability
- Provenance
- Rule compliance
- Privacy
- Determinism
- Boundary handling
- Failure handling

## Scenario categories
Learning State: stable, progressing, needs_support, insufficient_evidence.
Intervention: reinforcement, guided_retry, hint, socratic_question, concept_explanation, corrective_feedback.
Next Action: continue, review_concept, practice_again, guided_retry, reassessment, collect_more_evidence.
Closed loop: evidence → state → intervention → retry → new evidence → state/action.

## Required boundary cases
No evidence; uncertain-only; context-dependent; single failure; repeated failure; failure→acceptance; acceptance without failure; ambiguous pattern; missing optional context; missing Bloom/Dave; missing intervention; missing retry outcome; duplicate execution.

## Result
Each scenario produces scenario_id, status, expected, actual, differences, dimensions, provenance_check, constraint_check, notes.

Status: PASS / FAIL / REVIEW.

## Constraints
Bloom/Dave are task demand only. Affective indicators are observable behavior only. Psychological diagnosis, causal claims, and unnecessary PII are prohibited.

## Automation
Synthetic scenarios → automated runner → results → report.

## Scope
Only evaluation framework, scenarios, evaluator, comparison, constraint checking, automated runner, reports, and tests. No new production learning rules, ML/LLM decision-maker, causal/statistical inference, real learner data, or M6-02…M6-07 implementation.
