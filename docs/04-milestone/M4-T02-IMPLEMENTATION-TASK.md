# M4-T02 — Evidence Validation

## Objective
Implement the Evidence Validation layer of NEXUS. Transform raw observable learning evidence into contextualized, traceable validated evidence before future Learning State inference.

Core pipeline:
Raw Evidence → Classification → Context Check → Evidence Quality → Evidence Confidence → Validated Evidence

**T02 MUST NOT infer or declare a learner learning state.**

## Research Context
RQ1: Bagaimana NEXUS dapat menggunakan konteks dan bukti pembelajaran multidimensi untuk mengidentifikasi kondisi belajar (learning state) learner?

RQ2: Apakah intervensi adaptif berdasarkan learning state menghasilkan peningkatan yang dapat diamati pada perilaku dan kinerja belajar learner pada aktivitas berikutnya?

RQ3: Apakah validasi bukti berbasis konteks dapat mengurangi kesalahan inferensi learning state yang disebabkan oleh pengulangan soal dan kondisi sistem/lingkungan pembelajaran?

T02 primarily supports RQ1 and RQ3 and provides the evidence boundary required by RQ2.

## Relationship to M4-T01
Consumes activity lifecycle, task context, learner history, and M3 programming evidence. Produces validated evidence.

## Core Principle
A single observed behavior MUST NOT automatically be interpreted as a cognitive or affective state.

Example: 3 failed submissions → "Repeated submission failures detected", with task repetition, execution anomaly, difficulty, quality and confidence context. Never directly output "Learner is struggling".

## Scope
1. Evidence classification
2. Evidence quality
3. Context checks
4. Evidence confidence
5. Validated evidence record
6. Provenance/traceability
7. Automated tests
8. Full regression testing

## Evidence Classification
Support these conceptual categories:

### Performance Evidence
Score, correctness, passed/failed test cases, submission outcome, execution result.

### Behavioral Evidence
Repeated attempts, repeated execution, correction after failure, persistence, activity completion behavior.

### Interaction Evidence
Activity start, activity completion, code run, code submission, and future hint/help events if available. Do not implement hint system in T02.

### System/Context Evidence
Execution timeout, execution/runtime failure, known system anomaly, task repetition, task difficulty, network/environment condition when telemetry exists.

## Evidence Quality
Support:
- `valid`
- `uncertain`
- `context_dependent`

These describe evidence quality, not learner state.

## Evidence Confidence
Support:
- `high`
- `medium`
- `low`

Confidence means confidence in evidence validity/usefulness, NOT confidence in a psychological state. A simple explainable rule-based approach is acceptable. Do not implement ML inference.

## Context Checks

### Task Repetition
Use learner history. Values:
- `new`
- `repeated`
- `unknown`

### Task Difficulty
Use available task difficulty; otherwise `unknown`. Never invent a value.

### Execution Anomaly
Check observable timeout/runtime/system anomalies. Values:
- `none`
- `detected`
- `unknown`

### Network/Environment Condition
Infrastructure/network telemetry is OUT OF SCOPE. If unavailable, use `unknown`. Never assume normal conditions.

## Validated Evidence Record
Follow existing architecture. At minimum it must identify:
- learner
- activity/task
- source raw evidence/event
- evidence category
- evidence type
- observed value/result
- context summary
- evidence quality
- evidence confidence
- validation reason/explanation
- timestamp

Prefer references to raw records rather than duplicating complete payloads.

## Explainability
Every validated evidence result must contain a reason explaining validation, not diagnosing the learner.

Example:
Observed: `submission_rejected`
Context: task repeated
Execution anomaly: none
Difficulty: medium
Quality: `context_dependent`
Confidence: `medium`

Reason: "Submission failure is observable, but repeated exposure to the task requires contextual interpretation before inferring learner state."

## Provenance
Validated evidence must trace back to its raw source:
LearningEvent → Raw Evidence → Validation → Validated Evidence

A researcher must be able to determine what happened, when, which learner/activity was involved, which raw record produced it, and why the quality/confidence was assigned.

## Out of Scope
- Learning State inference
- cognitive/affective/psychological diagnosis
- Evidence Fusion into final learner state
- Adaptive Intervention
- Recommendation
- Research State Manager
- ML/AI model training
- HOTS inference/taxonomy
- hint system
- intervention framework
- real-time intervention
- infrastructure/network telemetry implementation
- complex trajectory engine
- statistical hypothesis testing
- human-reference assessment workflow

## M3 Compatibility
Preserve:
- `code_run`
- `code_submit`
- `submission_accepted`
- `submission_rejected`

T02 consumes these and MUST NOT redefine them. Do not weaken/remove existing M3 tests.

## Acceptance Criteria
- **T02-AC01:** Raw observable evidence can be classified into supported categories.
- **T02-AC02:** Available task/learner/system context is checked before evidence quality is assigned.
- **T02-AC03:** Task exposure is identified as `new`, `repeated`, or `unknown`.
- **T02-AC04:** Difficulty is included when available and `unknown` otherwise.
- **T02-AC05:** Observable execution anomalies affect validation.
- **T02-AC06:** Missing network/environment telemetry is recorded as `unknown`.
- **T02-AC07:** Quality supports `valid`, `uncertain`, `context_dependent`.
- **T02-AC08:** Confidence supports `high`, `medium`, `low` and refers to evidence validity/usefulness.
- **T02-AC09:** Validated evidence traces to raw source.
- **T02-AC10:** Every validated evidence result has a validation reason.
- **T02-AC11:** T02 does not output/persist learner learning state.
- **T02-AC12:** All new behavior has automated tests.
- **T02-AC13:** Full regression remains passing. Baseline: M3 9/9, full suite 150/150. Report changed totals if legitimate new tests are added.
- **T02-AC14:** No M4-T03 or later functionality is implemented.

## Test Requirements
At minimum cover:
1. performance classification;
2. behavioral classification;
3. interaction classification;
4. system/context classification;
5. new task;
6. repeated task;
7. unknown repetition;
8. known difficulty;
9. unknown difficulty;
10. execution anomaly detected;
11. execution anomaly absent;
12. missing network/environment telemetry → `unknown`;
13. quality classification;
14. confidence classification;
15. provenance;
16. validation explanation;
17. no learning-state inference;
18. M3 compatibility.

Tests must validate behavior/data, not merely class existence. Do not weaken tests.

## Implementation Rules
1. Inspect M4-T01 before changing architecture.
2. Reuse existing LearningEvent, CodeExecution, ActivitySubmission, ActivityProgress and task-context structures where appropriate.
3. Avoid duplicate raw evidence storage.
4. Keep validation deterministic and explainable.
5. Do not create hidden/implicit psychological models.
6. Do not infer cognitive or affective states in T02.
7. Absence of telemetry is not evidence of normal conditions.
8. Do not build a materialized learner trajectory view in T02 unless strictly required by existing architecture; trajectory computation belongs to the future Research State Manager.
9. Do not implement adaptive intervention.
10. Do not modify M3 event semantics.
11. Add tests for all new behavior.
12. Preserve backward compatibility where existing records exist.

## Research Traceability
| T02 Component | Research Relevance |
|---|---|
| Evidence classification | multidimensional evidence for RQ1 |
| Task repetition context | contextual validation for RQ3 |
| Difficulty context | contextual validation for RQ1/RQ3 |
| Execution anomaly | prevents false interpretation of performance evidence |
| Network unknown handling | prevents unsupported assumptions in RQ3 |
| Evidence quality | distinguishes usable vs context-dependent evidence |
| Evidence confidence | quantifies evidence reliability for later inference |
| Provenance | supports research reproducibility |
| Validation reason | supports explainability |
| No learning-state output | preserves separation between validation and inference |

Core chain:
`Raw Observable Evidence → Contextual Validation → Validated Evidence → M4-T03 Learning State Manager`

## Definition of Done
- [ ] Evidence classification implemented.
- [ ] Context validation implemented.
- [ ] Repetition context supported.
- [ ] Difficulty context supported.
- [ ] Execution anomaly handling implemented.
- [ ] Missing network/environment represented as `unknown`.
- [ ] Evidence quality implemented.
- [ ] Evidence confidence implemented.
- [ ] Provenance implemented.
- [ ] Validation reason implemented.
- [ ] No learning-state inference implemented.
- [ ] Automated tests added.
- [ ] M3 tests green.
- [ ] Full regression green.
- [ ] No out-of-scope M4 functionality implemented.
- [ ] Implementation summary documented.
- [ ] Test results reported.

## Required Final Report from Hermes
After implementation report:
1. Files changed
2. Migrations added
3. Models/controllers/services changed
4. Events changed/added
5. Validation rules implemented
6. Evidence quality/confidence rules implemented
7. Tests added/changed
8. T02 acceptance criteria PASS/FAIL
9. M3 test result
10. Full regression result
11. Architectural concerns
12. STOP after T02; do not begin M4-T03
