# M6-07 — Integrated NEXUS Validation

**Purpose:** Validate M3–M6 as one coherent learning analytics and adaptive-learning system before M7.

## Integrated flow
LearningEvent
→ ValidatedEvidence
→ LearningState
→ AdaptiveIntervention
→ NextLearningAction
→ learner retry/next activity
→ new evidence
→ trajectory/research analytics
→ evaluation

## Validation areas
- end-to-end correctness
- state/intervention/action consistency
- closed-loop integrity
- provenance across layers
- research analytics consistency
- reassessment flow integrity
- privacy/data minimization
- explainability
- failure recovery
- deterministic behavior where expected
- regression protection

## Independent end-to-end scenarios
Include:
1. successful learner path
2. repeated difficulty path
3. recovery path
4. insufficient-evidence path
5. failed retry path
6. reassessment path
7. contextual variation path
8. privacy/provenance path
9. error/failure path

## Acceptance
Every scenario must produce PASS, FAIL, or REVIEW. Critical violations are blocking failures.

## Regression
M3, M4-T02…T06, M5-01…M5-07, and all M6 evaluation suites must remain passing.

## Important boundary
M6-07 validates the integrated system. It does not constitute a real-learner pilot, educational effectiveness study, or final deployment approval.

## Scope
M6-07 only. No M7 implementation, no real learner study, no causal claims.
