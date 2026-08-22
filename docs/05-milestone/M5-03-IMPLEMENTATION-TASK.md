# M5-03 — Weak Area Identification

## Purpose
Identify competency/task areas where available evidence indicates the learner still needs strengthening.

Core question:
> Which areas remain weak, and what evidence supports that conclusion?

## Inputs
Use:
- M5-01 research evidence foundation
- M5-02 trajectory/state changes
- ValidatedEvidence
- LearningState
- Activity task demand
- Bloom demand
- Dave demand
- observable behavioral indicators

## Critical distinction
Weak area is NOT:
- psychological diagnosis
- personality assessment
- permanent learner label
- direct copy of Bloom/Dave demand

Bloom/Dave describe task demand, not learner capability.

## V1 algorithm
Use deterministic multi-factor rule-based identification.

Evidence may include:
- repeated unsuccessful outcomes
- repeated unresolved cognitive indicators
- repeated unresolved psychomotor indicators
- persistent support states
- failed retry/reassessment evidence
- repeated difficulty within the same competency/task area

Avoid declaring weakness from one ambiguous event unless the evidence model explicitly supports it.

## Output
Each weak-area finding should contain:
- learner
- competency/skill area
- strength/weakness status
- evidence count
- supporting evidence IDs
- relevant activities
- task demand context
- confidence
- explanation
- detection rule
- timestamp

Prefer a derived/read model or service unless persistent storage is genuinely necessary.

## Important
Weak area must be tied to a competency or learning area, not merely:
> Activity 17 was failed.

The system should distinguish:
```text
failed activity
vs.
evidence of weakness in competency X
```

## Tests
Cover:
- repeated weakness
- single weak/insufficient evidence
- cognitive weakness
- psychomotor weakness
- behavioral observable evidence
- evidence quality/confidence
- Bloom/Dave as demand only
- provenance
- no psychological inference
- historical behavior
- false-positive prevention

Run complete M3/M4 regression.

## Scope fence
Do NOT implement:
- AI question generation
- reassessment generation
- intervention effectiveness
- contextual variation analysis
- research export
- ML/LLM decision-making
- causal inference

No commit. No push.

## STOP
Produce final report and wait for review.
