# M6 — NEXUS Evaluation & Validation

## Milestones

| Task | Focus |
|---|---|
| M6-01 | NEXUS Evaluation Framework |
| M6-02 | Learning State Validation |
| M6-03 | Intervention & Reassessment Evaluation |
| M6-04 | Cognitive–Affective Model Validation |
| M6-05 | Explainability & Trustworthiness |
| M6-06 | System & AI Performance Evaluation |
| M6-07 | Integrated NEXUS Validation |


---

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

---

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

---

# M6-03 — Intervention & Reassessment Evaluation

**Purpose:** Evaluate whether T04 Adaptive Intervention and M5-04 AI-Assisted Reassessment behave consistently with independently defined pedagogical scenarios and constraints.

## T04 evaluation
Validate:
- intervention selection by learning state/context;
- Socratic response type;
- direct-answer prohibition;
- reason and decision provenance;
- idempotency;
- insufficient-evidence handling;
- needs-support branches.

## Reassessment evaluation
Validate:
- weak-area eligibility;
- specification alignment;
- Bloom/Dave task-demand alignment;
- candidate validation;
- no PII leakage;
- no psychological claims;
- no direct answer;
- provenance;
- failure handling.

## Human-review boundary
Automated checks can validate structure and constraints. Expert REVIEW is required for pedagogical appropriateness, quality of Socratic prompts, and quality of generated/reassessment content.

## Metrics
Intervention scenario pass rate, reassessment validation pass rate, constraint compliance, provenance compliance, deterministic consistency, and REVIEW count.

Do not claim intervention effectiveness or learner improvement. Those are empirical questions.

## Scope
M6-03 only. No new intervention/reassessment rules, no learner delivery redesign, no M6-04+, no ML/LLM decision-maker.

---

# M6-04 — Cognitive–Affective Model Validation

**Purpose:** Validate the NEXUS cognitive–affective model as an observable-learning-behavior framework, without psychological diagnosis.

## Model boundary
Cognitive and affective-related interpretations must be based on observable learning behavior and validated evidence. The system must not diagnose emotion, personality, mental state, motivation, or psychological condition.

## Validation dimensions
- Indicator observability
- Evidence-to-indicator traceability
- Indicator consistency
- Cognitive interpretation consistency
- Behavioral/affective interpretation consistency
- State/intervention constraint compliance
- Explanation transparency
- Provenance

## Independent scenarios
Create scenarios for:
- persistent attempt behavior
- corrective behavior
- persistent engagement
- reduced activity engagement
- cognitive unresolved
- psychomotor unresolved
- combinations and conflicting signals
- insufficient/uncertain evidence

## Automated validation
Check indicator classification, provenance, prohibited claims, deterministic behavior, and rule consistency.

## Human/expert validation
Required for construct and pedagogical interpretation: whether observable indicators are reasonable proxies for the intended learning construct and whether interpretations are educationally defensible.

## Important limitation
Technical correctness does not establish construct validity. Expert review and later empirical study are required.

## Scope
M6-04 only. No psychological diagnosis, no clinical inference, no ML model training, no causal claims, no M6-05+ implementation.

---

# M6-05 — Explainability & Trustworthiness

**Purpose:** Evaluate whether NEXUS decisions and research outputs are understandable, traceable, consistent, and appropriately bounded.

## Explainability checks
For Learning State, Intervention, Next Learning Action, Weak Area, Reassessment, Response, and Contextual Variation verify:
- reason/explanation exists where required;
- decision/inference rule exists where required;
- provenance exists;
- source evidence can be followed;
- Bloom/Dave wording remains task demand;
- no psychological diagnosis;
- no unsupported causal claim.

## Consistency
Same evidence and configuration should produce the same logical explanation for deterministic components.

## Trustworthiness dimensions
- transparency
- provenance completeness
- constraint compliance
- privacy
- determinism
- uncertainty visibility
- boundary clarity
- failure transparency

## Human review
Expert review may assess whether explanations are understandable and pedagogically meaningful. Automated tests validate structural and logical consistency.

## Scope
M6-05 only. No redesign of production rules, no ML/LLM decision-maker, no causal/statistical inference, no M6-06/07 implementation.

---

# M6-06 — System & AI Performance Evaluation

**Purpose:** Evaluate technical performance, reliability, resource behavior, and failure handling of the NEXUS research layer and AI-assisted components.

## Evaluation areas
- response time
- throughput
- concurrency behavior
- database query behavior
- memory/CPU behavior where measurable
- timeout handling
- retry/failure handling
- export performance
- deterministic component consistency
- AI provider abstraction behavior where applicable

## Baseline
Establish measurable baselines before defining performance targets. Do not invent acceptable thresholds without evidence or project requirements.

## Test levels
- focused service tests
- integration performance checks
- representative workload
- bounded load/stress test where environment permits

## AI boundary
AI generation performance may be measured, but AI is not the decision-maker for Learning State, Intervention, or Next Action.

## Outputs
Report measurements, environment, workload, sample size, latency distribution where available, error rate, and limitations.

## Scope
M6-06 only. No production architecture rewrite, no new ML decision model, no M6-07 implementation.

---

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
