# M5-06 — Contextual Variation

## Purpose
Allow research analysis to examine whether learning patterns vary across learning contexts.

Core question:
> Does the observed learning pattern differ by context?

Potential contexts:
- campus/institution
- course
- cohort
- programming language
- activity type
- learning unit
- session/time period

## Principle
Context is an ANALYSIS DIMENSION, not an automatic explanation.

For example:
```text
Campus A / Python
Campus B / C++
```

M5-06 may compare observed patterns, but must not conclude that:
> Python causes better learning.

## Inputs
Use M5-01 context and M5-02/M5-05 derived evidence.

## V1 approach
Deterministic grouping and descriptive comparison.

Support:
- grouping by context
- counts
- state distribution
- transition distribution
- weak-area frequency
- observed improvement frequency
- intervention/reassessment response patterns

Do not implement advanced statistical inference unless explicitly required later.

## Missing context
Do not invent or hard-code context.

If a required research dimension is missing:
- report the gap
- preserve available data
- do not fabricate values

## Research safety
Always distinguish:
```text
observed difference
vs.
causal explanation
```

Small samples should remain identifiable as small samples.

## Tests
Cover:
- context grouping
- Python/C++ as configurable context, not hard-coded logic
- campus grouping
- missing context
- mixed context
- provenance
- no fabricated values
- no causal conclusion
- historical preservation

Run complete M3/M4 regression.

## Scope fence
Do NOT implement:
- new intervention logic
- new Learning State inference
- AI generation
- ML prediction
- causal inference
- final export

No commit. No push.

## STOP
Produce final report and wait for human review.
