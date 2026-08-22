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
