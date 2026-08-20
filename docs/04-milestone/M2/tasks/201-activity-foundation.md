# TASK-201 — Activity Foundation

**Status:** REVIEW

## Objective
Create the generic Activity domain model attached to a Learning Unit.

## Requirements
- Activity belongs to one Learning Unit.
- Types: LESSON, QUIZ, ASSIGNMENT, CODING_EXERCISE, DISCUSSION, PROJECT, EXAM.
- Status: DRAFT, PUBLISHED, ARCHIVED.
- Persistent sort order.
- Extensible configuration boundary.
- Server-side authorization.

## Out of Scope
Type-specific engines, NEXUS, code execution, grading.

## Acceptance Criteria
- Activity migration/model exists.
- Type and status are constrained.
- Ordering persists.
- Relationship to Learning Unit works.
- Authorization boundary exists.
- Focused tests pass.
- Full suite passes.

## Commit
`feat(TASK-201): add activity foundation`
