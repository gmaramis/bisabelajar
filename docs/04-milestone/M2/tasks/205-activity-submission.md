# TASK-205 — Activity Submission

**Status:** REVIEW

## Objective
Create the generic submission layer for supported activities.

## Requirements
- Submission belongs to authenticated student and activity.
- Attempt/version information.
- Submission status.
- Validated payload boundary.
- Multiple attempts only when allowed by configuration.

## Rules
Do not calculate final grades. Do not implement AI grading, quiz scoring engine, or code execution.

## Acceptance Criteria
- Student can submit a supported activity.
- Ownership is enforced.
- Submission persists.
- Attempt/version persists.
- Unauthorized submission is rejected.
- Focused and full tests pass.

## Commit
`feat(TASK-205): add activity submissions`
