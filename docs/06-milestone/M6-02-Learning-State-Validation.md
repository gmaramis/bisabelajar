# M6-02 — Learning State Validation

**Purpose:** Validate the correctness and consistency of M4-T03 Learning State inference against independently defined evaluation scenarios.

## Inputs
ValidatedEvidence, LearningEvent, Activity task demand, and the existing T03 inference service.

## States to validate
- progressing
- needs_support
- stable
- insufficient_evidence

## Validation areas
1. Evidence quality handling
2. Cognitive indicators
3. Psychomotor indicators
4. Observable behavioral indicators
5. State classification
6. State confidence
7. Explanation
8. Provenance
9. Idempotency
10. Boundary/ambiguous cases

## Independent expected scenarios
Include positive, negative, recovery, insufficient, ambiguous, and mixed-quality evidence patterns.

## Required checks
- Bloom/Dave remain task demand.
- No psychological diagnosis.
- No intervention is created by T03.
- Same evidence set does not create duplicate state.
- Provenance points to actual evidence/events.
- Existing T02/T03 behavior is not modified.

## Metrics
Report scenario pass rate, state-specific pass rate, boundary pass rate, provenance compliance, constraint violations, and unresolved REVIEW cases.

Do not claim educational effectiveness or causal validity.

## Scope
M6-02 only. No M6-03+, no ML/LLM, no production rule redesign, no causal inference.
