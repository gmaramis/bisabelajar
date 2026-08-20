# TASK-206 — Activity Completion & Progress

**Status:** REVIEW

## Objective
Track activity participation/completion separately from Learning Unit mastery.

## States
NOT_STARTED, IN_PROGRESS, COMPLETED

## Requirements
- Progress scoped to student + enrollment + activity.
- Start records IN_PROGRESS.
- Valid completion records COMPLETED.
- Completion rule remains configurable.
- Existing Learning Unit progress semantics remain intact.

## Acceptance Criteria
- New activity is NOT_STARTED.
- Start changes state to IN_PROGRESS.
- Valid completion changes state to COMPLETED.
- State persists.
- Student sees own progress.
- Tutor sees appropriate course activity progress.
- Focused and full tests pass.

## Out of Scope
Mastery, competency, AI diagnosis, recommendations.

## Commit
`feat(TASK-206): add activity progress`
