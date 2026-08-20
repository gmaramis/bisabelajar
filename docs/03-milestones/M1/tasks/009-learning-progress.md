# TASK-009 — Basic Learning Progress

**Owner:** Hermes  
**Priority:** P1  
**Status:** ACCEPTED

## Objective

Persist basic learning progress without confusing completion with mastery.

## Status

- NOT_STARTED
- IN_PROGRESS
- COMPLETED

## Rules

1. Progress belongs to a student and Learning Unit.
2. Progress is scoped to the student's course/enrollment context.
3. Opening a Learning Unit may move NOT_STARTED → IN_PROGRESS.
4. Completion must use an explicit completion action or defined M1 rule.
5. COMPLETED does not mean MASTERED.
6. Do not implement mastery scoring.
7. Keep the boundary extensible for future evidence/mastery.

## Acceptance Criteria

- AC-009-01 New unit is NOT_STARTED.
- AC-009-02 Opening unit can record IN_PROGRESS.
- AC-009-03 Completion can be recorded.
- AC-009-04 Progress persists across sessions.
- AC-009-05 Student can see own progress.
- AC-009-06 Tutor can see appropriate basic progress.
- AC-009-07 Student cannot modify another student's progress.
- AC-009-08 Tests pass.

## Out of Scope

- Mastery score
- Competency score
- AI diagnosis
- Recommendation
- NEXUS

## Git

`feat(TASK-009): add learning progress`
