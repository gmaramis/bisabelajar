# M4-T01 — Research Data Foundation

## 1. Objective

Implement the minimum research data foundation required by NEXUS without implementing learning-state inference, evidence fusion, adaptive intervention, recommendation, or other advanced M4 intelligence.

The purpose of M4-T01 is to ensure that learner activity produces sufficiently structured, traceable evidence for later M4 processing and empirical evaluation.

## 2. Research Context

M4/NEXUS is designed to answer three research questions:

### RQ1 — Inferensi Learning State
> Bagaimana NEXUS dapat menggunakan konteks dan bukti pembelajaran multidimensi untuk mengidentifikasi kondisi belajar (learning state) learner?

### RQ2 — Adaptive Intervention
> Apakah intervensi adaptif yang diberikan berdasarkan learning state yang diidentifikasi NEXUS menghasilkan peningkatan yang dapat diamati pada perilaku dan kinerja belajar learner pada aktivitas berikutnya?

### RQ3 — Contextual Evidence Validation
> Apakah validasi bukti berbasis konteks dapat mengurangi kesalahan inferensi learning state yang disebabkan oleh pengulangan soal dan kondisi sistem/lingkungan pembelajaran?

M4-T01 menyediakan data foundation yang diperlukan untuk ketiga RQ tersebut. M4-T01 tidak melakukan inference; hanya memastikan evidence mentah dan task context tersedia secara konsisten.

## 3. Existing M3 Boundary

M3 telah selesai dan terverifikasi:
- M3-specific tests: 9/9 passed
- Full test suite: 150/150 passed
- Assertions: 1,126
- No regression

M3 menyediakan:
- `code_run` — action event
- `code_submit` — action event
- `submission_accepted` — outcome event
- `submission_rejected` — outcome event
- execution records
- test-case results
- submission tracking
- learning event storage

M4-T01 MUST NOT break or redefine the existing M3 event taxonomy.

## 4. Scope

M4-T01 mencakup:
1. Activity lifecycle events
2. Basic task context
3. Traceable learner activity evidence
4. Support for deriving novelty
5. Support for deriving repetition count
6. Automated tests
7. Full regression testing

## 5. Activity Lifecycle Events

Add or complete these canonical learning events:

### `activity_started`
Recorded when a learner actually starts an activity. Minimum information:
- learner
- activity
- timestamp

### `activity_completed`
Recorded when a learner completes an activity according to the existing application completion semantics. Minimum information:
- learner
- activity
- timestamp

Do not invent a new completion definition if an existing application-level completion mechanism already exists.

## 6. Task Context

Activities must expose the minimum task context required by NEXUS:
- `concept`
- `learning_objective`
- `difficulty`

Use existing project conventions where available. Do not introduce a new pedagogical taxonomy without justification.

## 7. Novelty and Repetition

M4 needs to distinguish:
- a new task/concept for the learner;
- a previously encountered task/concept;
- repeated exposure to the same task.

`novelty` and `repetition_count` should NOT be stored as manually maintained learner-state fields in T01 unless the existing architecture requires it. They should be derivable from learner history, activity identity, concept identity, and prior learning events.

The exact computation of advanced trajectory features belongs to the future Research State Manager. T01 only needs to make the underlying history sufficient for those computations.

## 8. Existing M3 Events

These M3 events MUST remain functional:
```text
code_run
code_submit
submission_accepted
submission_rejected
```

Their semantic distinction MUST remain:
- action event: what the learner did;
- outcome event: what happened as a result.

Do not replace these events with a generic event.

## 9. Learning Event Traceability

T01 lifecycle events must contain, directly or through existing relationships, enough information to establish:
- learner
- activity
- timestamp
- event type

Where already available, preserve relevant contextual identifiers such as course, module, learning unit, programming activity, and execution/submission identifiers. Do not duplicate data unnecessarily.

## 10. HOTS

HOTS is part of the M4 research framework, but M4-T01 MUST NOT invent or finalize a new HOTS taxonomy.

For T01, the system only needs to remain extensible so task-level HOTS targets/evidence can be added after the pedagogical framework and rubric are finalized.

Do not implement a full HOTS inference engine in T01.

## 11. Out of Scope

Explicitly OUT OF SCOPE:
- Learning State inference
- Evidence Validation engine
- Evidence Fusion
- Cognitive-affective inference
- Adaptive Intervention
- Recommendation engine
- Research State Manager
- Complex trajectory computation
- Full HOTS taxonomy
- HOTS inference engine
- Hint system
- Intervention model
- Real-time intervention delivery
- Infrastructure telemetry
- Network-condition inference
- Human Reference assessment interface
- Statistical evaluation
- ML model training
- AI model training or fine-tuning

## 12. Responsibility Boundary

### M3 responsibility
M3 remains responsible for:
- programming activity execution
- code execution
- test-case evaluation
- submission processing
- raw execution/submission evidence
- canonical programming action/outcome events

### M4-T01 responsibility
M4-T01 is responsible for:
- activity lifecycle evidence
- task-context availability
- ensuring existing learning-event history is usable
- preserving traceability for later NEXUS analysis

### Future M4 responsibility
Future M4 tasks will derive:
- cognitive evidence
- behavioral evidence
- learning trajectory
- learning state
- intervention
- contextual validation
- research measurements

## 13. Acceptance Criteria

### T01-AC01 — Activity Started
When a learner starts an activity according to the application's existing start semantics, an `activity_started` LearningEvent is recorded.

### T01-AC02 — Activity Completed
When a learner completes an activity according to the application's existing completion semantics, an `activity_completed` LearningEvent is recorded.

### T01-AC03 — Event Traceability
Lifecycle events can be traced to learner, activity, and timestamp.

### T01-AC04 — Task Context
An activity can expose concept, learning objective, and difficulty.

### T01-AC05 — Existing M3 Events
`code_run`, `code_submit`, `submission_accepted`, and `submission_rejected` continue to work without semantic regression.

### T01-AC06 — History Sufficiency
Available event/history data is sufficient for a future M4 component to derive novelty and repetition count. Do not implement complex trajectory computation in T01.

### T01-AC07 — Automated Tests
Every implementation change introduced by T01 must have appropriate automated test coverage.

### T01-AC08 — Regression
The complete existing test suite must pass after implementation.

Baseline:
- M3 tests: 9/9 passing
- Full test suite: 150/150 passing

If the baseline test count changes because legitimate new tests are added, report the new total and explain the change.

### T01-AC09 — No Scope Creep
No M4-T02 or later functionality is implemented as part of T01.

## 14. Test Requirements

At minimum, add or update tests covering:
1. learner starts an activity → `activity_started` exists;
2. learner completes an activity → `activity_completed` exists;
3. lifecycle events contain correct learner/activity relationship;
4. task context can be retrieved;
5. existing M3 programming events remain correct;
6. repetition/history data can be queried sufficiently for future derivation.

Tests MUST validate behavior, not merely model existence.

Do not weaken existing tests to achieve green status.

## 15. Implementation Rules

1. Inspect the existing architecture before adding new tables or columns.
2. Reuse existing models, enums, relationships, and event infrastructure where appropriate.
3. Avoid duplicate representations of the same information.
4. Prefer nullable/backward-compatible changes where existing records may exist.
5. Do not change M3 behavior unless required to preserve the agreed contract.
6. Do not introduce AI/ML inference in T01.
7. Do not introduce complex derived metrics prematurely.
8. Keep implementation explainable and testable.
9. Preserve the M3/M4 separation of concerns.

## 16. Research Traceability

| M4-T01 Component | Research Relevance |
|---|---|
| `activity_started` | establishes activity participation/trajectory |
| `activity_completed` | establishes activity completion |
| concept | task context for RQ1/RQ3 |
| learning objective | task context and learning interpretation |
| difficulty | contextual evidence for RQ1/RQ3 |
| learner history | trajectory and repetition derivation |
| activity identity | novelty/repetition derivation |
| timestamp | temporal ordering and trajectory |
| existing M3 events | performance/cognitive/behavioral evidence foundation |

T01 supports:
```text
Learning Event
    ↓
Task Context + Learner History
    ↓
Future Evidence Validation / Fusion
    ↓
Learning State
```

## 17. Definition of Done

M4-T01 is complete only when:
- [ ] Activity lifecycle events are implemented.
- [ ] Task context is available.
- [ ] Existing M3 event semantics remain intact.
- [ ] Learner/activity/timestamp traceability is verified.
- [ ] History is sufficient for future novelty/repetition derivation.
- [ ] Automated tests are added.
- [ ] M3 tests remain green.
- [ ] Full regression suite is green.
- [ ] No out-of-scope M4 functionality has been implemented.
- [ ] Implementation summary is documented.
- [ ] Test results are reported.

## 18. Required Final Report from Hermes

After implementation, Hermes MUST report:
1. Files changed
2. Migrations added, if any
3. Models changed
4. Controllers/services changed
5. Events changed/added
6. Tests added/changed
7. M4-T01 acceptance criteria results
8. M3 test result
9. Full regression result
10. Remaining technical or architectural concerns

Do not report M4-T01 as complete unless the acceptance criteria and regression requirements are actually satisfied.
