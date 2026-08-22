# M5-02 — Learning State Change + Trajectory

## Purpose
Build the longitudinal layer that compares Learning State records across time.

M5-02 answers:
> How does a learner's Learning State change over time?

## Inputs
Use M5-01 research evidence foundation and existing M4 outputs:
- LearningEvent
- ValidatedEvidence
- LearningState
- AdaptiveIntervention
- NextLearningAction
- available course/activity/context

Do not recreate M4 logic.

## Required capabilities
1. Retrieve ordered Learning State history per learner.
2. Compare consecutive states.
3. Detect state transitions.
4. Build a trajectory representation.
5. Preserve timestamps and provenance.
6. Support activity/course/context filtering.
7. Distinguish:
   - improving/progressing pattern
   - stable pattern
   - persistent support need
   - insufficient/ambiguous evidence
8. Preserve the actual state values; do not overwrite history.

## V1 algorithm
Use deterministic rule-based transition analysis.

Example:
```text
needs_support → progressing = positive transition
progressing → stable = stabilization
needs_support → stable = positive transition
needs_support → needs_support = persistent support need
stable → needs_support = deterioration signal
```

Do not claim causal improvement from a transition alone.

## Trajectory
Trajectory must be an evidence-derived sequence, for example:
```text
t1 needs_support
t2 needs_support
t3 progressing
t4 stable
```

Store or return:
- learner
- ordered states
- transition type
- timestamps
- source evidence IDs
- confidence/context
- explanation/rule

Avoid a large new table unless existing structures cannot support the requirement.

## Tests
Test:
- chronological ordering
- transition detection
- positive transition
- persistent weakness
- deterioration signal
- insufficient evidence
- provenance
- historical preservation
- context filtering
- no mutation of M3/M4

Run all M4 and M3 regression suites.

## Scope fence
Do NOT implement:
- weak area identification (M5-03)
- AI reassessment (M5-04)
- improvement effectiveness (M5-05)
- contextual comparison (M5-06)
- export (M5-07)
- ML/LLM
- AI question generation
- causal inference
- dashboard

No commit. No push.

## STOP
Produce a final report and STOP for human review.
