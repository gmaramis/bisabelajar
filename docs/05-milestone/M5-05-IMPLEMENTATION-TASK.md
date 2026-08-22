# M5-05 — Intervention Response + Improvement

## Purpose
Analyze what happens after an intervention or reassessment and determine whether observable evidence indicates improvement.

Core question:
> Did the learner show improvement after support?

## Important research boundary
M5-05 measures OBSERVED IMPROVEMENT.

It must NOT automatically claim:
- causal effectiveness
- intervention efficacy
- treatment effect

Example:
```text
Before:
needs_support
weak competency X

Intervention
↓
Different reassessment task
↓
New evidence
↓
After:
progressing
```

This supports an observed improvement pattern, not causal proof.

## Inputs
Use:
- intervention history
- weak-area findings
- reassessment lineage
- pre-intervention evidence
- post-intervention evidence
- Learning State trajectory
- task demand context

## V1 algorithm
Deterministic rule-based comparison.

Examples:
- needs_support → progressing = observed improvement
- needs_support → stable = stabilization/improvement signal
- needs_support → needs_support = no observed improvement
- needs_support → worse evidence = deterioration signal
- insufficient evidence = inconclusive

Where possible compare evidence on the same competency while allowing a DIFFERENT task.

## Required output
- learner
- competency
- intervention/reassessment reference
- before state/evidence
- after state/evidence
- outcome classification
- evidence IDs
- confidence
- explanation
- comparison rule
- timestamp

## Tests
Cover:
- improvement
- stabilization
- no improvement
- deterioration
- inconclusive
- different-task reassessment
- same competency preservation
- provenance
- evidence quality
- no causal claim
- no mutation of M4

Run all M3/M4 regression.

## Scope fence
Do NOT implement:
- contextual variation analysis
- statistical significance testing
- causal inference
- ML
- final export
- new intervention rules
- new Learning State rules

No commit. No push.

## STOP
Produce final report and wait for human review.
