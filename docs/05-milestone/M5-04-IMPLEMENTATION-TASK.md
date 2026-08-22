# M5-04 — AI-Assisted Competency-Aligned Reassessment

## Purpose
Create a safe AI-assisted mechanism for generating a NEW assessment/reassessment task for an identified weak area.

Core question:
> After identifying a weak area, can AI create a different task that tests the same intended capability?

## Critical principle
AI-generated task is a CANDIDATE, not automatically valid.

Pipeline:
```text
Weak Area
 ↓
Target Competency
 ↓
Original Task Demand
 ├─ Bloom
 └─ Dave
 ↓
AI generates candidate task
 ↓
Validation
 ↓
Approved reassessment
 ↓
Learner
 ↓
New LearningEvent
```

## AI responsibility
Hermes/NEXUS may generate candidate questions/tasks.

AI must preserve:
- target competency
- intended learning objective
- Bloom demand
- Dave demand where applicable
- relevant constraints
- appropriate difficulty/context

AI must NOT simply copy the original question.

The new task should differ in wording/scenario/data/structure while preserving the intended capability being measured.

## Validation
At minimum validate:
- target competency exists
- task demand is preserved
- no obvious duplicate of source task
- required answer/task format is valid
- task is relevant to weak area
- no unsupported competency claim
- provenance records source weak-area finding and original task context

Human tutor review may be supported where configured.

## Architecture
Do not make the LLM the final decision-maker.

Separate:
1. candidate generation
2. validation
3. approval
4. delivery
5. new evidence collection

If the current codebase has no LLM integration, implement interfaces/contracts and deterministic validation without inventing external credentials.

## Provenance
Record:
- source weak area
- target competency
- source activity/task
- Bloom/Dave demand
- generator identity/model metadata if available
- generation timestamp
- validation result
- approval status
- candidate lineage

## Tests
Test:
- same competency
- different task
- Bloom preservation
- Dave preservation where applicable
- weak-area linkage
- duplicate prevention
- invalid candidate rejection
- provenance
- no automatic approval of invalid AI output
- no direct Learning State mutation

Do not test against a live external LLM unless infrastructure already exists.

## Scope fence
Do NOT implement:
- intervention effectiveness
- contextual variation
- final research export
- causal claims
- unrestricted autonomous AI assessment
- ML training

No commit. No push.

## STOP
Produce final report and wait for human review.
